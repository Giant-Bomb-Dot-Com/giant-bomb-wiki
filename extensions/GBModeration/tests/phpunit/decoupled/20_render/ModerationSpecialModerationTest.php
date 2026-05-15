<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2018-2025 Edward Chernenko.

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
 * Checks how HTML of Special:Moderation is rendered from the 'moderation' SQL table.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\MediaWikiServices;
use SpecialPage;

require_once __DIR__ . "/../../framework/ModerationTestsuite.php";

/**
 * @group Database
 * @covers MediaWiki\Moderation\ModerationEntryFormatter
 * @covers MediaWiki\Moderation\SpecialModeration
 */
class ModerationSpecialModerationTest extends ModerationTestCase
{
    /**
     * @dataProvider dataProvider
     */
    public function testRenderSpecial(array $options)
    {
        $this->runSet($options);
    }

    /**
     * Provide datasets for testRenderSpecial() runs.
     */
    public function dataProvider()
    {
        global $wgModerationTimeToOverrideRejection, $wgRCChangedSizeThreshold;

        $longAgo =
            "-" . ($wgModerationTimeToOverrideRejection + 1) . " seconds";
        $notLongAgoEnough =
            "-" . ($wgModerationTimeToOverrideRejection - 3600) . " seconds";

        return [
            "pending edit" => [[]],
            "pending edit in main namespace" => [
                [
                    "mod_namespace" => NS_MAIN,
                    "mod_title" => "Page_in_main_namespace",
                ],
            ],
            "pending edit in Project namespace" => [
                [
                    "mod_namespace" => NS_PROJECT,
                    "mod_title" => "Page_in_Project_namespace",
                ],
            ],
            "pending edit by anonymous user" => [
                [
                    "mod_user" => 0,
                    "mod_user_text" => "127.0.0.1",
                ],
            ],
            "pending edit by registered user" => [
                [
                    "mod_user" => 12345,
                    "mod_user_text" => "Some registered user",
                ],
            ],
            "pending edit with empty edit summary" => [["mod_comment" => ""]],
            "pending edit with non-empty edit summary (plaintext only)" => [
                ["mod_comment" => "Hello, World!"],
            ],
            "pending edit where edit summary contains [[wikitext links]]" => [
                ["mod_comment" => "Hello, [[World]]!"],
            ],
            "pending edit where edit summary contains <b>HTML</b> (it should be escaped)" => [
                [
                    "mod_comment" => "<i>HTML</i> will be <b>escaped",
                ],
            ], // no </b> on purpose
            'pending edit where edit summary contains "/* SectionName */" link' => [
                [
                    "mod_comment" => "/* Section 1 */ edited this section",
                ],
            ],
            "rejected edit" => [
                [
                    "mod_rejected" => 1,
                    "expectedFolder" => "rejected",
                ],
            ],
            "edit that was rejected automatically (should be in the Spam folder)" => [
                [
                    "mod_rejected" => 1,
                    "mod_rejected_auto" => 1,
                    "expectedFolder" => "spam",
                ],
            ],
            "edit that has already been manually merged" => [
                [
                    "mod_merged_revid" => 12345,
                    "expectedFolder" => "merged",
                ],
            ],
            'edit of registered user, as seen by moderator with "checkuser" right' => [
                ["isCheckuser" => 1, "mod_ip" => "127.0.0.2"],
            ],
            'edit of anonymous user, as seen by moderator with "checkuser" right' => [
                [
                    "isCheckuser" => 1,
                    "mod_user" => 0,
                    "mod_user_text" => "127.0.0.3",
                ],
            ],
            "pending move (new title in main namespace)" => [
                [
                    "mod_type" => "move",
                    "mod_page2_namespace" => NS_MAIN,
                    "mod_page2_title" => "NewTitle_in_Main_namespace",
                ],
            ],
            "pending move (new title in Project namespace)" => [
                [
                    "mod_type" => "move",
                    "mod_page2_namespace" => NS_PROJECT,
                    "mod_page2_title" => "NewTitle_in_Project_namespace",
                ],
            ],
            "pending edit with detected edit conflict" => [
                ["mod_conflict" => 1],
            ],
            "pending edit with edit conflict, as seen by moderator-but-not-automoderated" => [
                [
                    "mod_conflict" => 1,
                    "notAutomoderated" => true,
                ],
            ],
            'pending edit when $wgModerationPreviewLink=true' => [
                ["previewLinkEnabled" => true],
            ],
            'pending move when $wgModerationPreviewLink=true' => [
                [
                    "previewLinkEnabled" => true,
                    "mod_type" => "move",
                ],
            ],
            'pending edit when $wgModerationEnableEditChange=true' => [
                ["editChangeEnabled" => true],
            ],
            "pending edit by modblocked user" => [["modblocked" => true]],
            "pending anonymous edit from modblocked IP" => [
                [
                    "modblocked" => true,
                    "mod_user" => 0,
                    "mod_user_text" => "127.0.0.1",
                ],
            ],
            "pending minor edit" => [["mod_minor" => 1]],
            "pending bot edit" => [["mod_bot" => 1]],
            "pending edit that creates a new article" => [["mod_new" => 1]],
            "pending edit that was queued 2 days ago" => [
                ["mod_timestamp" => "-2 days"],
            ],
            "pending move that was queued 2 days ago" => [
                [
                    "mod_timestamp" => "-2 days",
                    "mod_type" => "move",
                ],
            ],

            "large pending edit (more than $wgRCChangedSizeThreshold bytes added)" => [
                [
                    "mod_old_len" => 100,
                    "mod_new_len" => 101 + $wgRCChangedSizeThreshold,
                ],
            ],
            "small pending edit (less than $wgRCChangedSizeThreshold bytes added)" => [
                [
                    "mod_old_len" => 100,
                    "mod_new_len" => 99 + $wgRCChangedSizeThreshold,
                ],
            ],
            "large pending edit (more than $wgRCChangedSizeThreshold bytes deleted)" => [
                [
                    "mod_old_len" => 100 + $wgRCChangedSizeThreshold,
                    "mod_new_len" => 99,
                ],
            ],
            "large pending edit (less than $wgRCChangedSizeThreshold bytes deleted)" => [
                [
                    "mod_old_len" => 100 + $wgRCChangedSizeThreshold,
                    "mod_new_len" => 101,
                ],
            ],
            "edit that was rejected very long ago (shouldn't have Approve link)" => [
                [
                    "expectNotReapprovable" => true,
                    "expectedFolder" => "rejected",
                    "mod_rejected" => 1,
                    "mod_timestamp" => $longAgo,
                ],
            ],
            "edit that was automatically rejected very long ago (shouldn't have Approve link)" => [
                [
                    "expectNotReapprovable" => true,
                    "expectedFolder" => "spam",
                    "mod_rejected" => 1,
                    "mod_rejected_auto" => 1,
                    "mod_timestamp" => $longAgo,
                ],
            ],
            "recently rejected edit (should have Approve link)" => [
                [
                    "expectedFolder" => "rejected",
                    "mod_rejected" => 1,
                    "mod_timestamp" => $notLongAgoEnough,
                ],
            ],
        ];
    }

