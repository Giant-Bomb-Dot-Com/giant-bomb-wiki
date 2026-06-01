<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2018-2025 Edward Chernenko.

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
 * Verifies that modactions work via api.php?action=moderation.
 */

namespace MediaWiki\Moderation\Tests;

require_once __DIR__ . "/../framework/ModerationTestsuite.php";

/**
 * @group Database
 * @covers MediaWiki\Moderation\ApiModeration
 */
class ModerationApiTest extends ModerationTestCase
{
    /**
     * Checks return value of api.php?action=moderation&modaction=...
     * @note Consequences of actions are checked by other tests (e.g. ModerationApproveTest).
     * @dataProvider dataProviderModerationApi
     */
    public function testModerationApi(
        $action,
        array $expectedResult,
        ModerationTestsuite $t,
    ) {
        /* Prepare a fake moderation entry */
        $entry = $t->getSampleEntry();

        $this->recursiveReplace($expectedResult, [
            "{{ID}}" => (int) $entry->id,
            "{{AUTHOR}}" => $entry->user,
            "{{TITLE}}" => $entry->title,
        ]);

        $ret = $t->query([
            "action" => "moderation",
            "modid" => $entry->id,
            "modaction" => $action,
            "token" => null,
        ]);

        if ($action == "show" && isset($ret["moderation"]["diff-html"])) {
            /* Correctness of diff is already checked in ModerationShowTest,
				we don't want to duplicate the same check.
			*/
            $ret["moderation"]["diff-html"] = "{{DIFF}}";
        }

        $this->assertSame(["moderation" => $expectedResult], $ret);
    }

    /**
     * Provide datasets for testModerationApi() runs.
     * @return array
     */
    public function dataProviderModerationApi()
    {
        return [
            [
                "approve",
                [
                    "approved" => ["{{ID}}"],
                ],
            ],
            [
                "approveall",
                [
                    "approved" => ["{{ID}}" => ""],
                    "failed" => [],
                ],
            ],
            [
                "reject",
                [
                    "rejected-count" => 1,
                ],
            ],
            [
                "rejectall",
                [
                    "rejected-count" => 1,
                ],
            ],
            [
                "block",
                [
                    "action" => "block",
                    "username" => "{{AUTHOR}}",
                ],
            ],
            [
                "unblock",
                [
                    "action" => "unblock",
                    "username" => "{{AUTHOR}}",
                    "noop" => "",
                ],
            ],
            [
                "show",
                [
                    "diff-html" => "{{DIFF}}",
                    "title" => "{{TITLE}}",
                ],
            ],
        ];
    }

    /**
     * Recursively search $data for any strings and apply replacements to them.
     * @param array|string &$data
     * @param array $replacements E.g. [ 'A' => 'B', 'textToReplace' => 'newText' ].
     */
    protected function recursiveReplace(&$data, array $replacements): void
    {
        if (!is_array($data)) {
            // Value found: replace it if needed.
            if (array_key_exists($data, $replacements)) {
                $data = $replacements[$data];
            }
            return;
        }

        // Search the array and apply replacements to both keys and values.
        $newArray = [];
        foreach ($data as $key => $val) {
            $this->recursiveReplace($key, $replacements);
            $this->recursiveReplace($val, $replacements);

            $newArray[$key] = $val;
        }
        $data = $newArray;
    }
}
