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
 * Unit test of ApiQueryModerationPreload.
 */

namespace MediaWiki\Moderation\Tests;

use ApiMain;
use ApiQuery;
use ApiQueryBase;
use ApiTestCase;
use MediaWiki\Moderation\ApiQueryModerationPreload;
use MediaWiki\Moderation\ModerationPreload;
use MediaWiki\Moderation\PendingEdit;
use MediaWiki\Title\Title;
use Wikimedia\TestingAccessWrapper;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 * @group medium
 */
class ApiQueryModerationPreloadTest extends ApiTestCase
{
    /**
     * Verify the result of api.php?action=query&prop=moderationpreload.
     * @param bool $notFound If true, PendingEdit won't be found and false will be returned instead.
     * @param array $extraParams
     * @dataProvider dataProviderPreload
     *
     * @covers MediaWiki\Moderation\ApiQueryModerationPreload
     */
    public function testPreload($notFound, array $extraParams)
    {
        $user = self::getTestUser()->getUser();
        $title = Title::newFromText("Talk:UTPage " . rand(0, 100000));
        $text = "Preloaded text " . rand(0, 100000);
        $comment = "Preloaded comment" . rand(0, 100000);
        $articleId = $this->getExistingTestPage($title)->getId();

        $pendingEdit = false;
        if (!$notFound) {
            $pendingEdit = $this->createMock(PendingEdit::class);
            $pendingEdit
                ->expects($this->once())
                ->method("getSectionText")
                ->with($this->equalTo($extraParams["mpsection"] ?? ""))
                ->willReturn($text);

            $pendingEdit
                ->expects($this->any())
                ->method("getComment")
                ->willReturn($comment);
        }

        // Mock ModerationPreload service.
        $preload = $this->createMock(ModerationPreload::class);
        $preload
            ->expects($this->any())
            ->method("findPendingEdit")
            ->will(
                $this->returnCallback(function ($target) use (
                    $title,
                    $pendingEdit,
                ) {
                    $this->assertSame(
                        $title->getFullText(),
                        $target->getFullText(),
                    );
                    return $pendingEdit;
                }),
            );
        $this->setService("Moderation.Preload", $preload);

        $query = $extraParams + [
            "action" => "query",
            "prop" => "moderationpreload",
            "mptitle" => $title->getFullText(),
        ];
        [$result] = $this->doApiRequest($query, null, false, $user);

        $this->assertArrayHasKey("query", $result);
        $this->assertArrayHasKey("moderationpreload", $result["query"]);

        $actualResult = $result["query"]["moderationpreload"];
        $actualDisplayTitle = $actualResult["parsed"]["displaytitle"] ?? null;
        if ($actualDisplayTitle) {
            // MediaWiki adds tags like <span class="mw-page-title-namespace">,
            // we don't need to check these tags here.
            $actualResult["parsed"]["displaytitle"] = strip_tags(
                $actualDisplayTitle,
            );
        }

        $expectedResult = [
            "user" => $user->getName(),
            "title" => $title->getFullText(),
            "pageid" => $articleId,
        ];
        if ($notFound) {
            $expectedResult["missing"] = "";
        } else {
            if (($extraParams["mpmode"] ?? "wikitext") === "wikitext") {
                $expectedResult["wikitext"] = $text;
            } else {
                $expectedText =
                    '<div class="mw-content-ltr mw-parser-output" lang="en" dir="ltr"><p>' .
                    "$text\n</p></div>";

                $expectedResult["parsed"] = [
                    "text" => $expectedText,
                    "categorieshtml" =>
                        '<div id="catlinks" class="catlinks catlinks-allhidden" ' .
                        'data-mw="interface"></div>',
                    "displaytitle" => $title->getFullText(),
                ];
            }

            $expectedResult["comment"] = $comment;
        }
        $this->assertSame(
            $expectedResult,
            $actualResult,
            "API response doesn't match expected.",
        );
    }

    /**
     * Provide datasets for testPreload() runs.
     * @return array
     */
    public function dataProviderPreload()
    {
        return [
            "PendingEdit not found" => [true, []],
            "PendingEdit not found, section=2" => [true, ["mpsection" => 2]],
            "PendingEdit not found, mpmode=parsed" => [
                true,
                ["mpmode" => "parsed"],
            ],
            "PendingEdit found" => [false, []],
            "PendingEdit found, section=2" => [false, ["mpsection" => 2]],
            "PendingEdit found, mpmode=parsed" => [
                false,
                ["mpmode" => "parsed"],
            ],
        ];
    }

    /**
     * Test that ApiQueryModerationPreload subclass overrides some methods of ApiBase class.
     * @covers MediaWiki\Moderation\ApiQueryModerationPreload
     */
    public function testApiBaseSubclass()
    {
        $preload = $this->createMock(ModerationPreload::class);
        '@phan-var ModerationPreload $preload';

        $apiMain = new ApiMain();
        $apiQuery = $apiMain->getModuleManager()->getModule("query");
        '@phan-var ApiQuery $apiQuery';

        $api = new ApiQueryModerationPreload($apiQuery, "query", $preload);

        $this->assertInstanceof(ApiQueryBase::class, $api);
        $this->assertSame("mp", $api->getModulePrefix(), "getModulePrefix");

        $allowedParams = $api->getAllowedParams();
        $this->assertArrayHasKey("mode", $allowedParams);
        $this->assertArrayHasKey("title", $allowedParams);
        $this->assertArrayHasKey("pageid", $allowedParams);
        $this->assertArrayHasKey("section", $allowedParams);

        $wrapper = TestingAccessWrapper::newFromObject($api);
        $this->assertNotEmpty(
            $wrapper->getExamplesMessages(),
            "getExamplesMessages",
        );
    }
}