    /*-------------------------------------------------------------------*/
    /* TestSet of this test                                              */
    /*-------------------------------------------------------------------*/

    use ModerationTestsuitePendingChangeTestSet {
        applyOptions as parentApplyOptions;
    }

    /** @var string Folder of Special:Moderation where this entry should appear */
    protected $expectedFolder = "DEFAULT";

    /** @var bool If true, moderator who visits Special:Moderation will be a checkuser. */
    protected $isCheckuser = false;

    /** @var bool If true, $wgModerationPreviewLink will be enabled. */
    protected $previewLinkEnabled = false;

    /** @var bool If true, $wgModerationEnableEditChange will be enabled. */
    protected $editChangeEnabled = false;

    /**
     * @var bool
     * If true, Approve link should be absent, because the entry was rejected too long ago.
     */
    protected $expectNotReapprovable = false;

    /**
     * Initialize this TestSet from the input of dataProvider.
     * @param array $options
     */
    protected function applyOptions(array $options)
    {
        foreach ($options as $key => $value) {
            switch ($key) {
                case "expectedFolder":
                case "isCheckuser":
                case "previewLinkEnabled":
                case "expectNotReapprovable":
                case "editChangeEnabled":
                    $this->$key = $value;
                    unset($options[$key]);
            }
        }

        $this->parentApplyOptions($options);
    }

