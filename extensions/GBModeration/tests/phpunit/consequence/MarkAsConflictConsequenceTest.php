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
 * Unit test of MarkAsConflictConsequence.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\MarkAsConflictConsequence;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class MarkAsConflictConsequenceTest extends ModerationUnitTestCase
{
    use ModifyDbRowTestTrait;

    /**
     * Verify that MarkAsConflictConsequence marks the database row as conflict.
     * @covers MediaWiki\Moderation\MarkAsConflictConsequence
     */
    public function testMarkAsConflict()
    {
        $modid = $this->makeDbRow();

        // Create and run the Consequence.
        $consequence = new MarkAsConflictConsequence($modid);
        $consequence->run();

        // Check the state of the database.
        $this->assertSelect(
            "moderation",
            ["mod_conflict"],
            ["mod_id" => $modid],
            [[1]],
        );
    }
}
