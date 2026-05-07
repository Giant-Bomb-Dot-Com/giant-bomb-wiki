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
 * Unit test of ModifyPendingChangeConsequence.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\ModifyPendingChangeConsequence;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class ModifyPendingChangeConsequenceTest extends ModerationUnitTestCase
{
    use ModifyDbRowTestTrait;

    /**
     * Verify that ModifyPendingChangeConsequence changes mod_text, mod_comment and mod_new_len.
     * @covers MediaWiki\Moderation\ModifyPendingChangeConsequence
     */
    public function testModify()
    {
        $modid = $this->makeDbRow();
        $newText = "Modified text";
        $newComment = "Another edit comment";
        $newLen = strlen($newText);

        // Create and run the Consequence.
        $consequence = new ModifyPendingChangeConsequence(
            $modid,
            $newText,
            $newComment,
            $newLen,
        );
        $consequence->run();

        // Check the state of the database.
        $this->assertSelect(
            "moderation",
            ["mod_text", "mod_comment", "mod_new_len"],
            ["mod_id" => $modid],
            [[$newText, $newComment, $newLen]],
        );
    }

    /**
     * Verify that ModifyPendingChangeConsequence truncates long mod_comment to fit the database field.
     * @covers MediaWiki\Moderation\ModifyPendingChangeConsequence
     */
    public function testTruncateLongSummary()
    {
        $modid = $this->makeDbRow();
        $newText = "Modified text";
        $newLen = strlen($newText);

        $submittedComment = str_repeat("length 16 string", 100);
        $expectedComment = mb_strcut($submittedComment, 0, 250);

        // Create and run the Consequence.
        $consequence = new ModifyPendingChangeConsequence(
            $modid,
            $newText,
            $submittedComment,
            $newLen,
        );
        $consequence->run();

        // Check the state of the database.
        $this->assertSelect(
            "moderation",
            ["mod_text", "mod_comment", "mod_new_len"],
            ["mod_id" => $modid],
            [[$newText, $expectedComment, $newLen]],
        );
    }
}
