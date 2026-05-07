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
 * Unit test of QueueUploadConsequence.
 */

namespace MediaWiki\Moderation\Tests;

use ContentHandler;
use IDBAccessObject;
use MediaWiki\Moderation\BlockUserConsequence;
use MediaWiki\Moderation\InsertRowIntoModerationTableConsequence;
use MediaWiki\Moderation\ModerationCompatTools;
use MediaWiki\Moderation\QueueUploadConsequence;
use MediaWiki\Moderation\RememberAnonIdConsequence;
use MediaWiki\Moderation\SendNotificationEmailConsequence;
use MediaWiki\Title\Title;
use RequestContext;
use User;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class QueueUploadConsequenceTest extends ModerationUnitTestCase
{
    use UploadTestTrait;

    /**
     * Check the secondary consequences of running QueueUploadConsequence.
     * @covers MediaWiki\Moderation\QueueUploadConsequence
     * @covers MediaWiki\Moderation\ModerationNewChange
     * @dataProvider dataProviderQueueUpload
     *
     * See also: ModerationQueueTest from the blackbox integration tests.
     */
    public function testQueueUpload(array $params)
    {
        $opt = (object) $params;

        $opt->existing ??= false;
        $opt->modblocked ??= false;
        $opt->notifyEmail ??= false;
        $opt->notifyNewOnly ??= false;
        $opt->anonymously ??= false;

        if ($opt->anonymously) {
            $this->disableAutoCreateTempUser();
        }

        $user = $opt->anonymously
            ? User::newFromName("127.0.0.1", false)
            : self::getTestUser()->getUser();
        $title = Title::newFromText(
            "File:UTUpload-" . rand(0, 100000) . ".png",
        );
        $summary = $opt->summary ?? "Some summary " . rand(0, 100000);
        $text = $opt->text ?? "Initial text";
        $content = ContentHandler::makeContent($text, $title);

        if ($opt->existing) {
            // Precreate the page.
            $status = $this->prepareTestUpload($title)->performUpload(
                "Initial comment",
                "Initial description",
                false, // $watch
                self::getTestUser(["moderator", "automoderated"])->getUser(),
            );
            $this->assertTrue(
                $status->isOK(),
                "Upload failed: " . $status->getMessage()->plain(),
            );
        }

        if ($opt->modblocked) {
            $consequence = new BlockUserConsequence(
                $user->getId(),
                $user->getName(),
                self::getTestUser(["moderator", "automoderated"])->getUser(),
            );
            $consequence->run();
        }

        // Replace real ConsequenceManager with a mock.
        $manager = $this->mockConsequenceManager();

        $this->setMwGlobals([
            "wgModerationNotificationEnable" => $opt->notifyEmail
                ? true
                : false,
            "wgModerationEmail" => $opt->notifyEmail,
            "wgModerationNotificationNewOnly" => $opt->notifyNewOnly ?? false,
        ]);

        // No actual DB operations will be happening, so mock the returned mod_id.
        $modid = 12345;
        $manager->mockResult(
            InsertRowIntoModerationTableConsequence::class,
            $modid,
        );

        $anonId = 67890;
        $manager->mockResult(RememberAnonIdConsequence::class, $anonId);

        // TODO: verify that ModerationPending hook will be called.

        // Create and run the Consequence.
        $consequence = new QueueUploadConsequence(
            $this->prepareTestUpload($title),
            $user,
            $summary,
            $text,
        );
        $error = $consequence->run();

        $this->assertNull($error, "QueueUploadConsequence returned an error");

        // This is very similar to ModerationQueueTest::getExpectedRow().
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $expectedStashKey = $dbw->selectField(
            "uploadstash",
            "us_key",
            "",
            __METHOD__,
        );

        $expectedFields = [
            "mod_timestamp" => "ignored by assertConsequencesEqual()",
            "mod_user" => $user->getId(),
            "mod_user_text" => $user->getName(),
            "mod_cur_id" => $opt->existing
                ? $title->getArticleId(IDBAccessObject::READ_LATEST)
                : 0,
            "mod_namespace" => $title->getNamespace(),
            "mod_title" => $title->getDBKey(),
            "mod_comment" => $summary,
            "mod_minor" => 0,
            "mod_bot" => 0,
            "mod_new" => $opt->existing ? 0 : 1,
            "mod_last_oldid" => $opt->existing
                ? $title->getLatestRevID(IDBAccessObject::READ_LATEST)
                : 0,
            "mod_ip" => "127.0.0.1",
            "mod_old_len" => $opt->existing
                ? $title->getLength(IDBAccessObject::READ_LATEST)
                : 0,
            "mod_new_len" => $content->getSize(),
            "mod_header_xff" => $opt->xff ?? null,
            "mod_header_ua" => $opt->userAgent ?? null,
            "mod_preload_id" => $opt->anonymously
                ? "]" . $anonId
                : "[" . $user->getName(),
            "mod_rejected" => $opt->modblocked ? 1 : 0,
            "mod_rejected_by_user" => 0,
            "mod_rejected_by_user_text" => $opt->modblocked
                ? wfMessage("moderation-blocker")->inContentLanguage()->text()
                : null,
            "mod_rejected_batch" => 0,
            "mod_rejected_auto" => $opt->modblocked ? 1 : 0,
            "mod_preloadable" => 0,
            "mod_conflict" => 0,
            "mod_merged_revid" => 0,
            "mod_text" => $content->serialize(), // Won't work if $content needs PST
            "mod_stash_key" => $expectedStashKey,
            "mod_tags" => null,
            "mod_type" => "edit",
            "mod_page2_namespace" => 0,
            "mod_page2_title" => "",
        ];

        // Check secondary consequences.
        $expectedConsequences = [];
        if ($opt->anonymously) {
            $expectedConsequences[] = new RememberAnonIdConsequence();
        }

        $expectedConsequences[] = new InsertRowIntoModerationTableConsequence(
            $expectedFields,
        );

        if (
            !$opt->modblocked &&
            $opt->notifyEmail &&
            (!$opt->notifyNewOnly || !$opt->existing)
        ) {
            $expectedConsequences[] = new SendNotificationEmailConsequence(
                $title,
                $user,
                $modid,
            );
        }

        $this->assertConsequencesEqual(
            $expectedConsequences,
            $manager->getConsequences(),
        );
    }

    /**
     * Provide datasets for testQueueUpload() runs.
     * @return array
     */
    public function dataProviderQueueUpload()
    {
        return [
            "logged-in upload" => [[]],
            "anonymous upload" => [["anonymously" => true]],
            "reupload" => [["existing" => true]],
            "upload with edit summary" => [["summary" => "Summary 1"]],
            "upload with initial text" => [["text" => "Initial description 1"]],
            "upload by modblocked user" => [["modblocked" => true]],
            "email notification about upload" => [
                ["notifyEmail" => "noreply@localhost"],
            ],
            'email notification for new upload when $wgModerationNotificationNewOnly=true' => [
                [
                    "notifyEmail" => "noreply@localhost",
                    "notifyNewOnly" => true,
                ],
            ],
            'absence of email for reupload when $wgModerationNotificationNewOnly=true' => [
                [
                    "existing" => true,
                    "notifyNewOnly" => true,
                    "notifyEmail" => "noreply@localhost",
                ],
            ],
            "absence of email for upload by modblocked user" => [
                [
                    "modblocked" => true,
                    "notifyEmail" => "noreply@localhost",
                ],
            ],
        ];
    }

    /**
     * Verify that QueueUploadConsequence fails if the file can't be saved into the Stash.
     * @covers MediaWiki\Moderation\QueueUploadConsequence
     */
    public function testStashFailed()
    {
        $user = self::getTestUser()->getUser();
        $title = Title::newFromText(
            "File:UTUpload-" . rand(0, 100000) . ".png",
        );

        // Provide incorrect "where to store" directory, so that saving into Stash will fail.
        $repo = RequestContext::getMain()->getConfig()->get("LocalFileRepo");
        $repo["directory"] = "/dev/null/clearly/incorrect/path";
        $this->setMwGlobals("wgLocalFileRepo", $repo);

        // Replace real ConsequenceManager with a mock.
        $manager = $this->mockConsequenceManager();

        // Create and run the Consequence.
        $consequence = new QueueUploadConsequence(
            $this->prepareTestUpload($title),
            $user,
            "",
            "",
        );
        $error = $consequence->run();

        $this->assertSame(["api-error-stashfailed"], $error);
        $this->assertConsequencesEqual([], $manager->getConsequences());
    }
}
