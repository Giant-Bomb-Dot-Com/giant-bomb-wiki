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
 * Normal edit (modification of page text) that awaits moderation.
 */

namespace MediaWiki\Moderation;

use Status;
use User;

class ModerationEntryEdit extends ModerationApprovableEntry
{
    /**
     * Approve this edit.
     * @param User $moderator @phan-unused-param
     * @return Status object.
     */
    public function doApprove(User $moderator)
    {
        $row = $this->getRow();
        $user = $this->getUser();
        $title = $this->getTitle();

        $status = $this->consequenceManager->add(
            new ApproveEditConsequence(
                $user,
                $title,
                $row->text,
                $row->comment,
                $row->bot && $user->isAllowed("bot"),
                (bool) $row->minor,
                (int) $row->last_oldid,
            ),
        );

        if ($status->hasMessage("moderation-edit-conflict")) {
            /* Failed to merge automatically.
             Can still be merged manually by moderator */
            $this->consequenceManager->add(
                new MarkAsConflictConsequence($row->id),
            );
        } elseif ($status->hasMessage("edit-no-change")) {
            /* There is nothing to approve,
				because this page already the same text as what this change is proposing.
				Move this change from Pending folder to Rejected folder.
			*/
            $rejectedCount = $this->consequenceManager->add(
                new RejectOneConsequence($row->id, $moderator),
            );
            if ($rejectedCount > 0) {
                $this->consequenceManager->add(
                    new AddLogEntryConsequence("reject", $moderator, $title, [
                        "modid" => $row->id,
                        "user" => (int) $row->user,
                        "user_text" => $row->user_text,
                    ]),
                );
            }
        }

        return $status;
    }
}
