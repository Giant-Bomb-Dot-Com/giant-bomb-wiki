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
 * Checks SQL table 'moderation' after the edit.
 */

namespace MediaWiki\Moderation\Tests;

use ContentHandler;
use IDBAccessObject;
use MediaWiki\MediaWikiServices;
use MediaWiki\Moderation\ModerationCompatTools;
use MediaWiki\Moderation\ModerationUploadStorage;
use MediaWiki\Title\Title;
use ParserOptions;
use ReflectionClass;
use SpecialPage;
use UnexpectedValueException;
use User;

require_once __DIR__ . "/../../framework/ModerationTestsuite.php";

/**
 * @group Database
 * @covers MediaWiki\Moderation\ModerationNewChange
 */
class ModerationQueueTest extends ModerationTestCase
{
    /**
     * @dataProvider dataProvider
     */
    public function testQueue(array $options)
    {
        $this->runSet($options);
    }

    /**
     * Provide datasets for testQueue() runs.
     */
    public function dataProvider()
    {
        return [
            "anonymous edit" => [["anonymously" => true]],
            "edit by unprivileged user #1" => [[]],
            "edit by unprivileged user #2" => [["user" => "User 6"]],
            "edit in the article without spaces in the title" => [
                ["title" => "TitleWithoutSpaces"],
            ],
            "edit in the article with spaces in the title" => [
                ["title" => "Title with spaces"],
            ],
            'edit in the article with underscores ("_") in the title' => [
                ["title" => "Title_with_underscores"],
            ],
            "edit in Project namespace" => [
                [
                    "title" => "Project:Title_in_another_namespace",
                ],
            ],
            "edit with plain text only" => [["text" => "Interesting text 1"]],
            "edit with complex wikitext" => [
                [
                    "text" =>
                        "Wikitext with [[links]] and {{templates}} and something",
                ],
            ],
            'edit with "~~~~" in its wikitext (should be replaced by PreSaveTransform)' => [
                [
                    "text" => "Text before signature ~~~ Text after signature",
                    "needPst" => true,
                ],
            ],
            "edit with summary #1" => [["summary" => "Summary 1"]],
            "edit with summary #2" => [["summary" => "Summary 2"]],
            "edit with very long summary (>255 bytes)" => [
                [
                    "summary" => str_repeat("length 16 string", 100),
                ],
            ],
            "edit with User-Agent #1" => [
                ["userAgent" => "UserAgent for Testing/1.0"],
            ],
            "edit with User-Agent #2" => [
                [
                    "userAgent" =>
                        "Mozilla/5.0 (iPhone; CPU iPhone OS 10_3_1 like Mac OS X) " .
                        "AppleWebKit/603.1.30 (KHTML, like Gecko) Version/10.0 " .
                        "Mobile/14E304 Safari/602.1",
                ],
            ],
            "image uploaded via Special:Upload" => [
                ["filename" => "image100x100.png"],
            ],
            "image uploaded via Special:Upload (stash owner precreated)" => [
                [
                    "filename" => "image100x100.png",
                    "precreateUploadStashOwner" => true,
                ],
            ],
            "image uploaded via API" => [
                [
                    "filename" => "image100x100.png",
                    "viaApi" => true,
                ],
            ],
            "image uploaded via API (stash owner precreated)" => [
                [
                    "filename" => "image100x100.png",
                    "viaApi" => true,
                    "precreateUploadStashOwner" => true,
                ],
            ],
            'image with "~~~~" in its description (should be replaced by PreSaveTransform)' => [
                [
                    "filename" => "image100x100.png",
                    "text" => "Before ~~~ After",
                    "needPst" => true,
                ],
            ],
            "edit in existing page" => [["existing" => true]],
            "image reupload" => [
                [
                    "existing" => true,
                    "filename" => "image100x100.png",
                ],
            ],
            "image reupload (stash owner precreated)" => [
                [
                    "existing" => true,
                    "filename" => "image100x100.png",
                    "precreateUploadStashOwner" => true,
                ],
            ],
            "anonymous upload" => [
                [
                    "filename" => "image100x100.png",
                    "anonymously" => true,
                ],
            ],
            "anonymous upload (stash owner precreated)" => [
                [
                    "filename" => "image100x100.png",
                    "anonymously" => true,
                    "precreateUploadStashOwner" => true,
                ],
            ],
            "page move (new title with spaces)" => [
                [
                    "title" => "Old title",
                    "newTitle" => "New title with spaces",
                ],
            ],
            "page move (new title with underscores)" => [
                [
                    "title" => "Old title",
                    "newTitle" => "New_title_with_underscores",
                    "summary" => "New title is cooler",
                ],
            ],
            "page move via API" => [
                [
                    "title" => "Title 1",
                    "newTitle" => "Title 2",
                    "viaApi" => true,
                ],
            ],
            "edit by modblocked user" => [["modblocked" => true]],
            "anonymous edit from modblocked IP" => [
                ["modblocked" => true, "anonymously" => true],
            ],
            "edit by modblocked user via API" => [
                ["modblocked" => true, "viaApi" => true],
            ],
            'edit with "X-Forwarded-For: <ipv4 address>"' => [
                ["xff" => "127.1.2.3", "viaApi" => true],
            ],
            'edit with "X-Forwarded-For: <ipv4 address #1>, <ip #2>, <ip #3>"' => [
                [
                    "xff" => "203.0.113.195, 70.41.3.18, 150.172.238.178",
                ],
            ],
            'edit with "X-Forwarded-For: <ipv6 address>"' => [
                [
                    "xff" => "2001:db8:85a3:8d3:1319:8a2e:370:7348",
                ],
            ],

            'non-API edit with enabled "Watch this page" checkbox' => [
                ["watch" => true],
            ],
            'non-API edit with disabled "Watch this page" checkbox' => [
                ["unwatch" => true],
            ],
            'non-API upload with enabled "Watch this file" checkbox' => [
                [
                    "watch" => true,
                    "filename" => "image100x100.png",
                ],
            ],
            'non-API upload with disabled "Watch this file" checkbox' => [
                [
                    "unwatch" => true,
                    "filename" => "image100x100.png",
                ],
            ],
            'non-API move with enabled "Watch source page and target page" checkbox' => [
                ["watch" => true, "newTitle" => "Title #2"],
            ],
            'non-API move with disabled "Watch source page and target page" checkbox' => [
                ["unwatch" => true, "newTitle" => "Title #2"],
            ],
            'anonymous non-API edit: enabled "Watch this page" checkbox should be ignored' => [
                ["watch" => true, "anonymously" => true],
            ],
            'anonymous non-API edit: disabled "Watch this page" checkbox should be ignored' => [
                ["unwatch" => true, "anonymously" => true],
            ],

            "email notification about anonymous edit" => [
                [
                    "anonymously" => true,
                    "notifyEmail" => "noreply@localhost",
                ],
            ],
            "email notification about edit by unprivileged user" => [
                ["notifyEmail" => "noreply@localhost"],
            ],
            "email notification about edit in existing page" => [
                [
                    "existing" => true,
                    "notifyEmail" => "noreply@localhost",
                ],
            ],
            "email notification about upload" => [
                [
                    "filename" => "image100x100.png",
                    "notifyEmail" => "noreply@localhost",
                ],
            ],
            "email notification about page move" => [
                [
                    "title" => "Title 1",
                    "newTitle" => "Title 2",
                    "notifyEmail" => "noreply@localhost",
                ],
            ],
            'absence of email for existing page when $wgModerationNotificationNewOnly=true' => [
                [
                    "existing" => true,
                    "notifyNewOnly" => true,
                    "notifyEmail" => "noreply@localhost",
                ],
            ],
            'absence of email for reupload when $wgModerationNotificationNewOnly=true' => [
                [
                    "filename" => "image100x100.png",
                    "notifyNewOnly" => true,
                    "notifyEmail" => "noreply@localhost",
                ],
            ],
            "absence of email for edit by modblocked user" => [
                [
                    "modblocked" => true,
                    "notifyEmail" => "noreply@localhost",
                ],
            ],

            // Minor edits
            "minor edit" => [["minor" => true, "existing" => true]],
            "minor edit via API" => [
                [
                    "minor" => true,
                    "existing" => true,
                    "viaApi" => true,
                ],
            ],

            // MediaWiki ignores "minor" flag for edits that create a new page.
            "ignored minor edit flag (new page)" => [["minor" => true]],
            "ignored minor edit flag (new page) via API" => [
                ["minor" => true, "viaApi" => true],
            ],

            // Change tags added by AbuseFilter
            "edit tagged by AbuseFilter" => [
                [
                    "tags" => [
                        "Author of edit likes cats",
                        "Author of edit likes dogs",
                    ],
                ],
            ],
            "upload tagged by AbuseFilter" => [
                [
                    "filename" => "image100x100.png",
                    "tags" => [
                        "Author of edit likes cats",
                        "Author of edit likes dogs",
                    ],
                ],
            ],
            "page move tagged by AbuseFilter" => [
                [
                    "title" => "Old title",
                    "newTitle" => "New title",
                    "tags" => [
                        "Author of edit likes cats",
                        "Author of edit likes dogs",
                    ],
                ],
            ],
            "edit marked as spam by AbuseFilter" => [
                [
                    "tags" => ["moderation-spam"],
                ],
            ],
            "edit marked as spam AND tagged by AbuseFilter" => [
                [
                    "tags" => [
                        "Author of edit likes cats",
                        "Author of edit likes dogs",
                        "moderation-spam",
                    ],
                ],
            ],
        ];
    }

