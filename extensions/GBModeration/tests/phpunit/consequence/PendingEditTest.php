<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020 Edward Chernenko.

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
 * Unit test of PendingEdit.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\PendingEdit;
use MediaWiki\Title\Title;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class PendingEditTest extends ModerationUnitTestCase
{
    /**
     * Check all methods of PendingEdit except getSectionText(). These are simple getters.
     * @covers MediaWiki\Moderation\PendingEdit
     */
    public function testPendingEdit()
    {
        $title = Title::newFromText("Talk:UTPage-" . rand(0, 100000));
        $modid = rand(100, 10000);
        $text = "Preloaded text " . rand(0, 100000);
        $comment = "Preloaded comment" . rand(0, 100000);

        $pendingEdit = new PendingEdit($title, $modid, $text, $comment);

        $this->assertSame($title, $pendingEdit->getTitle(), "Wrong title");
        $this->assertSame($modid, $pendingEdit->getId(), "Wrong mod_id");
        $this->assertSame(
            $comment,
            $pendingEdit->getComment(),
            "wrong comment",
        );
        $this->assertSame($text, $pendingEdit->getText(), "Wrong text");
    }

    /**
     * Verify that PendingEdit::getSectionText() correctly extracts the section and returns its text.
     * @param string|int $sectionId
     * @param string $text
     * @param string $expectedResult
     * @dataProvider dataProviderSectionText
     *
     * @covers MediaWiki\Moderation\PendingEdit
     */
    public function testSectionText($sectionId, $text, $expectedResult)
    {
        $title = Title::newFromText("Talk:UTPage-" . rand(0, 100000));
        $modid = rand(100, 10000);
        $comment = "Preloaded comment" . rand(0, 100000);

        $pendingEdit = new PendingEdit($title, $modid, $text, $comment);

        $this->assertSame(
            $expectedResult,
            $pendingEdit->getSectionText($sectionId),
            "Incorrect text of section #$sectionId.",
        );
    }

    /**
     * Provide datasets for testSectionText() runs.
     * @return array
     */
    public function dataProviderSectionText()
    {
        return [
            "No section= parameter (empty string), full text should be returned" => [
                "",
                "Text 0\n== Header 1 ==\nText 1\n== Header 2 ==\nText 2\n== Header 3 ==\nText 3\n",
                "Text 0\n== Header 1 ==\nText 1\n== Header 2 ==\nText 2\n== Header 3 ==\nText 3\n",
            ],
            "section=2 (integer), only text of section #2 should be returned" => [
                2,
                "Text 0\n== Header 1 ==\nText 1\n== Header 2 ==\nText 2\n== Header 3 ==\nText 3\n",
                "== Header 2 ==\nText 2",
            ],
            "section=2 (string), only text of section #2 should be returned" => [
                "2", # string
                "Text 0\n== Header 1 ==\nText 1\n== Header 2 ==\nText 2\n== Header 3 ==\nText 3\n",
                "== Header 2 ==\nText 2",
            ],
            // Ensure that section #0 (text above the first ==Header==) is not confused for "no section".
            "section=0 (integer), only text of section #0 should be returned" => [
                0,
                "Text 0\n== Header 1 ==\nText 1\n== Header 2 ==\nText 2\n== Header 3 ==\nText 3\n",
                "Text 0",
            ],
            "section=0 (string), only text of section #0 should be returned" => [
                "0",
                "Text 0\n== Header 1 ==\nText 1\n== Header 2 ==\nText 2\n== Header 3 ==\nText 3\n",
                "Text 0",
            ],
            // Invalid section (the one that doesn't exist in original text): should return full text.
            "section=123: there is no section #123 in the text, so full text should be returned" => [
                123,
                "Text 0\n== Header 1 ==\nText 1\n== Header 2 ==\nText 2\n== Header 3 ==\nText 3\n",
                "Text 0\n== Header 1 ==\nText 1\n== Header 2 ==\nText 2\n== Header 3 ==\nText 3\n",
            ],
        ];
    }
}
