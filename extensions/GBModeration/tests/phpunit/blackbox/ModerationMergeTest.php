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
 * Verifies that modaction=merge works as expected.
 */

namespace MediaWiki\Moderation\Tests;

require_once __DIR__ . "/../framework/ModerationTestsuite.php";

/**
 * @group Database
 * @covers MediaWiki\Moderation\ModerationActionMerge
 */
class ModerationMergeTest extends ModerationTestCase
{
    /*
		This is how we create edit conflict:
		1) The page has 4 lines of text,
		2) User A deletes 2 lines,
		3) User B modifies one of those deleted lines.
		Both users edit the original revision of the page.
	*/

    /** @var string */
    private $page = "Test page 1";

    /** @var string */
    private $text0 =
        "Normal line 1\nNot very interesting line 2\n" .
        "Not very interesting line 3\nNormal line 4\n";

    /** @var string */
    private $text1 =
        "Normal line 1\nJust made line 2 more interesting\n" .
        "Not very interesting line 3\nNormal line 4\n";

    /** @var string */
    private $text2 = "Normal line 1\nNormal line 4\n";

    /** @var string */
    private $text3 = "Normal line 1\nJust made line 2 more interesting\nNormal line 4\n";

    private function makeUnresolvableEditConflict(ModerationTestsuite $t)
    {
        return $t->causeEditConflict(
            $this->page,
            $this->text0,
            $this->text1,
            $this->text2,
        );
    }

