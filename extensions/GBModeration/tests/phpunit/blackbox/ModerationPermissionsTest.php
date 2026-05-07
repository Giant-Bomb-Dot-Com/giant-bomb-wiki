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
 */

namespace MediaWiki\Moderation\Tests;

require_once __DIR__ . "/../framework/ModerationTestsuite.php";

/**
 * @group Database
 */
class ModerationPermissionsTest extends ModerationTestCase
{
    /**
     * Ensures that only moderators can use Special:Moderation.
     * @coversNothing
     */
    public function testPermissions(ModerationTestsuite $t)
    {
        $t->loginAs($t->unprivilegedUser);
        $title = $t->html->loadUrl($t->getSpecialURL())->getTitle();

        $this->assertMatchesRegularExpression(
            "/\(permissionserrors\)/",
            $title,
        );
    }
}