    /*-------------------------------------------------------------------*/
    /* TestSet of this test                                              */
    /*-------------------------------------------------------------------*/

    use ModerationTestsuiteTestSet;

    /** @var User */
    protected $user = null;

    /** @var Title */
    protected $title = null;

    /** @var Title Second title, only used for moves. */
    protected $newTitle = null;

    /** @var string */
    protected $text = "Hello, World!";

    /** @var string */
    protected $summary = "Edit by the Moderation Testsuite";

    /** @var string */
    protected $userAgent = ModerationTestsuite::DEFAULT_USER_AGENT;

    /** @var string|null X-Forwarded-For header */
    protected $xff = null;

    /** @var string|null Source filename, only used for uploads. */
    protected $filename = null;

    /**
     * @var bool If true, system user that owns UploadStash will be precreated.
     * This setting allows to test uploads both with and without UploadStorage migration.
     */
    protected $precreateUploadStashOwner = false;

    /** @var bool If true, the edit will be anonymous. ($user will be ignored) */
    protected $anonymously = false;

    /** @var bool If true, edit will be marked as minor. */
    protected $minor = false;

    /** @var bool If true, edits are made via API, if false, via the user interface. */
    protected $viaApi = false;

    /** @var bool If true, text is expected to be altered by PreSaveTransform (e.g. contains "~~~~"). */
    protected $needPst = false;

