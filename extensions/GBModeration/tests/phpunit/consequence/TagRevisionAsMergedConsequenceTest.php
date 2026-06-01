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
 * Unit test of TagRevisionAsMergedConsequence.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\TagRevisionAsMergedConsequence;
use MediaWiki\Title\Title;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class TagRevisionAsMergedConsequenceTest extends ModerationUnitTestCase
{
    use MakeEditTestTrait;

    /**
     * Verify that TagRevisionAsMergedConsequence adds a tag to selected revision.
     * @covers MediaWiki\Moderation\TagRevisionAsMergedConsequence
     */
    public function testTagRevision()
    {
        $revid = $this->makeEdit(
            Title::newFromText("UTPage-" . rand(0, 100000)),
            self::getTestUser(["automoderated"])->getUser(),
        );

        $hookFired = false;

        $this->setTemporaryHook("ChangeTagsAfterUpdateTags", function (
            $tagsToAdd,
            $tagsToRemove,
            $prevTags,
            $rc_id,
            $hookRevId,
            $log_id,
            $params,
            $rc,
            $user,
        ) use (&$hookFired, $revid) {
            $hookFired = true;

            $this->assertSame($revid, $hookRevId);
            $this->assertSame(["moderation-merged"], $tagsToAdd);
            $this->assertSame([], $tagsToRemove);

            return true;
        });

        // Create and run the Consequence.
        $consequence = new TagRevisionAsMergedConsequence($revid);
        $consequence->run();

        $this->assertTrue(
            $hookFired,
            "TagRevisionAsMergedConsequence didn't tag anything.",
        );
    }
}
