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
 * Unit test of QueueEditConsequence.
 */

namespace MediaWiki\Moderation\Tests;

use ContentHandler;
use IDBAccessObject;
use MediaWiki\Moderation\BlockUserConsequence;
use MediaWiki\Moderation\Hook\HookRunner;
use MediaWiki\Moderation\InsertRowIntoModerationTableConsequence;
use MediaWiki\Moderation\ModerationCompatTools;
use MediaWiki\Moderation\ModerationPreload;
use MediaWiki\Moderation\PendingEdit;
use MediaWiki\Moderation\QueueEditConsequence;
use MediaWiki\Moderation\SendNotificationEmailConsequence;
use MediaWiki\Title\Title;
use User;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class QueueEditConsequenceTest extends ModerationUnitTestCase
{
    use MakeEditTestTrait;

    /**
     * Check the secondary consequences of running QueueEditConsequence.
     * @covers MediaWiki\Moderation\QueueEditConsequence
     * @covers MediaWiki\Moderation\ModerationNewChange
     * @dataProvider dataProviderQueueEdit
     *
     * See also: ModerationQueueTest from the blackbox integration tests.
     */
    public function testQueueEdit(array $params)
    {
        $opt = (object) $params;

        $opt->bot ??= false;
        $opt->minor ??= false;
        $opt->existing ??= false;
        $opt->modblocked ??= false;
        $opt->notifyEmail ??= false;
        $opt->notifyNewOnly ??= false;
        $opt->anonymously ??= false;
        $opt->editedBefore ??= false;
        $opt->section ??= "";
        $opt->sectionText ??= "";
        $opt->preloadedText ??= "";

        if ($opt->anonymously) {
            $this->disableAutoCreateTempUser();
        }

        $user = $opt->anonymously
            ? User::newFromName("127.0.0.1", false)
            : self::getTestUser()->getUser();
        $title = Title::newFromText($opt->title ?? "UTPage-" . rand(0, 100000));
        $page = ModerationCompatTools::makeWikiPage($title);
        $content = ContentHandler::makeContent(
            $opt->text ?? "Some text" . rand(0, 100000),
            null,
            CONTENT_MODEL_WIKITEXT,
        );
        $opt->summary ??= "Some summary " . rand(0, 100000);
        $opt->expectedText ??= $content->serialize();

        if ($opt->existing) {
            // Precreate the page.
            $this->makeEdit(
                $title,
                self::getTestUser(["moderator", "automoderated"])->getUser(),
                "Original text" . rand(0, 100000),
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

        // @phan-suppress-next-line PhanUndeclaredMethod - Phan thinks it's not FauxRequest
        $user->getRequest()->setHeaders([
            "User-Agent" => $opt->userAgent ?? "",
            "X-Forwarded-For" => $opt->xff ?? "",
        ]);

        // Replace real ConsequenceManager with a mock.
        $manager = $this->mockConsequenceManager();

        $this->setMwGlobals([
            "wgModerationNotificationEnable" => $opt->notifyEmail
                ? true
                : false,
            "wgModerationEmail" => $opt->notifyEmail,
            "wgModerationNotificationNewOnly" => $opt->notifyNewOnly ?? false,
        ]);

        // Mock ModerationPreload service.
        $preloadId = "{MockedPreloadId}";
        $preload = $this->createMock(ModerationPreload::class);

        $preload
            ->expects($this->any())
            ->method("findPendingEdit")
            ->with($this->identicalTo($title))
            ->willReturn(new PendingEdit($title, 123, $opt->preloadedText, ""));
        $preload
            ->expects($this->once())
            ->method("getId")
            ->with($this->identicalTo(true))
            ->willReturn($preloadId);

        $this->setService("Moderation.Preload", $preload);

        // No actual DB operations will be happening, so mock the returned mod_id.
        $modid = 12345;
        $manager->mockResult(
            InsertRowIntoModerationTableConsequence::class,
            $modid,
        );

        // This is very similar to ModerationQueueTest::getExpectedRow().
        $expectedFields = [
            "mod_timestamp" => "ignored by assertConsequencesEqual()",
            "mod_user" => $user->getId(),
            "mod_user_text" => $user->getName(),
            "mod_cur_id" => $opt->existing
                ? $title->getArticleId(IDBAccessObject::READ_LATEST)
                : 0,
            "mod_namespace" => $title->getNamespace(),
            "mod_title" => $title->getDBKey(),
            "mod_comment" => $opt->summary,
            "mod_minor" => $opt->minor ? 1 : 0,
            "mod_bot" => $opt->bot ? 1 : 0,
            "mod_new" => $opt->existing ? 0 : 1,
            "mod_last_oldid" => $opt->existing
                ? $title->getLatestRevID(IDBAccessObject::READ_LATEST)
                : 0,
            "mod_ip" => "127.0.0.1",
            "mod_old_len" => $opt->existing
                ? $title->getLength(IDBAccessObject::READ_LATEST)
                : 0,
            "mod_new_len" => strlen($opt->expectedText),
            "mod_header_xff" => $opt->xff ?? null,
            "mod_header_ua" => $opt->userAgent ?? null,
            "mod_preload_id" => $preloadId,
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
            "mod_text" => $opt->expectedText,
            "mod_stash_key" => null,
            "mod_tags" => null,
            "mod_type" => "edit",
            "mod_page2_namespace" => 0,
            "mod_page2_title" => "",
        ];

        // Mock HookRunner service to ensure that ModerationPending hook will be called.
        $hookRunner = $this->createMock(HookRunner::class);
        $hookRunner
            ->expects($this->once())
            ->method("onModerationPending")
            ->will(
                $this->returnCallback(function ($hookFields, $hookModid) use (
                    $expectedFields,
                    $modid,
                ) {
                    $this->assertSame($modid, $hookModid);

                    // With the exception of timestamp, all fields must match.
                    unset($expectedFields["mod_timestamp"]);
                    unset($hookFields["mod_timestamp"]);
                    $this->assertArrayEquals(
                        $expectedFields,
                        $hookFields,
                        false,
                        true,
                    );
                }),
            );
        $this->setService("Moderation.HookRunner", $hookRunner);

        // Create and run the Consequence.
        $consequence = new QueueEditConsequence(
            $page,
            $user,
            $content,
            $opt->summary,
            $opt->section,
            $opt->sectionText,
            $opt->bot,
            $opt->minor,
        );
        $consequence->run();

        // Check secondary consequences.
        $expectedConsequences = [];
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
     * Provide datasets for testQueueEdit() runs.
     * @return array
     */
    public function dataProviderQueueEdit()
    {
        return [
            "logged-in edit" => [[]],
            "anonymous edit" => [["anonymously" => true]],
            "anonymous edit (already edited before)" => [
                ["anonymously" => true, "editedBefore" => true],
            ],
            "edit in Project namespace" => [
                [
                    "title" => "Project:Title in another namespace",
                ],
            ],
            "edit in existing page" => [["existing" => true]],
            "edit with edit summary" => [["summary" => "Summary 1"]],
            "edit with User-Agent" => [
                ["userAgent" => "UserAgent for Testing/1.0"],
            ],
            "edit with XFF" => [["xff" => "10.11.12.13"]],
            "edit by modblocked user" => [["modblocked" => true]],
            "bot edit" => [["bot" => true]],
            "minor edit" => [["minor" => true, "existing" => true]],
            "email notification" => [["notifyEmail" => "noreply@localhost"]],
            'email notification for new page when $wgModerationNotificationNewOnly=true' => [
                [
                    "notifyEmail" => "noreply@localhost",
                    "notifyNewOnly" => true,
                ],
            ],
            'absence of email for existing page when $wgModerationNotificationNewOnly=true' => [
                [
                    "existing" => true,
                    "notifyNewOnly" => true,
                    "notifyEmail" => "noreply@localhost",
                ],
            ],
            "absence of email for edit by modblocked user" => [
                [
                    "modblocked" => true,
                    "notifyEmail" => "noreply@localhost",
                ],
            ],
            "section edit" => [
                [
                    "section" => 2,
                    "sectionText" => "==NewHeader 2==\n\nNewText 2",
                    "preloadedText" =>
                        "Text 0\n\n== Header 1 ==\n\nText 1\n\n" .
                        "== Header 2 ==\n\nText 2\n\n== Header 3 ==\n\nText 3",
                    "expectedText" =>
                        "Text 0\n\n== Header 1 ==\n\nText 1\n\n" .
                        "==NewHeader 2==\n\nNewText 2\n\n== Header 3 ==\n\nText 3",
                ],
            ],
            "edit with PreSaveTransform" => [
                [
                    "text" => "[[Project:PipeTrick|]]",
                    "expectedText" => "[[Project:PipeTrick|PipeTrick]]",
                ],
            ],
        ];
    }
}