    /**
     * @var bool
     * If true, existing page will be edited. If false, new page will be created.
     */
    protected $existing = false;

    /** @var string Text of existing article (if any) */
    protected $oldText = "";

    /** @var bool If true, user will be modblocked before the edit */
    protected $modblocked = false;

    /** @var bool|null If true/false, defines the state of "Watch this page" checkbox. */
    protected $watch = null;

    /** @var string|false Email address (to enable notifications about new edits) or false. */
    protected $notifyEmail = false;

    /** @var bool If true, $wgModerationNotificationNewOnly is set to true. */
    protected $notifyNewOnly = false;

    /** @var string[] Tags that AbuseFilter will add to this edit. */
    protected $tags = [];

    /**
     * Initialize this TestSet from the input of dataProvider.
     * @param array $options
     */
    protected function applyOptions(array $options)
    {
        foreach ($options as $key => $value) {
            switch ($key) {
                case "user":
                    $this->user = User::newFromName($value);
                    break;

                case "title":
                    $this->title = Title::newFromText($value);
                    break;

                case "newTitle":
                    $this->newTitle = Title::newFromText($value);
                    break;

                case "watch":
                case "unwatch":
                    $this->watch = $key == "watch";
                    break;

                case "text":
                case "summary":
                case "userAgent":
                case "xff":
                case "filename":
                case "precreateUploadStashOwner":
                case "anonymously":
                case "minor":
                case "viaApi":
                case "needPst":
                case "existing":
                case "modblocked":
                case "notifyEmail":
                case "notifyNewOnly":
                case "tags":
                    $this->$key = $value;
                    break;

                default:
                    throw new UnexpectedValueException(
                        __CLASS__ . ": unknown key {$key} in options",
                    );
            }
        }

        /* Default options */
        if ($this->anonymously) {
            $this->user = User::newFromName("127.0.0.1", false);
        } elseif (!$this->user) {
            $this->user = User::newFromName("User 5");
        }

        if (!$this->title) {
            $suffix = $this->getTestsuite()->uniqueSuffix();
            $pageName = $this->filename
                ? "File:Test image $suffix.png"
                : "Test page $suffix";
            $this->title = Title::newFromText($pageName);
        }

        /* Shouldn't contain PreSaveTransform-affected text, e.g. "~~~~" */
        $this->oldText = wfTimestampNow() . "_" . rand() . "_OldText";
        if ($this->oldText == $this->text) {
            $this->oldText .= "+"; // Ensure that $oldText is different from $text
        }

        if ($this->anonymously && $this->filename) {
            // Test of anonymous uploads: allow anonymous users to upload files
            // (normally they are not permitted to do so).
            $this->setGroupPermissions("*", "upload", true);
        }

        if ($this->precreateUploadStashOwner) {
            $user = User::newSystemUser(ModerationUploadStorage::USERNAME, [
                "steal" => true,
            ]);
            $this->assertNotNull($user);
        }
    }

