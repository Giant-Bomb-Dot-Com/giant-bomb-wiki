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
 * Basic TestSet for tests which precreate a change that awaits moderation.
 */

namespace MediaWiki\Moderation\Tests;

use IDBAccessObject;
use MediaWiki\Moderation\ModerationCompatTools;
use MediaWiki\Moderation\ModerationUploadStorage;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MWTimestamp;
use UnexpectedValueException;
use User;

trait ModerationTestsuitePendingChangeTestSet
{
    use ModerationTestsuiteTestSet;

    /** @var array All mod_* fields of one row in the 'moderation' SQL table */
    protected $fields;

    /** @var bool If true, existing page will be edited. If false, new page will be created. */
    protected $existing = false;

    /** @var string Source filename, only used for uploads. */
    protected $filename = null;

    /** @var bool If true, user will be modblocked. */
    protected $modblocked = false;

    /** @var bool If true, moderator will NOT be automoderated. */
    protected $notAutomoderated = false;

    /** @var bool If true, mod_text will equal the text of previous revision (if any) or "". */
    protected $nullEdit = false;

    /** @var string Text of existing page. Used if 1) $existing is true or 2) when testing a move. */
    protected $textOfPrecreatedPage = "Original text of the page precreated by PendingChangeTestSet";

    /**
     * Initialize this TestSet from the input of dataProvider.
     * @param array $options
     */
    protected function applyOptions(array $options)
    {
        $this->fields = $this->getDefaultFields();
        foreach ($options as $key => $value) {
            switch ($key) {
                case "existing":
                case "filename":
                case "modblocked":
                case "notAutomoderated":
                case "nullEdit":
                    $this->$key = $value;
                    break;

                default:
                    if (strpos($key, "mod_") !== 0) {
                        throw new UnexpectedValueException(
                            "Incorrect key \"{$key}\": expected \"mod_\" prefix.",
                        );
                    }
                    if (!array_key_exists($key, $this->fields)) {
                        throw new UnexpectedValueException(
                            "Unknown key \"{$key}\": not listed in default field values.",
                        );
                    }
                    $this->fields[$key] = $value;
            }
        }

        $dbr = ModerationCompatTools::getDB(DB_REPLICA); // Only for $dbr->timestamp();

        /* Anonymous users have mod_user_text=mod_ip, so we don't want mod_ip in $options
			(for better readability of dataProvider and to avoid typos).
		*/
        if ($this->fields["mod_user"] == 0) {
            $this->fields["mod_ip"] = $this->fields["mod_user_text"];
        }

        /* Remove default mod_page2_* fields if we are not testing the move. */
        if ($this->fields["mod_type"] != "move") {
            $this->fields["mod_page2_namespace"] = 0;
            $this->fields["mod_page2_title"] = "";
        }

        // Support tests like 'mod_timestamp' => '-5 days'
        if (preg_match("/^[+\-]/", $this->fields["mod_timestamp"])) {
            $modify = $this->fields["mod_timestamp"];

            $ts = new MWTimestamp();
            $ts->timestamp->modify($modify);
            $this->fields["mod_timestamp"] = $ts->getTimestamp(TS_MW);
        }

        // Avoid timestamps like 23:59, because they can be tested
        // on 0:00 of the next day, while assertTimestamp() has checks
        // that depend on "was the edit today or not?".
        $this->fields["mod_timestamp"] = preg_replace(
            "/(?<=235)[0-9]/",
            "0", // Replace with 23:50
            $this->fields["mod_timestamp"],
        );

        // Populate mod_stash_key for uploads.
        if ($this->filename) {
            $srcPath = $this->findSourceFilename();

            // Store the image in UploadStash.
            $stash = ModerationUploadStorage::getStash();
            $this->fields["mod_stash_key"] = $stash
                ->stashFile($srcPath)
                ->getFileKey();

            // Default value of mod_title for uploads: same as filename.
            if (!isset($options["mod_title"])) {
                $this->fields["mod_title"] = strtr(
                    ucfirst($this->filename),
                    " ",
                    "_",
                );
            }

            // Uploads are always in File: namespace.
            $this->fields["mod_namespace"] = NS_FILE;
        }

        if ($this->existing || $this->fields["mod_type"] == "move") {
            // Precreate the page/file.
            $title = $this->getExpectedTitleObj();

            $this->precreatePage(
                $title,
                $this->textOfPrecreatedPage,
                $this->filename,
            );

            $this->fields["mod_new"] = 0;
            $this->fields["mod_last_oldid"] = $title->getLatestRevID(
                IDBAccessObject::READ_LATEST,
            );
            $this->fields["mod_old_len"] = strlen($this->textOfPrecreatedPage);

            // Make sure that mod_timestamp is not earlier than the timestamp of precreated edit,
            // otherwise the order of history will be wrong.
            $this->fields["mod_timestamp"] = $dbr->timestamp();
        }

        if ($this->nullEdit || ($this->existing && $this->filename)) {
            // Either simulated "null edit" or a reupload
            // (reuploads don't modify the text of the page).
            $page = ModerationCompatTools::makeWikiPage(
                $this->getExpectedTitleObj(),
            );
            $oldContent = $page->getContent(RevisionRecord::RAW);

            $this->fields["mod_text"] = $oldContent
                ? $oldContent->serialize()
                : "";
            $this->fields["mod_new_len"] = $oldContent
                ? $oldContent->getSize()
                : 0;
        }

        // Encode the timestamp (needed for PostgreSQL)
        $this->fields["mod_timestamp"] = $dbr->timestamp(
            $this->fields["mod_timestamp"],
        );
    }

