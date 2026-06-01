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
 * Unit test of GiveAnonChangesToNewUserConsequence.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\GiveAnonChangesToNewUserConsequence;
use MediaWiki\Moderation\ModerationCompatTools;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class GiveAnonChangesToNewUserConsequenceTest extends ModerationUnitTestCase
{
    use ModifyDbRowTestTrait;

    /**
     * Verify that GiveAnonChangesToNewUserConsequence modifies the author of database rows.
     * @covers MediaWiki\Moderation\GiveAnonChangesToNewUserConsequence
     */
    public function testGiveChanges()
    {
        [$idsToAffect, $idsToPreserve] = array_chunk(
            $this->makeSeveralDbRows(6),
            3,
        );

        $oldPreloadId = "some anonymous preload ID";
        $newPreloadId = "new non-anonymous preload ID";
        $unrelatedPreloadId = 'preload ID in rows that shouldn\'t be modified';

        // Make $idsToPreserve ineligible for modification due to having another mod_preload_id.
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $dbw->update(
            "moderation",
            ["mod_preload_id" => $unrelatedPreloadId],
            ["mod_id" => $idsToPreserve],
            __METHOD__,
        );

        // Make $idsToAffect targetable by modification
        $dbw->update(
            "moderation",
            ["mod_preload_id" => $oldPreloadId],
            ["mod_id" => $idsToAffect],
            __METHOD__,
        );

        // Create new user account.
        $user = self::getTestUser()->getUser();

        // Create and run the Consequence.
        $consequence = new GiveAnonChangesToNewUserConsequence(
            $user,
            $oldPreloadId,
            $newPreloadId,
        );
        $consequence->run();

        // Assert that $idsToAffect were modified.
        $this->assertSelect(
            "moderation",
            ["mod_user", "mod_user_text", "mod_preload_id"],
            ["mod_id" => $idsToAffect],
            array_fill(0, count($idsToAffect), [
                $user->getId(),
                $user->getName(),
                $newPreloadId,
            ]),
        );

        // Assert that $idsToPreserve were NOT changed.
        $this->assertSelect(
            "moderation",
            ["mod_user", "mod_user_text", "mod_preload_id"],
            ["mod_id" => $idsToPreserve],
            array_fill(0, count($idsToPreserve), [
                $this->authorUser->getId(),
                $this->authorUser->getName(),
                $unrelatedPreloadId,
            ]),
        );
    }

    /**
     * Verify that GiveAnonChangesToNewUserConsequence doesn't affect non-preloadable rows.
     * @covers MediaWiki\Moderation\GiveAnonChangesToNewUserConsequence
     */
    public function testIgnoreNonPreloadableChanges()
    {
        [$idsToAffect, $idsToPreserve] = array_chunk(
            $this->makeSeveralDbRows(6),
            3,
        );

        $oldPreloadId = "some anonymous preload ID";
        $newPreloadId = "new non-anonymous preload ID";

        // Set mod_preload_id to a fixed known value (to check $idsToPreserve after the test)
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $dbw->update(
            "moderation",
            ["mod_preload_id" => $oldPreloadId],
            "*",
            __METHOD__,
        );

        // Make $idsToPreserve ineligible for modification due to having mod_preloadable=0.
        $dbw->update(
            "moderation",
            ["mod_preloadable=mod_id"], // mod_preloadable=mod_id means "NOT preloadable"
            ["mod_id" => $idsToPreserve],
            __METHOD__,
        );

        // Make $idsToAffect targetable for modification by GiveAnonChangesToNewUserConsequence
        $dbw->update(
            "moderation",
            ["mod_preloadable" => 0], // mod_preloadable=0 means "prelodable"
            ["mod_id" => $idsToAffect],
            __METHOD__,
        );

        // Create new user account.
        $user = self::getTestUser()->getUser();

        // Create and run the Consequence.
        $consequence = new GiveAnonChangesToNewUserConsequence(
            $user,
            $oldPreloadId,
            $newPreloadId,
        );
        $consequence->run();

        // Assert that $idsToAffect were modified.
        $this->assertSelect(
            "moderation",
            ["mod_user", "mod_user_text", "mod_preload_id"],
            ["mod_id" => $idsToAffect],
            array_fill(0, count($idsToAffect), [
                $user->getId(),
                $user->getName(),
                $newPreloadId,
            ]),
        );

        // Assert that $idsToPreserve were NOT changed.
        $this->assertSelect(
            "moderation",
            ["mod_user", "mod_user_text", "mod_preload_id"],
            ["mod_id" => $idsToPreserve],
            array_fill(0, count($idsToPreserve), [
                $this->authorUser->getId(),
                $this->authorUser->getName(),
                $oldPreloadId,
            ]),
        );
    }
}
