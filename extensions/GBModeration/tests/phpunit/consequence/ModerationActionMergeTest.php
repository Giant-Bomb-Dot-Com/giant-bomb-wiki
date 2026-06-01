<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2024 Edward Chernenko.

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
 * Unit test of ModerationActionMerge.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\EditPage\EditPage;
use MediaWiki\Moderation\ModerationActionMerge;
use MediaWiki\Moderation\ModerationError;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use OutputPage;
use User;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class ModerationActionMergeTest extends ModerationUnitTestCase
{
    use ActionTestTrait;

    /**
     * Verify that execute() returns expected result.
     * @param array $opt
     * @dataProvider dataProviderExecute
     * @covers MediaWiki\Moderation\ModerationActionMerge
     */
    public function testExecute(array $opt)
    {
        $expectedError = $opt["expectedError"] ?? null;
        $isModeratorAutomoderated = $opt["isModeratorAutomoderated"] ?? true;
        $isConflict = $opt["isConflict"] ?? true;
        $isAlreadyMerged = $opt["isAlreadyMerged"] ?? false;

        $user = self::getTestUser()->getUser();
        $moderator = User::newFromName("127.0.0.30", false);

        $row = (object) [
            "namespace" => rand(0, 1),
            "title" => "UTPage_" . rand(0, 100000),
            "user_text" => $user->getName(),
            "text" => "Some text " . rand(0, 100000),
            "conflict" => $isConflict ? 1 : 0,
            "merged_revid" => $isAlreadyMerged ? 56789 : 0,
        ];

        // $result['summary'] should have a message in ContentLanguage
        $this->overrideConfigValue("LanguageCode", "qqx");

        $action = $this->makeActionForTesting(
            ModerationActionMerge::class,
            function (
                $context,
                $entryFactory,
                $manager,
                $canSkip,
                $editFormOptions,
                $actionLinkRenderer,
                $repoGroup,
                $contentLanguage,
                $revisionRenderer,
            ) use ($row, $moderator, $isModeratorAutomoderated) {
                $context->setRequest(new FauxRequest(["modid" => 12345]));
                $context->setUser($moderator);

                $entryFactory
                    ->expects($this->once())
                    ->method("loadRowOrThrow")
                    ->with(
                        $this->identicalTo(12345),
                        $this->identicalTo([
                            "mod_namespace AS namespace",
                            "mod_title AS title",
                            "mod_user_text AS user_text",
                            "mod_text AS text",
                            "mod_conflict AS conflict",
                            "mod_merged_revid AS merged_revid",
                        ]),
                    )
                    ->willReturn($row);

                $canSkip
                    ->expects($this->any())
                    ->method("canEditSkip")
                    ->with(
                        $this->identicalTo($moderator),
                        $this->identicalTo($row->namespace),
                    )
                    ->willReturn($isModeratorAutomoderated);

                // This is a readonly action. Ensure that it has no consequences.
                $manager->expects($this->never())->method("add");
            },
        );

        if ($expectedError) {
            $this->expectExceptionObject(new ModerationError($expectedError));
        }
        $result = $action->execute();

        $expectedResult = [
            "id" => 12345,
            "namespace" => $row->namespace,
            "title" => $row->title,
            "text" => $row->text,
            "summary" => "(moderation-merge-comment: " . $row->user_text . ")",
        ];
        $this->assertSame(
            $expectedResult,
            $result,
            "Result of execute() doesn't match expected.",
        );
    }

    /**
     * Provide datasets for testExecute() runs.
     * @return array
     */
    public function dataProviderExecute()
    {
        return [
            "successful merge" => [[]],
            "error: merge not needed" => [
                [
                    "expectedError" => "moderation-merge-not-needed",
                    "isConflict" => false,
                ],
            ],
            'error: moderator is not automoderated (can\'t merge)' => [
                [
                    "expectedError" => "moderation-merge-not-automoderated",
                    "isModeratorAutomoderated" => false,
                ],
            ],
            "error: already merged" => [
                [
                    "expectedError" => "moderation-already-merged",
                    "isAlreadyMerged" => true,
                ],
            ],
        ];
    }

    /**
     * Verify that outputResult() correctly shows the EditPage form in "edit conflict" mode.
     * @covers MediaWiki\Moderation\ModerationActionMerge
     */
    public function testOutputResult()
    {
        $modid = 12345;
        $executeResult = [
            "id" => $modid,
            "namespace" => rand(0, 1),
            "title" => "UTPage_" . rand(0, 100000),
            "text" => "{MockedText}",
            "summary" => "{MockedComment}",
        ];
        $title = Title::makeTitle(
            $executeResult["namespace"],
            $executeResult["title"],
        );

        // Expected behavior of outputResult() is to display the form of EditPage,
        // so let's install the hook that can monitor this situation.
        $hookFired = false;
        $hookName = "EditPage::showEditForm:initial";
        $this->setTemporaryHook($hookName, function (
            EditPage $editPage,
            OutputPage $out,
        ) use (&$hookFired, $title) {
            $hookFired = true;

            $this->assertTrue($editPage->isConflict, "EditPage.isConflict");
            $this->assertSame(
                $title->getFullText(),
                $editPage->getContextTitle()->getFullText(),
                "EditPage.ContextTitle",
            );
            $this->assertSame(
                "{MockedText}",
                $editPage->textbox1,
                "EditPage.textbox1",
            );
            $this->assertSame(
                "{MockedComment}",
                $editPage->summary,
                "EditPage.summary",
            );
        });

        $action = $this->makeActionForTesting(
            ModerationActionMerge::class,
            function (
                $context,
                $entryFactory,
                $manager,
                $canSkip,
                $editFormOptions,
                $actionLinkRenderer,
                $repoGroup,
                $contentLanguage,
                $revisionRenderer,
            ) use ($title, $modid) {
                $context->setRequest(new FauxRequest(["modid" => $modid]));
                $context->setTitle($title);

                $editFormOptions
                    ->expects($this->once())
                    ->method("setMergeID")
                    ->with($this->identicalTo($modid));

                // This is a readonly action. Ensure that it has no consequences.
                $manager->expects($this->never())->method("add");
            },
        );

        $action->outputResult($executeResult, $action->getOutput());
        $this->assertTrue(
            $hookFired,
            "outputResult() didn't cause $hookName hook to be fired.",
        );
    }
}
