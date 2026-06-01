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
 * Unit test of ModerationActionBlock.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\AddLogEntryConsequence;
use MediaWiki\Moderation\BlockUserConsequence;
use MediaWiki\Moderation\ModerationActionBlock;
use MediaWiki\Moderation\UnblockUserConsequence;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use PHPUnit\Framework\MockObject\MockObject;
use User;

require_once __DIR__ . "/autoload.php";

class ModerationActionBlockTest extends ModerationUnitTestCase
{
    use ActionTestTrait;

    /**
     * @var User
     * Populated in setUp().
     */
    protected $moderatorUser;

    /**
     * Check result/consequences of modaction=block.
     * @covers MediaWiki\Moderation\ModerationActionBlock
     */
    public function testExecuteBlock()
    {
        $this->runExecuteTest(
            "block",
            [
                "action" => "block",
                "username" => "Some user",
                "noop" => false,
            ],
            function (MockObject $manager) {
                $manager
                    ->expects($this->exactly(2))
                    ->method("add")
                    ->withConsecutive(
                        [
                            $this->consequenceEqualTo(
                                new BlockUserConsequence(
                                    456,
                                    "Some user",
                                    $this->moderatorUser,
                                ),
                            ),
                        ],
                        [
                            $this->consequenceEqualTo(
                                new AddLogEntryConsequence(
                                    "block",
                                    $this->moderatorUser,
                                    Title::makeTitle(NS_USER, "Some user"),
                                ),
                            ),
                        ],
                    )
                    ->willReturnOnConsecutiveCalls(true); // Something changed
            },
        );
    }

    /**
     * Check result/consequences of modaction=block when the user is already blocked.
     * @covers MediaWiki\Moderation\ModerationActionBlock
     */
    public function testNoopBlock()
    {
        $this->runExecuteTest(
            "block",
            [
                "action" => "block",
                "username" => "Some user",
                "noop" => true,
            ],
            function (MockObject $manager) {
                $manager
                    ->expects($this->once())
                    ->method("add")
                    ->with(
                        $this->consequenceEqualTo(
                            new BlockUserConsequence(
                                456,
                                "Some user",
                                $this->moderatorUser,
                            ),
                        ),
                    )
                    ->willReturn(false); // Nothing changed
            },
        );
    }

    /**
     * Check result/consequences of modaction=unblock.
     * @covers MediaWiki\Moderation\ModerationActionBlock
     */
    public function testExecuteUnblock()
    {
        $this->runExecuteTest(
            "unblock",
            [
                "action" => "unblock",
                "username" => "Some user",
                "noop" => false,
            ],
            function (MockObject $manager) {
                $manager
                    ->expects($this->exactly(2))
                    ->method("add")
                    ->withConsecutive(
                        [
                            $this->consequenceEqualTo(
                                new UnblockUserConsequence("Some user"),
                            ),
                        ],
                        [
                            $this->consequenceEqualTo(
                                new AddLogEntryConsequence(
                                    "unblock",
                                    $this->moderatorUser,
                                    Title::makeTitle(NS_USER, "Some user"),
                                ),
                            ),
                        ],
                    )
                    ->willReturnOnConsecutiveCalls(true); // Something changed
            },
        );
    }

    /**
     * Check result/consequences of modaction=unblock when the user is already not blocked.
     * @covers MediaWiki\Moderation\ModerationActionBlock
     */
    public function testExecuteNoopUnblock()
    {
        $this->runExecuteTest(
            "unblock",
            [
                "action" => "unblock",
                "username" => "Some user",
                "noop" => true,
            ],
            function (MockObject $manager) {
                $manager
                    ->expects($this->once())
                    ->method("add")
                    ->with(
                        $this->consequenceEqualTo(
                            new UnblockUserConsequence("Some user"),
                        ),
                    )
                    ->willReturn(false); // Nothing changed
            },
        );
    }

    /**
     * Verify that outputResult() correctly converts return value of execute() into HTML output.
     * @param array $expectedHtml What should outputResult() write into its OutputPage parameter.
     * @param array $executeResult Return value of execute().
     * @dataProvider dataProviderOutputResult
     * @covers MediaWiki\Moderation\ModerationActionBlock
     */
    public function testOutputResult($expectedHtml, array $executeResult)
    {
        $action = $this->makeActionForTesting(ModerationActionBlock::class);

        // Obtain a new OutputPage object that is different from OutputPage in $context.
        // This verifies that outputResult() does indeed use its second parameter for output
        // rather than printing into $this->getContext()->getOutput() (which would be incorrect).
        $output = clone $action->getOutput();
        $action->outputResult($executeResult, $output);

        $this->assertSame(
            $expectedHtml,
            $output->getHTML(),
            "Result of outputResult() doesn't match expected.",
        );
    }

    /**
     * Provide datasets for testOutputResult() runs.
     * @return array
     */
    public function dataProviderOutputResult()
    {
        return [
            "block" => [
                "<p>(moderation-block-ok: Some user)\n</p>",
                [
                    "action" => "block",
                    "username" => "Some user",
                    "noop" => false,
                ],
            ],
            "block (noop)" => [
                // Noop blocks are also reported as success.
                // There is no point to say "error" if the moderator just clicked the Block link twice.
                "<p>(moderation-block-ok: Some user)\n</p>",
                [
                    "action" => "block",
                    "username" => "Some user",
                    "noop" => true,
                ],
            ],
            "unblock" => [
                "<p>(moderation-unblock-ok: Some user)\n</p>",
                [
                    "action" => "unblock",
                    "username" => "Some user",
                    "noop" => false,
                ],
            ],
            "unblock (noop)" => [
                // Noop unblocks are reported as success.
                "<p>(moderation-unblock-ok: Some user)\n</p>",
                [
                    "action" => "unblock",
                    "username" => "Some user",
                    "noop" => true,
                ],
            ],
        ];
    }

    /**
     * Verify the consequences and return value of execute().
     * @param string $actionName
     * @param array $expectedResult
     * @param callable $setupManager
     */
    public function runExecuteTest(
        $actionName,
        array $expectedResult,
        callable $setupManager,
    ) {
        $action = $this->makeActionForTesting(
            ModerationActionBlock::class,
            function ($context, $entryFactory, $manager) use (
                $actionName,
                $setupManager,
            ) {
                $context->setRequest(
                    new FauxRequest([
                        "modid" => 12345,
                        "modaction" => $actionName,
                    ]),
                );
                $context->setUser($this->moderatorUser);

                $entryFactory
                    ->expects($this->once())
                    ->method("loadRowOrThrow")
                    ->with(
                        $this->identicalTo(12345),
                        $this->identicalTo([
                            "mod_user AS user",
                            "mod_user_text AS user_text",
                        ]),
                    )
                    ->willReturn(
                        (object) [
                            "user" => "456",
                            "user_text" => "Some user",
                        ],
                    );

                $setupManager($manager);
            },
        );

        $this->assertSame($expectedResult, $action->execute());
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->moderatorUser = User::newFromName("10.30.50.70", false);
    }
}