    /**
     * Assert the state of the database after the edit.
     */
    protected function assertResults()
    {
        $t = $this->getTestsuite();

        if ($this->isCheckuser) {
            $t->loginAs($t->moderatorAndCheckuser);
        } elseif ($this->notAutomoderated) {
            $t->loginAs($t->moderatorButNotAutomoderated);
        }

        if ($this->previewLinkEnabled) {
            $t->setMwConfig("ModerationPreviewLink", true);
        }

        if ($this->editChangeEnabled) {
            $t->setMwConfig("ModerationEnableEditChange", true);
        }

        $t->fetchSpecial($this->expectedFolder);
        $this->assertCount(
            1,
            $t->new_entries,
            "Incorrect number of entries on Special:Moderation (folder " .
                $this->expectedFolder .
                ").",
        );
        $entry = $t->new_entries[0];

        /* Verify that other Folders of Special:Moderation are empty */
        $this->assertOtherFoldersAreEmpty();

        /* Now we compare $this->fields (expected results)
         with $entry (parsed HTML of Special:Moderation) */
        $this->assertBasicInfo($entry);
        $this->assertTimestamp($entry);
        $this->assertFlags($entry);
        $this->assertLengthChange($entry);
        $this->assertWhoisLink($entry);
        $this->assertMoveEntry($entry);
        $this->assertConflictStatus($entry);
        $this->assertActionLinks($entry);
        $this->assertRejectedBy($entry);
    }

    /**
     * Check whether user, title, comment and ID of $entry are correct.
     * @param ModerationTestsuiteEntry $entry
     */
    protected function assertBasicInfo(ModerationTestsuiteEntry $entry)
    {
        $this->assertSame(
            $this->fields["mod_id"],
            $entry->id,
            "Special:Moderation: ID of the change doesn't match expected",
        );
        $this->assertSame(
            $this->getExpectedTitle(),
            $entry->title,
            "Special:Moderation: Title of the edited page doesn't match expected",
        );
        $this->assertSame(
            $this->fields["mod_user_text"],
            $entry->user,
            "Special:Moderation: Username of the author doesn't match expected",
        );

        MediaWikiServices::getInstance()->getLinkCache()->clear();

        $comment = $this->fields["mod_comment"];
        $title = $this->getExpectedTitleObj();

        $commentFormatter = MediaWikiServices::getInstance()->getCommentFormatter();
        $expectedComment = $commentFormatter->format($comment, $title);

        $this->assertSame(
            $expectedComment,
            $entry->commentHtml ?? "",
            "Special:Moderation: Edit summary doesn't match expected",
        );
    }

    /**
     * Check whether timestamp of $entry is correct.
     * @param ModerationTestsuiteEntry $entry
     */
    protected function assertTimestamp(ModerationTestsuiteEntry $entry)
    {
        $timestamp = wfTimestamp(TS_MW, $this->fields["mod_timestamp"]);

        // When mod_timestamp is today, only time is shown.
        // Otherwise both time and date are shown.
        /*
			FIXME: this test can fail around 23:59 due to fetchSpecial() being called yesterday.
			($expectTimeOnly would be false, but fetchSpecial() showing only the time is correct,
			because yesterday $expectTimeOnly would be true)
			This is the limitation of the test itself, not the code that is being tested.
		*/
        $expectTimeOnly =
            substr($timestamp, 0, 8) == substr(wfTimestampNow(), 0, 8);

        $user = $this->getTestsuite()->moderator;
        $lang = ModerationTestUtil::getLanguageQqx();

        $expectedTime = $lang->userTime($timestamp, $user);
        $expectedDatetime = $expectTimeOnly
            ? $expectedTime
            : $lang->userTimeAndDate($timestamp, $user);

        $this->assertSame(
            $expectedTime,
            $entry->time,
            "Special:Moderation: time of the change doesn't match expected",
        );
        $this->assertSame(
            $expectedDatetime,
            $entry->datetime,
            "Special:Moderation: datetime of the change doesn't match expected",
        );
    }

