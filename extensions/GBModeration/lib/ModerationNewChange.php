<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2018-2024 Edward Chernenko.

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
*/

/**
 * @file
 * Methods to manage "moderation" SQL table.
 */

namespace MediaWiki\Moderation;

use Content;
use ContentHandler;
use Language;
use MediaWiki\MediaWikiServices;
use MediaWiki\Moderation\Hook\HookRunner;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use ParserOptions;
use RecentChange;
use User;
use WikiPage;

class ModerationNewChange
{
    public const MOD_TYPE_EDIT = "edit";
    public const MOD_TYPE_MOVE = "move";

    /** @var Title Page to be edited */
    protected $title;

    /** @var User Author of the edit */
    protected $user;

    /** @var array All mod_* database fields */
    protected $fields = [];

    /** @var IConsequenceManager */
    protected $consequenceManager;

    /** @var ModerationPreload */
    protected $preload;

    /** @var HookRunner */
    protected $hookRunner;

    /** @var ModerationNotifyModerator */
    protected $notifyModerator;

    /** @var Language */
    protected $contentLanguage;

    /**
     * @param Title $title
     * @param User $user
     * @param IConsequenceManager $consequenceManager
     * @param ModerationPreload $preload
     * @param HookRunner $hookRunner
     * @param ModerationNotifyModerator $notifyModerator
     * @param ModerationBlockCheck $blockCheck
     * @param Language $contentLanguage
     */
    public function __construct(
        Title $title,
        User $user,
        IConsequenceManager $consequenceManager,
        ModerationPreload $preload,
        HookRunner $hookRunner,
        ModerationNotifyModerator $notifyModerator,
        ModerationBlockCheck $blockCheck,
        Language $contentLanguage,
    ) {
        $this->title = $title;
        $this->user = $user;

        $preload->setUser($user);

        $this->consequenceManager = $consequenceManager;
        $this->preload = $preload;
        $this->hookRunner = $hookRunner;
        $this->notifyModerator = $notifyModerator;
        $this->contentLanguage = $contentLanguage;

        $request = $user->getRequest();
        $dbr = ModerationCompatTools::getDB(
            DB_REPLICA,
        ); /* Only for $dbr->timestamp(), won't do any SQL queries */

        /* Prepare known values of $fields */
        $this->fields = [
            "mod_timestamp" => $dbr->timestamp(),
            "mod_user" => $user->getId(),
            "mod_user_text" => $user->getName(),
            "mod_cur_id" => 0, # Unknown, set by edit()
            "mod_namespace" => $title->getNamespace(),
            "mod_title" => $title->getDBKey(),
            "mod_comment" => "", # Unknown, set by setSummary()
            "mod_minor" => 0, # Unknown, set by setMinor()
            "mod_bot" => 0, # Unknown, set by setBot()
            "mod_new" => 0, # Unknown, set by edit()
            "mod_last_oldid" => 0, # Unknown, set by edit()
            "mod_ip" => $request->getIP(),
            "mod_old_len" => 0, # Unknown, set by edit()
            "mod_new_len" => 0, # Unknown, set by edit()
            "mod_header_xff" => $request->getHeader("X-Forwarded-For") ?: null,
            "mod_header_ua" => $request->getHeader("User-Agent") ?: null,
            "mod_preload_id" => $this->preload->getId(true),
            "mod_rejected" => 0,
            "mod_rejected_by_user" => 0,
            "mod_rejected_by_user_text" => null,
            "mod_rejected_batch" => 0,
            "mod_rejected_auto" => 0,
            "mod_preloadable" => 0,
            "mod_conflict" => 0,
            "mod_merged_revid" => 0,
            "mod_text" => "", # Unknown, set by edit()
            "mod_stash_key" => null,
            "mod_type" => self::MOD_TYPE_EDIT, # Default, can be changed by move()
            "mod_page2_namespace" => 0, # Unknown, set by move()
            "mod_page2_title" => "", # Unknown, set by move()
        ];

        if ($blockCheck->isModerationBlocked($user)) {
            $this->markAsSpam();
        }
    }

