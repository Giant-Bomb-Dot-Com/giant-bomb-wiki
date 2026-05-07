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
 * Unit test of ModerationEntryEdit.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\AddLogEntryConsequence;
use MediaWiki\Moderation\ApproveEditConsequence;
use MediaWiki\Moderation\IConsequenceManager;
use MediaWiki\Moderation\MarkAsConflictConsequence;
use MediaWiki\Moderation\ModerationEntryEdit;
use MediaWiki\Moderation\RejectOneConsequence;
use MediaWiki\Title\Title;
use Status;
use User;
use Wikimedia\TestingAccessWrapper;

require_once __DIR__ . "/autoload.php";

class ModerationEntryEditTest extends ModerationUnitTestCase
{
    /**
     * Check result/consequences of ModerationEntryEdit::doApprove.
     * @param array $opt
     * @dataProvider dataProviderApprove
     * @covers MediaWiki\Moderation\ModerationEntryEdit
     */
    public function testApprove(array $opt)
    {
        $errorFromConsequence = $opt["errorFromConsequence"] ?? null;
        $warningFromConsequence = $opt["warningFromConsequence"] ?? null;
        $isMinor = $opt["minor"] ?? false;
        $isBot = $opt["bot"] ?? false;
        $isAllowedBot = $opt["isAllowedBot"] ?? false;

        $row = (object) [
            "id" => 12345,
            "comment" => "Edit comment",
            "text" => "New text",
            "minor" => $isMinor ? 1 : 0,
            "bot" => $isBot ? 1 : 0,
            "last_oldid" => $opt["last_oldid"] ?? 0,
            "user" => 678,
            "user_text" => "Username of author",
        ];

        $title = $this->createMock(Title::class);
        $moderatorUser = $this->createMock(User::class);
        $authorUser = $this->createMock(User::class);

        $authorUser
            ->expects($this->any())
            ->method("isAllowed")
            ->with($this->identicalTo("bot"))
            ->willReturn($isAllowedBot);

        $status = Status::newGood();
        if ($errorFromConsequence) {
            $status->fatal($errorFromConsequence);
        }
        if ($warningFromConsequence) {
            $status->warning($warningFromConsequence);
        }

        '@phan-var Title $title';
        '@phan-var User $authorUser';
        '@phan-var User $moderatorUser';

        $expectedCalls = [
            [
                $this->consequenceEqualTo(
                    new ApproveEditConsequence(
                        $authorUser,
                        $title,
                        $row->text,
                        $row->comment,
                        $isBot && $isAllowedBot,
                        $isMinor,
                        $row->last_oldid,
                    ),
                ),
            ],
        ];
        if ($errorFromConsequence === "moderation-edit-conflict") {
            $expectedCalls[] = [
                $this->consequenceEqualTo(
                    new MarkAsConflictConsequence($row->id),
                ),
            ];
        } elseif ($warningFromConsequence === "edit-no-change") {
            // Attempt to Approve has revealed that this change is a null edit.
            // Null edit can't be approved, so it should be automatically Rejected.
            $expectedCalls[] = [
                $this->consequenceEqualTo(
                    new RejectOneConsequence($row->id, $moderatorUser),
                ),
            ];
            $expectedCalls[] = [
                $this->consequenceEqualTo(
                    new AddLogEntryConsequence(
                        "reject",
                        $moderatorUser,
                        $title,
                        [
                            "modid" => $row->id,
                            "user" => (int) $row->user,
                            "user_text" => $row->user_text,
                        ],
                    ),
                ),
            ];
        }

        $manager = $this->createMock(IConsequenceManager::class);
        $manager
            ->expects($this->exactly(count($expectedCalls)))
            ->method("add")
            ->withConsecutive(...$expectedCalls)
            ->willReturnOnConsecutiveCalls(
                $status, // From ApproveEditConsequence
                1, // From RejectOneConsequence
            );

        $entry = $this->getMockBuilder(ModerationEntryEdit::class)
            ->disableOriginalConstructor()
            ->onlyMethods(["getRow", "getTitle", "getUser"])
            ->getMock();

        $entry->expects($this->once())->method("getRow")->willReturn($row);
        $entry
            ->expects($this->once())
            ->method("getUser")
            ->willReturn($authorUser);
        $entry->expects($this->once())->method("getTitle")->willReturn($title);

        $wrapper = TestingAccessWrapper::newFromObject($entry);
        $wrapper->consequenceManager = $manager;

        $result = $wrapper->doApprove($moderatorUser);
        $this->assertSame(
            $status,
            $result,
            "Result of doApprove() doesn't match expected.",
        );
    }

    /**
     * Provide datasets for testApprove() runs.
     * @return array
     */
    public function dataProviderApprove()
    {
        return [
            "successful approval" => [[]],
            "error: ApproveEditConsequence returned Status that indicates failure" => [
                [
                    "errorFromConsequence" => "some-mocked-error-code",
                ],
            ],
            "error: ApproveEditConsequence returned Status that indicates edit conflict" => [
                [
                    "errorFromConsequence" => "moderation-edit-conflict",
                ],
            ],
            "warning: ApproveEditConsequence returned Status that indicates null edit" => [
                [
                    "warningFromConsequence" => "edit-no-change",
                ],
            ],

            "successful approval: minor edit" => [["minor" => true]],
            "successful approval: ignored bot=true: author is not allowed to make bot edits" => [
                ["bot" => true, "isAllowedBot" => false],
            ],
            'successful approval: bot edit, author has "bot" permission' => [
                ["bot" => true, "isAllowedBot" => true],
            ],
            "successful approval: last_oldid=98765" => [
                ["last_oldid" => 98765],
            ],
        ];
    }
}
