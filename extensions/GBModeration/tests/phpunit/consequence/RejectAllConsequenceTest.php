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
 * Unit test of RejectAllConsequence.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\ModerationCompatTools;
use MediaWiki\Moderation\RejectAllConsequence;
use User;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class RejectAllConsequenceTest extends ModerationUnitTestCase
{
    use ModifyDbRowTestTrait;

    /**
     * Verify that RejectAllConsequence marks database rows as rejected and returns their number.
     * @param array $ineligibleFieldValues Changes to DB fields that should make edit non-rejectable.
     * @dataProvider dataProviderRejectAll
     * @covers MediaWiki\Moderation\RejectAllConsequence
     */
    public function testRejectAll(array $ineligibleFieldValues)
    {
        $moderatorUser = self::getTestUser()->getUser();
        $this->authorUser = self::getTestUser()->getUser();

        // Let's reject half of the rows. This allows us to test that other rows are unmodified.
        [$idsToReject, $idsToPreserve] = array_chunk(
            $this->makeSeveralDbRows(6),
            3,
        );

        // Make $idsToPreserve ineligible for modification (e.g. due to having another mod_user_text).
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $dbw->update(
            "moderation",
            $ineligibleFieldValues,
            ["mod_id" => $idsToPreserve],
            __METHOD__,
        );

        // Create and run the Consequence.
        $consequence = new RejectAllConsequence(
            $this->authorUser->getName(),
            $moderatorUser,
        );
        $rejectedCount = $consequence->run();

        $this->assertSame(count($idsToReject), $rejectedCount);

        // Check the state of the database.
        $this->assertWereBatchRejected($idsToReject, $moderatorUser);

        // If the rows with $idsToPreserve were not already rejected before,
        // ensure that they weren't rejected by this RejectAllConsequence.
        $wasRejectedBefore =
            ($ineligibleFieldValues["mod_rejected"] ?? 0) === 1;
        if (!$wasRejectedBefore) {
            $this->assertNotRejected($idsToPreserve);
        }

        $this->assertNotRejectedBatch($idsToPreserve);
    }

    /**
     * Provide datasets for testRejectAll() runs.
     * @return array
     */
    public function dataProviderRejectAll()
    {
        return [
            "rejected all edits, but not with different mod_user_text" => [
                ["mod_user_text" => "Another username"],
            ],
            "rejected all edits, but not with mod_merged_revid <> 0" => [
                ["mod_merged_revid" => 12345],
            ],
            "rejected all edits, but not with mod_rejected=1" => [
                ["mod_rejected" => 1],
            ],
        ];
    }

    /**
     * Assert that the change was marked as rejected in the database.
     * @param int[] $ids
     * @param User $moderator
     */
    private function assertWereBatchRejected(array $ids, User $moderator)
    {
        foreach ($ids as $modid) {
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
                        1, // mod_rejected_batch
                        0, // mod_rejected_auto
                    ],
                ],
            );
        }
    }

    /**
     * Assert that these changes are not rejected.
     * @param int[] $ids
     */
    private function assertNotRejected(array $ids)
    {
        $this->assertSelect(
            "moderation",
            ["mod_rejected"],
            ["mod_id" => $ids],
            array_fill(0, count($ids), [0]),
        );
    }

    /**
     * Assert that these changes are not marked as mod_rejected_batch=1.
     * @param int[] $ids
     */
    private function assertNotRejectedBatch(array $ids)
    {
        $this->assertSelect(
            "moderation",
            ["mod_rejected_batch"],
            ["mod_id" => $ids],
            array_fill(0, count($ids), [0]),
        );
    }
}
