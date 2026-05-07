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
 * Unit test of ModerationPreload.
 */

namespace MediaWiki\Moderation\Tests;

use Article;
use MediaWiki\EditPage\EditPage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Moderation\EntryFactory;
use MediaWiki\Moderation\ModerationPreload;
use MediaWiki\Moderation\PendingEdit;
use MediaWiki\Moderation\RememberAnonIdConsequence;
use MediaWiki\Title\Title;
use RequestContext;
use User;
use Wikimedia\IPUtils;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class ModerationPreloadTest extends ModerationUnitTestCase
{
    /**
     * Verify that getId() returns correct values for both logged-in and anonymous users.
     * @param string|false $expectedResult Value that getId() should return.
     * @param string $username
     * @param string $existingAnonId Value of "anon_id" field in SessionData.
     * @param bool $create This parameter is passed to getId().
     * @dataProvider dataProviderGetId
     *
     * @covers MediaWiki\Moderation\ModerationPreload
     */
    public function testGetId(
        $expectedResult,
        $username,
        $existingAnonId,
        $create,
    ) {
        RequestContext::getMain()
            ->getRequest()
            ->setSessionData("anon_id", $existingAnonId);

        $entryFactory = $this->createMock(EntryFactory::class);
        $user = $this->createMock(User::class);

        $manager = new MockConsequenceManager();
        $expectedConsequences = [];

        if (IPUtils::isIPAddress($username)) {
            $this->disableAutoCreateTempUser();

            $user
                ->expects($this->once())
                ->method("isRegistered")
                ->willReturn(false);
            $user->expects($this->never())->method("getName");

            if (!$existingAnonId && $create) {
                $manager->mockResult(
                    RememberAnonIdConsequence::class,
                    "NewlyGeneratedAnonId",
                );
                $expectedConsequences = [new RememberAnonIdConsequence()];
            }
        } else {
            $user
                ->expects($this->once())
                ->method("isRegistered")
                ->willReturn(true);
            $user
                ->expects($this->once())
                ->method("getName")
                ->willReturn($username);
        }

        '@phan-var EntryFactory $entryFactory';
        '@phan-var User $user';

        $preload = new ModerationPreload($entryFactory, $manager);

        $preload->setUser($user);
        $preloadId = $preload->getId($create);

        $this->assertSame(
            $expectedResult,
            $preloadId,
            "Result of getId() doesn't match expected.",
        );
        $this->assertConsequencesEqual(
            $expectedConsequences,
            $manager->getConsequences(),
        );
    }

    /**
     * Provide datasets for testGetId() runs.
     * @return array
     */
    public function dataProviderGetId()
    {
        return [
            "Logged-in user, create=false" => [
                "[Test user",
                "Test user",
                null,
                false,
            ],
            "Logged-in user, create=true" => [
                "[Test user",
                "Test user",
                null,
                true,
            ],
            "Anonymous user, AnonId already exists in the session, create=false" => [
                "]ExistingAnonId",
                "127.0.0.1",
                "ExistingAnonId",
                false,
            ],
            "Anonymous user, AnonId already exists in the session, create=true" => [
                "]ExistingAnonId",
                "127.0.0.1",
                "ExistingAnonId",
                true,
            ],
            "Anonymous user, no AnonId in the session, create=false" => [
                false,
                "127.0.0.1",
                null,
                false,
            ],
            "Anonymous user, no AnonId in the session, create=true" => [
                "]NewlyGeneratedAnonId",
                "127.0.0.1",
                null,
                true,
            ],
        ];
    }

    /**
     * Verify that getId() will use main RequestContext if setUser() was never called.
     * @covers MediaWiki\Moderation\ModerationPreload
     */
    public function testGetIdMainContext()
    {
        $entryFactory = $this->createMock(EntryFactory::class);
        $manager = new MockConsequenceManager();

        $user = $this->createMock(User::class);
        $user->expects($this->once())->method("isRegistered")->willReturn(true);
        $user
            ->expects($this->once())
            ->method("getName")
            ->willReturn("Global User");

        '@phan-var EntryFactory $entryFactory';
        '@phan-var User $user';

        // Place $user into the global RequestContext.
        RequestContext::getMain()->setUser($user);

        $preload = new ModerationPreload($entryFactory, $manager);
        $preloadId = $preload->getId();

        $this->assertSame(
            "[Global User",
            $preloadId,
            "Result of getId() doesn't match expected.",
        );
        $this->assertNoConsequences($manager);
    }

    /**
     * Verify that findPendingEdit() returns expected PendingEdit object.
     * @covers MediaWiki\Moderation\ModerationPreload
     */
    public function testFindPendingEdit()
    {
        RequestContext::getMain()
            ->getRequest()
            ->setSessionData("anon_id", "ExistingAnonId");
        $title = Title::newFromText("UTPage-" . rand(0, 100000));

        $entryFactory = $this->createMock(EntryFactory::class);
        $manager = new MockConsequenceManager();

        $entryFactory
            ->expects($this->once())
            ->method("findPendingEdit")
            ->with(
                $this->identicalTo("]ExistingAnonId"),
                $this->identicalTo($title),
            )
            ->willReturn("{MockedResultFromFactory}");

        '@phan-var EntryFactory $entryFactory';

        $preload = new ModerationPreload($entryFactory, $manager);
        $pendingEdit = $preload->findPendingEdit($title);

        $this->assertSame(
            "{MockedResultFromFactory}",
            $pendingEdit,
            "Result of findPendingEdit() doesn't match expected.",
        );
        $this->assertNoConsequences($manager);
    }

    /**
     * Verify that findPendingEdit will return false if current user doesn't have an existing AnonId.
     * @covers MediaWiki\Moderation\ModerationPreload
     */
    public function testNoPendingEdit()
    {
        $title = Title::newFromText("UTPage-" . rand(0, 100000));
        $manager = new MockConsequenceManager();

        $entryFactory = $this->createMock(EntryFactory::class);
        $entryFactory->expects($this->never())->method("findPendingEdit");

        '@phan-var EntryFactory $entryFactory';

        $preload = new ModerationPreload($entryFactory, $manager);
        $pendingEdit = $preload->findPendingEdit($title);

        $this->assertFalse(
            $pendingEdit,
            "findPendingEdit() should return false for anonymous users who haven't edited.",
        );
        $this->assertNoConsequences($manager);
    }

    /**
     * Ensure that EditFormPreloadText hook correctly preloads text/comment of PendingEdit.
     * This happens when user is creating a new article via the UI (action=edit).
     * @covers MediaWiki\Moderation\ModerationPreload
     */
    public function testNewArticlePreloadHook()
    {
        [$title, $preloadedText, $preloadedComment] = $this->beginShowTest();
        $origTitle = clone $title;

        // Because EditFormPreloadText hook doesn't receive EditPage object as parameter, EditPage
        // is instead remembered in AlternateEdit hook, which is called before EditFormPreloadText.
        // See testNewArticlePreloadHookNoEditPage() below for situation when this doesn't happen.
        $editPage = new EditPage(new Article($title));
        $hookContainer = MediaWikiServices::getInstance()->getHookContainer();
        $hookResult = $hookContainer->run("AlternateEdit", [&$editPage]);
        $this->assertTrue(
            $hookResult,
            "Handler of AlternateEdit hook should return true.",
        );

        // Call the tested hook.
        $text = "Unmodified text";
        $hookResult = $hookContainer->run("EditFormPreloadText", [
            &$text,
            &$title,
        ]);
        $this->assertTrue(
            $hookResult,
            "Handler of EditFormPreloadText hook should return true.",
        );
        $this->assertSame(
            $preloadedText,
            $text,
            "Text wasn't modified by EditFormPreloadText hook.",
        );
        $this->assertSame(
            $preloadedComment,
            $editPage->summary,
            "Edit summary wasn't preloaded into EditPage by EditFormPreloadText hook.",
        );
        $this->assertSame(
            $origTitle->getFullText(),
            $title->getFullText(),
            "Title shouldn't have been modified by EditFormPreloadText hook.",
        );

        $this->checkOutputAfterShowTest();
    }

    /**
     * Ensure that EditFormPreloadText hook works even if AlternateEdit hook wasn't called.
     * This doesn't happen when editing normally via UI, but it is possible in ApiQueryInfo, etc.
     * @see testNewArticlePreloadHook() - checks the situation when AlternateEdit hook is called.
     * @covers MediaWiki\Moderation\ModerationPreload
     */
    public function testNewArticlePreloadHookNoEditPage()
    {
        [$title, $preloadedText] = $this->beginShowTest();
        $origTitle = clone $title;

        // Call the tested hook.
        $text = "Unmodified text";
        $hookContainer = MediaWikiServices::getInstance()->getHookContainer();
        $hookResult = $hookContainer->run("EditFormPreloadText", [
            &$text,
            &$title,
        ]);
        $this->assertTrue(
            $hookResult,
            "Handler of EditFormPreloadText hook should return true.",
        );
        $this->assertSame(
            $preloadedText,
            $text,
            "Text wasn't modified by EditFormPreloadText hook.",
        );
        $this->assertSame(
            $origTitle->getFullText(),
            $title->getFullText(),
            "Title shouldn't have been modified by EditFormPreloadText hook.",
        );

        $this->checkOutputAfterShowTest();
    }

    /**
     * Ensure that onEditFormInitialText hook correctly preloads text/comment of PendingEdit.
     * This happens when user is editing an existing article via the UI (action=edit).
     * @covers MediaWiki\Moderation\ModerationPreload
     */
    public function testExistingArticlePreloadHook()
    {
        [$title, $preloadedText, $preloadedComment] = $this->beginShowTest();
        $editPage = new EditPage(new Article($title));

        // Call the tested hook.
        $hookContainer = MediaWikiServices::getInstance()->getHookContainer();
        $hookResult = $hookContainer->run("EditFormInitialText", [$editPage]);
        $this->assertTrue(
            $hookResult,
            "Handler of EditFormInitialText hook should return true.",
        );
        $this->assertSame(
            $preloadedText,
            $editPage->textbox1,
            "Text wasn't modified by EditFormInitialText hook.",
        );
        $this->assertSame(
            $preloadedComment,
            $editPage->summary,
            "Edit summary wasn't preloaded into EditPage by EditFormInitialText hook.",
        );
        $this->assertSame(
            $title->getFullText(),
            $editPage->getTitle()->getFullText(),
            "Title shouldn't have been modified by EditFormInitialText hook.",
        );

        $this->checkOutputAfterShowTest();
    }

    /**
     * Ensure that onEditFormInitialText hook correctly handles "section=NUMBER" parameter.
     * This happens when user is editing one section of existing article via the UI.
     * @covers MediaWiki\Moderation\ModerationPreload
     */
    public function testEditSectionPreloadHook()
    {
        $sectionId = 2;
        [$title, $preloadedText] = $this->beginShowTest(false, $sectionId);
        $editPage = new EditPage(new Article($title));

        // Call the tested hook.
        $hookContainer = MediaWikiServices::getInstance()->getHookContainer();
        $hookResult = $hookContainer->run("EditFormInitialText", [$editPage]);
        $this->assertTrue(
            $hookResult,
            "Handler of EditFormInitialText hook should return true.",
        );
        $this->assertSame(
            $preloadedText,
            $editPage->textbox1,
            "Text in section=$sectionId doesn't match the text that should have been preloaded.",
        );

        $this->checkOutputAfterShowTest();
    }

    /**
     * Check situation when EditFormPreloadText hook doesn't find a PendingEdit (nothing to preload).
     * @covers MediaWiki\Moderation\ModerationPreload
     */
    public function testNothingToPreloadNewArticleHook()
    {
        [$title] = $this->beginShowTest(true);
        $origTitle = clone $title;
        $origText = $text = "Original text " . rand(0, 100000);

        // Call the tested hook.
        $hookContainer = MediaWikiServices::getInstance()->getHookContainer();
        $hookResult = $hookContainer->run("EditFormPreloadText", [
            &$text,
            &$title,
        ]);
        $this->assertTrue(
            $hookResult,
            "Handler of EditFormPreloadText hook should return true.",
        );
        $this->assertSame(
            $origText,
            $text,
            "Text shouldn't have be modified when PendingEdit doesn't exist.",
        );
        $this->assertSame(
            $origTitle->getFullText(),
            $title->getFullText(),
            "Title shouldn't have been modified by EditFormPreloadText hook.",
        );

        $this->checkOutputAfterNothingToShowTest();
    }

    /**
     * Check situation when onEditFormInitialText hook doesn't find a PendingEdit (nothing to preload).
     * @covers MediaWiki\Moderation\ModerationPreload
     */
    public function testNothingToPreloadExistingArticleHook()
    {
        [$title] = $this->beginShowTest(true);
        $editPage = new EditPage(new Article($title));

        $origText = $editPage->textbox1 = "Original summary " . rand(0, 100000);
        $origSummary = $editPage->summary =
            "Original summary " . rand(0, 100000);

        // Call the tested hook.
        $hookContainer = MediaWikiServices::getInstance()->getHookContainer();
        $hookResult = $hookContainer->run("EditFormInitialText", [$editPage]);
        $this->assertTrue(
            $hookResult,
            "Handler of EditFormInitialText hook should return true.",
        );
        $this->assertSame(
            $origText,
            $editPage->textbox1,
            "Text shouldn't have be modified when PendingEdit doesn't exist.",
        );
        $this->assertSame(
            $origSummary,
            $editPage->summary,
            "Edit summary shouldn't have be modified when PendingEdit doesn't exist.",
        );
        $this->assertSame(
            $title->getFullText(),
            $editPage->getTitle()->getFullText(),
            "Title shouldn't have been modified by EditFormInitialText hook.",
        );

        $this->checkOutputAfterNothingToShowTest();
    }

    /**
     * Begin the test of Preload hooks: create ModerationPreload object and set it as a service,
     * and have its EntryFactory return the mocked PendingEdit object.
     * @param bool $notFound If true, PendingEdit won't be found and false will be returned instead.
     * @param string|int $sectionId
     * @return array
     * @phan-return array{0:Title,1:string,2:string} Title, preload text and preloaded comment.
     */
    private function beginShowTest($notFound = false, $sectionId = "")
    {
        if ($sectionId) {
            RequestContext::getMain()
                ->getRequest()
                ->setVal("section", $sectionId);
        }

        $title = Title::newFromText("UTPage-" . rand(0, 100000));
        $text = "Preloaded text " . rand(0, 100000);
        $comment = "Preloaded comment" . rand(0, 100000);

        $pendingEdit = false;
        if (!$notFound) {
            $pendingEdit = $this->createMock(PendingEdit::class);
            $pendingEdit
                ->expects($this->once())
                ->method("getSectionText")
                ->with($this->equalTo($sectionId))
                ->willReturn($text);

            $pendingEdit
                ->expects($this->any())
                ->method("getComment")
                ->willReturn($comment);
        }

        $mainContext = RequestContext::getMain();
        $mainContext->getRequest()->setSessionData("anon_id", "ExistingAnonId");
        $mainContext->setLanguage("qqx");

        $entryFactory = $this->createMock(EntryFactory::class);
        $entryFactory
            ->expects($this->once())
            ->method("findPendingEdit")
            ->with(
                $this->identicalTo("]ExistingAnonId"),
                $this->identicalTo($title),
            )
            ->willReturn($pendingEdit);

        '@phan-var EntryFactory $entryFactory';

        // Install this ModerationPreload object (which we are testing) as a service.
        $preload = new ModerationPreload(
            $entryFactory,
            new MockConsequenceManager(),
        );
        $this->setService("Moderation.Preload", $preload);

        return [$title, $text, $comment];
    }

    /**
     * Assert that "You are editing your version of this article" message was added to OutputPage.
     */
    private function checkOutputAfterShowTest()
    {
        $out = RequestContext::getMain()->getOutput();
        $this->assertSame(["ext.moderation.edit"], $out->getModules());
        $this->assertStringContainsString(
            '<div id="mw-editing-your-version">(moderation-editing-your-version)</div>',
            $out->getHTML(),
        );
    }

    /**
     * Assert that "You are editing your version of this article" message wasn't added to OutputPage.
     */
    private function checkOutputAfterNothingToShowTest()
    {
        $out = RequestContext::getMain()->getOutput();
        $this->assertNotContains("ext.moderation.edit", $out->getModules());
        $this->assertStringNotContainsString(
            "(moderation-editing-your-version)",
            $out->getHTML(),
        );
    }

    /**
     * Ensure that EditFormPreloadText hook skips preloading if Request contains "section=new".
     * @covers MediaWiki\Moderation\ModerationPreload
     */
    public function testPreloadingSkippedForNewSection()
    {
        RequestContext::getMain()->getRequest()->setVal("section", "new");

        // In case of section=new there shouldn't even be any search for PendingEdit.
        $entryFactory = $this->createMock(EntryFactory::class);
        $entryFactory->expects($this->never())->method("findPendingEdit");

        '@phan-var EntryFactory $entryFactory';

        // Install this ModerationPreload object (which we are testing) as a service.
        $preload = new ModerationPreload(
            $entryFactory,
            new MockConsequenceManager(),
        );
        $this->setService("Moderation.Preload", $preload);

        $text = "old text";
        $title = Title::newFromText("Whatever");
        $editPage = new EditPage(new Article($title));

        // Call the tested hooks.
        $hookContainer = MediaWikiServices::getInstance()->getHookContainer();
        $hookResult = $hookContainer->run("EditFormPreloadText", [
            &$text,
            &$title,
        ]);
        $this->assertTrue(
            $hookResult,
            "Handler of EditFormPreloadText hook should return true.",
        );

        $hookResult = $hookContainer->run("EditFormInitialText", [$editPage]);
        $this->assertTrue(
            $hookResult,
            "Handler of EditFormInitialText hook should return true.",
        );
    }
}