    public function testMerge(ModerationTestsuite $t)
    {
        # Done: attempt to approve the edit by $t->unprivilegedUser
        # will cause an edit conflict.

        $entry = $this->makeUnresolvableEditConflict($t);
        $this->assertFalse(
            $entry->conflict,
            "testMerge(): Edit with not-yet-detected conflict is marked with class='modconflict'",
        );

        $error = $t->html->loadUrl($entry->approveLink)->getModerationError();
        $this->assertSame(
            "(moderation-edit-conflict)",
            $error,
            "testMerge(): Edit conflict not detected by modaction=approve",
        );

        $t->fetchSpecial();

        $this->assertCount(
            0,
            $t->new_entries,
            "testMerge(): Something was added into Pending folder when modaction=approve " .
                "detected edit conflict",
        );
        $this->assertCount(
            0,
            $t->deleted_entries,
            "testMerge(): Something was deleted from Pending folder when modaction=approve " .
                "detected edit conflict",
        );

        $t->assumeFolderIsEmpty();
        $t->fetchSpecial();

        $entry = $t->new_entries[0];
        $id = $entry->id;

        $this->assertTrue(
            $entry->conflict,
            "testMerge(): Edit with detected conflict is not marked with class='modconflict'",
        );
        $this->assertNotNull(
            $entry->mergeLink,
            "testMerge(): Merge link not found for edit with detected conflict",
        );

        $this->assertNotNull(
            $entry->rejectLink,
            "testMerge(): Reject link not found for edit with detected conflict",
        );
        $this->assertNotNull(
            $entry->rejectAllLink,
            "testMerge(): RejectAll link not found for edit with detected conflict",
        );
        $this->assertNotNull(
            $entry->showLink,
            "testMerge(): Show link not found for edit with detected conflict",
        );
        $this->assertNotNull(
            $entry->blockLink,
            "testMerge(): Block link not found for edit with detected conflict",
        );

        $this->assertNull(
            $entry->approveLink,
            "testMerge(): Approve link found for edit with detected conflict",
        );
        $this->assertNull(
            $entry->approveAllLink,
            "testMerge(): ApproveAll link found for edit with detected conflict",
        );
        $this->assertNull(
            $entry->mergedDiffLink,
            "testMerge(): MergedDiff link found for not yet merged edit",
        );

        $this->assertNull(
            $entry->rejected_by_user,
            "testMerge(): Not yet rejected edit with detected conflict is marked rejected",
        );
        $this->assertFalse(
            $entry->rejected_batch,
            "testMerge(): Not yet rejected edit with detected conflict has rejected_batch flag ON",
        );
        $this->assertFalse(
            $entry->rejected_auto,
            "testMerge(): Not yet rejected edit with detected conflict has rejected_auto flag ON",
        );

        $title = $t->html->loadUrl($entry->mergeLink)->getTitle();
        $this->assertMatchesRegularExpression(
            "/\(editconflict: " . $t->lastEdit["Title"] . "\)/",
            $title,
            "testMerge(): Wrong HTML title from modaction=merge",
        );

        $this->assertSame(
            $this->text2,
            $t->html->getElementById("wpTextbox1")->textContent,
            "testMerge(): The upper textarea doesn't contain the current page text",
        );
        $this->assertSame(
            $this->text1,
            $t->html->getElementById("wpTextbox2")->textContent,
            "testMerge(): The lower textarea doesn't contain the text we attempted to approve",
        );

        $form = $t->html->getElementById("editform");
        $this->assertNotNull(
            $form,
            "testMerge(): Edit form isn't shown by the Merge link\n",
        );

        $inputs = $t->html->getFormElements($form);

        $this->assertArrayHasKey(
            "wpIgnoreBlankSummary",
            $inputs,
            "testMerge(): Edit form doesn't contain wpIgnoreBlankSummary field",
        );
        $this->assertSame(
            "1",
            $inputs["wpIgnoreBlankSummary"],
            "testMerge(): Value of wpIgnoreBlankSummary field isn't 1",
        );

        $this->assertArrayHasKey(
            "wpMergeID",
            $inputs,
            "testMerge(): Edit form doesn't contain wpMergeID field",
        );
        $this->assertSame(
            (string) $id,
            $inputs["wpMergeID"],
            "testMerge(): Value of wpMergeID field doesn't match the entry id",
        );

        # Try to edit now
        $req = $t
            ->getBot("nonApi")
            ->edit($this->page, $this->text3, "Wow, I merged an edit", "", [
                "wpMergeID" => $id,
            ]);
        $this->assertNotNull(
            $req->getResponseHeader("location"),
            "testMerge(): non-API edit with wpMergeID failed",
        );

        $rev = $t->getLastRevision($this->page);
        $this->assertSame($t->moderator->getName(), $rev["user"]);

        # Was the edit moved into the 'merged' folder?

        $t->fetchSpecial();
        $this->assertCount(
            0,
            $t->new_entries,
            "testMerge(): Something was added into Pending folder when the edit was merged",
        );
        $this->assertCount(
            1,
            $t->deleted_entries,
            "testMerge(): One edit was merged, but number of deleted entries in Pending folder isn't 1",
        );
        $this->assertSame($id, $t->deleted_entries[0]->id);

        $t->fetchSpecial("merged");
        $this->assertCount(
            1,
            $t->new_entries,
            "testMerge(): One edit was merged, but number of new entries in Merged folder isn't 1",
        );
        $this->assertCount(
            0,
            $t->deleted_entries,
            "testMerge(): Something was deleted from Merged folder when the edit was merged",
        );

        $entry = $t->new_entries[0];
        $this->assertSame($id, $entry->id);

        $this->assertNull(
            $entry->rejectLink,
            "testMerge(): Reject link found for already merged edit",
        );
        $this->assertNull(
            $entry->rejectAllLink,
            "testMerge(): RejectAll link found for already merged edit",
        );
        $this->assertNull(
            $entry->approveLink,
            "testMerge(): Approve link found for already merged edit",
        );
        $this->assertNull(
            $entry->approveAllLink,
            "testMerge(): ApproveAll link found for already merged edit",
        );
        $this->assertNull(
            $entry->mergeLink,
            "testMerge(): Merge link found for already merged edit",
        );

        $this->assertNotNull(
            $entry->showLink,
            "testMerge(): Show link not found for already merged edit",
        );
        $this->assertNotNull(
            $entry->blockLink,
            "testMerge(): Block link not found for already merged edit",
        );
        $this->assertNotNull(
            $entry->mergedDiffLink,
            "testMerge(): MergedDiff link not found for already merged edit",
        );

        $params = wfCgiToArray(
            preg_replace("/^.*?\?/", "", $entry->mergedDiffLink),
        );

        $this->assertArrayHasKey("diff", $params);
        $this->assertSame(
            (string) $rev["revid"],
            $params["diff"],
            "testMerge(): diff parameter doesn't match revid of the last revision on the page we edited",
        );
    }

