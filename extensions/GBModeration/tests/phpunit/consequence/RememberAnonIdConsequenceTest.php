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
 * Unit test of RememberAnonIdConsequence.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\RememberAnonIdConsequence;
use RequestContext;

require_once __DIR__ . "/autoload.php";

class RememberAnonIdConsequenceTest extends ModerationUnitTestCase
{
    /**
     * Verify that RememberAnonIdConsequence returns a newly generated ID, saving it into session.
     * @covers MediaWiki\Moderation\RememberAnonIdConsequence
     */
    public function testNewAnonId()
    {
        // Sanity check: Session shouldn't have anon_id before the test.
        $request = RequestContext::getMain()->getRequest();
        $this->assertEmpty($request->getSessionData("anon_id"));
        $this->assertFalse($request->getSession()->isPersistent());

        // Create and run the Consequence.
        $consequence = new RememberAnonIdConsequence();
        $id = $consequence->run();

        $this->assertNotEmpty($id);
        $this->assertSame(
            32,
            strlen($id),
            "Length of newly generated anon_id string",
        );

        $request = RequestContext::getMain()->getRequest();
        $this->assertSame(
            $id,
            $request->getSessionData("anon_id"),
            "Newly generated anon_id wasn't saved into the session.",
        );
        $this->assertTrue(
            $request->getSession()->isPersistent(),
            "Session didn't become persistent after RememberAnonIdConsequence.",
        );
    }
}
