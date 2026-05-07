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
 * Verifies that uploading a file has consequences.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\ModerationCanSkip;
use MediaWiki\Moderation\QueueUploadConsequence;
use MediaWiki\Title\Title;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class UploadsHaveConsequencesTest extends ModerationUnitTestCase
{
    use UploadTestTrait;

    /** @var Title */
    protected $title;

    /** @var string */
    protected $reason;

    /**
     * Test consequences when an upload is queued for moderation.
     * @covers MediaWiki\Moderation\ModerationUploadHooks::onUploadVerifyUpload
     */
    public function testUpload()
    {
        $title = Title::newFromText(
            "File:UTUpload-" . rand(0, 100000) . ".png",
        );
        $upload = $this->prepareTestUpload($title);

        $user = self::getTestUser()->getUser();
        $comment = "Edit comment when uploading the file";
        $pageText = "Initial content of File:Something (description page)";

        $manager = $this->mockConsequenceManager();

        // Mock the result of canUploadSkip()
        $canSkip = $this->createMock(ModerationCanSkip::class);
        $canSkip
            ->expects($this->once())
            ->method("canUploadSkip")
            ->with($user)
            ->willReturn(false); // Can't bypass moderation
        $this->setService("Moderation.CanSkip", $canSkip);

        $status = $upload->performUpload($comment, $pageText, false, $user);
        $this->assertTrue(
            $status->hasMessage("moderation-image-queued"),
            "Status returned by performUpload doesn't include \"moderation-image-queued\".",
        );

        $this->assertConsequencesEqual(
            [new QueueUploadConsequence($upload, $user, $comment, $pageText)],
            $manager->getConsequences(),
        );
    }

    /**
     * Test consequences of upload when User is automoderated (can bypass moderation of uploads).
     * @covers MediaWiki\Moderation\ModerationUploadHooks::onUploadVerifyUpload
     */
    public function testAutomoderatedUpload()
    {
        $title = Title::newFromText(
            "File:UTUpload-" . rand(0, 100000) . ".png",
        );
        $upload = $this->prepareTestUpload($title);

        $user = self::getTestUser()->getUser();
        $comment = "Edit comment when uploading the file";
        $pageText = "Initial content of File:Something (description page)";

        $manager = $this->mockConsequenceManager();

        // 1) Mock the result of canUploadSkip(), which is called from UploadVerifyUpload hook.
        // 2) Mock the result of canEditSkip(), which is called due to the fact the performUpload()
        // (even when not intercepted by Moderation) creates an image description page,
        // which triggers MultiContentSave hook, and Moderation checks canEditSkip() in that hook.
        $canSkip = $this->createMock(ModerationCanSkip::class);

        $canSkip
            ->expects($this->once())
            ->method("canUploadSkip")
            ->with($user)
            ->willReturn(true); // Can bypass moderation and Upload the file

        $canSkip
            ->expects($this->once())
            ->method("canEditSkip")
            ->with($user, NS_FILE)
            ->willReturn(true); // Can bypass moderation to create [[File:Something]] page.

        // Install the mock.
        $this->setService("Moderation.CanSkip", $canSkip);

        $status = $upload->performUpload($comment, $pageText, false, $user);
        $this->assertTrue(
            $status->isGood(),
            "User can bypass moderation, but performUpload() didn't return successful Status.",
        );

        // The moderation was skipped, so should be no consequences.
        $this->assertNoConsequences($manager);
    }

    /**
     * Test situation when upload was to be queued, but QueueUploadConsequence failed for some reason.
     * @covers MediaWiki\Moderation\ModerationUploadHooks::onUploadVerifyUpload
     */
    public function testUploadQueueFailed()
    {
        $title = Title::newFromText(
            "File:UTUpload-" . rand(0, 100000) . ".png",
        );
        $upload = $this->prepareTestUpload($title);

        $user = self::getTestUser()->getUser();
        $comment = "Edit comment when uploading the file";
        $pageText = "Initial content of File:Something (description page)";

        $manager = $this->mockConsequenceManager();
        $expectedConsequences = [
            new QueueUploadConsequence($upload, $user, $comment, $pageText),
        ];
        $manager->mockResult(QueueUploadConsequence::class, [
            "MockedSimulatedError",
        ]);

        // Mock the result of canUploadSkip()
        $canSkip = $this->createMock(ModerationCanSkip::class);
        $canSkip
            ->expects($this->once())
            ->method("canUploadSkip")
            ->with($user)
            ->willReturn(false); // Can't bypass moderation
        $this->setService("Moderation.CanSkip", $canSkip);

        $status = $upload->performUpload($comment, $pageText, false, $user);
        $this->assertFalse(
            $status->isOK(),
            "QueueUploadConsequence has failed, but performUpload() returned Success.",
        );
        $this->assertTrue(
            $status->hasMessage("MockedSimulatedError"),
            "Failed Status from QueueUploadConsequence wasn't returned by performUpload().",
        );

        $this->assertConsequencesEqual(
            $expectedConsequences,
            $manager->getConsequences(),
        );
    }
}
