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
 * Unit test of ApproveEditConsequence.
 */

namespace MediaWiki\Moderation\Tests;

use IDBAccessObject;
use MediaWiki\MediaWikiServices;
use MediaWiki\Moderation\ApproveEditConsequence;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use RecentChange;
use User;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class ApproveEditConsequenceTest extends ModerationUnitTestCase
{
    use MakeEditTestTrait;

    /**
     * Verify that ApproveEditConsequence makes a new edit.
     * @covers MediaWiki\Moderation\ApproveEditConsequence
     * @dataProvider dataProviderApproveEdit
     * @param array $params
     */
    public function testApproveEdit(array $params)
    {
        $opt = (object) $params;

        $opt->existing ??= false;
        $opt->bot ??= false;
        $opt->minor ??= false;
        $opt->summary ??= "Some summary " . rand(0, 100000);
        $opt->anonymously ??= false;

        if ($opt->anonymously) {
            $this->disableAutoCreateTempUser();
        }

        $user = $opt->anonymously
            ? User::newFromName("127.0.0.1", false)
            : self::getTestUser($opt->bot ? ["bot"] : [])->getUser();
        $title = Title::newFromText($opt->title ?? "UTPage-" . rand(0, 100000));
        $newText = "New text " . rand(0, 100000);

        // Edits shouldn't be intercepted (including edit caused by approval).
        $this->setMwGlobals("wgModerationEnable", false);

        if ($opt->existing) {
            // Precreate the page.
            $this->makeEdit(
                $title,
                self::getTestUser(["moderator", "automoderated"])->getUser(),
                "Before $newText",
            );
        }

        $baseRevId = $opt->existing
            ? $title->getLatestRevID(IDBAccessObject::READ_LATEST)
            : 0;

        // Monitor RecentChange_save hook.
        $hookFired = false;
        $this->setTemporaryHook(
            "RecentChange_save",
            function (RecentChange $rc) use (
                &$hookFired,
                $user,
                $title,
                $newText,
                $baseRevId,
                $opt,
            ) {
                $hookFired = true;

                $performer = $rc->getPerformerIdentity();

                $this->assertTrue(
                    $rc->getPage()->isSamePageAs($title),
                    "Wrong title",
                );
                $this->assertSame($user->getName(), $performer->getName());
                $this->assertSame($user->getId(), $performer->getId());
                $this->assertSame(
                    $opt->minor ? 1 : 0,
                    $rc->getAttribute("rc_minor"),
                );
                $this->assertSame(
                    $opt->bot ? 1 : 0,
                    $rc->getAttribute("rc_bot"),
                );
                $this->assertSame(
                    $opt->summary,
                    $rc->getAttribute("rc_comment"),
                );

                $revid = $rc->getAttribute("rc_this_oldid");
                $this->assertNotSame(0, $revid);

                $rec = MediaWikiServices::getInstance()
                    ->getRevisionLookup()
                    ->getRevisionById($revid);
                $this->assertSame($baseRevId, $rec->getParentId());
                $this->assertSame(
                    $newText,
                    $rec->getSlot(SlotRecord::MAIN)->getContent()->serialize(),
                );

                return true;
            },
            false,
        );

        // Create and run the Consequence.
        $consequence = new ApproveEditConsequence(
            $user,
            $title,
            $newText,
            $opt->summary,
            $opt->bot,
            $opt->minor,
            $baseRevId,
        );
        $status = $consequence->run();

        $this->assertTrue(
            $status->isOK(),
            "ApproveEditConsequence failed: " . $status->getMessage()->plain(),
        );
        $this->assertTrue(
            $hookFired,
            "ApproveEditConsequence: didn't edit anything.",
        );
    }

    /**
     * Provide datasets for testApproveEdit() runs.
     * @return array
     */
    public function dataProviderApproveEdit()
    {
        return [
            "logged-in edit" => [[]],
            "anonymous edit" => [["anonymously" => true]],
            "edit in Project namespace" => [
                [
                    "title" => "Project:Title in another namespace",
                ],
            ],
            "edit in existing page" => [["existing" => true]],
            "edit with edit summary" => [["summary" => "Summary 1"]],
            "bot edit" => [["bot" => true]],
            "minor edit" => [["minor" => true, "existing" => true]],
        ];
    }

    /**
     * Verify that ApproveEditConsequence can automatically resolve a resolvable edit conflict.
     * @covers MediaWiki\Moderation\ApproveEditConsequence
     * See also: ModerationEditConflictTest::testResolvableEditConflict()
     */
    public function testResolvableEditConflict()
    {
        $this->disableAutoCreateTempUser();

        $title = Title::newFromText("UTPage-" . rand(0, 100000));
        $user = User::newFromName("127.0.0.1", false);

        // Edits shouldn't be intercepted (including edit caused by approval).
        $this->setMwGlobals("wgModerationEnable", false);

        [$revid1, $revid2] = $this->makeTwoEdits(
            $title,
            "Original paragraph about dogs\n\nOriginal paragraph about cats",
            "Original paragraph about dogs\n\nModified paragraph about cats",
        );

        $textToApprove =
            "Modified paragraph about dogs\n\nOriginal paragraph about cats";
        $expectedText =
            "Modified paragraph about dogs\n\nModified paragraph about cats";

        // Create and run the Consequence.
        $consequence = new ApproveEditConsequence(
            $user,
            $title,
            $textToApprove,
            "",
            false,
            false,
            $revid1,
        );
        $status = $consequence->run();

        $this->assertTrue(
            $status->isOK(),
            "ApproveEditConsequence failed: " . $status->getMessage()->plain(),
        );

        // @phan-suppress-next-line PhanTypeArraySuspiciousNullable
        $rev = $status->value["revision-record"] ?? $status->value["revision"];
        $revid = $rev->getId();
        $rec = MediaWikiServices::getInstance()
            ->getRevisionLookup()
            ->getRevisionById($revid);

        $this->assertSame($revid2, $rec->getParentId());
        $this->assertSame(
            $expectedText,
            $rec->getSlot(SlotRecord::MAIN)->getContent()->serialize(),
        );
    }

    /**
     * Verify that ApproveEditConsequence detects a non-resolvable edit conflict.
     * @covers MediaWiki\Moderation\ApproveEditConsequence
     * See also: ModerationMergeTest::testMerge()
     */
    public function testUnresolvableEditConflict()
    {
        $this->disableAutoCreateTempUser();

        $title = Title::newFromText("UTPage-" . rand(0, 100000));
        $user = User::newFromName("127.0.0.1", false);

        // Edits shouldn't be intercepted (including edit caused by approval).
        $this->setMwGlobals("wgModerationEnable", false);

        [$revid1, $revid2] = $this->makeTwoEdits(
            $title,
            "Normal line 1\nNormal line 2\nNormal line 3\n",
            "Normal line 1\nLine 2 was modified\nNormal line 3\n",
        );

        $textToApprove =
            "Normal line 1, but second line was deleted\nNormal line 3\n";

        // Create and run the Consequence.
        $consequence = new ApproveEditConsequence(
            $user,
            $title,
            $textToApprove,
            "",
            false,
            false,
            $revid1,
        );
        $status = $consequence->run();

        $this->assertFalse(
            $status->isOK(),
            "ApproveEditConsequence returned incorrect Success for unresolvable edit conflict.",
        );

        $this->assertSame(
            "moderation-edit-conflict",
            $status->getMessage()->getKey(),
            "ApproveEditConsequence didn't return \"moderation-edit-conflict\" error.",
        );

        $this->assertSame(
            $revid2,
            $title->getLatestRevID(IDBAccessObject::READ_LATEST),
            "Page was modified after ApproveEditConsequence on unresolvable edit conflict.",
        );
    }

    /**
     * Make two edits in the same page with two different users.
     * @param Title $title
     * @param string $text1
     * @param string $text2
     * @return int[] Array of rev_id of both edits
     */
    public function makeTwoEdits(Title $title, $text1, $text2)
    {
        $revIds = [];
        $revIds[] = $this->makeEdit(
            $title,
            User::newFromName("127.0.0.2", false),
            $text1,
        );
        $revIds[] = $this->makeEdit(
            $title,
            User::newFromName("127.0.0.3", false),
            $text2,
        );
        return $revIds;
    }
}
