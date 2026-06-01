<?php
/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2016-2020 Edward Chernenko.

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
class ModerationReturntoTest extends ModerationTestCase
{
    /**
     * Verifies that after-action link "Return to Special:Moderation" is shown.
     * @coversNothing
     */
    public function testReturnto(ModerationTestsuite $t)
    {
        $t->loginAs($t->unprivilegedUser);
        $t->doTestEdit();
        $t->fetchSpecial();

        /*
			We need to ensure that "Return to" link is shown for:
			1) successfully completed action,
			2) failed action which printed an error.

			To test this, we follow Reject link twice:
			first one will be successful,
			second will result in "moderation-already-rejected".
		*/
        $url = $t->new_entries[0]->rejectLink;

        /* 1) check if "Return to" link is present after successful action */
        $html = $t->html->loadUrl($url)->getMainText();
        $this->assertMatchesRegularExpression(
            "/\(moderation-rejected-ok: 1\)/",
            $html,
            "testReturnto(): Result page doesn't contain (moderation-rejected-ok: 1)",
        );
        $this->assertMatchesRegularExpression(
            "/\(returnto: Special:Moderation\)/",
            $html,
            "testReturnto(): Result page doesn't contain (returnto: Special:Moderation)",
        );

        /* 2) check if "Return to" link is present after error */
        $t->html->loadUrl($url);
        $this->assertSame(
            "(moderation-already-rejected)",
            $t->html->getModerationError(),
            "testReturnto(): Error page doesn't contain (moderation-already-rejected)",
        );
        $this->assertMatchesRegularExpression(
            "/\(returnto: Special:Moderation\)/",
            $t->html->getMainText(),
            "testReturnto(): Error page doesn't contain (returnto: Special:Moderation)",
        );
    }
}
