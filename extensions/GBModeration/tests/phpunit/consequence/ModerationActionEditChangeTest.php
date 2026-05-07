<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020 Edward Chernenko.

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
 * Unit test of ModerationActionEditChange.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\EditPage\EditPage;
use MediaWiki\Moderation\ModerationActionEditChange;
use MediaWiki\Moderation\ModerationEditChangePage;
use MediaWiki\Moderation\ModerationError;
use MediaWiki\Moderation\ModerationNewChange;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use OutputPage;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class ModerationActionEditChangeTest extends ModerationUnitTestCase
{
    use ActionTestTrait;

    /**
     * Verify that execute() returns expected result.
     * @param array $opt
     * @dataProvider dataProviderExecute
     * @covers MediaWiki\Moderation\ModerationActionEditChange
     */
    public function testExecute(array $opt)
    {
        $expectedError = $opt["expectedError"] ?? null;
        $enabled = $opt["enabled"] ?? true;
        $type = $opt["mod_type"] ?? ModerationNewChange::MOD_TYPE_EDIT;

        $this->setMwGlobals("wgModerationEnableEditChange", $enabled);

        $row = (object) [
            "namespace" => rand(0, 1),
            "title" => "UTPage_" . rand(0, 100000),
            "text" => "Some text " . rand(0, 100000),
            "comment" => "Edit summary " . rand(0, 100000),
            "type" => $type,
        ];

        $action = $this->makeActionForTesting(
            ModerationActionEditChange::class,
            function ($context, $entryFactory, $manager) use ($row, $enabled) {
                $context->setRequest(new FauxRequest(["modid" => 12345]));

                $entryFactory
                    ->expects($enabled ? $this->once() : $this->never())
                    ->method("loadRowOrThrow")
                    ->with(
                        $this->identicalTo(12345),
                        $this->identicalTo([
                            "mod_namespace AS namespace",
                            "mod_title AS title",
                            "mod_text AS text",
                            "mod_comment AS comment",
                            "mod_type AS type",
                        ]),
                    )
                    ->willReturn($row);

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
            "summary" => $row->comment,
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
            "successful editchange" => [[]],
            "error: editchange not enabled" => [
                [
                    "expectedError" => "moderation-unknown-modaction",
                    "enabled" => false,
                ],
            ],
            "error: editchange is not applicable to moves" => [
                [
                    "expectedError" => "moderation-editchange-not-edit",
                    "mod_type" => ModerationNewChange::MOD_TYPE_MOVE,
                ],
            ],
        ];
    }

    /**
     * Verify that outputResult() correctly shows the ModerationEditChangePage form.
     * @covers MediaWiki\Moderation\ModerationActionEditChange
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
        ) use (&$hookFired) {
            $hookFired = true;

            $this->assertInstanceOf(
                ModerationEditChangePage::class,
                $editPage,
                "EditPage object should be of subclass ModerationEditChangePage.",
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
            ModerationActionEditChange::class,
            function ($context, $entryFactory, $manager) use ($title, $modid) {
                $context->setRequest(new FauxRequest(["modid" => $modid]));
                $context->setTitle($title);

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
