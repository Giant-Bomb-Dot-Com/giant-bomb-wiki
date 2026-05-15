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
 * Consequence that writes new move (renaming of a page) into the moderation queue.
 */

namespace MediaWiki\Moderation;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use User;

class QueueMoveConsequence implements IConsequence
{
    /** @var Title */
    protected $oldTitle;

    /** @var Title */
    protected $newTitle;

    /** @var User */
    protected $user;

    /** @var string */
    protected $reason;

    /**
     * @param Title $oldTitle
     * @param Title $newTitle
     * @param User $user
     * @param string $reason
     */
    public function __construct(
        Title $oldTitle,
        Title $newTitle,
        User $user,
        $reason,
    ) {
        $this->oldTitle = $oldTitle;
        $this->newTitle = $newTitle;
        $this->user = $user;
        $this->reason = $reason;
    }

    /**
     * Execute the consequence.
     * @return int mod_id of affected row.
     */
    public function run()
    {
        $factory = MediaWikiServices::getInstance()->getService(
            "Moderation.NewChangeFactory",
        );
        $change = $factory->makeNewChange($this->oldTitle, $this->user);
        return $change
            ->move($this->newTitle)
            ->setSummary($this->reason)
            ->queue();
    }
}
