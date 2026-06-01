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
 * Verifies compatibility with Extension:PageForms.
 */

namespace MediaWiki\Moderation\Tests;

use SpecialPage;

require_once __DIR__ . "/../framework/ModerationTestsuite.php";

/**
 * @group Database
 */
class ModerationPageFormsIntegrationTest extends ModerationTestCase
{
    /**
     * Is pending edit preloaded into the edit form of Special:FormEdit?
     * @covers MediaWiki\Moderation\ModerationPageForms
     * @dataProvider dataProviderPageFormsPreload
     */
    public function testPageFormsPreload(
        $isExistingPage,
        ModerationTestsuite $t,
    ) {
        $this->requireExtension("PageForms");

        if ($t->mwVersionCompare("1.44.0-alpha", ">=")) {
            $this->markTestSkipped(
                "Test skipped: Extension:PageForms prints deprecation warnings in 1.44.",
            );
        }

        $page = "Test page 1";
        $formName = "Cat";
        $sections = [
            "Appearance" => "Really cool",
            "Behavior" => "Really funny",
            "See also" => "Dogs",
        ];

        /* Content of test pages */
        $formText = $pageText = "";
        foreach ($sections as $name => $content) {
            $formText .= "== $name ==\n{{{section|$name|level=2}}}\n\n";
            $pageText .= "== $name ==\n$content\n\n";
        }
        $formText .=
            "{{{standard input|summary}}}\n\n{{{standard input|save}}}";

        /* First, create the Form */
        $t->loginAs($t->automoderated);
        $t->doTestEdit("Form:$formName", $formText);

        if ($isExistingPage) {
            $t->doTestEdit($page, "Whatever");
        }

        /* Now, create the page as non-automoderated user,
			then revisit Special:EditForm and verify that is shows
			the pending edit to its author.
		*/
        $t->loginAs($t->unprivilegedUser);
        $t->doTestEdit($page, $pageText);

        $html = $t->html->loadUrl(
            SpecialPage::getTitleFor("FormEdit")->getFullURL([
                "form" => $formName,
                "target" => $page,
            ]),
        );
        $inputs = $html->getElementsByXPath('//form[@id="pfForm"]//textarea');
        $this->assertCount(
            count($sections),
            $inputs,
            'testPageFormsPreload(): this test is outdated (haven\'t found expected ' .
                "fields on Special:EditForm)",
        );

        $idx = 0;
        foreach ($sections as $name => $expectedContent) {
            $input = $inputs->item($idx++);

            // @phan-suppress-next-line PhanUndeclaredMethod
            $this->assertSame(
                "_section[$name]",
                $input->getAttribute("name"),
                "testPageFormsPreload(): name of the EditForm field doesn't match expected",
            );

            $this->assertSame(
                $expectedContent,
                $input->textContent,
                "testPageFormsPreload(): value of the EditForm field doesn't match expected",
            );
        }
    }

    /**
     * Provide datasets for testPageFormsPreload() runs.
     * @return array
     */
    public function dataProviderPageFormsPreload()
    {
        /* Sections are handled differently in API and non-API editing.
			Test both situations.
		*/
        return [
            "new page" => [false],
            "existing page" => [true],
        ];
    }
}
