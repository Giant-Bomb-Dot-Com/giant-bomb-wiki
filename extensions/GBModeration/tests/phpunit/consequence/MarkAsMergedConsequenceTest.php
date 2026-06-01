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
 * Unit test of MarkAsMergedConsequence.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\MarkAsMergedConsequence;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class MarkAsMergedConsequenceTest extends ModerationUnitTestCase
{
    use ModifyDbRowTestTrait;

    /**
     * Verify that MarkAsMergedConsequence marks the database row as merged.
     * @covers MediaWiki\Moderation\MarkAsMergedConsequence
     */
    public function testMarkAsMerged()
    {
        $revid = 12345;
        $modid = $this->makeDbRow(["mod_conflict" => 1]);

        // Create and run the Consequence.
        $consequence = new MarkAsMergedConsequence($modid, $revid);
        $somethingChanged = $consequence->run();

        $this->assertTrue(
            $somethingChanged,
            "No changes after MarkAsMergedConsequence.",
        );
        $this->assertIsMerged($modid, $revid);
    }

    /**
     * Verify that MarkAsMergedConsequence does nothing when not applicable (already merged, etc.).
     * @covers MediaWiki\Moderation\MarkAsMergedConsequence
     * @param array $fields
     * @dataProvider dataProviderNoopMarkAsMerged
     */
    public function testNoopMarkAsMerged($fields)
    {
        $revid = 12345;
        $modid = $this->makeDbRow($fields);

        // Noop test: try applying MarkAsMergedConsequence to an already merged row again.
        $consequence = new MarkAsMergedConsequence($modid, $revid);
        $somethingChanged = $consequence->run();

        $this->assertFalse(
            $somethingChanged,
            "Unexpected changes after MarkAsMergedConsequence.",
        );
    }

    /**
     * Provide datasets for testNoopMarkAsMerged() runs.
     * @return array
     */
    public function dataProviderNoopMarkAsMerged()
    {
        return [
            "already merged" => [
                [
                    "mod_conflict" => 1,
                    "mod_merged_revid" => 56789,
                ],
            ],
            "not a conflict" => [["mod_conflict" => 0]],
        ];
    }

    /**
     * Throw an exception if row is not marked as merged with mod_merged_revid=$revid.
     * @param int $modid
     * @param int $revid
     */
    protected function assertIsMerged($modid, $revid)
    {
        $this->assertSelect(
            "moderation",
            ["mod_merged_revid", "mod_preloadable"],
            ["mod_id" => $modid],
            [
                [
                    $revid, // mod_merged_revid
                    $modid, // mod_preloadable: when it equals mod_id, it means "NOT preloadable"
                ],
            ],
        );
    }
}
