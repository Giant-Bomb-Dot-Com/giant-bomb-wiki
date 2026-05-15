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
 * Unit test of ModerationEntryUpload.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\ApproveUploadConsequence;
use MediaWiki\Moderation\IConsequenceManager;
use MediaWiki\Moderation\ModerationEntryUpload;
use MediaWiki\Title\Title;
use Status;
use User;
use Wikimedia\TestingAccessWrapper;

require_once __DIR__ . "/autoload.php";

class ModerationEntryUploadTest extends ModerationUnitTestCase
{
    /**
     * Check result/consequences of ModerationEntryUpload::doApprove.
     * @covers MediaWiki\Moderation\ModerationEntryUpload
     */
    public function testApprove()
    {
        $row = (object) [
            "stash_key" => "MockedStashKey.jpg",
            "comment" => "Upload comment",
            "text" => "Initial description",
        ];
        $title = $this->createMock(Title::class);
        $authorUser = $this->createMock(User::class);
        $status = $this->createMock(Status::class);

        '@phan-var Title $title';
        '@phan-var User $authorUser';

        $manager = $this->createMock(IConsequenceManager::class);
        $manager
            ->expects($this->once())
            ->method("add")
            ->with(
                $this->consequenceEqualTo(
                    new ApproveUploadConsequence(
                        $row->stash_key,
                        $title,
                        $authorUser,
                        $row->comment,
                        $row->text,
                    ),
                ),
            )
            ->willReturn($status);

        $entry = $this->getMockBuilder(ModerationEntryUpload::class)
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

        $result = $wrapper->doApprove($this->createMock(User::class));

        $this->assertSame(
            $status,
            $result,
            "Result of doApprove() doesn't match expected.",
        );
    }
}
