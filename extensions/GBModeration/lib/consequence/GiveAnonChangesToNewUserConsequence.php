<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2021 Edward Chernenko.

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
 * Consequence to make anonymous pending changes into non-anonymous if their author has registered.
 */

namespace MediaWiki\Moderation;

use User;

class GiveAnonChangesToNewUserConsequence implements IConsequence
{
    /** @var User */
    protected $user;

    /** @var string */
    protected $oldPreloadId;

    /** @var string */
    protected $newPreloadId;

    /**
     * @param User $user
     * @param string $oldPreloadId
     * @param string $newPreloadId
     */
    public function __construct(User $user, $oldPreloadId, $newPreloadId)
    {
        $this->user = $user;
        $this->oldPreloadId = $oldPreloadId;
        $this->newPreloadId = $newPreloadId;
    }

    /**
     * Execute the consequence.
     */
    public function run()
    {
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $dbw->update(
            "moderation",
            [
                "mod_user" => $this->user->getId(),
                "mod_user_text" => $this->user->getName(),
                "mod_preload_id" => $this->newPreloadId,
            ],
            [
                "mod_preload_id" => $this->oldPreloadId,
                "mod_preloadable" => 0,
            ],
            __METHOD__,
            ["USE INDEX" => "moderation_signup"],
        );
    }
}