    /**
     * @covers MediaWiki\Moderation\ModerationEditHooks::onEditPage__showEditForm_fields
     * Ensure that wpMergeID is preserved when user clicks Preview.
     */
    public function testPreserveMergeID(ModerationTestsuite $t)
    {
        $t->loginAs($t->moderator);

        $someID = "12345";

        $req = $t->httpPost(wfScript("index"), [
            "action" => "submit",
            "title" => "Test page 1",
            "wpTextbox1" => "Test text 1",
            "wpEdittime" => wfTimestampNow(),

            # Preview mode, provide wpMergeID
            "wpPreview" => "1",
            "wpMergeID" => $someID,
        ]);
        $t->html->loadReq($req);

        $form = $t->html->getElementById("editform");
        $this->assertNotNull(
            $form,
            "testPreserveMergeID(): Edit form not found\n",
        );

        $inputs = $t->html->getFormElements($form);

        $this->assertArrayHasKey(
            "wpIgnoreBlankSummary",
            $inputs,
            "testPreserveMergeID(): Edit form doesn't contain wpIgnoreBlankSummary field",
        );
        $this->assertSame(
            "1",
            $inputs["wpIgnoreBlankSummary"],
            "testPreserveMergeID(): Value of wpIgnoreBlankSummary field isn't 1",
        );

        $this->assertArrayHasKey(
            "wpMergeID",
            $inputs,
            "testPreserveMergeID(): Edit form doesn't contain wpMergeID field",
        );
        $this->assertSame(
            $someID,
            $inputs["wpMergeID"],
            "testPreserveMergeID(): Value of wpMergeID field doesn't match expected id",
        );
    }

    /**
     * Ensure that token is required for Merge action.
     */
    public function testMergeToken(ModerationTestsuite $t)
    {
        $entry = $this->makeUnresolvableEditConflict($t);
        $t->httpGet($entry->approveLink);

        $t->assumeFolderIsEmpty();
        $t->fetchSpecial();
        $url = $t->new_entries[0]->mergeLink;
        $this->assertMatchesRegularExpression(
            "/\(sessionfailure-title\)/",
            $t->noTokenTitle($url),
        );
        $this->assertMatchesRegularExpression(
            "/\(sessionfailure-title\)/",
            $t->badTokenTitle($url),
        );
    }

    public function testApproveAllConflicts(ModerationTestsuite $t)
    {
        $t->doNTestEditsWith($t->unprivilegedUser, null, "Page A");
        $this->makeUnresolvableEditConflict($t);
        $t->doNTestEditsWith($t->unprivilegedUser, null, "Page B");

        # Will attempt to ApproveAll the edit by $t->unprivilegedUser
        # cause an edit conflict?

        $t->fetchSpecial();
        $t->html->loadUrl($t->new_entries[0]->approveAllLink);

        $text = $t->html->getMainText();
        $this->assertMatchesRegularExpression(
            "/\(moderation-approved-ok: " . $t->TEST_EDITS_COUNT * 2 . "\)/",
            $text,
            "testApproveAllConflicts(): Result page doesn't contain (moderation-approved-ok: N)",
        );

        $this->assertMatchesRegularExpression(
            "/\(moderation-approved-errors: 1\)/",
            $text,
            "testApproveAllConflicts(): Result page doesn't contain (moderation-approved-errors: 1)",
        );

        $t->assumeFolderIsEmpty();
        $t->fetchSpecial();

        $this->assertCount(
            1,
            $t->new_entries,
            "testApproveAllConflicts(): Nothing left in Pending folder after " .
                "modaction=approveall, even though there was an edit conflict",
        );
        $this->assertTrue(
            $t->new_entries[0]->conflict,
            "testApproveAllConflicts(): Edit with detected conflict is not marked " .
                "with class='modconflict'",
        );
    }
}
