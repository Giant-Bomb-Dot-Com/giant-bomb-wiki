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
 * Consequence to remove anon_id (used by ModerationPreload) from the current session.
 */

namespace MediaWiki\Moderation;

use MediaWiki\Session\SessionManager;

class ForgetAnonIdConsequence implements IConsequence
{
    /**
     * Execute the consequence.
     */
    public function run()
    {
        $session = SessionManager::getGlobalSession();
        $session->remove("anon_id");

        // No need to call $session->persist():
        // if session is not already persistent, then anon_id is not remembered anyway.
    }
}
