<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2024 Edward Chernenko.

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
 * Consequence that approves one pending page move (proposal to rename the page).
 */

namespace MediaWiki\Moderation;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Status;
use User;

class ApproveMoveConsequence implements IConsequence
{
    /** @var User */
    protected $moderator;

    /** @var Title */
    protected $oldTitle;

    /** @var Title */
    protected $newTitle;

    /** @var User */
    protected $user;

    /** @var string */
    protected $reason;

    /**
     * @param User $moderator
     * @param Title $oldTitle
     * @param Title $newTitle
     * @param User $user
     * @param string $reason
     */
    public function __construct(
        User $moderator,
        Title $oldTitle,
        Title $newTitle,
        User $user,
        $reason,
    ) {
        $this->moderator = $moderator;
        $this->oldTitle = $oldTitle;
        $this->newTitle = $newTitle;
        $this->user = $user;
        $this->reason = $reason;
    }

    /**
     * Execute the consequence.
     * @return Status
     */
    public function run()
    {
        $factory = MediaWikiServices::getInstance()->getMovePageFactory();
        $mp = $factory->newMovePage($this->oldTitle, $this->newTitle);

        /* Sanity checks like "page with the new name should not exist" */
        $status = $mp->isValidMove();
        if (!$status->isOK()) {
            return $status;
        }

        /* There is no need to call $mp->authorizeMove( $this->getUser(), $reason ),
			because (1) it was already checked BEFORE the move was queued,
			(2) this move is now being approved by moderator, so it doesn't matter
			whether $user has lost its right to move (e.g. got blocked) or not.

			However, we need to ensure that moderator himself is allowed to move!
			Some wikis may grant moderator flag to random users who offer help,
			and they don't necessarily want to give them "move" right,
			because "move" right can be used for hard-to-revert vandalism.
		*/
        $permissionStatus = $mp->authorizeMove($this->moderator, $this->reason);
        if (!$permissionStatus->isOK()) {
            /* Moderator is not allowed to move */
            return Status::wrap($permissionStatus);
        }

        return $mp->move(
            $this->user /* User who suggested the move */,
            $this->reason,
            true /* Always create redirect. This may be changed in the future */,
        );
    }
}