    /**
     * @param WikiPage $wikiPage
     * @param Content $newContent
     * @param string $section
     * @param string $sectionText
     * @return self
     */
    public function edit(
        WikiPage $wikiPage,
        Content $newContent,
        $section,
        $sectionText,
    ) {
        $this->fields["mod_cur_id"] = $wikiPage->getId();
        $this->fields["mod_new"] = $wikiPage->exists() ? 0 : 1;
        $this->fields["mod_last_oldid"] = $wikiPage->getLatest();

        $oldContent = $wikiPage->getContent(RevisionRecord::RAW); // current revision's content
        if ($oldContent) {
            $this->fields["mod_old_len"] = $oldContent->getSize();
        }

        // Apply any section-related adjustments (if necessary).
        $newContent = $this->applySectionToNewContent(
            $newContent,
            $section,
            $sectionText,
        );

        $pstContent = $this->preSaveTransform($newContent);
        $this->fields["mod_text"] = $pstContent->serialize();
        $this->fields["mod_new_len"] = $pstContent->getSize();
        $this->addChangeTags("edit");

        return $this;
    }

    /**
     * Apply any section-editing logic to $newContent.
     * Returns Content object that can be used to correctly overwrite the entire page ("mod_text" field).
     *
     * @param Content $newContent New content that is only usable when you know which section was edited.
     * @param string $section
     * @param string $sectionText
     * @return Content
     */
    protected function applySectionToNewContent(
        Content $newContent,
        $section,
        $sectionText,
    ) {
        if ($section === "") {
            // Editing the entire page (not one section), no adjustments needed.
            return $newContent;
        }

        $pendingEdit = $this->preload->findPendingEdit($this->title);
        if (!$pendingEdit) {
            // No pending edit, no adjustments needed.
            return $newContent;
        }

        // We must recalculate $newContent here.
        // Otherwise if the user adds or modifies two (or more) different sections (in consequent edits),
        // then only modification to the last one will be saved,
        // because $newContent is [old content] PLUS [modified section from the edit].
        $model = $newContent->getModel();
        return ContentHandler::makeContent(
            $pendingEdit->getText(),
            null,
            $model,
        )->replaceSection(
            $section,
            ContentHandler::makeContent($sectionText, null, $model),
            "",
        );
    }

    /**
     * @param Title $newTitle
     * @return self
     */
    public function move(Title $newTitle)
    {
        $this->fields["mod_type"] = self::MOD_TYPE_MOVE;
        $this->fields["mod_page2_namespace"] = $newTitle->getNamespace();
        $this->fields["mod_page2_title"] = $newTitle->getDBKey();
        $this->addChangeTags("move");

        return $this;
    }

    /**
     * @param string $stashKey
     * @return self
     */
    public function upload($stashKey)
    {
        $this->fields["mod_stash_key"] = $stashKey;
        $this->addChangeTags("upload");

        return $this;
    }

    /**
     * Place this change into the Spam folder.
     * @return self
     */
    public function markAsSpam()
    {
        $this->fields["mod_rejected"] = 1;
        $this->fields["mod_rejected_by_user_text"] = wfMessage(
            "moderation-blocker",
        )
            ->inContentLanguage()
            ->text();
        $this->fields["mod_rejected_auto"] = 1;

        return $this;
    }

    /**
     * @param bool $isMinor
     * @return self
     */
    public function setMinor($isMinor)
    {
        $this->fields["mod_minor"] = (int) $isMinor;
        return $this;
    }

    /**
     * @param bool $isBot
     * @return self
     */
    public function setBot($isBot)
    {
        $this->fields["mod_bot"] = (int) $isBot;
        return $this;
    }

    /**
     * @param string $summary
     * @return self
     */
    public function setSummary($summary)
    {
        $this->fields["mod_comment"] = $summary;
        return $this;
    }

    /*-------------------------------------------------------------------*/

    /**
     * Replace things like "~~~~" in $content.
     * @param Content $content
     * @return Content object.
     */
    protected function preSaveTransform(Content $content)
    {
        $popts = ParserOptions::newFromUserAndLang(
            $this->user,
            $this->contentLanguage,
        );

        return ModerationCompatTools::preSaveTransform(
            $content,
            $this->title,
            $this->user,
            $popts,
        );
    }

