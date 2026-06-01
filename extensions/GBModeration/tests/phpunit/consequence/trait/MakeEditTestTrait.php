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
 * Trait that provides makeEdit() for quickly precreating pages.
 */

namespace MediaWiki\Moderation\Tests;

use CommentStoreComment;
use ContentHandler;
use MediaWiki\Moderation\ModerationCompatTools;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use User;

/**
 * @method static void assertTrue($a, string $message='')
 */
trait MakeEditTestTrait
{
    /**
     * Make one test edit on behalf of $user in page $title.
     * @param Title $title
     * @param User $user
     * @param string|null $text
     * @return int rev_id of the newly created edit.
     */
    protected function makeEdit(Title $title, User $user, $text = null)
    {
        if (!$text) {
            $text =
                "Some text " .
                rand(0, 100000) .
                " in page " .
                $title->getFullText() .
                " by " .
                $user->getName();
        }

        $page = ModerationCompatTools::makeWikiPage($title);
        $content = ContentHandler::makeContent(
            $text,
            null,
            CONTENT_MODEL_WIKITEXT,
        );
        $summary = CommentStoreComment::newUnsavedComment("Some edit summary");

        $updater = $page->newPageUpdater($user);
        $updater->setContent(SlotRecord::MAIN, $content);
        $rev = $updater->saveRevision($summary, EDIT_INTERNAL);

        $status = $updater->getStatus();
        $this->assertTrue(
            $status->isGood(),
            "Edit failed: " . $status->getMessage()->plain(),
        );

        return $rev ? $rev->getId() : 0;
    }
}
