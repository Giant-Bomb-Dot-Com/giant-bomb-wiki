<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2018-2025 Edward Chernenko.

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
 * Unit test of ModerationLogFormatter.
 */

namespace MediaWiki\Moderation\Tests;

use DOMElement;
use ManualLogEntry;
use MediaWiki\Linker\Linker;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use SpecialPage;
use User;
use WebRequest;
use Wikimedia\TestingAccessWrapper;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class ModerationLogFormatterTest extends ModerationUnitTestCase
{
    /**
     * @covers MediaWiki\Moderation\ModerationLogFormatter
     * @dataProvider dataProvider
     */
    public function testLogFormatter(array $options)
    {
        $services = MediaWikiServices::getInstance();

        $this->setMwGlobals("wgLogRestrictions", []);
        $this->overrideConfigValue("LanguageCode", "qqx");

        $performer = self::getTestUser([
            "moderator",
            "automoderated",
        ])->getUser();
        $target = Title::newFromText($options["target"]);

        $entry = new ManualLogEntry("moderation", $options["subtype"]);
        $entry->setPerformer($performer);
        $entry->setTarget($target);
        $entry->setParameters($options["params"] ?? []);

        $formatter = $services->getLogFormatterFactory()->newFromEntry($entry);
        $html = $formatter->getActionText();

        // Check $html for validity
        $isMatched = preg_match(
            '/\(logentry-moderation-([^:]+): (.*)\)\s*$/',
            $html,
            $matches,
        );
        $this->assertSame(1, $isMatched, "Malformed log line.");

        [, $subtype, $paramLine] = $matches;
        $this->assertSame($options["subtype"], $subtype, "Incorrect subtype.");

        // Now check $paramLine for correctness
        $params = explode(", ", $paramLine);

        $this->assertSame(
            $performer->getName(),
            $params[1],
            "Incorrect performer.",
        );
        $this->assertSame(
            Linker::userLink($performer->getId(), $performer->getName()),
            $params[0],
            "Incorrect link to performer.",
        );

        // Check $params[2], which is the link to the target
        if ($options["expectTargetUserlink"] ?? false) {
            $user = User::newFromName($target->getText(), false);
            $this->assertSame(
                Linker::userLink($user->getId(), $user->getName()),
                $params[2],
                "Incorrect userlink to the target user.",
            );
        } else {
            $this->assertSame(
                $services->getLinkRenderer()->makeLink($target),
                $params[2],
                "Incorrect link to the target page.",
            );
        }

        // Check other $params (aside from the already checked 3)
        $expectedParams = $options["expectedParams"] ?? [];
        $this->assertCount(
            3 + count($expectedParams),
            $params,
            "Incorrect number of parameters in i18n message of logentry.",
        );

        foreach ($expectedParams as $idx => $expectedParam) {
            $this->assertParamHtml(
                $expectedParam,
                $params[$idx],
                $idx,
                $target,
            );
        }

        // Check preload titles
        $preloadTitles = array_map(static function (LinkTarget $linkTarget) {
            return Title::newFromLinkTarget($linkTarget)->getFullText();
        }, $formatter->getPreloadTitles());
        $this->assertSame(
            $options["expectedPreloadTitles"] ?? [],
            $preloadTitles,
            "Incorrect values returned by getPreloadTitles().",
        );
    }

    /**
     * Provide datasets for testLogFormatter() runs.
     */
    public function dataProvider()
    {
        return [
            "approve" => [
                [
                    "subtype" => "approve",
                    "target" => "Project:Some page",
                    "params" => ["revid" => 12345],
                    "expectedParams" => [
                        3 => [
                            "text" => "(moderation-log-diff: 12345)",
                            "query" => [
                                "title" => "{{TARGET}}",
                                "diff" => "12345",
                            ],
                            "tooltip" => "(tooltip-moderation-approved-diff)",
                        ],
                    ],
                ],
            ],
            "approve-move" => [
                [
                    "subtype" => "approve-move",
                    "target" => "Help:Moving pages",
                    "params" => [
                        "4::target" => "New title",
                        "user" => 500,
                        "user_text" => "Test username",
                    ],
                    "expectedParams" => [
                        3 => [
                            "pagelink" => "New title",
                        ],
                        4 => [
                            "userlink" => [500, "Test username"],
                        ],
                    ],
                    "expectedPreloadTitles" => ["New title"],
                ],
            ],
            "approveall" => [
                [
                    "subtype" => "approveall",
                    "target" => "User:Some author",
                    "expectTargetUserlink" => true,
                ],
            ],
            "reject" => [
                [
                    "subtype" => "reject",
                    "target" => "Project:Some page",
                    "params" => [
                        "modid" => 678,
                        "user" => 987,
                        "user_text" => "Some author",
                    ],
                    "expectedParams" => [
                        3 => [
                            "text" => "(moderation-log-change: 678)",
                            "query" => [
                                "title" => "Special:Moderation",
                                "modaction" => "show",
                                "modid" => "678",
                            ],
                            "tooltip" => "(tooltip-moderation-rejected-change)",
                        ],
                        4 => [
                            "userlink" => [987, "Some author"],
                        ],
                    ],
                    "expectedPreloadTitles" => ["User:Some author"],
                ],
            ],
            "reject (anonymous user)" => [
                [
                    "subtype" => "reject",
                    "target" => "Project:Some page",
                    "params" => [
                        "modid" => 678,
                        "user" => 0,
                        "user_text" => "10.11.12.13",
                    ],
                    "expectedParams" => [
                        3 => [
                            "text" => "(moderation-log-change: 678)",
                            "query" => [
                                "title" => "Special:Moderation",
                                "modaction" => "show",
                                "modid" => "678",
                            ],
                            "tooltip" => "(tooltip-moderation-rejected-change)",
                        ],
                        4 => [
                            "userlink" => [0, "10.11.12.13"],
                        ],
                    ],
                ],
            ],
            "rejectall" => [
                [
                    "subtype" => "rejectall",
                    "target" => "User:Some author",
                    "params" => [
                        "4::count" => 42,
                    ],
                    "expectTargetUserlink" => true,
                    "expectedParams" => [
                        3 => [
                            "text" => "42",
                        ],
                    ],
                ],
            ],
            "block" => [
                [
                    "subtype" => "block",
                    "target" => "User:Some author",
                    "expectTargetUserlink" => true,
                ],
            ],
            "unblock" => [
                [
                    "subtype" => "unblock",
                    "target" => "User:Some author",
                    "expectTargetUserlink" => true,
                ],
            ],
            "merge" => [
                [
                    "subtype" => "merge",
                    "target" => "User:Some page",
                    "params" => [
                        "modid" => 200,
                        "revid" => 3000,
                    ],
                    "expectedParams" => [
                        3 => [
                            "text" => "(moderation-log-change: 200)",
                            "query" => [
                                "title" => "Special:Moderation",
                                "modaction" => "show",
                                "modid" => "200",
                            ],
                            "tooltip" => "(tooltip-moderation-rejected-change)",
                        ],
                        4 => [
                            "text" => "(moderation-log-diff: 3000)",
                            "query" => [
                                "title" => "{{TARGET}}",
                                "diff" => "3000",
                            ],
                            "tooltip" => "(tooltip-moderation-approved-diff)",
                        ],
                    ],
                ],
            ],
            "editchange" => [
                [
                    "subtype" => "editchange",
                    "target" => "Project:Some page",
                    "params" => ["modid" => 12345],
                    "expectedParams" => [
                        3 => [
                            "text" => "(moderation-log-change: 12345)",
                            "query" => [
                                "title" => "Special:Moderation",
                                "modaction" => "show",
                                "modid" => "12345",
                            ],
                            // This link currently doesn't have a custom tooltip.
                            "tooltip" => "Special:Moderation",
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Assert correctness of the parsed HTML of one LogEntry parameter.
     * @param array $expectedParam Expected text/tooltip/etc. of the parameter, see dataProvider().
     * @param string $paramHtml HTML to check.
     * @param int $idx Index of the parameter (for error messages).
     * @param Title $target Title of target page in the tested logentry.
     */
    protected function assertParamHtml(
        array $expectedParam,
        $paramHtml,
        $idx,
        Title $target,
    ) {
        if (!isset($expectedParam["query"])) {
            if (isset($expectedParam["userlink"])) {
                [$userId, $username] = $expectedParam["userlink"];
                $title = $userId
                    ? Title::makeTitle(NS_USER, $username)
                    : SpecialPage::getTitleFor("Contributions", $username);
                unset($expectedParam["userlink"]);

                $expectedParam["text"] = $username;
            } elseif (isset($expectedParam["pagelink"])) {
                $title = Title::newFromText($expectedParam["pagelink"]);
                unset($expectedParam["pagelink"]);
            } else {
                // Plaintext parameter (not a link).
                $this->assertSame(
                    $expectedParam["text"],
                    $paramHtml,
                    "Incorrect text of parameter #$idx.",
                );
                return;
            }

            $expectedParam["query"]["title"] = $title->getPrefixedDBKey();
            $expectedParam["tooltip"] = $title->getFullText();

            if (!isset($expectedParam["text"])) {
                $expectedParam["text"] = $title->getText();
            }
        }

        if ($expectedParam["query"]["title"] == "{{TARGET}}") {
            $expectedParam["query"]["title"] = $target->getPrefixedDBKey();
        }

        $linkTitle = Title::newFromText($expectedParam["query"]["title"]);
        if (!$linkTitle->isKnown()) {
            // Expect a redlink
            $expectedParam["query"] += [
                "action" => "edit",
                "redlink" => "1",
            ];

            // Add "page does not exist" if this is NOT a customized tooltip
            $trivialTooltip = str_replace(
                "_",
                " ",
                $expectedParam["query"]["title"],
            );
            if ($expectedParam["tooltip"] == $trivialTooltip) {
                $expectedParam["tooltip"] =
                    "(red-link-title: " . $expectedParam["tooltip"] . ")";
            }
        }

        $param = $this->parseParam($paramHtml);

        // Order of keys doesn't matter.
        ksort($param);
        ksort($expectedParam);

        $this->assertSame(
            $expectedParam,
            $param,
            "Incorrect HTML of parameter #$idx.",
        );
    }

    protected function parseParam($paramHtml)
    {
        $html = new ModerationTestHTML();
        $html->loadString($paramHtml);

        $parsed = [
            "text" => $html->textContent,
        ];

        $link = $html->getElementsByTagName("a")->item(0);
        if (!($link instanceof DOMElement)) {
            return $parsed;
        }

        $parsed["tooltip"] = $link->getAttribute("title");
        $href = $link->getAttribute("href");

        // Split $href (URL of this link as a string)
        // into an array of querystring parameters.

        $wrapper = TestingAccessWrapper::newFromObject(new WebRequest());

        $_SERVER["REQUEST_URI"] = $href;
        $parsed["query"] = $wrapper->getPathInfo(); // Will find 'title', if any.
        unset($_SERVER["REQUEST_URI"]);

        $bits = ModerationTestUtil::parseUrl($href);
        if (isset($bits["query"])) {
            $parsed["query"] += wfCgiToArray($bits["query"]);
        }

        return $parsed;
    }
}
