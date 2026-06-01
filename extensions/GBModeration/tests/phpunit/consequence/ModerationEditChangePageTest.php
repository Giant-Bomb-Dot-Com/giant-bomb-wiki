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
 * Unit test of ModerationEditChangePage and ModerationCompatTools.
 */

namespace MediaWiki\Moderation\Tests;

use Article;
use MediaWiki\Moderation\ModerationEditChangePage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use RequestContext;
use Wikimedia\TestingAccessWrapper;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class ModerationEditChangePageTest extends ModerationUnitTestCase
{
    /**
     * Ensure that showFormAfterText() adds HTML field 'token' (for modaction=editchangesubmit).
     * @covers MediaWiki\Moderation\ModerationEditChangePage
     */
    public function testShowFormAfterText()
    {
        $context = new RequestContext();
        $context->setUser(self::getTestUser()->getUser());

        $article = Article::newFromTitle(
            Title::newFromText("pagename"),
            $context,
        );
        $editPage = new ModerationEditChangePage($article);

        TestingAccessWrapper::newFromObject($editPage)->showFormAfterText();
        $printedString = $context->getOutput()->getHTML();

        $html = new ModerationTestHTML();
        $html->loadString($printedString);

        $tokenInput = $html->getElementByXPath('//input[@name="token"]');
        $this->assertNotNull($tokenInput, "<input name='token'> is missing.");

        $this->assertTrue(
            $context
                ->getUser()
                ->matchEditToken($tokenInput->getAttribute("value")),
            "Value of <input name='token'> is not a valid edit token.",
        );
    }

    /**
     * Check return value of getActionURL().
     * @covers MediaWiki\Moderation\ModerationEditChangePage
     */
    public function testGetActionURL()
    {
        $modid = 12345;
        $context = new RequestContext();
        $context->setRequest(new FauxRequest(["modid" => $modid]));

        $article = Article::newFromTitle(
            Title::newFromText("pagename"),
            $context,
        );
        $editPage = new ModerationEditChangePage($article);

        $wrapper = TestingAccessWrapper::newFromObject($editPage);
        $url = $wrapper->getActionURL(Title::newFromText("unused"));

        $query = wfCgiToArray(ModerationTestUtil::parseUrl($url)["query"]);
        $expectedQuery = [
            "title" => "Special:Moderation",
            "modid" => (string) $modid,
            "modaction" => "editchangesubmit",
        ];

        $this->assertSame(
            $expectedQuery,
            $query,
            "Unexpected return value of getActionURL()",
        );
    }

    /**
     * Check return value of getEditButtons().
     * @covers MediaWiki\Moderation\ModerationEditChangePage
     */
    public function testGetEditButtons()
    {
        $editPage = new ModerationEditChangePage(
            new Article(Title::newFromText("pagename")),
        );

        $unused = 0;
        $buttons = $editPage->getEditButtons($unused);

        // Preview/diff buttons are not yet supported.
        $this->assertArrayHasKey("save", $buttons);
        $this->assertArrayNotHasKey("preview", $buttons);
        $this->assertArrayNotHasKey("diff", $buttons);
    }

    /**
     * Check return value of getContextTitle().
     * @covers MediaWiki\Moderation\ModerationEditChangePage
     */
    public function testGetContextTitle()
    {
        $editPage = new ModerationEditChangePage(
            new Article(Title::newFromText("pagename")),
        );
        $title = $editPage->getContextTitle();

        $this->assertTrue(
            $title->isSpecial("Moderation"),
            "getContextTitle() doesn't return Special:Moderation",
        );
    }
}