    /**
     * Assert the state of the database after the edit.
     */
    protected function assertResults()
    {
        $row = $this->assertRowEquals($this->getExpectedRow());

        $this->assertTimestampIsRecent($row->mod_timestamp);
        $this->checkUpload($row->mod_stash_key);
        $this->checkWatchlist($this->watch);
        $this->assertHooksWereCalled();
    }

    /**
     * Execute the TestSet, making an edit/upload/move with requested parameters.
     */
    protected function makeChanges()
    {
        $t = $this->getTestsuite();
        $t->setUserAgent($this->userAgent);

        if ($this->xff) {
            $t->setHeader("X-Forwarded-For", $this->xff);
        }

        if ($this->notifyEmail) {
            // Test emails that were sent to $wgModerationEmail about new edit being queued.
            $t->setMwConfig("ModerationEmail", $this->notifyEmail);
            $t->setMwConfig("ModerationNotificationEnable", true);
        }

        if ($this->notifyNewOnly) {
            $t->setMwConfig("ModerationNotificationNewOnly", true);
        }

        if ($this->existing || $this->newTitle) {
            $this->precreatePage($this->title, $this->oldText, $this->filename);
        }

        $t->loginAs($this->user);

        if ($this->modblocked) {
            /* Apply ModerationBlock to $this->user */
            $t->modblock($this->user);
        }

        if ($this->watch === false) {
            /* Unwatch test requested, add $this->title into the Watchlist */
            $watchlistManager = MediaWikiServices::getInstance()->getWatchlistManager();
            $watchlistManager->addWatchIgnoringRights(
                $this->user,
                $this->title,
            );
        }

        $abuseFilterId = null;
        if ($this->tags) {
            $this->requireExtension("Abuse Filter");
            $abuseFilterId = $t->addTagAllAbuseFilter($this->tags);
        }

        $extraParams = [];
        if ($this->watch === true) {
            $watchField = $this->newTitle ? "wpWatch" : "wpWatchthis";
            $extraParams[$watchField] = 1;
        }

        $bot = $t->getBot($this->viaApi ? "api" : "nonApi");

        $t->trackHook("ModerationIntercept");
        $t->trackHook("ModerationPending");
        $t->trackHook("AlternateUserMailer"); // To check notification emails

        if ($this->filename) {
            /* Upload */
            $result = $bot->upload(
                $this->title->getText() /* Without "File:" namespace prefix */,
                $this->filename,
                $this->text,
                $extraParams,
            );
            $this->assertTrue(
                $result->isIntercepted(),
                "Upload wasn't intercepted by Moderation.",
            );
        } elseif ($this->newTitle) {
            $result = $bot->move(
                $this->title->getFullText(),
                $this->newTitle->getFullText(),
                $this->summary,
                $extraParams,
            );
            $this->assertTrue(
                $result->isIntercepted(),
                "Move wasn't intercepted by Moderation.",
            );
        } else {
            /* Normal edit */
            if ($this->minor) {
                $minorField = $this->viaApi ? "minor" : "wpMinoredit";
                $extraParams[$minorField] = 1;
            }

            $result = $bot->edit(
                $this->title->getFullText(),
                $this->text,
                $this->summary,
                "",
                $extraParams,
            );
            $this->assertTrue(
                $result->isIntercepted(),
                "Edit wasn't intercepted by Moderation.",
            );
        }

        if ($abuseFilterId) {
            /* Disable the filter (so that it would no longer add tags to newly made edits). */
            $t->disableAbuseFilter($abuseFilterId);
        }
    }

