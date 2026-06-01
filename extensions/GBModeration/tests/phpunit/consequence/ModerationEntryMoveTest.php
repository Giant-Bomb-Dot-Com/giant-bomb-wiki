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
 * Unit test of ModerationEntryMove.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\ApproveMoveConsequence;
use MediaWiki\Moderation\IConsequenceManager;
use MediaWiki\Moderation\ModerationEntryMove;
use MediaWiki\Title\Title;
use Status;
use User;
use Wikimedia\TestingAccessWrapper;

require_once __DIR__ . "/autoload.php";

class ModerationEntryMoveTest extends ModerationUnitTestCase
{
    /**
     * Check result/consequences of ModerationEntryMove::doApprove().
     * @covers MediaWiki\Moderation\ModerationEntryMove
     */
    public function testApprove()
    {
        $row = (object) [
            "comment" => "Sample reason for moving the page",
        ];
        $title = $this->createMock(Title::class);
        $page2Title = $this->createMock(Title::class);
        $authorUser = $this->createMock(User::class);
        $moderatorUser = $this->createMock(User::class);
        $status = $this->createMock(Status::class);

        '@phan-var Title $title';
        '@phan-var Title $page2Title';
        '@phan-var User $authorUser';
        '@phan-var User $moderatorUser';

        $manager = $this->createMock(IConsequenceManager::class);
        $manager
            ->expects($this->once())
            ->method("add")
            ->with(
                $this->consequenceEqualTo(
                    new ApproveMoveConsequence(
                        $moderatorUser,
                        $title,
                        $page2Title,
                        $authorUser,
                        $row->comment,
                    ),
                ),
            )
            ->willReturn($status);

        $entry = $this->makeEntry(
            ["getRow", "getTitle", "getPage2Title", "getUser"],
            $manager,
        );
        $entry->expects($this->once())->method("getRow")->willReturn($row);
        $entry->expects($this->once())->method("getTitle")->willReturn($title);
        $entry
            ->expects($this->once())
            ->method("getPage2Title")
            ->willReturn($page2Title);
        $entry
            ->expects($this->once())
            ->method("getUser")
            ->willReturn($authorUser);

        $wrapper = TestingAccessWrapper::newFromObject($entry);
        $result = $wrapper->doApprove($moderatorUser);

        $this->assertSame(
            $status,
            $result,
            "Result of doApprove() doesn't match expected.",
        );
    }

    /**
     * Test return value of getApproveLogSubtype().
     * @covers MediaWiki\Moderation\ModerationEntryMove
     */
    public function testApproveLogSubtype()
    {
        $wrapper = TestingAccessWrapper::newFromObject($this->makeEntry());
        $this->assertSame("approve-move", $wrapper->getApproveLogSubtype());
    }

    /**
     * Test return value of getApproveLogParameters().
     * @covers MediaWiki\Moderation\ModerationEntryMove
     */
    public function testApproveLogParameters()
    {
        $row = (object) ["user" => 678, "user_text" => "Some username"];
        $targetPageName = "Talk:Sample article";

        $entry = $this->makeEntry(["getRow", "getPage2Title"]);
        $entry->expects($this->once())->method("getRow")->willReturn($row);
        $entry
            ->expects($this->once())
            ->method("getPage2Title")
            ->willReturn(Title::newFromText($targetPageName));

        $expectedResult = [
            "4::target" => $targetPageName,
            "user" => $row->user,
            "user_text" => $row->user_text,
        ];

        $wrapper = TestingAccessWrapper::newFromObject($entry);
        $this->assertSame($expectedResult, $wrapper->getApproveLogParameters());
    }

    /**
     * Create ModerationEntryMove for testing.
     * @param string[] $methods Array of method names to mock (for MockBuilder::setMethods()).
     * @param mixed|null $manager ConsequenceManager or its mock.
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function makeEntry(array $methods = [], $manager = null)
    {
        $entry = $this->getMockBuilder(ModerationEntryMove::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();

        $wrapper = TestingAccessWrapper::newFromObject($entry);
        $wrapper->consequenceManager =
            $manager ?? $this->createMock(IConsequenceManager::class);

        return $entry;
    }
}
