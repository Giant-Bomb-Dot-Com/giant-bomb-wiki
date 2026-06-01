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
 * Checks that user can continue editing their version of the page.
 */

namespace MediaWiki\Moderation\Tests;

require_once __DIR__ . "/../framework/ModerationTestsuite.php";

/**
 * @group Database
 * @covers MediaWiki\Moderation\ModerationPreload
 */
class ModerationPreloadIntegrationTest extends ModerationTestCase
{
    /** @covers MediaWiki\Moderation\ModerationPreload::onEditFormPreloadText */
    public function testPreloadNewPage(ModerationTestsuite $t)
    {
        $t->loginAs($t->unprivilegedUser);
        $t->doTestEdit(
            null,
            null,
            "The quick brown fox jumps over the lazy dog",
        );

        $this->tryToPreload($t, __FUNCTION__);
    }

    /** @covers MediaWiki\Moderation\ModerationPreload::onEditFormInitialText */
    public function testPreloadExistingPage(ModerationTestsuite $t)
    {
        $page = "Test page 1";

        $t->loginAs($t->automoderated); /* Create the page first */
        $t->doTestEdit($page, "Text 1");

        $t->loginAs($t->unprivilegedUser);
        $t->doTestEdit(
            $page,
            "Another text",
            "The quick brown fox jumps over the lazy dog",
        );

        $this->tryToPreload($t, __FUNCTION__);
    }

    /** @covers MediaWiki\Moderation\ModerationPreload::onLocalUserCreated */
    public function testAnonymousPreload(ModerationTestsuite $t)
    {
        $t->logout();
        $t->doTestEdit();

        $this->assertSame(
            $t->lastEdit["Text"],
            $t->html->getPreloadedText($t->lastEdit["Title"]),
            "testAnonymousPreload(): Preloaded text differs from what the user saved before",
        );

        /* Now create an account
         and check that text can still be preloaded */

        $username = "FinallyLoggedIn";
        $user = $t->createAccount($username);
        if (!$user) {
            $this->markTestIncomplete('testAnonymousPreload(): Failed to create account, ".
				"most likely the captcha is enabled.');
        }

        // Note: phan doesn't know that markTestIncomplete() unconditionally throws an exception.
        // @phan-suppress-next-line PhanTypeMismatchArgumentNullable
        $t->loginAs($user);
        $this->assertSame(
            $t->lastEdit["Text"],
            $t->html->getPreloadedText($t->lastEdit["Title"]),
            "testAnonymousPreload(): Text was not preloaded after creating an account",
        );
    }

    /** @covers MediaWiki\Moderation\ApiQueryModerationPreload */
    public function testApiPreload(ModerationTestsuite $t)
    {
        /* We make an edit with '''bold''' and ''italic'' markup
			and then check for <b> and <i> tags in "mpmode=parsed" mode.
		*/
        $boldText = "very bold";
        $italicText = "somewhat italic";
        $categories = ["Example category", "Cats"];
        $extraSectionText = "== More information ==\nText in section #1";

        $text = "This text is '''$boldText''' and ''$italicText''.";
        foreach ($categories as $name) {
            $text .= "\n[[Category:$name]]";
        }
        $text .= "\n" . $extraSectionText;

        $t->loginAs($t->unprivilegedUser);
        $t->doTestEdit(
            null,
            $text,
            "The quick brown fox jumps over the lazy dog",
        );

        /* Test 1: mpmode=wikitext */
        $ret = $t->query([
            "action" => "query",
            "prop" => "moderationpreload",
            "mptitle" => $t->lastEdit["Title"],
        ]);

        $this->assertArrayHasKey("query", $ret);
        $this->assertArrayHasKey("moderationpreload", $ret["query"]);

        $mp = $ret["query"]["moderationpreload"];
        $this->assertSame($t->lastEdit["User"], $mp["user"]);
        $this->assertSame($t->lastEdit["Title"], $mp["title"]);
        $this->assertSame($t->lastEdit["Summary"], $mp["comment"]);
        $this->assertArrayHasKey("wikitext", $mp);
        $this->assertArrayNotHasKey("parsed", $mp);
        $this->assertSame($text, $mp["wikitext"]);

        /* Test 2: mpmode=parsed */
        $ret = $t->query([
            "action" => "query",
            "prop" => "moderationpreload",
            "mptitle" => $t->lastEdit["Title"],
            "mpmode" => "parsed",
        ]);

        $mp = $ret["query"]["moderationpreload"];
        $this->assertArrayHasKey("parsed", $mp);
        $this->assertArrayNotHasKey("wikitext", $mp);

        $parsed = $mp["parsed"];
        $this->assertArrayHasKey("text", $parsed);
        $this->assertArrayHasKey("categorieshtml", $parsed);
        $this->assertArrayHasKey("displaytitle", $parsed);

        $this->assertStringContainsString(
            "<b>" . $boldText . "</b>",
            $parsed["text"],
        );
        $this->assertStringContainsString(
            "<i>" . $italicText . "</i>",
            $parsed["text"],
        );

        $this->assertStringContainsString(
            "(pagecategories: " . count($categories) . ")",
            $parsed["categorieshtml"],
        );
        foreach ($categories as $name) {
            $this->assertStringContainsString($name, $parsed["categorieshtml"]);
        }

        /* Test 3: mpsection=N */
        $ret = $t->query([
            "action" => "query",
            "prop" => "moderationpreload",
            "mptitle" => $t->lastEdit["Title"],
            "mpsection" => 1,
        ]);
        $this->assertSame(
            $extraSectionText,
            $ret["query"]["moderationpreload"]["wikitext"],
        );
    }

    private function tryToPreload(ModerationTestsuite $t, $caller)
    {
        $this->assertSame(
            $t->lastEdit["Text"],
            $t->html->getPreloadedText($t->lastEdit["Title"]),
            "$caller(): Preloaded text differs from what the user saved before",
        );

        $elem = $t->html->getElementByXPath('//input[@name="wpSummary"]');
        $this->assertSame(
            $t->lastEdit["Summary"],
            $elem->getAttribute("value"),
            "$caller(): Preloaded summary doesn't match",
        );

        $this->assertContains(
            "ext.moderation.edit",
            $t->html->getLoaderModulesList(),
            "$caller(): Module ext.moderation.edit wasn't loaded",
        );

        $elem = $t->html->getElementById("mw-editing-your-version");
        $this->assertNotNull(
            $elem,
            "$caller(): #mw-editing-your-version not found",
        );
        $this->assertSame(
            "(moderation-editing-your-version)",
            $elem->textContent,
            "$caller(): #mw-editing-your-version doesn't contain " .
                "(moderation-editing-your-version) message",
        );
    }
}
