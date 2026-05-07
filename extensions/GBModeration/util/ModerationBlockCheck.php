<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2014-2021 Edward Chernenko.

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
 * Checks if the user is blacklisted.
 */

namespace MediaWiki\Moderation;

use User;

class ModerationBlockCheck
{
    /**
     * Returns true if $user is blacklisted, false otherwise.
     * @param User $user
     * @return bool
     */
    public function isModerationBlocked(User $user)
    {
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY); # Need actual data
        $blocked = $dbw->selectField(
            "moderation_block",
            "mb_id",
            ["mb_address" => $user->getName()],
            __METHOD__,
        );
        return (bool) $blocked;
    }
}
