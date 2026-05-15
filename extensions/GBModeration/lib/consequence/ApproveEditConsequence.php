<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2021 Edward Chernenko.

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
 * Consequence that approves one pending normal edit (not an upload, etc.).
 */

namespace MediaWiki\Moderation;

use CommentStoreComment;
use ContentHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use Status;
use User;
use WikiPage;

class ApproveEditConsequence implements IConsequence
{
    /** @var User */
    protected $user;

    /** @var Title */
    protected $title;

    /** @var string */
    protected $newText;

    /** @var string */
    protected $comment;

    /** @var bool */
    protected $isBot;

    /** @var bool */
    protected $isMinor;

    /** @var int */
    protected $baseRevId;

    /**
     * @param User $user
     * @param Title $title
     * @param string $newText
     * @param string $comment
     * @param bool $isBot
     * @param bool $isMinor
     * @param int $baseRevId
     */
    public function __construct(
        User $user,
        Title $title,
        $newText,
        $comment,
        $isBot,
        $isMinor,
        $baseRevId,
    ) {
        $this->user = $user;
        $this->title = $title;
        $this->newText = $newText;
        $this->comment = $comment;
        $this->isBot = $isBot;
        $this->isMinor = $isMinor;
        $this->baseRevId = $baseRevId;
    }

    /**
     * Execute the consequence.
     * @return Status
     */
    public function run()
    {
        $flags = EDIT_INTERNAL | EDIT_AUTOSUMMARY;
        if ($this->isBot && $this->user->isAllowed("bot")) {
            $flags |= EDIT_FORCE_BOT;
        }
        if ($this->isMinor && $this->user->isAllowed("minoredit")) {
            $flags |= EDIT_MINOR;
        }

        $model = $this->title->getContentModel();
        $newContent = ContentHandler::makeContent($this->newText, null, $model);
        $summary = CommentStoreComment::newUnsavedComment($this->comment);

        $page = new WikiPage($this->title);
        $updater = $page->newPageUpdater($this->user);

        if (!$page->exists() || $page->getLatest() == $this->baseRevId) {
            // This page is either new or hasn't changed since this edit was queued for moderation.
            // No need to check for edit conflicts.
            $updater->setContent(SlotRecord::MAIN, $newContent);
            $updater->saveRevision($summary, $flags);

            return $updater->getStatus();
        }

        # Page has changed! (edit conflict)
        # Let's try to merge this automatically (resolve the conflict),
        # as MediaWiki does in private EditPage::mergeChangesIntoContent().

        $services = MediaWikiServices::getInstance();

        $handler = $services
            ->getContentHandlerFactory()
            ->getContentHandler($model);
        $baseContent = $handler->makeEmptyContent();

        if ($this->baseRevId) {
            $rec = $services
                ->getRevisionLookup()
                ->getRevisionById($this->baseRevId);

            // Note: $rec may be null if page was deleted.
            if ($rec) {
                $baseContent = $rec
                    ->getSlot(SlotRecord::MAIN, RevisionRecord::RAW)
                    ->getContent();
            }
        }

        $latestContent = $page->getContent(RevisionRecord::RAW);
        $mergedContent = $handler->merge3(
            $baseContent,
            $newContent,
            $latestContent,
        );

        if ($mergedContent) {
            $updater->setContent(SlotRecord::MAIN, $mergedContent);
            $updater->saveRevision($summary, $flags);

            return $updater->getStatus();
        }

        return Status::newFatal("moderation-edit-conflict");
    }
}
