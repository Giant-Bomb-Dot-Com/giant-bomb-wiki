<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2025 Edward Chernenko.

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
 * Unit test of UnblockUserConsequence.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\BlockUserConsequence;
use MediaWiki\Moderation\UnblockUserConsequence;
use User;
use Wikimedia\IPUtils;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class UnblockUserConsequenceTest extends ModerationUnitTestCase
{
    /**
     * Verify that UnblockUserConsequence returns false if the user wasn't blocked to begin with.
     * @param string $username
     * @covers MediaWiki\Moderation\BlockUserConsequence
     * @dataProvider dataProviderUnblockUser
     */
    public function testNoopUnblockUser($username)
    {
        // Create and run the Consequence.
        $consequence = new UnblockUserConsequence($username);
        $somethingChanged = $consequence->run();

        $this->assertFalse($somethingChanged);
        $this->assertNotBlocked($username);
    }

    /**
     * Verify that UnblockUserConsequence removes an existing block from the database.
     * @param string $username
     * @covers MediaWiki\Moderation\UnblockUserConsequence
     * @dataProvider dataProviderUnblockUser
     */
    public function testUnblockUser($username)
    {
        if (IPUtils::isIPAddress($username)) {
            $this->disableAutoCreateTempUser();
        }

        // Make a currently blocked user.
        $user = User::createNew($username);
        $moderator = User::createNew("Some moderator");
        $blockConsequence = new BlockUserConsequence(
            $user->getId(),
            $username,
            $moderator,
        );
        $blockConsequence->run();

        // Create and run the Consequence.
        $consequence = new UnblockUserConsequence($username);
        $somethingChanged = $consequence->run();

        $this->assertTrue($somethingChanged);
        $this->assertNotBlocked($username);
    }

    /**
     * Assert that the block doesn't exist in the database.
     * @param string $username
     */
    private function assertNotBlocked($username)
    {
        $this->assertSelect(
            "moderation_block",
            ["mb_id"],
            ["mb_address" => $username],
            [], // Expected result: no rows selected.
        );
    }

    /**
     * Provide datasets for testUnblockUser() and testNoopUnblockUser() runs.
     * @return array
     */
    public function dataProviderUnblockUser()
    {
        return [
            "anonymous user" => ["10.11.12.13"],
            "registered user" => ["Registered user (ID 1234)"],
        ];
    }
}