    /**
     * Assert that necessary hooks were called during the test.
     */
    protected function assertHooksWereCalled()
    {
        $this->assertModerationInterceptWasCalled();
        $this->assertModerationPendingWasCalled();
        $this->assertEmailWasSent();
    }

    /**
     * Assert that AlternateUserMailer hook has detected a "new change was queued" email.
     */
    protected function assertEmailWasSent()
    {
        $hooks = $this->getTestsuite()->getCapturedHooks("AlternateUserMailer");
        if (!$this->notifyEmail) {
            $this->assertEmpty(
                $hooks,
                "An email was sent, even though \$wgModerationNotificationEnable " .
                    "wasn't set to true.",
            );
            return;
        }

        if ($this->isExpectedToBeSpam()) {
            $this->assertEmpty(
                $hooks,
                "An email was sent after a spam edit (e.g. edit from modblocked user).",
            );
            return;
        }

        if ($this->notifyNewOnly && $this->existing) {
            $this->assertEmpty(
                $hooks,
                "An email was sent for existing page, even though " .
                    "\$wgModerationNotificationNewOnly was set to true.",
            );
            return;
        }

        $this->assertCount(
            1,
            $hooks,
            "Number of emails that were sent isn't 1.",
        );
        [, $to, $from, $subject, $body] = $hooks[0][1];

        global $wgPasswordSender;
        $this->assertSame($wgPasswordSender, $from["address"]);
        $this->assertSame($this->notifyEmail, $to[0]["address"]);
        $this->assertSame("(moderation-notification-subject)", $subject);

        $modid = ModerationCompatTools::getDB(DB_PRIMARY)->selectField(
            "moderation",
            "mod_id",
            "",
            __METHOD__,
        );
        $this->assertSame(
            "(moderation-notification-content: " .
                $this->title->getFullText() .
                ", " .
                $this->user->getName() .
                ", " .
                SpecialPage::getTitleFor("Moderation")->getCanonicalURL([
                    "modaction" => "show",
                    "modid" => $modid,
                ]) .
                ")",
            $body,
        );
    }

    /**
     * Assert that ModerationIntercept hook was called during the test.
     */
    protected function assertModerationInterceptWasCalled()
    {
        $hooks = $this->getTestsuite()->getCapturedHooks("ModerationIntercept");
        if ($this->newTitle) {
            // ModerationIntercept hook shouldn't be called when renaming a page.
            $this->assertEmpty(
                $hooks,
                "ModerationIntercept hook was called when renaming a page.",
            );
            return;
        }

        if ($this->filename) {
            // ModerationIntercept hook shouldn't be called for uploads.
            $this->assertEmpty(
                $hooks,
                "ModerationIntercept hook was called for upload.",
            );
            return;
        }

        // Normal edit or upload.
        $this->assertNotEmpty(
            $hooks,
            "ModerationIntercept hook wasn't called.",
        );
        $this->assertCount(
            1,
            $hooks,
            "Number of times ModerationIntercept hook was called isn't 1.",
        );

        [$paramTypes, $params] = $hooks[0];

        $expectedPageClass = "MediaWiki\Page\WikiPage"; // MediaWiki 1.44+
        if (!class_exists($expectedPageClass, false)) {
            // MediaWiki 1.43 only
            $expectedPageClass = "WikiPage";
        }
        $this->assertSame($expectedPageClass, $paramTypes[0]);
        $this->assertSame("MediaWiki\User\User", $paramTypes[1]);

        $this->assertTrue(
            (new ReflectionClass($paramTypes[2]))->implementsInterface(
                "Content",
            ),
        );
        $this->assertSame("string", $paramTypes[3]); // $summary

        // $is_minor: 0 or EDIT_MINOR (int, not bool), same as received in PageContentSave hook
        $this->assertSame("integer", $paramTypes[4]);

        $this->assertSame("NULL", $paramTypes[5]); // Unused
        $this->assertSame("NULL", $paramTypes[6]); // Unused
        $this->assertSame("integer", $paramTypes[7]); // $flags
        $this->assertSame("MediaWiki\Storage\PageUpdateStatus", $paramTypes[8]);

        // FIXME: loss of types during JSON serialization is inconvenient.
        // serialize() is not currently used, because some classes have callbacks, etc.,
        // and where json_encode would provide an empty value, serialize() would fail completely.

        $this->assertSame($this->title->getFullText(), $params[0]);
        $this->assertSame($this->user->getName(), $params[1]["mName"]);
        // $params[2] is not serialiable
        $this->assertSame($this->getExpectedSummary(), $params[3]);

        $minorFlag = $this->minor && $this->existing ? EDIT_MINOR : 0;
        $this->assertSame($minorFlag, $params[4]);

        $this->assertNull($params[5]); // Unused parameter, always NULL
        $this->assertNull($params[6]); // Unused parameter, always NULL

        $expectedFlags =
            $minorFlag | ($this->existing ? EDIT_UPDATE : EDIT_NEW);
        if (!$this->filename) {
            $expectedFlags |= EDIT_AUTOSUMMARY;
        }

        $this->assertSame($expectedFlags, $params[7]);
        $this->assertSame(0, $params[8]["failCount"]);
    }

