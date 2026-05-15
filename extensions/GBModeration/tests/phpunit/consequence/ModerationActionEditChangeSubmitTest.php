<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2023 Edward Chernenko.

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
 * Unit test of ModerationActionEditChangeSubmit.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\AddLogEntryConsequence;
use MediaWiki\Moderation\ModerationActionEditChangeSubmit;
use MediaWiki\Moderation\ModerationError;
use MediaWiki\Moderation\ModerationNewChange;
use MediaWiki\Moderation\ModifyPendingChangeConsequence;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use User;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class ModerationActionEditChangeSubmitTest extends ModerationUnitTestCase
{
    use ActionTestTrait;

    /**
     * Verify that execute() returns expected result.
     * @param array $opt
     * @dataProvider dataProviderExecute
     * @covers MediaWiki\Moderation\ModerationActionEditChangeSubmit
     */
    public function testExecute(array $opt)
    {
        $expectedError = $opt["expectedError"] ?? null;
        $enabled = $opt["enabled"] ?? true;
        $noop = $opt["noop"] ?? false;

        $this->setMwGlobals("wgModerationEnableEditChange", $enabled);

        $modid = 12345;
        $authorUser = self::getTestUser()->getUser();

        $row = (object) [
            "namespace" => rand(0, 1),
            "title" => "UTPage_" . rand(0, 100000),
            "user" => $authorUser->getId(),
            "user_text" => $authorUser->getName(),
            "text" => "Some text " . rand(0, 100000),
            "comment" => "Edit summary " . rand(0, 100000),
        ];
        $newText = $opt["newText"] ?? ($noop ? $row->text : "New text");
        $expectedText = $opt["expectedText"] ?? $newText;

        $action = $this->makeActionForTesting(
            ModerationActionEditChangeSubmit::class,
            function ($context, $entryFactory, $manager) use (
                $modid,
                $row,
                $enabled,
                $expectedError,
                $noop,
                $newText,
                $expectedText,
            ) {
                $moderatorUser = User::newFromName("10.15.20.25", false);
                $newComment = $noop ? $row->comment : "New edit summary";

                $context->setRequest(
                    new FauxRequest([
                        "modid" => $modid,
                        "wpTextbox1" => $newText,
                        "wpSummary" => $newComment,
                    ]),
                );
                $context->setUser($moderatorUser);

                $entryFactory
                    ->expects($enabled ? $this->once() : $this->never())
                    ->method("loadRowOrThrow")
                    ->with(
                        $this->identicalTo([
                            "mod_id" => $modid,
                            "mod_type" => ModerationNewChange::MOD_TYPE_EDIT,
                        ]),
                        $this->identicalTo([
                            "mod_namespace AS namespace",
                            "mod_title AS title",
                            "mod_user AS user",
                            "mod_user_text AS user_text",
                            "mod_text AS text",
                            "mod_comment AS comment",
                        ]),
                    )
                    ->willReturn($row);

                if ($expectedError || $noop) {
                    // Unsuccessful or "no changes needed" action shouldn't have any consequences.
                    $manager->expects($this->never())->method("add");
                    return;
                }

                $manager
                    ->expects($this->exactly(2))
                    ->method("add")
                    ->withConsecutive(
                        [
                            $this->consequenceEqualTo(
                                new ModifyPendingChangeConsequence(
                                    $modid,
                                    $expectedText,
                                    $newComment,
                                    strlen($expectedText),
                                ),
                            ),
                        ],
                        [
                            $this->consequenceEqualTo(
                                new AddLogEntryConsequence(
                                    "editchange",
                                    $moderatorUser,
                                    Title::makeTitle(
                                        $row->namespace,
                                        $row->title,
                                    ),
                                    [
                                        "modid" => $modid,
                                    ],
                                ),
                            ),
                        ],
                    );
            },
        );

        if ($expectedError) {
            $this->expectExceptionObject(new ModerationError($expectedError));
        }
        $result = $action->execute();

        $expectedResult = [
            "id" => $modid,
            "success" => true,
            "noop" => $noop,
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
            "successful editchangesubmit" => [[]],
            "successful editchangesubmit (with PreSaveTransform applied to new text)" => [
                [
                    "newText" => "[[Project:PipeTrick|]]",
                    "expectedText" => "[[Project:PipeTrick|PipeTrick]]",
                ],
            ],
            "no-op editchangesubmit (new text/comment are same as before)" => [
                ["noop" => true],
            ],
            "error: editchange not enabled" => [
                [
                    "expectedError" => "moderation-unknown-modaction",
                    "enabled" => false,
                ],
            ],
        ];
    }

    /**
     * Verify that outputResult() correctly converts return value of execute() into HTML output.
     * @covers MediaWiki\Moderation\ModerationActionEditChangeSubmit
     */
    public function testOutputResult()
    {
        $action = $this->makeActionForTesting(
            ModerationActionEditChangeSubmit::class,
        );

        // Obtain a new OutputPage object that is different from OutputPage in $context.
        $output = clone $action->getOutput();
        $action->outputResult([], $output);

        $this->assertSame(
            "<p>(moderation-editchange-ok)\n</p>",
            $output->getHTML(),
            "Result of outputResult() doesn't match expected.",
        );
    }
}