    /**
     * Check whether minor/bot/newpage edits are properly marked.
     * @param ModerationTestsuiteEntry $entry
     */
    protected function assertFlags(ModerationTestsuiteEntry $entry)
    {
        $expectedFlags = [
            "is minor edit" => (bool) $this->fields["mod_minor"],
            "is bot edit" => (bool) $this->fields["mod_bot"],
            "is creation of new page" => (bool) $this->fields["mod_new"],
        ];
        $shownFlags = [
            "is minor edit" => $entry->minor,
            "is bot edit" => $entry->bot,
            "is creation of new page" => $entry->new,
        ];

        $this->assertSame(
            $expectedFlags,
            $shownFlags,
            "Special:Moderation: Incorrect entry flags.",
        );
    }

    /**
     * Check whether the difference between len_old/len_new is properly shown.
     * @param ModerationTestsuiteEntry $entry
     */
    protected function assertLengthChange(ModerationTestsuiteEntry $entry)
    {
        global $wgRCChangedSizeThreshold;

        $expectedChange =
            $this->fields["mod_new_len"] - $this->fields["mod_old_len"];

        $this->assertSame(
            [
                "change in length" => $expectedChange,
                "is length change hightlighted?" =>
                    abs($expectedChange) >= $wgRCChangedSizeThreshold,
            ],
            [
                "change in length" => $entry->charChange,
                "is length change hightlighted?" => $entry->charChangeBold,
            ],
        );
    }

    /**
     * Check whether the change is marked as edit conflict.
     * @param ModerationTestsuiteEntry $entry
     */
    protected function assertConflictStatus(ModerationTestsuiteEntry $entry)
    {
        $this->assertSame(
            [
                "shown as edit conflict?" =>
                    (bool) $this->fields["mod_conflict"],
            ],
            [
                "shown as edit conflict?" => $entry->conflict,
            ],
        );
    }

    /**
     * Assert that all folders (except expectedFolder) are empty.
     */
    protected function assertOtherFoldersAreEmpty()
    {
        $knownFolders = ["DEFAULT", "rejected", "spam", "merged"];
        $t = $this->getTestsuite();

        foreach ($knownFolders as $folder) {
            if ($folder != $this->expectedFolder) {
                $t->fetchSpecial($folder);
                $this->assertEmpty(
                    $t->new_entries,
                    "Unexpected entry found in folder \"$folder\" of Special:Moderation " .
                        "(this folder should be empty).",
                );
            }
        }
    }

    /**
     * Assert that Whois link is always shown for anonymous users,
     * and only to checkusers for registered users.
     * @param ModerationTestsuiteEntry $entry
     */
    protected function assertWhoisLink(ModerationTestsuiteEntry $entry)
    {
        if ($this->fields["mod_user"] == 0) {
            $this->assertSame(
                $this->fields["mod_user_text"],
                $entry->ip,
                "Special:Moderation: incorrect Whois link for anonymous user.",
            );
        } else {
            if ($this->isCheckuser) {
                $this->assertSame(
                    $this->fields["mod_ip"],
                    $entry->ip,
                    "Special:Moderation (viewed by checkuser): incorrect Whois link for registered user.",
                );
            } else {
                $this->assertNull(
                    $entry->ip,
                    "Special:Moderation: Whois link shown to non-checkuser.",
                );
            }
        }
    }

    /**
     * Check that the formatting of "suggested move" entry is correct.
     * @param ModerationTestsuiteEntry $entry
     */
    protected function assertMoveEntry(ModerationTestsuiteEntry $entry)
    {
        if ($this->fields["mod_type"] == "move") {
            $this->assertTrue(
                $entry->isMove,
                "Special:Moderation: incorrect formatting of the move entry.",
            );

            $this->assertSame(
                $this->getExpectedPage2Title(),
                $entry->page2Title,
                "Special:Moderation: New Title of suggested move doesn't match expected",
            );
        }
    }

