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
 * Unit test of BlockUserConsequence.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\BlockUserConsequence;
use User;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class BlockUserConsequenceTest extends ModerationUnitTestCase
{
    /**
     * Verify that BlockUserConsequence adds a block to the database.
     * @param int $userId
     * @param string $username
     * @param string $moderatorName
     * @covers MediaWiki\Moderation\BlockUserConsequence
     * @dataProvider dataProviderBlockUser
     */
    public function testBlockUser($userId, $username, $moderatorName)
    {
        $moderator = User::createNew($moderatorName);

        // Create and run the Consequence.
        $consequence = new BlockUserConsequence($userId, $username, $moderator);
        $somethingChanged = $consequence->run();

        $this->assertTrue($somethingChanged);

        // New row should have appeared in the database.
        $this->assertBlockRecorded($userId, $username, $moderator);
    }

    /**
     * Verify that BlockUserConsequence on already blocked user returns false.
     * @param int $userId
     * @param string $username
     * @param string $moderatorName
     * @covers MediaWiki\Moderation\BlockUserConsequence
     * @dataProvider dataProviderBlockUser
     */
    public function testNoopBlockUser($userId, $username, $moderatorName)
    {
        $moderator = User::createNew($moderatorName);

        // Create and run the Consequence.
        $consequence1 = new BlockUserConsequence(
            $userId,
            $username,
            $moderator,
        );
        $consequence1->run();

        $consequence2 = new BlockUserConsequence(
            $userId,
            $username,
            $moderator,
        );
        $somethingChanged = $consequence2->run();
        $this->assertFalse($somethingChanged);

        // Despite $consequence2 doing nothing, the row should still exist in the database.
        $this->assertBlockRecorded($userId, $username, $moderator);
    }

    /**
     * Assert that the block exists in the database.
     * @param int $userId
     * @param string $username
     * @param User $moderator
     */
    private function assertBlockRecorded($userId, $username, User $moderator)
    {
        $this->assertSelect(
            "moderation_block",
            ["mb_user", "mb_by", "mb_by_text"],
            ["mb_address" => $username],
            [[$userId, $moderator->getId(), $moderator->getName()]],
        );
    }

    /**
     * Provide datasets for testBlockUser() and testNoopBlockUser() runs.
     * @return array
     */
    public function dataProviderBlockUser()
    {
        return [
            "anonymous user" => [0, "10.11.12.13", "First moderator"],
            "registered user" => [
                1234,
                "Registered user (ID 1234)",
                "Second moderator",
            ],
        ];
    }
}
