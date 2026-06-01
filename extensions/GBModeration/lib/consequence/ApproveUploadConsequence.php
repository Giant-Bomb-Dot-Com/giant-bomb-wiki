<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2018-2020 Edward Chernenko.

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
 * Consequence that approves one upload.
 */

namespace MediaWiki\Moderation;

use MediaWiki\Title\Title;
use Status;
use UploadFromStash;
use UploadStashFileNotFoundException;
use User;

class ApproveUploadConsequence implements IConsequence
{
    /** @var string */
    protected $stashKey;

    /** @var Title */
    protected $title;

    /** @var User */
    protected $user;

    /** @var string */
    protected $comment;

    /** @var string */
    protected $pageText;

    /**
     * @param string $stashKey
     * @param Title $title
     * @param User $user
     * @param string $comment
     * @param string $pageText
     */
    public function __construct(
        $stashKey,
        Title $title,
        User $user,
        $comment,
        $pageText,
    ) {
        $this->stashKey = $stashKey;
        $this->title = $title;
        $this->user = $user;
        $this->comment = $comment;
        $this->pageText = $pageText;
    }

    /**
     * Execute the consequence.
     * @return Status
     */
    public function run()
    {
        # This is the upload from stash.
        $stash = ModerationUploadStorage::getStash();
        $upload = new UploadFromStash($this->user, $stash);

        try {
            $upload->initialize($this->stashKey, $this->title->getText());
        } catch (UploadStashFileNotFoundException $_) {
            return Status::newFatal("moderation-missing-stashed-image");
        }

        return $upload->performUpload(
            $this->comment,
            $this->pageText,
            false,
            $this->user,
        );
    }
}
