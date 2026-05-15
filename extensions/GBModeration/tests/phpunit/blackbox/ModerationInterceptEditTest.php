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
 * Ensures that edits are intercepted by Extension:Moderation.
 */

namespace MediaWiki\Moderation\Tests;

require_once __DIR__ . "/../framework/ModerationTestsuite.php";

/**
 * @group Database
 * @covers MediaWiki\Moderation\ModerationEditHooks
 */
class ModerationInterceptEditTest extends ModerationTestCase
{
    public function testPostEditRedirect(ModerationTestsuite $t)
    {
        $t->loginAs($t->unprivilegedUser);
        $req = $t->doTestEdit();
        $t->fetchSpecial();

        $this->assertTrue(
            $req->isRedirect(),
            "testPostEditRedirect(): User hasn't been redirected after the edit",
        );

        # Check the redirect URL
        $url = $req->getResponseHeader("Location");
        $params = wfCgiToArray(preg_replace("/^.*?\?/", "", $url));

        $this->assertArrayHasKey("title", $params);
        $this->assertArrayHasKey("modqueued", $params);
        $this->assertCount(
            2,
            $params,
            "testPostEditRedirect(): redirect URL has parameters other than 'title' and 'modqueued'",
        );

        $this->assertSame(
            $t->lastEdit["Title"],
            preg_replace("/_/", " ", $params["title"]),
            "testPostEditRedirect(): Title in the redirect URL doesn't match the title of page we edited",
        );
        $this->assertSame(
            "1",
            $params["modqueued"],
            "testPostEditRedirect(): parameter modqueued=1 not found in the redirect URL",
        );

        # Check the page where the user is being redirected to
        $t->loginAs($t->unprivilegedUser);
        $list = $t->html->loadUrl($url)->getLoaderModulesList();

        $this->assertContains(
            "ext.moderation.notify",
            $list,
            "testPostEditRedirect(): Module ext.moderation.notify wasn't loaded",
        );

        # [ext.moderation.notify] shouldn't be loaded for automoderated users.
        $t->loginAs($t->automoderated);
        $list = $t->html->loadUrl($url)->getLoaderModulesList();

        $this->assertNotContains(
            "ext.moderation.notify",
            $list,
            "testPostEditRedirect(): Module ext.moderation.notify was shown to automoderated users",
        );

        # Usual checks on whether the edit not via API was intercepted.
        $this->assertCount(
            1,
            $t->new_entries,
            "testPostEditRedirect(): One edit was queued for moderation, " .
                "but number of added entries in Pending folder isn't 1",
        );
        $this->assertCount(
            0,
            $t->deleted_entries,
            "testPostEditRedirect(): Something was deleted from Pending folder during the queueing",
        );
        $this->assertSame($t->lastEdit["User"], $t->new_entries[0]->user);
        $this->assertSame($t->lastEdit["Title"], $t->new_entries[0]->title);
    }
}