    /**
     * Returns full path of $this->filename.
     * @return string
     */
    protected function findSourceFilename()
    {
        return ModerationTestsuite::findSourceFilename($this->filename);
    }

    /**
     * Returns default value for $fields.
     * This represents situation when dataProvider provides an empty array.
     * @return array
     */
    protected function getDefaultFields()
    {
        $t = $this->getTestsuite();
        $user = $t->unprivilegedUser;

        $dbr = ModerationCompatTools::getDB(DB_REPLICA); // Only for $dbr->timestamp();

        return [
            "mod_timestamp" => $dbr->timestamp(),
            "mod_user" => $user->getId(),
            "mod_user_text" => $user->getName(),
            "mod_cur_id" => 0,
            "mod_namespace" => 0,
            "mod_title" => "Test page 1",
            "mod_comment" => "Some reason",
            "mod_minor" => 0,
            "mod_bot" => 0,
            "mod_new" => 1,
            "mod_last_oldid" => 0,
            "mod_ip" => "127.1.2.3",
            "mod_old_len" => 0,
            "mod_new_len" => 8, // Length of mod_text, see below
            "mod_header_xff" => null,
            "mod_header_ua" => ModerationTestsuite::DEFAULT_USER_AGENT,
            "mod_preload_id" => "]fake",
            "mod_rejected" => 0,
            "mod_rejected_by_user" => 0,
            "mod_rejected_by_user_text" => null,
            "mod_rejected_batch" => 0,
            "mod_rejected_auto" => 0,
            "mod_preloadable" => 0,
            "mod_conflict" => 0,
            "mod_merged_revid" => 0,
            "mod_text" => "New text",
            "mod_stash_key" => "",
            "mod_tags" => null,
            "mod_type" => "edit",
            "mod_page2_namespace" => 0,
            "mod_page2_title" => "Test page 2",
        ];
    }

    /**
     * Returns Title object of the page mentioned in $this->fields.
     * @param string $nsField Database field with namespace number.
     * @param string $titleField Database field with title.
     * @return Title
     */
    protected function getExpectedTitleObj(
        $nsField = "mod_namespace",
        $titleField = "mod_title",
    ) {
        return Title::makeTitle(
            $this->fields[$nsField],
            $this->fields[$titleField],
        );
    }

    /**
     * Returns pagename (string) of the page mentioned in $this->fields.
     * @param string $nsField Database field with namespace number.
     * @param string $titleField Database field with title.
     * @return string
     */
    protected function getExpectedTitle(
        $nsField = "mod_namespace",
        $titleField = "mod_title",
    ) {
        return $this->getExpectedTitleObj($nsField, $titleField)->getFullText();
    }

    /**
     * Returns pagename (string) of the second page mentioned in $this->fields.
     * @return string
     */
    protected function getExpectedPage2Title()
    {
        return $this->getExpectedTitle(
            "mod_page2_namespace",
            "mod_page2_title",
        );
    }

    /**
     * Get the test user who issues a moderation block if modblocked=true was requested.
     * @return User
     */
    protected function getModeratorWhoBlocked()
    {
        // We don't really need this account to exist,
        // it's only used for logging its ID/Name as mb_by/mb_by_text.
        return User::newFromName("Some moderator", false);
    }

    /**
     * Execute the TestSet, making an edit/upload/move with requested parameters.
     */
    protected function makeChanges()
    {
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $dbw->insert("moderation", $this->fields, __METHOD__);

        $this->assertSame(
            1,
            $dbw->affectedRows(),
            "Failed to insert a row into the 'moderation' SQL table.",
        );

        $this->fields["mod_id"] = $dbw->insertId();

        if ($this->modblocked) {
            /* Apply ModerationBlock to author of this change */
            $dbw->insert(
                "moderation_block",
                [
                    "mb_address" => $this->fields["mod_user_text"],
                    "mb_user" => $this->fields["mod_user"],
                    "mb_by" => $this->getModeratorWhoBlocked()->getId(),
                    "mb_by_text" => $this->getModeratorWhoBlocked()->getName(),
                    "mb_timestamp" => $dbw->timestamp(),
                ],
                __METHOD__,
            );
        }
    }
}
