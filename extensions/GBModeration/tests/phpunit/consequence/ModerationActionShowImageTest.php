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
 * Unit test of ModerationActionShowImage.
 */

namespace MediaWiki\Moderation\Tests;

use FileBackend;
use LocalRepo;
use MediaWiki\MediaWikiServices;
use MediaWiki\Moderation\ModerationActionShowImage;
use MediaWiki\Request\FauxRequest;
use MWException;
use UnregisteredLocalFile;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class ModerationActionShowImageTest extends ModerationUnitTestCase
{
    use ActionTestTrait;
    use UploadTestTrait;

    /**
     * Verify that execute() returns expected result.
     * @param array $opt
     * @dataProvider dataProviderExecute
     * @covers MediaWiki\Moderation\ModerationActionShowImage
     */
    public function testExecute(array $opt)
    {
        $isThumb = $opt["thumb"] ?? false;
        $isMissing = $opt["missing"] ?? false;
        $srcFilename = $opt["filename"] ?? "image640x50.png";
        $expectedWidth = $opt["expectedWidth"] ?? 0;
        $expectedThumbPrefix = $opt["expectedThumbPrefix"] ?? "";
        $expectThumbToEqualOriginal =
            $opt["expectThumbToEqualOriginal"] ?? false;

        $srcPath = __DIR__ . "/../../resources/" . $srcFilename;
        if (!file_exists($srcPath)) {
            throw new MWException("Test image not found: $srcPath");
        }

        $row = (object) [
            "title" => "Some_upload.png",
            "stash_key" => $isMissing
                ? "MissingStashKey.png"
                : $this->stashSampleImage($srcPath),
        ];

        // Mock EntryFactory that will return $entry
        $action = $this->makeActionForTesting(
            ModerationActionShowImage::class,
            function ($context, $entryFactory, $manager) use ($row, $isThumb) {
                $params = ["modid" => 12345];
                if ($isThumb) {
                    $params["thumb"] = 1;
                }
                $context->setRequest(new FauxRequest($params));

                $entryFactory
                    ->expects($this->once())
                    ->method("loadRowOrThrow")
                    ->with(
                        $this->identicalTo($params["modid"]),
                        $this->identicalTo([
                            "mod_title AS title",
                            "mod_stash_key AS stash_key",
                        ]),
                        DB_REPLICA,
                    )
                    ->willReturn($row);

                // This is a readonly action. Ensure that it has no consequences.
                $manager->expects($this->never())->method("add");
            },
        );
        $result = $action->execute();

        // Assert the result
        if ($isMissing) {
            $this->assertSame(
                ["missing" => ""],
                $result,
                "Stash image is missing, but the result of execute() doesn't match expected.",
            );
        } else {
            $this->assertArrayHasKey("thumb-filename", $result);
            $this->assertSame(
                $expectedThumbPrefix . $row->title,
                $result["thumb-filename"],
                "Result of execute(): thumb-filename doesn't match expected.",
            );

            $this->assertArrayHasKey("thumb-path", $result);
            $thumbPath = $result["thumb-path"];

            // Analyze the file returned in "thumb-path".
            $file = new UnregisteredLocalFile(
                false,
                MediaWikiServices::getInstance()
                    ->getRepoGroup()
                    ->getLocalRepo(),
                $thumbPath,
                false,
            );

            if ($isThumb && !$expectThumbToEqualOriginal) {
                $this->assertSame(
                    $expectedWidth,
                    $file->getWidth(),
                    "Incorrect width of returned image.",
                );
            } else {
                $this->assertSame(
                    file_get_contents($srcPath),
                    file_get_contents($file->getLocalRefPath()),
                    "Full image isn't equal to the originally uploaded file.",
                );
            }
        }
    }

    /**
     * Provide datasets for testExecute() runs.
     * @return array
     */
    public function dataProviderExecute()
    {
        return [
            "missing, full image" => [["missing" => true]],
            "missing, thumbnail" => [["missing" => true, "thumb" => true]],
            "not missing, full image" => [[]],
            "not missing, non-image" => [["filename" => "sound.ogg"]],
            "not missing, thumbnail (original image is 640x50, will be scaled down to 320px)" => [
                [
                    "thumb" => true,
                    "filename" => "image640x50.png",
                    // Default thumbnail width is 320px (ModeractionActionShowImage::THUMB_WIDTH)
                    "expectedWidth" => 320,
                    "expectedThumbPrefix" => "320px-",
                ],
            ],
            "not missing, thumbnail (original image is 100x100, will be unchanged)" => [
                [
                    // This image is too small (its width is 100, while requested thumbnail width is 320px),
                    // so an original file will be served instead.
                    "thumb" => true,
                    "filename" => "image100x100.png",
                    "expectedWidth" => 100,
                    "expectedThumbPrefix" => "",
                ],
            ],
            "not missing, thumbnail of non-image (will be unchanged)" => [
                [
                    "thumb" => true,
                    "filename" => "sound.ogg",
                    "expectThumbToEqualOriginal" => true,
                ],
            ],
        ];
    }

    /**
     * Verify that outputResult() correctly streams the file (if found) to standard output.
     * @param bool $expectFound If false, 404 Not Found is expected instead of streaming.
     * @param array $executeResult Return value of execute().
     * @dataProvider dataProviderOutputResult
     * @covers MediaWiki\Moderation\ModerationActionShowImage
     */
    public function testOutputResult($expectFound, array $executeResult)
    {
        $action = $this->makeActionForTesting(
            ModerationActionShowImage::class,
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
            ) use ($expectFound, $executeResult) {
                $context->setRequest(new FauxRequest(["modid" => 12345]));

                if ($expectFound) {
                    $repo = $this->createMock(LocalRepo::class);
                    $repo
                        ->expects($this->once())
                        ->method("streamFileWithStatus")
                        ->with(
                            $this->identicalTo($executeResult["thumb-path"]),
                            $this->identicalTo([
                                "Content-Disposition: " .
                                FileBackend::makeContentDisposition(
                                    "inline",
                                    $executeResult["thumb-filename"],
                                ),
                            ]),
                        );

                    $repoGroup
                        ->expects($this->once())
                        ->method("getLocalRepo")
                        ->willReturn($repo);
                } else {
                    $repoGroup->expects($this->never())->method("getLocalRepo");
                }

                // This is a readonly action. Ensure that it has no consequences.
                $manager->expects($this->never())->method("add");
            },
        );

        if (!$expectFound) {
            // Expect 404 message
            $this->expectOutputRegex("@Although this PHP script (.*) exists@");
        }

        // Obtain a clean OutputPage.
        $output = clone $action->getOutput();
        $action->outputResult($executeResult, $output);

        $this->assertTrue(
            $output->isDisabled(),
            "OutputPage wasn't disabled before streaming.",
        );
    }

    /**
     * Provide datasets for testOutputResult() runs.
     * @return array
     */
    public function dataProviderOutputResult()
    {
        return [
            "missing file" => [false, ["missing" => ""]],
            "successfully streamed file" => [
                true,
                [
                    "thumb-filename" => "Sample_filename.png",
                    "thumb-path" => "mwstore://path/to/file.png",
                ],
            ],
        ];
    }
}
