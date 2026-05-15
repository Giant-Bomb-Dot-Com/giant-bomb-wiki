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
 * Hooks related to moving (renaming) pages.
 */

namespace MediaWiki\Moderation;

use MediaWiki\Hook\TitleMoveHook;
use MediaWiki\Title\Title;
use Status;
use User;

class ModerationMoveHooks implements TitleMoveHook
{
    /** @var IConsequenceManager */
    protected $consequenceManager;

    /** @var ModerationCanSkip */
    protected $canSkip;

    /** @var EditFormOptions */
    protected $editFormOptions;

    /**
     * @param IConsequenceManager $consequenceManager
     * @param ModerationCanSkip $canSkip
     * @param EditFormOptions $editFormOptions
     */
    public function __construct(
        IConsequenceManager $consequenceManager,
        ModerationCanSkip $canSkip,
        EditFormOptions $editFormOptions,
    ) {
        $this->consequenceManager = $consequenceManager;
        $this->canSkip = $canSkip;
        $this->editFormOptions = $editFormOptions;
    }

    /**
     * Intercept attempts to rename pages and queue them for moderation.
     * @param Title $oldTitle
     * @param Title $newTitle
     * @param User $user
     * @param string $reason
     * @param Status &$status
     * @return bool|void
     */
    public function onTitleMove($oldTitle, $newTitle, $user, $reason, &$status)
    {
        if (
            $this->canSkip->canMoveSkip(
                $user,
                $oldTitle->getNamespace(),
                $newTitle->getNamespace(),
            )
        ) {
            // This user is allowed to bypass moderation
            return;
        }

        $this->consequenceManager->add(
            new QueueMoveConsequence($oldTitle, $newTitle, $user, $reason),
        );

        /* Watch/Unwatch $oldTitle/$newTitle immediately:
         watchlist is the user's own business, no reason to wait for approval of the move */
        $this->editFormOptions->watchIfNeeded($user, [$oldTitle, $newTitle]);

        $errorMsg = "moderation-move-queued";
        ModerationQueuedSuccessException::throwIfNeeded($errorMsg);

        $status->fatal($errorMsg);
        return false;
    }
}
