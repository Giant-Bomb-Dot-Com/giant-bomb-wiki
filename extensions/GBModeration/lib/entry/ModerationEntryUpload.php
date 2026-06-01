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
 * File upload that awaits moderation.
 */

namespace MediaWiki\Moderation;

use Status;
use User;

class ModerationEntryUpload extends ModerationApprovableEntry
{
    /**
     * Approve this upload.
     * @param User $moderator @phan-unused-param
     * @return Status object.
     */
    public function doApprove(User $moderator)
    {
        $row = $this->getRow();

        return $this->consequenceManager->add(
            new ApproveUploadConsequence(
                $row->stash_key,
                $this->getTitle(),
                $this->getUser(),
                $row->comment,
                $row->text,
            ),
        );
    }
}
