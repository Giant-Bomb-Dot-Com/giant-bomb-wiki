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
 * Unit test of InsertRowIntoModerationTableConsequence.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\InsertRowIntoModerationTableConsequence;
use MediaWiki\Moderation\ModerationCompatTools;
use MediaWiki\Moderation\RollbackResistantQuery;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class InsertRowIntoModerationTableConsequenceTest extends ModerationUnitTestCase
{
    /**
     * Verify that InsertRowIntoModerationTableConsequence can add a new row to the moderation table.
     * @covers MediaWiki\Moderation\InsertRowIntoModerationTableConsequence
     */
    public function testInsert()
    {
        $this->mockRollbackResistantQueryService(1);
        $fields = $this->getSampleFields();

        // Create and run the Consequence.
        $consequence = new InsertRowIntoModerationTableConsequence($fields);
        $modid = $consequence->run();

        $this->assertNotNull($modid);
        $this->assertRowExistsAndCorrect($modid, $fields);
    }

    /**
     * Verify that InsertRowIntoModerationTableConsequence can update an existing database row.
     * @covers MediaWiki\Moderation\InsertRowIntoModerationTableConsequence
     */
    public function testUpdateExistingRow()
    {
        $this->mockRollbackResistantQueryService(2);

        // First, create an existing row.
        $fields = $this->getSampleFields();
        $consequence = new InsertRowIntoModerationTableConsequence($fields);
        $oldRowId = $consequence->run();

        // Now try to insert another row with the same UNIQUE fields.
        // This should result in existing row being updated.
        $fields["mod_text"] .= "-new";
        $fields["mod_new_len"] += 4;
        $fields["mod_comment"] .= " (updated)";

        // Create and run the Consequence.
        $consequence = new InsertRowIntoModerationTableConsequence($fields);
        $modid = $consequence->run();

        $this->assertSame($oldRowId, $modid);
        $this->assertRowExistsAndCorrect($modid, $fields);
    }

    /**
     * Verify that InsertRowIntoModerationTableConsequence won't update unrelated rows.
     * @covers MediaWiki\Moderation\InsertRowIntoModerationTableConsequence
     */
    public function testNoChangesToUnrelatedRows()
    {
        $this->mockRollbackResistantQueryService(6);

        // First, create an existing row.
        $fields = $this->getSampleFields();
        $consequence = new InsertRowIntoModerationTableConsequence($fields);
        $oldRowId = $consequence->run();

        // Now try to insert another row with different UNIQUE fields.
        // This should result in new row being inserted (existing rows should be untouched).
        $uniqueFieldChanges = [
            ["mod_preloadable" => 12345],
            ["mod_type" => "move"],
            ["mod_namespace" => $fields["mod_namespace"] + 1],
            ["mod_title" => $fields["mod_title"] . " (new)"],
            [
                "mod_preload_id" => $fields["mod_preload_id"] . "MODIFIED",
            ],
        ];
        foreach ($uniqueFieldChanges as $changes) {
            $newFields = array_merge($this->getSampleFields(), $changes);

            // Create and run the Consequence.
            $consequence = new InsertRowIntoModerationTableConsequence(
                $newFields,
            );
            $modid = $consequence->run();

            $this->assertNotEquals($oldRowId, $modid); // Must create new row
            $this->assertRowExistsAndCorrect($modid, $newFields);

            // Ensure that existing row wasn't unchanged.
            $this->assertRowExistsAndCorrect($oldRowId, $fields);
        }

        // Check how many rows were inserted.
        $expectedNumberOfRows = count($uniqueFieldChanges) + 1;
        $this->assertSelect("moderation", ["COUNT(*)"], "", [
            [$expectedNumberOfRows],
        ]);
    }

    /**
     * Throw an exception if row with selected $modid doesn't exist or has incorrect fields.
     * @param int $modid
     * @param array $expectedFields
     */
    protected function assertRowExistsAndCorrect($modid, array $expectedFields)
    {
        $expectedKeys = array_keys($expectedFields);
        $expectedValues = array_values($expectedFields);

        array_unshift($expectedKeys, "mod_id");
        array_unshift($expectedValues, $modid);

        // Cast all numeric values to strings: select() returns everything as strings,
        // so comparison between 123 and '123' shouldn't result in failure of the test.
        $expectedValues = array_map(static function ($val) {
            return (string) $val;
        }, $expectedValues);

        $this->assertSelect(
            "moderation",
            $expectedKeys,
            ["mod_id" => $modid],
            [$expectedValues],
        );
    }

    /**
     * Arbitrary value of $fields for testing InsertRowIntoModerationTableConsequence.
     * @return array
     */
    protected function getSampleFields()
    {
        $dbr = ModerationCompatTools::getDB(DB_REPLICA); // Only for $dbr->timestamp();

        return [
            "mod_timestamp" => $dbr->timestamp(),
            "mod_user" => 12345,
            "mod_user_text" => "Some user with ID #12345",
            "mod_cur_id" => 0,
            "mod_namespace" => rand(0, 1),
            "mod_title" => "Test page " . rand(0, 100000),
            "mod_comment" => "Some reason " . rand(0, 100000),
            "mod_minor" => 0,
            "mod_bot" => 0,
            "mod_new" => 1,
            "mod_last_oldid" => 0,
            "mod_ip" => "127.1.2.3",
            "mod_old_len" => 0,
            "mod_new_len" => 8, // Length of mod_text, see below
            "mod_header_xff" => null,
            "mod_header_ua" => "SampleUserAgent/1.0",
            "mod_preload_id" => "]fake",
            "mod_rejected" => 0,
            "mod_rejected_by_user" => 0,
            "mod_rejected_by_user_text" => null,
            "mod_rejected_batch" => 0,
            "mod_rejected_auto" => 0,
            "mod_preloadable" => 0,
            "mod_conflict" => 0,
            "mod_merged_revid" => 0,
            "mod_stash_key" => "",
            "mod_text" => "New text",
            "mod_tags" => null,
            "mod_type" => "edit",
            "mod_page2_namespace" => 0,
            "mod_page2_title" => "Test page 2",
        ];
    }

    /**
     * Replace RollbackResistantQuery service with a mock that expects $numberOfCalls to perform().
     * @param int $numberOfCalls
     */
    private function mockRollbackResistantQueryService($numberOfCalls)
    {
        $rrQuery = $this->createMock(RollbackResistantQuery::class);
        $rrQuery
            ->expects($this->exactly($numberOfCalls))
            ->method("perform")
            ->will(
                $this->returnCallback(static function (callable $cb) {
                    // Run it immediately, nothing else.
                    $cb();
                }),
            );
        $this->setService("Moderation.RollbackResistantQuery", $rrQuery);
    }
}
