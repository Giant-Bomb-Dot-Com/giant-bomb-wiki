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
 * Consequence that removes ModerationBlock from the user.
 */

namespace MediaWiki\Moderation;

class UnblockUserConsequence implements IConsequence
{
    /** @var string */
    protected $username;

    /**
     * @param string $username
     */
    public function __construct($username)
    {
        $this->username = $username;
    }

    /**
     * Execute the consequence.
     * @return bool True if existing block was removed, false otherwise.
     */
    public function run()
    {
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $dbw->delete(
            "moderation_block",
            ["mb_address" => $this->username],
            __METHOD__,
        );

        return $dbw->affectedRows() > 0;
    }
}
