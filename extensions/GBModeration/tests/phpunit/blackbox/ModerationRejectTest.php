<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2015-2025 Edward Chernenko.

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
 * Verifies that modaction=reject(all) works as expected.
 */

namespace MediaWiki\Moderation\Tests;

require_once __DIR__ . "/../framework/ModerationTestsuite.php";

/**
 * @group Database
 * @covers MediaWiki\Moderation\ModerationActionReject
 */
class ModerationRejectTest extends ModerationTestCase
{
    public function testRejectAll(ModerationTestsuite $t)
    {
        # We edit with two users:
        #	$t->unprivilegedUser (A)
        #	and $t->unprivilegedUser2 (B)
        # We're applying rejectall to one of the edits by A.
        # Expected result is:
        # 1) All edits by A were rejected,
        # 2) No edits by B were touched during rejectall.

        $t->doNTestEditsWith($t->unprivilegedUser, $t->unprivilegedUser2);
        $t->fetchSpecial();

        # Find edits by user A (they will be rejected)
        $entries = ModerationTestsuiteEntry::findByUser(
            $t->new_entries,
            $t->unprivilegedUser,
        );
        $this->assertNotNull(
            $entries[0]->rejectAllLink,
            "testRejectAll(): RejectAll link not found",
        );

        $t->html->loadUrl($entries[0]->rejectAllLink);
        $this->assertMatchesRegularExpression(
            "/\(moderation-rejected-ok: " . $t->TEST_EDITS_COUNT . "\)/",
            $t->html->getMainText(),
            "testRejectAll(): Result page doesn't contain (moderation-rejected-ok: N)",
        );

        $t->fetchSpecial();
        $this->assertCount(
            0,
            $t->new_entries,
            "testRejectAll(): Something was added into Pending folder during modaction=rejectall",
        );
        $this->assertCount(
            $t->TEST_EDITS_COUNT,
            $t->deleted_entries,
            "testRejectAll(): Several edits were rejected, but number of deleted entries " .
                "in Pending folder doesn't match",
        );

        foreach ($entries as $entry) {
            $de = ModerationTestsuiteEntry::findById(
                $t->deleted_entries,
                $entry->id,
            );
            $this->assertNotNull($de);

            $this->assertSame($entry->user, $de->user);
            $this->assertSame($entry->title, $de->title);
        }

        $t->fetchSpecial("rejected");
        $this->assertCount(
            $t->TEST_EDITS_COUNT,
            $t->new_entries,
            "testRejectAll(): Several edits were rejected, but number of new entries " .
                "in Rejected folder doesn't match",
        );
        $this->assertCount(
            0,
            $t->deleted_entries,
            "testRejectAll(): Something was deleted from Rejected folder during modaction=rejectall",
        );

        foreach ($entries as $entry) {
            $de = ModerationTestsuiteEntry::findById(
                $t->new_entries,
                $entry->id,
            );
            $this->assertNotNull($de);

            $this->assertSame($entry->user, $de->user);
            $this->assertSame($entry->title, $de->title);

            $this->assertSame($t->moderator->getName(), $de->rejected_by_user);
            $this->assertTrue(
                $de->rejected_batch,
                "testRejectAll(): Edit rejected via modaction=rejectall has rejected_batch flag OFF",
            );
            $this->assertFalse(
                $de->rejected_auto,
                "testRejectAll(): Manually rejected edit has rejected_auto flag ON",
            );

            $this->assertNull(
                $de->rejectLink,
                "testRejectAll(): Reject link found for already rejected edit",
            );
            $this->assertNull(
                $de->rejectAllLink,
                "testRejectAll(): RejectAll link found for already rejected edit",
            );
            $this->assertNull(
                $de->approveAllLink,
                "testRejectAll(): ApproveAll link found for already rejected edit",
            );
        }

        # Check the log entry: there should be only one 'rejectall'.
        $events = $t->apiLogEntries();
        $this->assertCount(
            1,
            $events,
            "testRejectAll(): Number of log entries isn't 1.",
        );
        $le = $events[0];

        $this->assertSame(
            "rejectall",
            $le["action"],
            "testRejectAll(): Most recent log entry is not 'rejectall'",
        );
        $this->assertSame($t->moderator->getName(), $le["user"]);
        $this->assertSame(
            $t->unprivilegedUser->getUserPage()->getFullText(),
            $le["title"],
        );
        $this->assertSame($t->TEST_EDITS_COUNT, $le["params"]["count"]);

        $events = $t->nonApiLogEntries(1);
        $this->assertSame("rejectall", $events[0]["type"]);

        $this->assertSame($t->moderator->getName(), $events[0]["params"][1]);
        $this->assertSame(
            $t->unprivilegedUser->getUserPage()->getText(),
            $events[0]["params"][2],
        );
        $this->assertSame(
            (string) $t->TEST_EDITS_COUNT,
            $events[0]["params"][3],
        );
    }
}
