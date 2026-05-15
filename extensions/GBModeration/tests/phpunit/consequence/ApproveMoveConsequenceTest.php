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
 * Unit test of ApproveMoveConsequence.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\ApproveMoveConsequence;
use MediaWiki\Title\Title;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class ApproveMoveConsequenceTest extends ModerationUnitTestCase
{
    use MakeEditTestTrait;

    /**
     * Verify that ApproveMoveConsequence renames the page.
     * @covers MediaWiki\Moderation\ApproveMoveConsequence
     */
    public function testApproveMove()
    {
        $moderator = self::getTestUser([
            "moderator",
            "automoderated",
        ])->getUser();
        $user = self::getTestUser()->getUser();
        $title = Title::newFromText("UTPage-" . rand(0, 100000));
        $newTitle = Title::newFromText("UTPage-" . rand(0, 100000) . "-new");
        $reason = "Some reasons why the page was renamed";

        // Precreate the page that will be renamed.
        $this->makeEdit($title, $moderator);

        // Monitor PageMoveComplete hook
        $hookFired = false;
        $this->setTemporaryHook("PageMoveComplete", function (
            $hookTitle,
            $hookNewTitle,
            $hookUser,
            $pageid,
            $redirid,
            $hookReason,
        ) use ($user, $title, $reason, &$hookFired) {
            $hookFired = true;

            $this->assertSame($title->getFullText(), $hookTitle->getFullText());
            $this->assertSame($user->getName(), $hookUser->getName());
            $this->assertSame($user->getId(), $hookUser->getId());
            $this->assertSame($reason, $hookReason);

            return true;
        });

        // Moves caused by approval shouldn't be intercepted.
        $this->setMwGlobals("wgModerationEnable", false);

        // Create and run the Consequence.
        $consequence = new ApproveMoveConsequence(
            $moderator,
            $title,
            $newTitle,
            $user,
            $reason,
        );
        $status = $consequence->run();

        $this->assertTrue(
            $status->isOK(),
            "ApproveMoveConsequence failed: " . $status->getMessage()->plain(),
        );
        $this->assertTrue(
            $hookFired,
            "ApproveMoveConsequence: didn't move anything.",
        );
    }

    /**
     * Verify that ApproveMoveConsequence fails if the move is invalid (e.g. renaming A to A).
     * @covers MediaWiki\Moderation\ApproveMoveConsequence
     */
    public function testApproveInvalidMove()
    {
        $moderator = self::getTestUser([
            "moderator",
            "automoderated",
        ])->getUser();
        $user = self::getTestUser()->getUser();
        $title = Title::newFromText("UTPage-" . rand(0, 100000));
        $reason = "Some reasons why the page was renamed";

        // Precreate the page that will be renamed.
        $this->makeEdit($title, $moderator);

        // Monitor PageMoveComplete hook
        $hookFired = false;
        $this->setTemporaryHook("PageMoveComplete", static function () use (
            &$hookFired,
        ) {
            $hookFired = true;
        });

        // Cause "selfmove" error (trying to rename page into the name it already has).
        $newTitle = $title;

        // Create and run the Consequence.
        $consequence = new ApproveMoveConsequence(
            $moderator,
            $title,
            $newTitle,
            $user,
            $reason,
        );
        $status = $consequence->run();

        $this->assertFalse(
            $status->isOK(),
            "ApproveMoveConsequence succeeded for invalid move.",
        );
        $this->assertTrue(
            $status->hasMessage("selfmove"),
            "ApproveMoveConsequence didn't return expected 'selfmove' Status.",
        );
        $this->assertFalse(
            $hookFired,
            "PageMoveComplete hook was fired for invalid move.",
        );
    }

    /**
     * Verify that ApproveMoveConsequence fails if moderator doesn't have "move" permission.
     * @covers MediaWiki\Moderation\ApproveMoveConsequence
     */
    public function testModeratorNotAllowedToMove()
    {
        $moderator = self::getTestUser([
            "moderator",
            "automoderated",
        ])->getUser();
        $user = self::getTestUser()->getUser();
        $title = Title::newFromText("UTPage-" . rand(0, 100000));
        $newTitle = Title::newFromText("UTPage-" . rand(0, 100000) . "-new");
        $reason = "Some reasons why the page was renamed";

        // Simulate situation when the moderator is not allowed to rename pages.
        $this->setMwGlobals("wgRevokePermissions", [
            "moderator" => ["move" => true],
        ]);

        // Precreate the page that will be renamed.
        $this->makeEdit($title, $moderator);

        // Monitor PageMoveComplete hook
        $hookFired = false;
        $this->setTemporaryHook("PageMoveComplete", static function () use (
            &$hookFired,
        ) {
            $hookFired = true;
        });

        // Create and run the Consequence.
        $consequence = new ApproveMoveConsequence(
            $moderator,
            $title,
            $newTitle,
            $user,
            $reason,
        );
        $status = $consequence->run();

        $this->assertFalse(
            $status->isOK(),
            "ApproveMoveConsequence succeeded for a moderator who is not allowed to move.",
        );
        $this->assertSame(
            "movenotallowed",
            $status->getMessage()->getKey(),
            "ApproveMoveConsequence didn't return expected Status.",
        );
        $this->assertFalse(
            $hookFired,
            "PageMoveComplete hook was fired for non-allowed move.",
        );
    }
}
