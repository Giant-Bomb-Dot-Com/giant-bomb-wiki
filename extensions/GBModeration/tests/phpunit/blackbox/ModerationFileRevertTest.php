<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2017-2020 Edward Chernenko.

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
 * Ensures that reverting image to old revision doesn't bypass Moderation.
 */

namespace MediaWiki\Moderation\Tests;

require_once __DIR__ . "/../framework/ModerationTestsuite.php";

/**
 * @group Database
 */
class ModerationFileRevertTest extends ModerationTestCase
{
    /**
     * Check that index.php?action=revert can't be used to bypass Moderation.
     * @covers MediaWiki\Moderation\ModerationUploadHooks::ongetUserPermissionsErrors
     */
    public function testFileRevert(ModerationTestsuite $t)
    {
        $t->loginAs($t->unprivilegedUser);
        $req = $t->httpPost(wfScript("index"), [
            "action" => "revert",
        ]);
        $t->html->loadReq($req);

        $this->assertMatchesRegularExpression(
            "/\(moderation-revert-not-allowed\)/",
            $t->html->getMainText(),
            "testFileRevert(): Revert page doesn't contain (moderation-revert-not-allowed)",
        );
    }

    /**
     * Check that api.php?action=filerevert can't be used to bypass Moderation.
     * @covers MediaWiki\Moderation\ModerationApiHooks::onApiCheckCanExecute
     */
    public function testApiFileRevert(ModerationTestsuite $t)
    {
        $t->loginAs($t->unprivilegedUser);
        $ret = $t->query([
            "action" => "filerevert",
            "filename" => "whatever",
            "archivename" => "whatever",
            "token" => null,
        ]);

        /* File revert shouldn't be allowed (this user is not automoderated) */
        $t->assertApiError("moderation-revert-not-allowed", $ret, $this);
    }
}