    /**
     * Verify that only the needed action links are shown.
     * @param ModerationTestsuiteEntry $entry
     */
    protected function assertActionLinks(ModerationTestsuiteEntry $entry)
    {
        $expectedLinks = array_fill_keys(
            [
                // Fields of $entry
                "show",
                "preview",
                "approve",
                "approveall",
                "reject",
                "rejectall",
                "block",
                "unblock",
                "merge",
                "mergedDiff",
            ],
            false,
        );

        switch ($this->expectedFolder) {
            case "rejected":
            case "spam":
                if (!$this->expectNotReapprovable) {
                    $expectedLinks["approve"] = true;
                }
                break;

            case "merged":
                $expectedLinks["mergedDiff"] = true;
                break;

            default:
                $expectedLinks =
                    [
                        "approve" => true,
                        "approveall" => true,
                        "reject" => true,
                        "rejectall" => true,
                    ] + $expectedLinks;
        }

        if (
            $this->fields["mod_conflict"] &&
            $this->expectedFolder != "merged"
        ) {
            $expectedLinks["approve"] = false;
            $expectedLinks["approveall"] = false;

            if ($this->notAutomoderated) {
                $this->assertTrue(
                    $entry->noMergeNotAutomoderated,
                    "Special:Moderation: non-automoderated moderator doesn't see \"Can't merge\" message",
                );
            } else {
                $expectedLinks["merge"] = true;
            }
        }

        if ($this->fields["mod_type"] != "move") {
            $expectedLinks["show"] = true;

            if ($this->previewLinkEnabled) {
                $expectedLinks["preview"] = true;
            }

            if ($this->editChangeEnabled) {
                $expectedLinks["editchange"] = true;
            }
        }

        if ($this->modblocked) {
            $expectedLinks["unblock"] = true;
        } else {
            $expectedLinks["block"] = true;
        }

        foreach ($expectedLinks as $action => $isExpected) {
            $url = $entry->getActionLink($action);

            if ($isExpected) {
                $this->assertNotNull(
                    $url,
                    "Special:Moderation: expected link [$action] is not shown.",
                );
                $this->assertActionLinkURL($action, $url);
            } else {
                $this->assertNull(
                    $url,
                    "Special:Moderation: found unexpected [$action] link (it shouldn't be here).",
                );
            }
        }
    }

    /**
     * Check whether the URL of action link is correct.
     * @param string $action Name of modaction (e.g. 'rejectall') or 'mergedDiff'.
     * @param string $url
     */
    protected function assertActionLinkURL($action, $url)
    {
        if ($action == "mergedDiff") {
            $this->assertQueryString($url, [
                "title" => strtr($this->getExpectedTitle(), " ", "_"),
                "diff" => (string) $this->fields["mod_merged_revid"],
            ]);
        } else {
            $expectedQuery = [
                "title" => SpecialPage::getTitleFor(
                    "Moderation",
                )->getFullText(),
                "modaction" => $action,
                "modid" => (string) $this->fields["mod_id"],
            ];
            if ($action != "show" && $action != "preview") {
                $expectedQuery["token"] = null;
            }

            $this->assertQueryString($url, $expectedQuery);
        }
    }

    /**
     * Parse $url and assert the presence of needed QueryString parameters.
     * @param string $url
     * @param array $expectedQuery Querystring arguments: [ key1 => value1, ... ]
     */
    protected function assertQueryString($url, array $expectedQuery)
    {
        $bits = ModerationTestUtil::parseUrl($url);
        $query = wfCgiToArray($bits["query"]);

        if (
            array_key_exists("token", $expectedQuery) &&
            $expectedQuery["token"] === null
        ) {
            $this->assertMatchesRegularExpression(
                "/[+0-9a-f]+/",
                $query["token"],
                "QueryString of [$url]: incorrect format of CSRF token.",
            );
            $expectedQuery["token"] = $query["token"];
        }

        asort($query);
        asort($expectedQuery);

        $this->assertSame(
            $expectedQuery,
            $query,
            "QueryString of [$url] doesn't match expected",
        );
    }

    /**
     * Check information about who and how rejected this edit.
     * @param ModerationTestsuiteEntry $entry
     */
    protected function assertRejectedBy(ModerationTestsuiteEntry $entry)
    {
        $this->assertSame(
            $this->fields["mod_rejected_by_user_text"],
            $entry->rejected_by_user,
            "Special:Moderation: incorrect name of moderator who rejected the edit",
        );

        $this->assertSame(
            [
                "rejected via RejectAll" =>
                    (bool) $this->fields["mod_rejected_batch"],
                "rejected automatically" =>
                    (bool) $this->fields["mod_rejected_auto"],
            ],
            [
                "rejected via RejectAll" => $entry->rejected_batch,
                "rejected automatically" => $entry->rejected_auto,
            ],
        );
    }
}
