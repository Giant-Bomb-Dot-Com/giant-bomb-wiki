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
 * Page move (proposal to rename the page) that awaits moderation.
 */

namespace MediaWiki\Moderation;

use Status;
use User;

class ModerationEntryMove extends ModerationApprovableEntry
{
    /**
     * Approve this move.
     * @param User $moderator
     * @return Status object.
     */
    public function doApprove(User $moderator)
    {
        $row = $this->getRow();

        return $this->consequenceManager->add(
            new ApproveMoveConsequence(
                $moderator,
                $this->getTitle() /* old name of the article */,
                $this->getPage2Title() /* new (suggested) name of the article */,
                $this->getUser(),
                $row->comment,
            ),
        );
    }

    /**
     * Post-approval log subtype.
     * @return string
     */
    protected function getApproveLogSubtype()
    {
        return "approve-move";
    }

    /**
     * Parameters for post-approval log.
     * @return array
     */
    protected function getApproveLogParameters()
    {
        $row = $this->getRow();
        return [
            "4::target" => $this->getPage2Title()->getPrefixedText(),
            "user" => (int) $row->user,
            "user_text" => $row->user_text,
        ];
    }
}
