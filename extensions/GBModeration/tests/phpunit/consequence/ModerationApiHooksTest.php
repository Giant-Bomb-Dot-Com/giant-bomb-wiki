<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2023 Edward Chernenko.

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
 * Unit test of ModerationApiHooks.
 */

namespace MediaWiki\Moderation\Tests;

use ApiMain;
use ApiUsageException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Moderation\ModerationPreload;
use MediaWiki\Moderation\PendingEdit;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use RequestContext;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class ModerationApiHooksTest extends ModerationUnitTestCase
{
    /**
     * Ensure that ApiEdit parameters appendtext, prependtext and section work for intercepted edits.
     * @dataProvider dataProviderApiBeforeMain
     * @covers MediaWiki\Moderation\ModerationApiHooks
     */
    public function testApiBeforeMain(array $opt)
    {
        $title = Title::newFromText("Talk:UTPage " . rand(0, 100000));
        $defaultParams = [
            "action" => "edit",
            "title" => $title->getFullText(),
        ];

        $inputParams = $opt["inputParams"] + $defaultParams;
        $expectedParams =
            ($opt["expectedParams"] ?? $opt["inputParams"]) + $defaultParams;

        // Mock findPendingEdit() in the Moderation.Preload service.
        $pendingText = $opt["pendingText"] ?? null;
        if ($pendingText) {
            $pendingEdit = $this->createMock(PendingEdit::class);
            $pendingEdit
                ->expects($this->any())
                ->method("getText")
                ->willReturn($pendingText);
        } else {
            $pendingEdit = false;
        }

        $preload = $this->createMock(ModerationPreload::class);
        $preload
            ->expects($this->any())
            ->method("findPendingEdit")
            ->will(
                $this->returnCallback(function (Title $lookupTitle) use (
                    $title,
                    $pendingEdit,
                ) {
                    $this->assertSame(
                        $title->getFullText(),
                        $lookupTitle->getFullText(),
                    );
                    return $pendingEdit;
                }),
            );
        $this->setService("Moderation.Preload", $preload);

        // Prepare ApiMain object with input parameters.
        $context = new RequestContext();
        $context->setRequest(new FauxRequest($inputParams));
        $context->setTitle(
            Title::makeTitle(NS_SPECIAL, "Badtitle/dummy title for API calls"),
        );

        $processor = new ApiMain($context, true);
        if ($opt["expectedUsageException"] ?? false) {
            [$msg, $code] = $opt["expectedUsageException"];
            $this->expectExceptionObject(
                ApiUsageException::newWithMessage($processor, $msg, $code),
            );
        }

        // Invoke the tested hook handler.
        $hookResult = MediaWikiServices::getInstance()
            ->getHookContainer()
            ->run("ApiBeforeMain", [&$processor]);

        $this->assertTrue(
            $hookResult,
            "Handler of ApiBeforeMain hook should return true.",
        );
        $this->assertArrayEquals(
            $expectedParams,
            $processor->getRequest()->getValues(),
            false,
            true,
        );
    }

    /**
     * Provide datasets for testApiBeforeMain() runs.
     * @return array
     */
    public function dataProviderApiBeforeMain()
    {
        return [
            "not action=edit" => [
                [
                    "inputParams" => ["action" => "query"],
                ],
            ],
            "no section, no prependtext, no appendtext" => [
                [
                    "inputParams" => [],
                ],
            ],
            "has section=, but no pending edit" => [
                [
                    "inputParams" => ["section" => "123"],
                ],
            ],
            "has appendtext=, but no pending edit" => [
                [
                    "inputParams" => [
                        "appendtext" => "123",
                    ],
                ],
            ],
            "has prependtext=, but no pending edit" => [
                [
                    "inputParams" => [
                        "prependtext" => "123",
                    ],
                ],
            ],
            "has appendtext= and pending edit" => [
                [
                    "inputParams" => [
                        "appendtext" => "Cats",
                    ],
                    "pendingText" => "Dogs",
                    "expectedParams" => [
                        "text" => "DogsCats",
                    ],
                ],
            ],
            "has prependtext= and pending edit" => [
                [
                    "inputParams" => [
                        "prependtext" => "Foxes",
                    ],
                    "pendingText" => "Dogs",
                    "expectedParams" => [
                        "text" => "FoxesDogs",
                    ],
                ],
            ],
            "has appendtext=, prependtext= and pending edit" => [
                [
                    "inputParams" => [
                        "prependtext" => "Foxes",
                        "appendtext" => "Cats",
                    ],
                    "pendingText" => "Dogs",
                    "expectedParams" => [
                        "text" => "FoxesDogsCats",
                    ],
                ],
            ],
            "has section=new and pending edit" => [
                [
                    "inputParams" => [
                        "section" => "new",
                        "text" => "Foxes",
                    ],
                    "pendingText" => "Dogs",
                    "expectedParams" => [
                        "text" => "Dogs\n\nFoxes",
                    ],
                ],
            ],
            "has section=new, non-empty sectiontitle= and pending edit" => [
                [
                    "inputParams" => [
                        "section" => "new",
                        "sectiontitle" => "A very new header",
                        "text" => "Foxes",
                    ],
                    "pendingText" => "Dogs",
                    "expectedParams" => [
                        "text" => "Dogs\n\n== A very new header ==\n\nFoxes",
                    ],
                ],
            ],
            "has section=2 and pending edit, and that pending edit has section #2" => [
                [
                    "inputParams" => [
                        "section" => "2",
                        "text" => "==Newheader2==\nNewtext2",
                    ],
                    "pendingText" =>
                        "Text0\n\n==Header1==\nText1\n\n==Header2==\nText2\n\n==Header3==\nText3",
                    "expectedParams" => [
                        "text" =>
                            "Text0\n\n==Header1==\nText1\n\n==Newheader2==\nNewtext2\n\n==Header3==\nText3",
                    ],
                ],
            ],
            "error: has section=2 and pending edit, but this pending edit does NOT have section #2" => [
                [
                    "inputParams" => [
                        "section" => "2",
                        "text" => "==Newheader2==\nNewtext2",
                    ],
                    "pendingText" => "Text without section #2",
                    "expectedUsageException" => [
                        "There is no section 2.",
                        "nosuchsection",
                    ],
                ],
            ],
        ];
    }
}
