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
 * Unit test of EditFormOptions.
 */

namespace MediaWiki\Moderation\Tests;

use Article;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Moderation\EditFormOptions;
use MediaWiki\Moderation\WatchOrUnwatchConsequence;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use RequestContext;
use SpecialPage;
use User;
use Wikimedia\TestingAccessWrapper;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class EditFormOptionsTest extends ModerationUnitTestCase
{
    /**
     * Test setMergeID() and getMergeID().
     * @covers MediaWiki\Moderation\EditFormOptions
     */
    public function testMergeID()
    {
        $opt = new EditFormOptions(new MockConsequenceManager());

        $val = 12345;
        $opt->setMergeID($val);
        $this->assertSame($val, $opt->getMergeID());
    }

    /**
     * Verify that default return value of getMergeID() is wpMergeID parameter from WebRequest.
     * @covers MediaWiki\Moderation\EditFormOptions
     */
    public function testDefaultMergeID()
    {
        $opt = new EditFormOptions(new MockConsequenceManager());

        $val = 12345;
        RequestContext::getMain()->getRequest()->setVal("wpMergeID", 12345);

        $this->assertSame($val, $opt->getMergeID());
    }

    /**
     * Verify that "watch this" checkbox is detected on Special:Movepage and Special:Upload.
     * @param bool|null $expectedWatch True: page should be Watched, false: Unwatched, null: neither.
     * @param string $specialPageName Name of special page, e.g. "Upload".
     * @param array $requestParams Parameters im WebRequest, e.g. [ 'wpWatchthis' => 1 ].
     * @dataProvider dataProviderSpecialPageHook
     * @covers MediaWiki\Moderation\EditFormOptions
     */
    public function testSpecialPageHook(
        $expectedWatch,
        $specialPageName,
        array $requestParams,
    ) {
        $opt = new EditFormOptions(new MockConsequenceManager());
        $this->setService("Moderation.EditFormOptions", $opt);

        RequestContext::getMain()->setRequest(new FauxRequest($requestParams));

        $special = new SpecialPage($specialPageName);
        $special->run("");

        $wrapper = TestingAccessWrapper::newFromObject($opt);
        $this->assertSame($expectedWatch, $wrapper->watchthis, "watchthis");
    }

    /**
     * Provide datasets for testSpecialPageHook() runs.
     * @return array
     */
    public function dataProviderSpecialPageHook()
    {
        return [
            // Note: wpWatch is a checkbox, its mere presence means "true" (even if value is empty or 0)
            "Special:MovePage, watch" => [true, "Movepage", ["wpWatch" => ""]],
            "Special:MovePage, unwatch" => [false, "Movepage", []],
            "Special:Upload, watch" => [true, "Upload", ["wpWatchthis" => 1]],
            "Special:Upload, unwatch (wpWatchthis=0)" => [
                false,
                "Upload",
                ["wpWatchthis" => 0],
            ],
            "Special:Upload, unwatch (wpWatchthis is empty string)" => [
                false,
                "Upload",
                ["wpWatchthis" => ""],
            ],
            "Not Special:MovePage or Special:Upload, no need to neither Watch nor Unwatch" => [
                null,
                "Blankpage",
                [],
            ],
        ];
    }

    /**
     * Verify that "watch this" checkbox is detected on EditPage form.
     * @param bool $isWatch True to test "Watch this" checkbox being checked, false - unchecked.
     * @dataProvider dataProviderEditFilterHook
     * @covers MediaWiki\Moderation\EditFormOptions::onEditFilter
     * @covers MediaWiki\Moderation\EditFormOptions::getSectionText
     * @covers MediaWiki\Moderation\EditFormOptions::getSection
     */
    public function testEditFilterHook($isWatch)
    {
        $opt = new EditFormOptions(new MockConsequenceManager());
        $this->setService("Moderation.EditFormOptions", $opt);

        $text = "Sample text " . rand(0, 1000000);
        $section = rand(0, 2);

        $params = [
            "wpUnicodeCheck" => EditPage::UNICODE_CHECK,
            "wpTextbox1" => $text,
            "wpSection" => $section,
        ];
        if ($isWatch) {
            // Checkbox: its mere presence means "true"  (even if value is empty or 0)
            $params["wpWatchthis"] = "";
        }
        $request = new FauxRequest($params, true);

        $editPage = new EditPage(new Article(Title::newFromText("whatever")));
        $editPage->importFormData($request);

        // Prevent attemptSave() from actually saving the edit.
        $this->setTemporaryHook("MultiContentSave", static function () {
            return false;
        });

        $unusedResult = [];
        $editPage->attemptSave($unusedResult);

        $this->assertSame($text, $opt->getSectionText(), "getSectionText()");
        $this->assertSame(
            (string) $section,
            $opt->getSection(),
            "getSection()",
        );

        $wrapper = TestingAccessWrapper::newFromObject($opt);
        $this->assertSame($isWatch, $wrapper->watchthis, "watchthis");
    }

    /**
     * Provide datasets for testEditFilterHook() runs.
     * @return array
     */
    public function dataProviderEditFilterHook()
    {
        return [
            "Watch" => [true],
            "Unwatch" => [false],
        ];
    }

    /**
     * Verify that watchIfNeeded() watches or unwatches pages depending on the value of $watchthis.
     * @param bool|null $watchthis True: pages should be Watched, false: Unwatched, null: neither.
     * @dataProvider dataProviderWatchIfNeeded
     * @covers MediaWiki\Moderation\EditFormOptions
     */
    public function testWatchIfNeeded($watchthis)
    {
        $user = User::newFromName("10.12.14.16", false);
        $titles = [
            Title::newFromText("First page"),
            Title::newFromtext("Project:Another page"),
        ];

        $manager = new MockConsequenceManager();
        $opt = new EditFormOptions($manager);

        if ($watchthis !== null) {
            $wrapper = TestingAccessWrapper::newFromObject($opt);
            $wrapper->watchthis = $watchthis;

            $expectedConsequences = [
                new WatchOrUnwatchConsequence($watchthis, $titles[0], $user),
                new WatchOrUnwatchConsequence($watchthis, $titles[1], $user),
            ];
        } else {
            $expectedConsequences = [];
        }

        $opt->watchIfNeeded($user, $titles);
        $this->assertConsequencesEqual(
            $expectedConsequences,
            $manager->getConsequences(),
        );
    }

    /**
     * Provide datasets for testWatchIfNeeded() runs.
     * @return array
     */
    public function dataProviderWatchIfNeeded()
    {
        return [
            "Watch" => [true],
            "Unwatch" => [false],
            "neither Watch nor Unwatch" => [null],
        ];
    }
}
