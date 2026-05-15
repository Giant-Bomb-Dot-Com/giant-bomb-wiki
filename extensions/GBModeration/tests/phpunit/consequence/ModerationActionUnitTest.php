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
 * Unit test of ModerationAction.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\MediaWikiServices;
use MediaWiki\Moderation\EntryFactory;
use MediaWiki\Moderation\ModerationAction;
use MediaWiki\Title\Title;
use Profiler;
use ReadOnlyError;
use RequestContext;
use SpecialPage;
use Wikimedia\TestingAccessWrapper;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class ModerationActionUnitTest extends ModerationUnitTestCase
{
    use MockLinkRendererTrait;
    use MockLoadRowTestTrait;

    /**
     * Test that ModerationAction::run() does all the necessary preparations and then calls execute().
     * @param bool $requiresWrite True to test non-readonly action, false to test readonly action.
     * @param bool $simulateReadOnlyMode If true, the wiki will be ReadOnly during this test.
     * @dataProvider dataProviderActionRun
     *
     * @covers MediaWiki\Moderation\ModerationAction
     */
    public function testActionRun($requiresWrite, $simulateReadOnlyMode)
    {
        if ($simulateReadOnlyMode) {
            MediaWikiServices::getInstance()
                ->getService("ReadOnlyMode")
                ->setReason("for some reason");
        }

        $profiler = Profiler::instance()->getTransactionProfiler();
        $profiler->resetExpectations();

        $expectedResult = ["key1" => "value1", "something" => "else"];
        $expectReadOnlyError = $requiresWrite && $simulateReadOnlyMode;

        $mock = $this->getModerationActionMock();
        $mock
            ->expects($this->once())
            ->method("requiresWrite")
            ->willReturn($requiresWrite);

        if ($expectReadOnlyError) {
            $mock->expects($this->never())->method("execute");
        } else {
            $mock
                ->expects($this->once())
                ->method("execute")
                ->willReturn($expectedResult);
        }

        $readOnlyErrorThrown = false;
        $result = null;
        try {
            // @phan-suppress-next-line PhanUndeclaredMethod
            $result = $mock->run();
        } catch (ReadOnlyError $_) {
            $readOnlyErrorThrown = true;
        }

        if ($expectReadOnlyError) {
            $this->assertTrue(
                $readOnlyErrorThrown,
                "ReadOnlyError wasn't thrown by non-readonly action in readonly mode.",
            );
        } else {
            $this->assertFalse(
                $readOnlyErrorThrown,
                "Unexpected ReadOnlyError.",
            );
            $this->assertSame(
                $expectedResult,
                $result,
                "ModerationAction::run() didn't return the result of execute()",
            );
        }

        // Verify actions that require write are modifying expectations of $profiler,
        // and that expectations are unchanged after readonly actions.
        // Because we called resetExpectations() above, "unchanged" means "INF for everything".
        $profilerWrapper = TestingAccessWrapper::newFromObject($profiler);
        $differentExpectations = array_unique(
            array_values($profilerWrapper->expect),
            SORT_REGULAR,
        );

        $unchanged = [[INF, null]];
        if ($requiresWrite && !$simulateReadOnlyMode) {
            $this->assertNotEquals(
                $unchanged,
                $differentExpectations,
                "TransactionProfiler expectations weren't set by non-readonly action.",
            );
        } else {
            $this->assertSame(
                $unchanged,
                $differentExpectations,
                "TransactionProfiler expectations were changed when they shouldn't have been.",
            );
        }
    }

    /**
     * Make a mock for ModerationAction class (which is an abstract class).
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function getModerationActionMock()
    {
        $mock = $this->getMockBuilder(ModerationAction::class)
            ->disableOriginalConstructor()
            ->onlyMethods(["requiresWrite", "execute"])
            ->getMockForAbstractClass();

        // Since we are not calling the constructor (which sets ReadOnlyMode via dependency injection),
        // we must provide ReadOnlyMode object here.
        $wrapper = TestingAccessWrapper::newFromObject($mock);
        $wrapper->readOnlyMode = MediaWikiServices::getInstance()->getReadOnlyMode();

        return $mock;
    }

    /**
     * Test ModerationAction::getUserpageOfPerformer() returns correct userpage when edit is found.
     * @covers MediaWiki\Moderation\ModerationAction::getUserpageOfPerformer()
     */
    public function testUserpageOfPerformer()
    {
        $username = "Test user " . rand(0, 100000);
        $modid = 12345;

        // Mock the EntryFactory service before trying formatResult().
        $entryFactory = $this->mockLoadRow(
            $modid,
            ["mod_user_text AS user_text"],
            (object) ["user_text" => $username],
        );

        $mockWrapper = TestingAccessWrapper::newFromObject(
            $this->getModerationActionMock(),
        );
        $mockWrapper->id = $modid;
        $mockWrapper->entryFactory = $entryFactory;

        $title = $mockWrapper->getUserpageOfPerformer();

        $this->assertInstanceof(Title::class, $title);
        $this->assertSame("User:$username", $title->getFullText());
    }

    /**
     * Test ModerationAction::getUserpageOfPerformer() returns false when edit is NOT found.
     * @covers MediaWiki\Moderation\ModerationAction::getUserpageOfPerformer()
     */
    public function testUserpageOfPerformerNotFound()
    {
        $entryFactory = $this->createMock(EntryFactory::class);
        $entryFactory
            ->expects($this->once())
            ->method("loadRow")
            ->willReturn(false);

        $mockWrapper = TestingAccessWrapper::newFromObject(
            $this->getModerationActionMock(),
        );
        $mockWrapper->id = 12345;
        $mockWrapper->entryFactory = $entryFactory;

        $this->assertFalse($mockWrapper->getUserpageOfPerformer());
    }

    /**
     * Provide datasets for testActionRun() runs.
     * @return array
     */
    public function dataProviderActionRun()
    {
        return [
            "readonly action (wiki NOT in readonly mode)" => [false, false],
            "readonly action (wiki in readonly mode)" => [false, true],
            "non-readonly action (wiki NOT in readonly mode)" => [true, false],
            "non-readonly action (wiki in readonly mode)" => [true, true],
        ];
    }

    /**
     * Test that "Return to Page" links are printed when needed.
     * @param string ...$pageNames List of titles that will be passed to addReturnTitle().
     * @dataProvider dataProviderReturnLinks
     *
     * @covers MediaWiki\Moderation\ModerationAction::addReturnTitle()
     * @covers MediaWiki\Moderation\ModerationAction::printReturnLinks()
     *
     */
    public function testReturnLinks(...$pageNames)
    {
        $mock = $this->getModerationActionMock();

        '@phan-var ModerationAction $mock';

        $mockWrapper = TestingAccessWrapper::newFromObject($mock);
        $this->assertCount(0, $mockWrapper->returnTitles);

        $mockedLinks = [];
        foreach ($pageNames as $pageName) {
            $title = Title::newFromText($pageName);
            $mock->addReturnTitle($title);

            $mockedLinks["{MockedReturnTo.$pageName}"] = $title;
        }

        $this->mockLinkRenderer(
            array_merge(
                [
                    "{MockedReturnToSpecialModeration}" => SpecialPage::getTitleFor(
                        "Moderation",
                    ),
                ],
                $mockedLinks,
            ),
        );

        $this->assertCount(
            count($mockedLinks),
            $mockWrapper->returnTitles,
            "Unexpected number of return links.",
        );

        // Verify that page was added to "Return to" links.
        $context = new RequestContext();
        $context->setLanguage(ModerationTestUtil::getLanguageQqx());
        $out = $context->getOutput();
        $mock->printReturnLinks($out);

        $expectedHtml =
            "<p id=\"mw-returnto\">(returnto: {MockedReturnToSpecialModeration})</p>\n";
        foreach (array_keys($mockedLinks) as $linkHtml) {
            $expectedHtml .= "<p class=\"mw-returnto-extra\">(returnto: $linkHtml)</p>\n";
        }

        $this->assertSame(
            $expectedHtml,
            $out->getHTML(),
            'Output of printReturnLinks() doesn\'t contain expected links.',
        );
    }

    /**
     * Provide datasets for testReturnLinks() runs.
     * @return array
     */
    public function dataProviderReturnLinks()
    {
        return [
            "no return links" => [],
            "1 return link" => ["Project:Some page"],
            "2 return links" => ["Some page", "Category:Some category"],
            "3 return links" => ["Talk:A", "B", "User:C"],
        ];
    }
}