    /**
     * Add AbuseFilter tags to this change, if any.
     * @param string $action AbuseFilter action, e.g. 'edit' or 'delete'.
     */
    protected function addChangeTags($action)
    {
        $tagsArray = $this->findAbuseFilterTags(
            $this->title,
            $this->user,
            $action,
        );
        $tagsArray = array_filter($tagsArray, function ($tag) {
            if ($tag === "moderation-spam") {
                // Moderation allows AbuseFilter to instruct "this edit should be sent to Spam folder"
                // by adding "moderation-spam" tag to this edit.
                $this->markAsSpam();

                // Remove this internal tag, it shouldn't be restored on Approve.
                return false;
            }

            // Remember this tag.
            return true;
        });
        $this->fields["mod_tags"] = $tagsArray
            ? implode("\n", $tagsArray)
            : null;
    }

    /**
     * Calculate the value of mod_tags.
     * @param Title $title
     * @param User $user
     * @param string $action AbuseFilter action, e.g. 'edit' or 'delete'.
     * @return string[]
     */
    protected function findAbuseFilterTags(Title $title, User $user, $action)
    {
        $services = MediaWikiServices::getInstance();
        $serviceName = "AbuseFilterChangeTagger";

        if (!$services->hasService($serviceName)) {
            // AbuseFilter not installed.
            return [];
        }

        $changeTagger = $services->getService($serviceName);

        // Construct a synthetic RecentChange object for AbuseFilter to determine the "action ID".
        $recentChange = RecentChange::newLogEntry(
            wfTimestampNow(),
            $title,
            $user,
            "",
            "",
            $action,
            $action,
            $title,
            "",
            "",
        );
        return $changeTagger->getTagsForRecentChange($recentChange, false);
    }

    /**
     * Returns all mod_* fields for database INSERT.
     * @return array (as expected by $dbw->insert())
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * Returns one of the fields for database INSERT.
     * @param string $fieldName Field, e.g. "mod_timestamp".
     * @return string|null
     */
    public function getField($fieldName)
    {
        return $this->fields[$fieldName] ?? null;
    }

    /**
     * Queue edit for moderation.
     * @return int mod_id of affected row.
     */
    public function queue()
    {
        $modid = $this->insert();

        // Run hook to allow other extensions be notified about pending changes
        $this->hookRunner->onModerationPending($this->getFields(), $modid);

        $this->notify($modid);

        return $modid;
    }

    /**
     * Notify moderators about this newly saved pending change.
     * @param int $modid mod_id of affected row.
     */
    public function notify($modid)
    {
        if ($this->getField("mod_rejected_auto")) {
            // This change was placed into the Spam folder. No need to notify.
            return;
        }

        // Notify administrator by email
        $this->sendNotificationEmail($modid);

        // Enable in-wiki notification "New changes await moderation" for moderators
        $this->notifyModerator->setPendingTime(
            $this->getField("mod_timestamp"),
        );
    }

    /**
     * Insert this change into the moderation SQL table.
     * @return int mod_id of affected row.
     */
    protected function insert()
    {
        return $this->consequenceManager->add(
            new InsertRowIntoModerationTableConsequence($this->getFields()),
        );
    }

    /**
     * Send email to moderators that new change has appeared.
     * @param int $modid mod_id of affected row.
     */
    public function sendNotificationEmail($modid)
    {
        global $wgModerationNotificationEnable,
            $wgModerationNotificationNewOnly,
            $wgModerationEmail;

        if (!$wgModerationNotificationEnable || !$wgModerationEmail) {
            return; /* Disabled */
        }

        if (
            $wgModerationNotificationNewOnly &&
            $this->getField("mod_new") == 0
        ) {
            /* We only need to notify about new pages,
             and this is an edit in existing page */
            return;
        }

        $this->consequenceManager->add(
            new SendNotificationEmailConsequence(
                $this->title,
                $this->user,
                $modid,
            ),
        );
    }
}
