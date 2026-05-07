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
 * Consequence that applies ModerationBlock to the user.
 */

namespace MediaWiki\Moderation;

use User;

class BlockUserConsequence implements IConsequence
{
    /** @var int */
    protected $userId;

    /** @var string */
    protected $username;

    /** @var User */
    protected $moderator;

    /**
     * @param int $userId
     * @param string $username
     * @param User $moderator
     */
    public function __construct($userId, $username, User $moderator)
    {
        $this->userId = $userId;
        $this->username = $username;
        $this->moderator = $moderator;
    }

    /**
     * Execute the consequence.
     * @return bool True if a new block was added, false otherwise.
     */
    public function run()
    {
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $dbw->insert(
            "moderation_block",
            [
                "mb_address" => $this->username,
                "mb_user" => $this->userId,
                "mb_by" => $this->moderator->getId(),
                "mb_by_text" => $this->moderator->getName(),
                "mb_timestamp" => $dbw->timestamp(),
            ],
            __METHOD__,
            ["IGNORE"],
        );

        return $dbw->affectedRows() > 0;
    }
}
