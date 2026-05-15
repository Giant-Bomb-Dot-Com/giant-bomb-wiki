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
 * Verifies that modaction=approve(all) works as expected.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\ModerationCompatTools;
use MediaWiki\Title\Title;
use MWTimestamp;

require_once __DIR__ . "/../framework/ModerationTestsuite.php";

/**
 * @group Database
 * @covers MediaWiki\Moderation\ModerationActionApprove
 */
class ModerationApproveTest extends ModerationTestCase
{
    public function testApproveAll(ModerationTestsuite $t)
    {
        # We edit with two users:
        #	$t->unprivilegedUser (A)
        #	and $t->unprivilegedUser2 (B)
        # We're applying approveall to one of the edits by A.
        # Expected result is:
        # 1) All edits by A were approved,
        # 2) No edits by B were touched during approveall.

        $t->doNTestEditsWith($t->unprivilegedUser, $t->unprivilegedUser2);
        $t->fetchSpecial();

        # Find edits by user A (they will be approved)
        $entries = ModerationTestsuiteEntry::findByUser(
            $t->new_entries,
            $t->unprivilegedUser,
        );
        $this->assertNotNull(
            $entries[0]->approveAllLink,
            "testApproveAll(): ApproveAll link not found",
        );

        $t->html->loadUrl($entries[0]->approveAllLink);
        $this->assertMatchesRegularExpression(
            "/\(moderation-approved-ok: " . $t->TEST_EDITS_COUNT . "\)/",
            $t->html->getMainText(),
            "testApproveAll(): Result page doesn't contain (moderation-approved-ok: N)",
        );

        $t->fetchSpecial();
        $this->assertCount(
            0,
            $t->new_entries,
            "testApproveAll(): Something was added into Pending folder during modaction=approveall",
        );
        $this->assertCount(
            $t->TEST_EDITS_COUNT,
            $t->deleted_entries,
            "testApproveAll(): Several edits were approved, but number of deleted entries " .
                "in Pending folder doesn't match",
        );

        foreach ($entries as $entry) {
            $rev = $t->getLastRevision($entry->title);
            $this->assertSame($t->unprivilegedUser->getName(), $rev["user"]);
        }

        # Check the log entries: there should be
        # - one 'approveall' log entry
        # - TEST_EDITS_COUNT 'approve' log entries.

        $events = $t->apiLogEntries();
        $this->assertCount(
            1 + $t->TEST_EDITS_COUNT,
            $events,
            "testApproveAll(): Number of log entries doesn't match the number of " .
                "approved edits PLUS ONE (log entry for ApproveAll itself).",
        );

        # Per design, 'approveall' entry MUST be the most recent.
        $le = array_shift($events);

        $this->assertNotNull($le);
        '@phan-var array $le';

        $this->assertSame(
            "approveall",
            $le["action"],
            "testApproveAll(): Most recent log entry is not 'approveall'",
        );
        $this->assertSame($t->moderator->getName(), $le["user"]);
        $this->assertSame(
            $t->unprivilegedUser->getUserPage()->getFullText(),
            $le["title"],
        );

        foreach ($events as $le) {
            $this->assertSame("approve", $le["action"]);
            $this->assertSame($t->moderator->getName(), $le["user"]);
        }
    }

    public function testApproveAllNotRejected(ModerationTestsuite $t)
    {
        $t->TEST_EDITS_COUNT = 10;
        $t->doNTestEditsWith($t->unprivilegedUser);
        $t->fetchSpecial();

        # Already rejected edits must not be affected by ApproveAll.
        # So let's reject some edits and check...

        $approveAllLink = $t->new_entries[0]->approveAllLink;

        # Odd edits are rejected, even edits are approved.
        for ($i = 1; $i < $t->TEST_EDITS_COUNT; $i += 2) {
            $t->httpGet($t->new_entries[$i]->rejectLink);
        }

        $t->fetchSpecial("rejected");
        $t->httpGet($approveAllLink);
        $t->fetchSpecial("rejected");

        $this->assertCount(
            0,
            $t->new_entries,
            "testApproveAllNotRejected(): Something was added into Rejected folder " .
                "during modaction=approveall",
        );
        $this->assertCount(
            0,
            $t->deleted_entries,
            "testApproveAllNotRejected(): Something was deleted from Rejected folder " .
                "during modaction=approveall",
        );
    }

    public function testApproveAllTimestamp(ModerationTestsuite $t)
    {
        /*
			Check that rev_timestamp and rc_ip are properly modified by modaction=approveall.
		*/
        $testPages = [
            "Page 16" => [
                "timestamp" => "20100101001600",
                "ip" => "127.0.0.16",
            ],
            "Page 14" => [
                "timestamp" => "20100101001400",
                "ip" => "127.0.0.14",
            ],
            "Page 12" => [
                "timestamp" => "20100101001200",
                "ip" => "127.0.0.12",
            ],
        ];

        $t->loginAs($t->unprivilegedUser);

        foreach ($testPages as $title => $_) {
            $t->doTestEdit($title);
        }

        $dbr = ModerationCompatTools::getDB(DB_REPLICA); // Only for $dbr->timestamp();

        $t->fetchSpecial();
        foreach ($t->new_entries as $entry) {
            $task = $testPages[$entry->title];
            $entry->updateDbRow([
                "mod_timestamp" => $dbr->timestamp($task["timestamp"]),
                "mod_ip" => $task["ip"],
            ]);
        }

        $t->httpGet($t->new_entries[0]->approveAllLink);

        # Check rev_timestamp/rc_ip.

        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);

        foreach ($testPages as $title => $task) {
            $row = $dbw->selectRow(
                ["page", "revision", "recentchanges"],
                ["rev_timestamp", "rc_ip"],
                Title::newFromText($title)->pageCond(),
                __METHOD__,
                [],
                [
                    "revision" => ["INNER JOIN", ["rev_id=page_latest"]],
                    "recentchanges" => [
                        "INNER JOIN",
                        ["rc_this_oldid=page_latest"],
                    ],
                ],
            );

            $ts = new MWTimestamp($row->rev_timestamp);
            $foundTimestamp = $ts->getTimestamp(TS_MW);

            $this->assertSame(
                $task["timestamp"],
                $foundTimestamp,
                "testApproveAllTimestamp(): approved edit has incorrect timestamp in the page history",
            );
            $this->assertSame(
                $task["ip"],
                $row->rc_ip,
                "testApproveAllTimestamp(): approved edit has incorrect IP in recentchanges",
            );
        }
    }
}