    /**
     * Assert that ModerationPending hook was called during the test.
     */
    protected function assertModerationPendingWasCalled()
    {
        $hooks = $this->getTestsuite()->getCapturedHooks("ModerationPending");
        $this->assertNotEmpty($hooks, "ModerationPending hook wasn't called.");
        $this->assertCount(
            1,
            $hooks,
            "Number of times ModerationPending hook was called isn't 1.",
        );

        [$paramTypes, $params] = $hooks[0];
        $this->assertSame("array", $paramTypes[0]);
        $this->assertSame("integer", $paramTypes[1]);

        [$fields, $id] = $params;
        $this->assertArrayNotHasKey("mod_id", $fields);

        // Compare parameters received by ModerationPending hook
        // with what was actually inserted into the database.

        $expectedRow = $fields;
        $expectedRow["mod_id"] = $id;
        unset($expectedRow["mod_stash_key"]); // This hook is called before stash_key is known

        $this->assertRowEquals($expectedRow);
    }

    /**
     * Return non-truncated edit summary that was in the user's request.
     * @return string
     */
    protected function getSubmittedSummary()
    {
        if (!$this->filename) {
            /* Normal edit (not an upload) */
            return $this->summary;
        }

        if ($this->viaApi) {
            /* API has different parameters for 'text' and 'summary' */
            return "";
        }

        /* Special:Upload copies text into summary */
        return $this->text;
    }

    /**
     * Return expected post-edit edit summary (value of mod_comment DB field).
     * @return string
     */
    protected function getExpectedSummary()
    {
        return mb_strcut($this->getSubmittedSummary(), 0, 250);
    }

    /**
     * Return true if edit is supposed to end up in the Spam folder, false otherwise.
     * @return bool
     */
    protected function isExpectedToBeSpam()
    {
        return $this->modblocked || in_array("moderation-spam", $this->tags);
    }

