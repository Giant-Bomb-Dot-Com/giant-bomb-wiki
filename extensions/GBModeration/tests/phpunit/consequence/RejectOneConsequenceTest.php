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
 * Unit test of RejectOneConsequence.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\ModerationCompatTools;
use MediaWiki\Moderation\RejectOneConsequence;
use User;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class RejectOneConsequenceTest extends ModerationUnitTestCase
{
    use ModifyDbRowTestTrait;

    /**
     * Verify that RejectOneConsequence marks the database row as rejected and returns 1.
     * @covers MediaWiki\Moderation\RejectOneConsequence
     */
    public function testRejectOne()
    {
        $moderator = User::createNew("Some moderator");
        $modid = $this->makeDbRow();

        // Create and run the Consequence.
        $consequence = new RejectOneConsequence($modid, $moderator);
        $rejectedCount = $consequence->run();

        $this->assertSame(1, $rejectedCount);

        // Check the state of the database.
        $this->assertWasRejected($modid, $moderator);
    }

    /**
     * Verify that RejectOneConsequence returns 0 for an already rejected edit.
     * @covers MediaWiki\Moderation\RejectOneConsequence
     */
    public function testNoopRejectOne()
    {
        $moderator = User::createNew("Some moderator");
        $modid = $this->makeDbRow();

        // Create and run the Consequence.
        $consequence1 = new RejectOneConsequence($modid, $moderator);
        $consequence1->run();

        $consequence2 = new RejectOneConsequence($modid, $moderator);
        $rejectedCount = $consequence2->run();
        $this->assertSame(0, $rejectedCount);

        // Despite $consequence2 doing nothing, the row should still be marked as rejected.
        $this->assertWasRejected($modid, $moderator);
    }

    /**
     * Verify that RejectOneConsequence does nothing if DB row is marked as merged or rejected.
     * @param array $fields Passed to $dbw->update( 'moderation', ... ) before the test.
     * @covers MediaWiki\Moderation\RejectOneConsequence
     * @dataProvider dataProviderNotApplicableRejectOne
     */
    public function testNotApplicableRejectOne(array $fields)
    {
        $moderator = User::createNew("Some moderator");
        $modid = $this->makeDbRow();

        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $dbw->update("moderation", $fields, ["mod_id" => $modid], __METHOD__);

        // Create and run the Consequence.
        $consequence = new RejectOneConsequence($modid, $moderator);
        $rejectedCount = $consequence->run();
        $this->assertSame(0, $rejectedCount);
    }

    /**
     * Provide datasets for testNotApplicableRejectOne() runs.
     * @return array
     */
    public function dataProviderNotApplicableRejectOne()
    {
        return [
            "already rejected" => [["mod_rejected" => 1]],
            "already merged" => [["mod_merged_revid" => 1234]],
        ];
    }

    /**
     * Assert that the change was marked as rejected in the database.
     * @param int $modid
     * @param User $moderator
     */
    private function assertWasRejected($modid, User $moderator)
    {
        $this->assertSelect(
            "moderation",
            [
                "mod_rejected",
                "mod_rejected_by_user",
                "mod_rejected_by_user_text",
                "mod_preloadable",
                "mod_rejected_batch",
                "mod_rejected_auto",
            ],
            ["mod_id" => $modid],
            [
                [
                    1,
                    $moderator->getId(),
                    $moderator->getName(),
                    $modid, // mod_preloadable
                    0, // mod_rejected_batch
                    0, // mod_rejected_auto
                ],
            ],
        );
    }
}
