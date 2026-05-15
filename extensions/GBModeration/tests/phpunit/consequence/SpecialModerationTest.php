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
 * Unit test of SpecialModeration.
 */

namespace MediaWiki\Moderation\Tests;

use DerivativeContext;
use ErrorPageError;
use IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Moderation\EntryFactory;
use MediaWiki\Moderation\ModerationEntryFormatter;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use OutputPage;
use PermissionsError;
use QueryPage;
use RequestContext;
use Skin;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\TestingAccessWrapper;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class SpecialModerationTest extends ModerationUnitTestCase
{
    use MockModerationActionTrait;

    /**
     * Verify that Special:Moderation will throw PermissionsError for non-moderators.
     * @covers MediaWiki\Moderation\SpecialModeration
     */
    public function testNotPermitted()
    {
        $notModerator = self::getTestUser()->getUser();

        $mock = $this->addMockedAction("makesandwich");
        $mock->expects($this->never())->method("execute");

        $this->expectExceptionObject(new PermissionsError("moderation"));
        ModerationTestUtil::runSpecialModeration($notModerator, []);
    }

    /**
     * Verify that Special:Moderation will throw sessionfailure error if needed token is incorrect.
     * @param string|null $token Value of token= parameter (if any) or null.
     * @dataProvider dataProviderSessionFailure
     *
     * @covers MediaWiki\Moderation\SpecialModeration
     */
    public function testSessionFailure($token)
    {
        $moderator = self::getTestUser(["moderator"])->getUser();
        $params = ["modaction" => "makesandwich"];
        if ($token !== null) {
            $params["token"] = $token;
        }

        $mock = $this->addMockedAction($params["modaction"]);
        $mock
            ->expects($this->once())
            ->method("requiresEditToken")
            ->willReturn(true);
        $mock->expects($this->never())->method("execute");

        $this->expectExceptionObject(
            new ErrorPageError("sessionfailure-title", "sessionfailure"),
        );

        ModerationTestUtil::runSpecialModeration($moderator, $params);
    }

    /**
     * Provide datasets for testSessionFailure() runs.
     * @return array
     */
    public function dataProviderSessionFailure()
    {
        return [
            "token needed, token NOT provided" => [null],
            "token needed, provided token is INCORRECT" => ["WRONG TOKEN"],
        ];
    }

    /**
     * Verify that Special:Moderation?modaction=something runs execute() and then outputResult().
     * @param bool $isTokenNeeded If true, tested ModerationAction will require an edit token.
     * @dataProvider dataProviderSuccessfulAction
     *
     * @covers MediaWiki\Moderation\SpecialModeration
     */
    public function testSuccessfulAction($isTokenNeeded)
    {
        $moderator = self::getTestUser(["moderator"])->getUser();
        $params = ["modaction" => "makesandwich"];
        if ($isTokenNeeded) {
            $params["token"] = $moderator->getEditToken();
        }

        $mock = $this->addMockedAction($params["modaction"]);
        $mock
            ->expects($this->once())
            ->method("requiresEditToken")
            ->willReturn($isTokenNeeded);

        $mockedResult = ["cat" => "feline", "fennec fox" => "canine"];
        $mockedHtml = "This is HTML that will be returned by outputResult()";

        $mock
            ->expects($this->once())
            ->method("execute")
            ->willReturn($mockedResult);
        $mock
            ->expects($this->once())
            ->method("outputResult")
            ->with(
                $this->identicalTo($mockedResult),
                $this->isInstanceOf(OutputPage::class),
            )
            ->will(
                $this->returnCallback(static function (
                    $result,
                    OutputPage $out,
                ) use ($mockedHtml) {
                    $out->addHTML($mockedHtml);
                }),
            );
        $mock
            ->expects($this->once())
            ->method("printReturnLinks")
            ->willReturnCallback(static function (OutputPage $out) {
                $out->addHTML("{MockedReturnLinks}");
            });

        $html = ModerationTestUtil::runSpecialModeration($moderator, $params);
        $this->assertStringContainsString($mockedHtml, $html);

        // Assert that $html contains "return to Special:Moderation" link
        $this->assertStringContainsString(
            "{MockedReturnLinks}",
            $html,
            'Return links weren\'t printed.',
        );
    }

    /**
     * Provide datasets for testSuccessfulAction() runs.
     * @return array
     */
    public function dataProviderSuccessfulAction()
    {
        return [
            "token needed, provided token is correct" => [true],
            "token NOT needed" => [false],
        ];
    }

    /**
     * Test that SpecialModeration subclass overrides some methods of SpecialPage class.
     * @covers MediaWiki\Moderation\SpecialModeration
     */
    public function testSpecialPageSubclass()
    {
        $special = $this->getSpecial();

        $this->assertSame(
            "spam",
            $special->getFinalGroupName(),
            "getGroupName",
        );
        $this->assertFalse($special->isSyndicated(), "isSyndicated");
        $this->assertFalse($special->isCacheable(), "isCacheable");
    }

    /**
     * Test return value of QueryPage-related methods for different folders of Special:Moderation.
     * @param string|null $folder
     * @param array $expectedConds
     * @param array $expectedOptions
     * @dataProvider dataProviderFolders
     *
     * @covers MediaWiki\Moderation\SpecialModeration
     * @covers MediaWiki\Moderation\ModerationEntryFormatter::getQueryInfo()
     */
    public function testFolders(
        $folder,
        array $expectedConds,
        array $expectedOptions,
    ) {
        $expectedFields = ModerationEntryFormatter::getFields();
        $expectedFields[] = "mod_id AS value";

        $context = new DerivativeContext(RequestContext::getMain());
        $context->setRequest(
            new FauxRequest($folder ? ["folder" => $folder] : []),
        );
        $context->setLanguage("qqx");

        $special = $this->getSpecial();
        $special->setContext($context);

        $wrapper = TestingAccessWrapper::newFromObject($special);

        // Check getQueryInfo()
        $queryInfo = $special->getQueryInfo();

        $this->assertArrayHasKey("tables", $queryInfo);
        $this->assertSame(
            ["moderation", "moderation_block"],
            $queryInfo["tables"],
        );

        $this->assertArrayHasKey("fields", $queryInfo);
        $this->assertSame($expectedFields, $queryInfo["fields"]);

        $this->assertArrayHasKey("join_conds", $queryInfo);
        $this->assertSame(
            [
                "moderation_block" => [
                    "LEFT JOIN",
                    ["mb_address=mod_user_text"],
                ],
            ],
            $queryInfo["join_conds"],
        );

        $this->assertArrayHasKey("conds", $queryInfo);
        $this->assertSame($expectedConds, $queryInfo["conds"]);

        $this->assertArrayHasKey("options", $queryInfo);
        $this->assertSame($expectedOptions, $queryInfo["options"]);

        // Check getOrderFields()
        $this->assertSame(["mod_timestamp"], $wrapper->getOrderFields());

        // Check linkParameters()
        $expectedFolder =
            $folder == "nosuchfolder" ? "pending" : $folder ?? "pending";
        $this->assertSame(
            ["folder" => $expectedFolder],
            $wrapper->linkParameters(),
        );

        // Check getPageHeader(): it should contain (1) HTML links to other folders,
        // (2) <strong> tag with the name of current folder.
        $html = new ModerationTestHTML();
        $html->loadString($wrapper->getPageHeader());

        $links = $html->getElementsByXPath(
            '//*[@class="mw-moderation-folders"]//a',
        );
        $this->assertCount(
            3,
            $links,
            "There are 4 folders on Special:Moderation, " .
                "but the number of <a> links to other folders isn't 3.",
        );

        $allFolders = ["pending", "rejected", "merged", "spam"];
        $expectedFolderLinks = array_filter($allFolders, static function (
            $folder,
        ) use ($expectedFolder) {
            return $folder !== $expectedFolder;
        });

        foreach ($links as $link) {
            $folder = array_shift($expectedFolderLinks);

            $this->assertSame(
                "(moderation-folder-$folder)",
                $link->textContent,
            );
            $this->assertSame(
                "(tooltip-moderation-folder-$folder)",
                $link->getAttribute("title"),
            );

            $url = $link->getAttribute("href");
            $bits = ModerationTestUtil::parseUrl($url);
            $query = wfCgiToArray($bits["query"]);

            $this->assertSame(
                [
                    "title" => "Special:Moderation",
                    "folder" => $folder,
                ],
                $query,
            );
        }

        $selflink = $html->getElementByXPath(
            '//*[@class="mw-moderation-folders"]//strong[@class="selflink"]',
        );
        $this->assertNotNull($selflink);
        $this->assertSame(
            "(moderation-folder-$expectedFolder)",
            $selflink->textContent,
        );
    }

    /**
     * Provide datasets for testFolders() runs.
     * @return array
     */
    public function dataProviderFolders()
    {
        return [
            "default folder (no folder= parameter)" => [
                null,
                ["mod_rejected" => 0, "mod_merged_revid" => 0],
                [
                    "USE INDEX" => [
                        "moderation_block" => "moderation_block_address",
                        "moderation" => "moderation_folder_pending",
                    ],
                ],
            ],
            "default folder (explicit folder=pending)" => [
                "pending",
                ["mod_rejected" => 0, "mod_merged_revid" => 0],
                [
                    "USE INDEX" => [
                        "moderation_block" => "moderation_block_address",
                        "moderation" => "moderation_folder_pending",
                    ],
                ],
            ],
            "default folder (unknown value of folder= parameter)" => [
                "nosuchfolder",
                ["mod_rejected" => 0, "mod_merged_revid" => 0],
                [
                    "USE INDEX" => [
                        "moderation_block" => "moderation_block_address",
                        "moderation" => "moderation_folder_pending",
                    ],
                ],
            ],
            "folder=rejected" => [
                "rejected",
                [
                    "mod_rejected" => 1,
                    "mod_rejected_auto" => 0,
                    "mod_merged_revid" => 0,
                ],
                [
                    "USE INDEX" => [
                        "moderation_block" => "moderation_block_address",
                        "moderation" => "moderation_folder_rejected",
                    ],
                ],
            ],
            "folder=merged" => [
                "merged",
                ["mod_merged_revid <> 0"],
                [
                    "USE INDEX" => [
                        "moderation_block" => "moderation_block_address",
                        "moderation" => "moderation_folder_merged",
                    ],
                ],
            ],
            "folder=spam" => [
                "spam",
                ["mod_rejected_auto" => 1],
                [
                    "USE INDEX" => [
                        "moderation_block" => "moderation_block_address",
                        "moderation" => "moderation_folder_spam",
                    ],
                ],
            ],
        ];
    }

    /**
     * Test folder-independent behavior of SpecialModeration when showing the list of changes.
     * @param bool $useAjax
     * @dataProvider dataProviderShowChangesList
     *
     * @covers MediaWiki\Moderation\SpecialModeration::showChangesList()
     * @covers MediaWiki\Moderation\SpecialModeration::execute()
     */
    public function testShowChangesList($useAjax)
    {
        $expectedStyles = [
            "ext.moderation.special.css",
            "mediawiki.interface.helpers.styles",
        ];

        $moderator = self::getTestUser(["moderator"])->getUser();
        $this->setMwGlobals("wgModerationUseAjax", $useAjax);

        $context = null;
        $html = ModerationTestUtil::runSpecialModeration(
            $moderator,
            [],
            false,
            $context,
        );
        $this->assertStringContainsString("(moderation-text)", $html);

        $out = $context->getOutput();
        $this->assertSame($expectedStyles, $out->getModuleStyles());
        $this->assertSame(
            $useAjax ? ["ext.moderation.special.ajax"] : [],
            $out->getModules(),
        );
    }

    /**
     * Provide datasets for testShowChangesList() runs.
     * @return array
     */
    public function dataProviderShowChangesList()
    {
        return [
            '$wgModerationUseAjax=false (default)' => [false],
            '$wgModerationUseAjax=true' => [true],
        ];
    }

    /**
     * Ensure that formatResult() returns the result of ModerationEntryFormatter::getHTML().
     * @covers MediaWiki\Moderation\SpecialModeration
     */
    public function testFormatResult()
    {
        $skin = $this->createMock(Skin::class);
        $sampleRow = (object) [
            "mod_id" => 12345,
            "mod_title" => "something",
        ];
        $expectedResult = "Some returned value " . rand(0, 100000);

        $context = $this->createMock(IContextSource::class);
        '@phan-var IContextSource $context';

        // Mock the EntryFactory service before trying formatResult().
        $factory = $this->createMock(EntryFactory::class);
        $factory
            ->expects($this->once())
            ->method("makeFormatter")
            ->with($this->identicalTo($sampleRow), $this->identicalTo($context))
            ->will(
                $this->returnCallback(function () use ($expectedResult) {
                    $formatter = $this->createMock(
                        ModerationEntryFormatter::class,
                    );
                    $formatter
                        ->expects($this->once())
                        ->method("getHTML")
                        ->willReturn($expectedResult);
                    return $formatter;
                }),
            );
        $this->setService("Moderation.EntryFactory", $factory);

        '@phan-var Skin $skin';

        $special = $this->getSpecial();
        $special->setContext($context);

        // Run formatResult()
        $wrapper = TestingAccessWrapper::newFromObject($special);
        $result = $wrapper->formatResult($skin, $sampleRow);
        $this->assertSame($expectedResult, $result);
    }

    /**
     * Ensure that SpecialModeration::preprocessResults() adds all necessary pages to LinkCache.
     * @covers MediaWiki\Moderation\SpecialModeration
     * @covers MediaWiki\Moderation\ModerationEntryFormatter::addToLinkBatch
     */
    public function testPreprocessResults()
    {
        // Mock the response of DB::select()
        $res = new FakeResultWrapper([
            (object) [
                "namespace" => NS_TALK,
                "title" => "Some talkpage",
                "user" => 0,
                "rejected_by_user" => 0,
                "page2_title" => "",
            ],
            (object) [
                "namespace" => NS_MAIN,
                "title" => "Some article",
                "user" => 12345,
                "user_text" => "Username of author",
                "rejected_by_user" => 0,
                "page2_title" => "",
            ],
            (object) [
                "namespace" => NS_PROJECT,
                "title" => "Page where the edit was rejected",
                "user" => 0,
                "rejected_by_user" => 12345,
                "rejected_by_user_text" => "Username of moderator",
                "page2_title" => "",
            ],
            (object) [
                "namespace" => NS_MAIN,
                "title" => "Renamed page",
                "user" => 0,
                "rejected_by_user" => 0,
                "page2_namespace" => NS_USER,
                "page2_title" => "Sandbox/Renamed page",
            ],
        ]);
        $expectedPageNamesInCache = [
            "Talk:Some talkpage",
            "Some article",
            "User:Username of author",
            "Project:Page where the edit was rejected",
            "User:Username of moderator",
            "Renamed page",
            "User:Sandbox/Renamed page",
        ];

        $linkCache = MediaWikiServices::getInstance()->getLinkCache();
        $linkCache->clear();

        $wrapper = TestingAccessWrapper::newFromObject($this->getSpecial());
        $wrapper->preprocessResults($this->db, $res);

        // Verify that pages were added into the LinkCache.
        foreach ($expectedPageNamesInCache as $expectedBadLink) {
            $title = Title::newFromText($expectedBadLink);
            $linkCacheKey = $title->getPrefixedDBKey();

            $this->assertTrue(
                $linkCache->isBadLink($linkCacheKey),
                "Page $expectedBadLink wasn't found in LinkCache after preprocessResults().",
            );
        }

        // Verify that preprocessResults() has rewinded $res (which is an iterator).
        $resWrapper = TestingAccessWrapper::newFromObject($res);
        $this->assertSame(0, $resWrapper->currentPos);
    }

    /**
     * Returns SpecialModeration object.
     * @return QueryPage
     */
    private function getSpecial(): QueryPage
    {
        $special = MediaWikiServices::getInstance()
            ->getSpecialPageFactory()
            ->getPage("Moderation");
        '@phan-var QueryPage $special';
        return $special;
    }
}
