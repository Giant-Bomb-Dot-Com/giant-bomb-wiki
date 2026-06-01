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
 * Verifies that editing a page has consequences.
 */

namespace MediaWiki\Moderation\Tests;

use CommentStoreComment;
use Content;
use ContentHandler;
use DummyNonTextContent;
use DummyNonTextContentHandler;
use FormatJson;
use IContextSource;
use MediaWiki\Moderation\AddLogEntryConsequence;
use MediaWiki\Moderation\EditFormOptions;
use MediaWiki\Moderation\InvalidatePendingTimeCacheConsequence;
use MediaWiki\Moderation\MarkAsMergedConsequence;
use MediaWiki\Moderation\ModerationCanSkip;
use MediaWiki\Moderation\ModerationCompatTools;
use MediaWiki\Moderation\QueueEditConsequence;
use MediaWiki\Moderation\TagRevisionAsMergedConsequence;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use RequestContext;
use Status;
use User;
use WikiPage;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class EditsHaveConsequencesTest extends ModerationUnitTestCase
{
    /** @var int */
    protected $modid;

    /** @var User */
    protected $user;

    /** @var Title */
    protected $title;

    /** @var Content */
    protected $content;

    /** @var string */
    protected $summary;

    /**
     * Test consequences when normal edit is queued for moderation.
     * @covers MediaWiki\Moderation\ModerationEditHooks::onMultiContentSave
     * @covers MediaWiki\Moderation\ModerationEditHooks::getRedirectURL
     */
    public function testEdit()
    {
        // Replace real ConsequenceManager with a mock.
        $manager = $this->mockConsequenceManager();

        // Mock the result of canEditSkip()
        $canSkip = $this->createMock(ModerationCanSkip::class);
        $canSkip
            ->expects($this->once())
            ->method("canEditSkip")
            ->with(
                $this->identicalTo($this->user),
                $this->identicalTo($this->title->getNamespace()),
            )
            ->willReturn(false); // Can't bypass moderation
        $this->setService("Moderation.CanSkip", $canSkip);

        $status = $this->makeEdit();
        $this->assertTrue(
            $status->hasMessage("moderation-edit-queued"),
            "Status returned by doEditContent doesn't include \"moderation-edit-queued\".",
        );

        $this->assertConsequencesEqual(
            [
                new QueueEditConsequence(
                    ModerationCompatTools::makeWikiPage($this->title),
                    $this->user,
                    $this->content,
                    $this->summary,
                    "", // section
                    "", // sectionText
                    false, // isBot
                    false, // isMinor
                ),
            ],
            $manager->getConsequences(),
        );

        $redirectURL = RequestContext::getMain()->getOutput()->getRedirect();
        $this->assertNotEmpty(
            $redirectURL,
            "User wasn't redirected after making an edit.",
        );

        $this->assertSame(
            $this->title->getFullURL(["modqueued" => 1]),
            $redirectURL,
            "Incorrect redirect URL.",
        );
    }

    /**
     * Test consequences of normal edit when User is automoderated (can bypass moderation of edits).
     * @covers MediaWiki\Moderation\ModerationEditHooks::onMultiContentSave
     */
    public function testAutomoderatedEdit()
    {
        // Replace real ConsequenceManager with a mock.
        $manager = $this->mockConsequenceManager();

        // Mock the result of canEditSkip()
        $canSkip = $this->createMock(ModerationCanSkip::class);
        $canSkip
            ->expects($this->once())
            ->method("canEditSkip")
            ->with($this->user, $this->title->getNamespace())
            ->willReturn(true); // Can bypass moderation
        $this->setService("Moderation.CanSkip", $canSkip);

        $status = $this->makeEdit();
        $this->assertTrue(
            $status->isGood(),
            "User can bypass moderation, but doEditContent() didn't return successful Status.",
        );

        $this->assertNoConsequences($manager);
        $this->assertStringNotContainsString(
            "modqueued",
            RequestContext::getMain()->getOutput()->getRedirect(),
            "Redirect URL shouldn't contain modqueued= when the moderation was skipped.",
        );
    }

    /**
     * Verify that ModerationIntercept hook is called when edit is about to be queued for moderation.
     * Also verify that returning false from this hook will allow this edit to bypass moderation.
     * @covers MediaWiki\Moderation\ModerationEditHooks::onMultiContentSave
     */
    public function testModerationInterceptHook()
    {
        // Replace real ConsequenceManager with a mock.
        $manager = $this->mockConsequenceManager();

        $this->setTemporaryHook("ModerationIntercept", function (
            WikiPage $page,
            User $user,
            Content $content,
            $summary,
            $is_minor,
            $is_watch,
            $section,
            $flags,
            Status $status,
        ) {
            $this->assertSame(
                $this->title->getFullText(),
                $page->getTitle()->getFullText(),
            );
            $this->assertSame($this->user->getName(), $user->getName());
            $this->assertSame($this->user->getId(), $user->getId());
            $this->assertSame($this->summary, $summary);

            // Returning false from this hook means "this edit should bypass moderation".
            return false;
        });

        $status = $this->makeEdit();
        $this->assertTrue(
            $status->isGood(),
            "ModerationIntercept hook returned false, " .
                "but doEditContent() didn't return successful Status.",
        );

        // This edit shouldn't have been queued for moderation.
        $this->assertNotIntercepted($manager);
    }

    /**
     * Verify that ModerationContinueEditingLink hook can override redirect URL when edit is queued.
     * @covers MediaWiki\Moderation\ModerationEditHooks::getRedirectURL
     */
    public function testModerationContinueEditingLinkHook()
    {
        $expectedReturnTo = FormatJson::encode([
            "Another page",
            ["param1" => "val1", "anotherparam" => "anotherval"],
        ]);

        $this->setTemporaryHook(
            "ModerationContinueEditingLink",
            static function (
                &$returnto,
                array &$returntoquery,
                Title $title,
                IContextSource $context,
            ) {
                $returnto = "Another page";
                $returntoquery = [
                    "param1" => "val1",
                    "anotherparam" => "anotherval",
                ];
            },
        );

        $this->makeEdit();

        $redirectURL = RequestContext::getMain()->getOutput()->getRedirect();
        $this->assertNotEmpty(
            $redirectURL,
            "User wasn't redirected after making an edit.",
        );

        // Parse $redirectURL, extract query string parameters and check "returnto" parameter.
        $bits = ModerationTestUtil::parseUrl($redirectURL);
        $this->assertArrayHasKey(
            "query",
            $bits,
            "No querystring in the redirect URL.",
        );

        $query = wfCgiToArray($bits["query"]);
        $this->assertArrayHasKey("modqueued", $query);
        $this->assertSame("1", $query["modqueued"], "query.modqueued");

        $this->assertArrayHasKey("returnto", $query);
        $this->assertSame(
            $expectedReturnTo,
            $query["returnto"],
            "Query string parameter returnto= wasn't populated by ModerationContinueEditingLink hook.",
        );
    }

    /**
     * Verify that editing non-text content (such as Flow forums) will bypass moderation.
     * @covers MediaWiki\Moderation\ModerationEditHooks::onMultiContentSave
     */
    public function testEditNonTextContent()
    {
        // Replace real ConsequenceManager with a mock.
        $manager = $this->mockConsequenceManager();
        $content = new DummyNonTextContent("not a TextContent");

        $this->setMwGlobals([
            "wgExtraNamespaces" => [
                12314 => "DummyNonText",
                12315 => "DummyNonTextTalk",
            ],
            "wgNamespaceContentModels" => [
                12314 => "testing-nontext",
            ],
        ]);
        $this->mergeMwGlobalArrayValue("wgContentHandlers", [
            "testing-nontext" => DummyNonTextContentHandler::class,
        ]);
        $this->title = Title::makeTitle(12314, "UTPage-" . rand(0, 100000));

        $status = $this->makeEdit($content);
        $this->assertTrue(
            $status->isGood(),
            "Moderation shouldn't have intercepted non-text Content, " .
                "but doEditContent() didn't return successful Status.",
        );

        // This edit shouldn't have been queued for moderation.
        $this->assertNotIntercepted($manager);
    }

    /**
     * Verify that edits in namespace of Extension:CommentStreams will bypass moderation.
     * @covers MediaWiki\Moderation\ModerationEditHooks::onMultiContentSave
     */
    public function testEditCommentStreams()
    {
        $excludedNamespace = 4; // Arbitrary namespace number
        $this->setMwGlobals(
            "wgCommentStreamsNamespaceIndex",
            $excludedNamespace,
        );

        // Replace real ConsequenceManager with a mock.
        $manager = $this->mockConsequenceManager();

        $this->title = Title::makeTitle(
            $excludedNamespace,
            "UTPage-" . rand(0, 100000),
        );
        $status = $this->makeEdit();
        $this->assertTrue(
            $status->isOK(),
            "Edit in CommentStreams namespace has failed.",
        );

        // This edit shouldn't have been queued for moderation.
        $this->assertNotIntercepted($manager);
    }

    /**
     * Test consequences when moderator saves a manually merged edit (resolving an edit conflict).
     * @covers MediaWiki\Moderation\ModerationEditHooks::onPageSaveComplete
     */
    public function testMergedEdit()
    {
        // Replace real ConsequenceManager with a mock.
        $manager = $this->mockConsequenceManager();

        $modid = 12345;
        RequestContext::getMain()->getRequest()->setVal("wpMergeID", $modid);

        $this->user = self::getTestUser([
            "moderator",
            "automoderated",
        ])->getUser();
        $manager->mockResult(MarkAsMergedConsequence::class, true);

        $status = $this->makeEdit();
        $this->assertTrue($status->isOK(), "Failed to save an edit.");

        // @phan-suppress-next-line PhanTypeArraySuspiciousNullable
        $rev = $status->value["revision-record"] ?? $status->value["revision"];
        $revid = $rev->getId();

        $this->assertConsequencesEqual(
            [
                new MarkAsMergedConsequence($modid, $revid),
                new AddLogEntryConsequence("merge", $this->user, $this->title, [
                    "modid" => $modid,
                    "revid" => $revid,
                ]),
                new InvalidatePendingTimeCacheConsequence(),
                new TagRevisionAsMergedConsequence($revid),
            ],
            $manager->getConsequences(),
        );
    }

    /**
     * Test consequences when moderator tries to manually merge, but it results in a null edit.
     * @covers MediaWiki\Moderation\ModerationEditHooks::onPageSaveComplete
     */
    public function testMergedNullEdit()
    {
        $this->user = self::getTestUser([
            "moderator",
            "automoderated",
        ])->getUser();

        // Cause a null edit by precreating the page with the same text.
        $text = "Sample text " . rand(0, 1000000);
        $content = ContentHandler::makeContent(
            $text,
            null,
            CONTENT_MODEL_WIKITEXT,
        );

        $status = $this->makeEdit($content);
        $this->assertTrue($status->isOK(), "Failed to save an edit.");

        RequestContext::getMain()->getRequest()->setVal("wpMergeID", 12345);

        // Replace real ConsequenceManager with a mock.
        $manager = $this->mockConsequenceManager();

        $status = $this->makeEdit($content);
        $this->assertTrue($status->isOK(), "Failed to save an edit.");
        $this->assertTrue(
            $status->hasMessage("edit-no-change"),
            "Not a null edit.",
        );

        $this->assertNoConsequences($manager);
    }

    /**
     * Test consequences of 1) editing a section, 2) "Watch this page" checkbox being (un)checked.
     * @covers MediaWiki\Moderation\ModerationEditHooks::onMultiContentSave
     */
    public function testSectionEditAndWatchthis()
    {
        $section = "2"; // Section is a string (not integer), because it can be "new", etc.
        $sectionText = "New text of section #2";
        $fullText =
            "Text #0\n== Section 1 ==\nText #1\n\n " .
            "== Section 2 ==\nText #2\n\n== Section 3 ==\nText #3";

        $content = ContentHandler::makeContent(
            $fullText,
            null,
            CONTENT_MODEL_WIKITEXT,
        );

        $editFormOptions = $this->createMock(EditFormOptions::class);
        $editFormOptions
            ->expects($this->once())
            ->method("watchIfNeeded")
            ->will(
                $this->returnCallback(function ($user, array $titles) {
                    $this->assertSame($this->user, $user);
                    $this->assertCount(1, $titles);
                    $this->assertTrue($titles[0]->isSameLinkAs($this->title));
                }),
            );
        $editFormOptions
            ->expects($this->once())
            ->method("getSection")
            ->willReturn($section);
        $editFormOptions
            ->expects($this->once())
            ->method("getSectionText")
            ->willReturn($sectionText);
        $this->setService("Moderation.EditFormOptions", $editFormOptions);

        // Replace real ConsequenceManager with a mock.
        $manager = $this->mockConsequenceManager();

        $page = ModerationCompatTools::makeWikiPage($this->title);
        $this->makeEdit($content);

        $this->assertConsequencesEqual(
            [
                new QueueEditConsequence(
                    $page,
                    $this->user,
                    $content,
                    $this->summary,
                    $section,
                    $sectionText,
                    false, // isBot
                    false, // isMinor
                ),
            ],
            $manager->getConsequences(),
        );
    }

    /**
     * Perform one edit that will be queued for moderation. (for use in different tests)
     * Unlike MakeEditTestTrait::makeEdit(), this doesn't throw exception if Status is unsuccessful,
     * because "moderation-edit-queued" is a perfectly acceptable non-success status in this test.
     * @param Content|null $newContent Optional, as most tests don't depend on what text is added.
     * @return Status
     */
    private function makeEdit(Content $newContent = null)
    {
        $this->content =
            $newContent ??
            ContentHandler::makeContent(
                "Some text",
                null,
                CONTENT_MODEL_WIKITEXT,
            );
        $this->summary = "Default edit summary for this test";

        $page = ModerationCompatTools::makeWikiPage($this->title);

        $updater = $page->newPageUpdater($this->user);
        $updater->setContent(SlotRecord::MAIN, $this->content);
        $updater->saveRevision(
            CommentStoreComment::newUnsavedComment($this->summary),
            EDIT_INTERNAL,
        );

        return $updater->getStatus();
    }

    /**
     * Throw an exception if an edit was intercepted by Moderation.
     * @param MockConsequenceManager $manager
     */
    private function assertNotIntercepted(MockConsequenceManager $manager)
    {
        $this->assertNoConsequences($manager);
        $this->assertStringNotContainsString(
            "modqueued",
            RequestContext::getMain()->getOutput()->getRedirect(),
            "Redirect URL shouldn't contain modqueued= when the moderation was skipped.",
        );
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->user = self::getTestUser()->getUser();
        $this->title = Title::newFromText("UTPage-" . rand(0, 100000));
    }
}
