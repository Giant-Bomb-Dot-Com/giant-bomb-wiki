<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2015-2020 Edward Chernenko.

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
 * Verifies that modaction=show works as expected.
 */

namespace MediaWiki\Moderation\Tests;

use DomElement;

require_once __DIR__ . "/../framework/ModerationTestsuite.php";

/**
 * @group Database
 * @covers MediaWiki\Moderation\ModerationActionShow
 */
class ModerationShowTest extends ModerationTestCase
{
    public function testShow(ModerationTestsuite $t)
    {
        $page = "Test page 1";
        $text1 = "First string\nSecond string\nThird string\n";
        $text2 = "First string\nAnother second string\nThird string\n";

        $t->loginAs($t->automoderated);
        $t->doTestEdit($page, $text1);

        $t->loginAs($t->unprivilegedUser);
        $t->doTestEdit($page, $text2);
        $t->fetchSpecial();

        $url = $t->new_entries[0]->showLink;

        $this->assertNotNull($url, "testShow(): Show link not found");
        $this->assertDoesNotMatchRegularExpression(
            "/token=/",
            $url,
            "testShow(): Token was found in the read-only Show link",
        );

        $title = $t->html->loadUrl($url)->getTitle();

        $this->assertMatchesRegularExpression(
            "/\(difference-title: " . preg_quote($page) . "\)/",
            $title,
            "testShow(): Difference page has a wrong HTML title",
        );

        $added_lines = [];
        $deleted_lines = [];
        $context_lines = [];

        $table_cells = $t->html->getElementsByTagName("td");
        foreach ($table_cells as $td) {
            $classes = preg_split("/\s+/", $td->getAttribute("class"));
            $text = $td->textContent;

            foreach ($classes as $class) {
                if ($class == "diff-addedline") {
                    $added_lines[] = $text;
                } elseif ($class == "diff-deletedline") {
                    $deleted_lines[] = $text;
                } elseif ($class == "diff-context") {
                    $context_lines[] = $text;
                }
            }
        }

        # Each context line is shown twice: in Before and After columns
        $this->assertCount(
            4,
            $context_lines,
            "testShow(): Two lines were unchanged, but number of context lines " .
                "on the difference page is not 4",
        );
        $this->assertSame("First string", $context_lines[0]);
        $this->assertSame("First string", $context_lines[1]);
        $this->assertSame("Third string", $context_lines[2]);
        $this->assertSame("Third string", $context_lines[3]);

        $this->assertCount(
            1,
            $added_lines,
            "testShow(): One line was modified, but number of added lines " .
                "on the difference page is not 1",
        );
        $this->assertCount(
            1,
            $deleted_lines,
            "testShow(): One line was modified, but number of deleted lines " .
                "on the difference page is not 1",
        );
        $this->assertSame("Another second string", $added_lines[0]);
        $this->assertSame("Second string", $deleted_lines[0]);
    }

    public function testShowUpload(ModerationTestsuite $t)
    {
        $t->loginAs($t->unprivilegedUser);
        $t->doTestUpload(
            "Test image 1" . $t->uniqueSuffix() . ".png",
            "image640x50.png",
            "", # Empty description: check for (moderation-diff-upload-notext)
        );
        $t->fetchSpecial();

        $entry = $t->new_entries[0];
        $url = $entry->showLink;
        $this->assertNotNull($url, "testShowUpload(): Show link not found");
        $title = $t->html->loadUrl($url)->getTitle();

        $this->assertMatchesRegularExpression(
            "/\(difference-title: " . $t->lastEdit["Title"] . "\)/",
            $title,
            "testShowUpload(): Difference page has a wrong HTML title",
        );

        $this->assertMatchesRegularExpression(
            "/\(moderation-diff-upload-notext\)/",
            $t->html->getMainText(),
            "testShowUpload(): File was uploaded without description, " .
                "but (moderation-diff-upload-notext) is not shown",
        );

        # Is the image thumbnail displayed on the difference page?

        $images = $t->html->getElementsByTagName("img");

        $thumb = null;
        foreach ($images as $img) {
            if (
                strpos($img->getAttribute("src"), "modaction=showimg") !== false
            ) {
                $thumb = $img;
                break;
            }
        }

        $this->assertNotNull(
            $thumb,
            "testShowUpload(): Thumbnail image not found",
        );

        '@phan-var DomElement $thumb';

        $src = $thumb->getAttribute("src");
        $this->assertMatchesRegularExpression(
            "/thumb=1/",
            $src,
            "testShowUpload(): Thumbnail image URL doesn't contain thumb=1",
        );

        # Is the image thumbnail inside the link to the full image?
        $link = $thumb->parentNode;
        '@phan-var DomElement $link';

        $this->assertSame(
            "a",
            $link->nodeName,
            "testShowUpload(): Thumbnail image isn't encased in <a> tag",
        );

        $href = $link->getAttribute("href");
        $this->assertSame(
            $entry->expectedShowImgLink(),
            $href,
            "testShowUpload(): Full image URL doesn't match expected URL",
        );

        $nonthumb_src = str_replace("&thumb=1", "", $src);
        $this->assertSame(
            $nonthumb_src,
            $href,
            "testShowUpload(): Full image URL doesn't match thumbnail image URL without '&thumb=1'",
        );

        $this->assertDoesNotMatchRegularExpression(
            "/token=/",
            $href,
            "testShowUpload(): Token was found in the read-only ShowImage link",
        );
    }

    /**
     * Ensures that non-image uploads (e.g. OGG files) are shown correctly.
     * @covers MediaWiki\Moderation\ModerationActionShowImage
     */
    public function testShowUploadNonImage(ModerationTestsuite $t)
    {
        /* Allow OGG files (music, i.e. not images) to be uploaded */
        global $wgFileExtensions;
        $t->setMwConfig(
            "FileExtensions",
            array_merge($wgFileExtensions, ["ogg"]),
        );

        $t->loginAs($t->unprivilegedUser);
        $t->doTestUpload("Test sound 1.ogg", "sound.ogg");
        $t->fetchSpecial();

        # Check modaction=show for this upload
        $entry = $t->new_entries[0];
        $t->html->loadUrl($entry->showLink);
        $link = $t->html->getElementByXPath(
            '//a[contains(@href,"modaction=showimg")]',
        );

        $this->assertNotNull(
            $link,
            "testShowUploadNonImage(): no link to download the file",
        );
        $this->assertSame(
            $t->lastEdit["Title"],
            $link->textContent,
            "testShowUploadNonImage(): text of download link doesn't match expected",
        );

        $href = $link->getAttribute("href");
        $this->assertSame(
            $entry->expectedShowImgLink(),
            $href,
            "testShowUploadNonImage(): URL of download link doesn't match expected",
        );
    }
}
