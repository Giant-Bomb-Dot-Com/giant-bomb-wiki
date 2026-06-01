<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020 Edward Chernenko.

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
 * Consequence that writes new upload into the moderation queue.
 */

namespace MediaWiki\Moderation;

use ContentHandler;
use MediaWiki\MediaWikiServices;
use UploadBase;
use User;
use WikiPage;

class QueueUploadConsequence implements IConsequence
{
    /** @var UploadBase */
    protected $upload;

    /** @var User */
    protected $user;

    /** @var string */
    protected $comment;

    /** @var string */
    protected $pageText;

    /**
     * @param UploadBase $upload
     * @param User $user
     * @param string $comment
     * @param string $pageText
     */
    public function __construct(
        UploadBase $upload,
        User $user,
        $comment,
        $pageText,
    ) {
        $this->upload = $upload;
        $this->user = $user;
        $this->comment = $comment;
        $this->pageText = $pageText;
    }

    /**
     * Execute the consequence.
     * @return array|null Error (array of parameters for wfMessage) or null if queued successfully.
     *
     * @phan-return array<string>|null
     */
    public function run()
    {
        /* Step 1. Upload the file into the user's stash */
        $status = $this->upload->tryStashFile(
            ModerationUploadStorage::getOwner(),
            true /* Don't run UploadStashFile hook */,
        );
        if (!$status->isOK()) {
            return ["api-error-stashfailed"];
        }

        $file = $status->getValue();

        /* Step 2. Create a page in File namespace (it will be queued for moderation) */
        $title = $this->upload->getTitle();

        $page = new WikiPage($title);
        $content = ContentHandler::makeContent($this->pageText, $title);

        $factory = MediaWikiServices::getInstance()->getService(
            "Moderation.NewChangeFactory",
        );
        $change = $factory->makeNewChange($title, $this->user);
        $change
            ->edit($page, $content, "", "")
            ->upload($file->getFileKey())
            ->setSummary($this->comment)
            ->queue();

        // Successfully queued for moderation (no errors)
        return null;
    }
}
