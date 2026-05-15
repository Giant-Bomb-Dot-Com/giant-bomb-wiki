<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2021 Edward Chernenko.

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
 * Unit test of ModerationActionShow.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\ModerationActionShow;
use MediaWiki\Moderation\ModerationViewableEntry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class ModerationActionShowTest extends ModerationUnitTestCase
{
    use ActionTestTrait;

    /**
     * Verify that execute() returns expected result for different ViewableEntry objects.
     * @param array $expectedResult What should be returned by execute().
     * @param array $methodResults Return values of methods of mocked ViewableEntry,
     * e.g. [ 'isUpload' => false, 'getDiffHTML' => 'some HTML' ].
     * @param bool $pageExists True means "edit in existing article", false - newly created article.
     * @dataProvider dataProviderExecute
     * @covers MediaWiki\Moderation\ModerationActionShow
     */
    public function testExecute(
        array $expectedResult,
        array $methodResults,
        $pageExists,
    ) {
        $title = Title::newFromText("UTPage-" . rand(0, 100000));
        if ($pageExists) {
            $title->resetArticleId(12345);
        }

        $expectedResult["title"] = $title->getFullText();

        // Mock ViewableEntry
        $entry = $this->createMock(ModerationViewableEntry::class);
        $entry->expects($this->once())->method("getTitle")->willReturn($title);

        foreach ($methodResults as $method => $ret) {
            $entry
                ->expects($method == "isUpload" ? $this->any() : $this->once())
                ->method($method)
                ->willReturn($ret);
        }

        // Mock EntryFactory that will return $entry
        $action = $this->makeActionForTesting(
            ModerationActionShow::class,
            function ($context, $entryFactory, $manager) use ($entry) {
                $modid = 56789;
                $context->setRequest(new FauxRequest(["modid" => $modid]));

                $entryFactory
                    ->expects($this->once())
                    ->method("findViewableEntry")
                    ->with($this->identicalTo($modid))
                    ->willReturn($entry);

                // This is a readonly action. Ensure that it has no consequences.
                $manager->expects($this->never())->method("add");
            },
        );
        $result = $action->execute();

        $this->assertSame(
            $expectedResult,
            $result,
            "Result of execute() doesn't match expected.",
        );
    }

    /**
     * Provide datasets for testExecute() runs.
     * @return array
     */
    public function dataProviderExecute()
    {
        return [
            "non-upload with non-empty diff" => [
                ["diff-html" => "{MockedDiffHtml}"],
                [
                    "isUpload" => false,
                    "getDiffHTML" => "{MockedDiffHtml}",
                ],
                false,
            ],
            "non-upload with empty diff" => [
                [
                    "nodiff-reason" => "moderation-diff-no-changes",
                    "null-edit" => "",
                ],
                ["isUpload" => false, "getDiffHTML" => ""],
                false,
            ],
            "upload with non-empty diff" => [
                [
                    "image-url" => "{MockedImageURL}",
                    "image-thumb-html" => "{MockedImageThumbHTML}",
                    "diff-html" => "{MockedDiffHtml}",
                ],
                [
                    "isUpload" => true,
                    "getDiffHTML" => "{MockedDiffHtml}",
                    "getImageURL" => "{MockedImageURL}",
                    "getImageThumbHTML" => "{MockedImageThumbHTML}",
                ],
                false,
            ],
            "upload with empty diff" => [
                [
                    "image-url" => "{MockedImageURL}",
                    "image-thumb-html" => "{MockedImageThumbHTML}",
                    "nodiff-reason" => "moderation-diff-upload-notext",
                ],
                [
                    "isUpload" => true,
                    "getDiffHTML" => "",
                    "getImageURL" => "{MockedImageURL}",
                    "getImageThumbHTML" => "{MockedImageThumbHTML}",
                ],
                false,
            ],
            "reupload with empty diff" => [
                [
                    "image-url" => "{MockedImageURL}",
                    "image-thumb-html" => "{MockedImageThumbHTML}",
                    "nodiff-reason" => "moderation-diff-reupload",
                ],
                [
                    "isUpload" => true,
                    "getDiffHTML" => "",
                    "getImageURL" => "{MockedImageURL}",
                    "getImageThumbHTML" => "{MockedImageThumbHTML}",
                ],
                // Page already exists (i.e. this is a reupload)
                true,
            ],
        ];
    }

    /**
     * Verify that outputResult() correctly converts return value of execute() into HTML output.
     * @param array $expectedHtml What should outputResult() write into its OutputPage parameter.
     * @param array $executeResult Return value of execute().
     * @dataProvider dataProviderOutputResult
     * @covers MediaWiki\Moderation\ModerationActionShow
     */
    public function testOutputResult($expectedHtml, array $executeResult)
    {
        $modid = 12345;

        $action = $this->makeActionForTesting(
            ModerationActionShow::class,
            function (
                $context,
                $entryFactory,
                $manager,
                $canSkip,
                $editFormOptions,
                $actionLinkRenderer,
                $repoGroup,
                $contentLanguage,
                $revisionRenderer,
            ) use ($modid) {
                $context->setRequest(new FauxRequest(["modid" => $modid]));

                $actionLinkRenderer
                    ->expects($this->any())
                    ->method("makeLink")
                    ->willReturnCallback(function ($actionName, $id) use (
                        $modid,
                    ) {
                        $this->assertSame($modid, $id);
                        return "{ActionLink:$actionName}";
                    });
                $manager->expects($this->never())->method("add");
            },
        );

        $output = clone $action->getOutput();
        $action->outputResult($executeResult, $output);

        $this->assertSame(
            $expectedHtml,
            $output->getHTML(),
            "Result of outputResult() doesn't match expected.",
        );
        $this->assertSame(
            ["mediawiki.diff.styles"],
            $output->getModuleStyles(),
            "Necessary styles weren't added by outputResult().",
        );
        $this->assertSame(
            "(difference-title: " . $executeResult["title"] . ")",
            $output->getPageTitle(),
            "Page title wasn't set by outputResult().",
        );
    }

    /**
     * Provide datasets for testOutputResult() runs.
     * @return array
     */
    public function dataProviderOutputResult()
    {
        return [
            "non-upload with non-empty diff" => [
                "{MockedDiffHtml}{ActionLink:approve} / {ActionLink:reject}",
                [
                    "title" => "Name of article",
                    "diff-html" => "{MockedDiffHtml}",
                ],
            ],
            "non-upload with empty diff" => [
                "<p>(mocked-no-diff-reason-message)\n</p>{ActionLink:reject}",
                [
                    "title" => "Name of article",
                    "nodiff-reason" => "mocked-no-diff-reason-message",
                    "null-edit" => "",
                ],
            ],
            "upload with non-empty diff" => [
                '<a href="{MockedImageURL}">{MockedImageThumbHTML}</a>{MockedDiffHtml}' .
                "{ActionLink:approve} / {ActionLink:reject}",
                [
                    "title" => "File:Name of image.png",
                    "image-url" => "{MockedImageURL}",
                    "image-thumb-html" => "{MockedImageThumbHTML}",
                    "diff-html" => "{MockedDiffHtml}",
                ],
            ],
            "upload with empty diff" => [
                '<a href="{MockedImageURL}">{MockedImageThumbHTML}</a><p>(mocked-no-diff-reason-message)' .
                "\n</p>{ActionLink:approve} / {ActionLink:reject}",
                [
                    "title" => "File:Name of image.png",
                    "image-url" => "{MockedImageURL}",
                    "image-thumb-html" => "{MockedImageThumbHTML}",
                    "nodiff-reason" => "mocked-no-diff-reason-message",
                ],
            ],
        ];
    }
}
