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
class ModerationTokensTest extends ModerationTestCase
{
    /**
     * Verifies that moderation tokens are required.
     * @coversNothing
     */
    public function testTokens(ModerationTestsuite $t)
    {
        $t->loginAs($t->unprivilegedUser);
        $t->doTestEdit();
        $t->fetchSpecial();

        $entry = $t->new_entries[0];
        $entry->fakeBlockLink();

        # Non-readonly actions require a correct token
        $links = [
            $entry->approveLink,
            $entry->approveAllLink,
            $entry->rejectLink,
            $entry->rejectAllLink,
            $entry->blockLink,
            $entry->unblockLink,
        ];
        foreach ($links as $url) {
            $this->assertMatchesRegularExpression(
                "/\(sessionfailure-title\)/",
                $t->noTokenTitle($url),
            );

            /* Double-check that nothing happened */
            $t->fetchSpecial();
            $this->assertCount(0, $t->new_entries);
            $this->assertCount(0, $t->deleted_entries);

            # Would the wrong token work?

            $this->assertMatchesRegularExpression(
                "/\(sessionfailure-title\)/",
                $t->badTokenTitle($url),
            );

            /* Double-check that nothing happened */
            $t->fetchSpecial();
            $this->assertCount(0, $t->new_entries);
            $this->assertCount(0, $t->deleted_entries);
        }
    }
}
