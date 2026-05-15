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
 * Unit test of ModerationBlockCheck.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\ModerationBlockCheck;
use User;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class ModerationBlockCheckTest extends ModerationUnitTestCase
{
    /**
     * Test that ModerationBlockCheck::isModerationBlocked() returns correct value.
     * @covers MediaWiki\Moderation\ModerationBlockCheck
     */
    public function testIsBlocked()
    {
        $blockedUser = User::newFromName(
            "Some blocked user " . rand(0, 100000),
            false,
        );
        $notBlockedUser = User::newFromName(
            "Not blocked " . rand(0, 100000),
            false,
        );

        $this->db->insert(
            "moderation_block",
            [
                "mb_address" => $blockedUser->getName(),
                "mb_user" => 0,
                "mb_by" => 123,
                "mb_by_text" => "Some moderator",
                "mb_timestamp" => $this->db->timestamp(),
            ],
            __METHOD__,
        );

        $blockCheck = new ModerationBlockCheck();

        $this->assertTrue($blockCheck->isModerationBlocked($blockedUser));
        $this->assertFalse($blockCheck->isModerationBlocked($notBlockedUser));

        // isModerationBlocked() should return false if the user has been unblocked.
        $this->db->delete(
            "moderation_block",
            ["mb_address" => $blockedUser->getName()],
            __METHOD__,
        );
        $this->assertFalse($blockCheck->isModerationBlocked($blockedUser));
    }
}
