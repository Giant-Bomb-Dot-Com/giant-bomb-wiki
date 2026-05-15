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
 * Consequence to generate new anon_id (used by ModerationPreload) and to save it in the session.
 */

namespace MediaWiki\Moderation;

use MediaWiki\Session\SessionManager;
use MWCryptRand;

class RememberAnonIdConsequence implements IConsequence
{
    /**
     * Execute the consequence.
     * @return string
     */
    public function run()
    {
        $id = MWCryptRand::generateHex(32);

        $session = SessionManager::getGlobalSession();
        $session->set("anon_id", $id);
        $session->persist();

        return $id;
    }
}