    /**
     * Returns array of expected post-edit values of all mod_* fields in the database.
     * @note Values like "/value/" are treated as regular expressions.
     * @return array [ 'mod_user' => ..., 'mod_namespace' => ..., ... ]
     */
    protected function getExpectedRow()
    {
        $expectedContent = ContentHandler::makeContent(
            $this->text,
            null,
            CONTENT_MODEL_WIKITEXT,
        );
        if ($this->needPst) {
            /* Not done for all tests to make tests faster.
				DataSet must explicitly indicate that its text needs PreSaveTransform.
			*/
            $lang = MediaWikiServices::getInstance()->getContentLanguage();
            $expectedContent = ModerationCompatTools::preSaveTransform(
                $expectedContent,
                $this->title,
                $this->user,
                ParserOptions::newFromUserAndLang($this->user, $lang),
            );
        }

        $expectedText = $expectedContent->serialize();

        if ($this->filename && !$this->viaApi) {
            /* Special:Upload prepends InitialText with "== Summary ==" header */
            $headerText = "== (filedesc) ==";
            $expectedText = "$headerText\n$expectedText";
        }

        $isSpam = $this->isExpectedToBeSpam();
        $expectedTags = array_filter($this->tags, static function ($tag) {
            // All tags except "moderation-spam" are preserved.
            return $tag !== "moderation-spam";
        });

        return [
            "mod_id" => new ModerationTestSetRegex('/^[0-9]+$/'),
            "mod_timestamp" => new ModerationTestSetRegex('/^[0-9]{14}$/'),
            "mod_user" => $this->user->getId(),
            "mod_user_text" => $this->user->getName(),
            "mod_cur_id" => $this->existing
                ? $this->title->getArticleId(IDBAccessObject::READ_LATEST)
                : 0,
            "mod_namespace" => $this->title->getNamespace(),
            "mod_title" => $this->title->getDBKey(),
            "mod_comment" => $this->getExpectedSummary(),
            "mod_minor" => $this->minor && $this->existing ? 1 : 0,
            "mod_bot" => 0,
            "mod_new" => $this->existing || $this->newTitle ? 0 : 1,
            "mod_last_oldid" => $this->existing
                ? $this->title->getLatestRevID(IDBAccessObject::READ_LATEST)
                : 0,
            "mod_ip" => "127.0.0.1",
            "mod_old_len" => $this->existing ? strlen($this->oldText) : 0,
            "mod_new_len" => $this->newTitle ? 0 : strlen($expectedText),
            "mod_header_xff" => $this->xff ?: null,
            "mod_header_ua" => $this->userAgent,
            "mod_preload_id" => $this->user->isRegistered()
                ? "[" . $this->user->getName()
                : new ModerationTestSetRegex('/^\][0-9a-f]+$/'),
            "mod_rejected" => $isSpam ? 1 : 0,
            "mod_rejected_by_user" => 0,
            "mod_rejected_by_user_text" => $isSpam
                ? "(moderation-blocker)"
                : null,
            "mod_rejected_batch" => 0,
            "mod_rejected_auto" => $isSpam ? 1 : 0,
            "mod_preloadable" => 0,
            "mod_conflict" => 0,
            "mod_merged_revid" => 0,
            "mod_text" => $this->newTitle ? "" : $expectedText,
            "mod_stash_key" => $this->filename
                ? new ModerationTestSetRegex('/^[0-9a-z\.]+$/i')
                : null,
            "mod_tags" => $expectedTags ? implode("\n", $expectedTags) : null,
            "mod_type" => $this->newTitle ? "move" : "edit",
            "mod_page2_namespace" => $this->newTitle
                ? $this->newTitle->getNamespace()
                : 0,
            "mod_page2_title" => $this->newTitle
                ? $this->newTitle->getDBKey()
                : "",
        ];
    }

    /**
     * Assert the state of UploadStash after the test.
     * @param string $stashKey Value of mod_stash_key (as found in the database after the test).
     */
    protected function checkUpload($stashKey)
    {
        if (!$this->filename) {
            return; // Not an upload, nothing to do
        }

        /* Check that UploadStash contains the newly uploaded file */
        $srcPath = ModerationTestsuite::findSourceFilename($this->filename);
        $expectedContents = file_get_contents($srcPath);

        $stash = ModerationUploadStorage::getStash();
        $file = $stash->getFile($stashKey);
        $contents = file_get_contents($file->getLocalRefPath());

        $this->assertSame(
            $expectedContents,
            $contents,
            "Stashed file is different from uploaded file",
        );
    }

    /**
     * Assert the state of Watchlist after the test.
     * @param bool|null $expected
     */
    protected function checkWatchlist($expected)
    {
        if ($expected === null) {
            return; // Watch/Unwatch test not requested
        }

        if ($this->user->isAnon()) {
            $expected = false; /* Anonymous users don't have a watchlist */
        }

        $this->assertWatched($expected, $this->title);
        if ($this->newTitle) {
            $this->assertWatched($expected, $this->newTitle);
        }
    }

    /**
     * Assert that $title is watched/unwatched.
     * @param bool $expectedState True if $title should be watched, false if not.
     * @param Title $title
     */
    protected function assertWatched($expectedState, Title $title)
    {
        // Note: $user->isWatched() can't be used,
        // because it would return cached results.
        $watchedItemStore = MediaWikiServices::getInstance()->getWatchedItemStore();

        $isWatched = (bool) $watchedItemStore->loadWatchedItem(
            $this->user,
            $title,
        );
        if ($expectedState) {
            $this->assertTrue(
                $isWatched,
                "Page edited with \"Watch this page\" is not in watchlist",
            );
        } else {
            $this->assertFalse(
                $isWatched,
                "Page edited without \"Watch this page\" was not deleted from the watchlist",
            );
        }
    }
}
