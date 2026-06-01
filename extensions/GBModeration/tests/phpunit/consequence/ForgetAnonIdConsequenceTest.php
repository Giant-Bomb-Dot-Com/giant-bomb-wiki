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
 * Unit test of ForgetAnonIdConsequence.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\ForgetAnonIdConsequence;
use RequestContext;

require_once __DIR__ . "/autoload.php";

class ForgetAnonIdConsequenceTest extends ModerationUnitTestCase
{
    /**
     * Verify that ForgetAnonIdConsequence removes anon_id from the current session.
     * @covers MediaWiki\Moderation\ForgetAnonIdConsequence
     */
    public function testForgetAnonId()
    {
        $oldAnonId = 67890;
        RequestContext::getMain()
            ->getRequest()
            ->setSessionData("anon_id", $oldAnonId);

        // Create and run the Consequence.
        $consequence = new ForgetAnonIdConsequence();
        $consequence->run();

        $this->assertNull(
            RequestContext::getMain()->getRequest()->getSessionData("anon_id"),
            "anon_id wasn't deleted from the session after ForgetAnonIdConsequence.",
        );
    }
}
