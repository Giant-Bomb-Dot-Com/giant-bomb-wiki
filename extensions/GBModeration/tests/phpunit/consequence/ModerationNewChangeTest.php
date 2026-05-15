<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2021-2024 Edward Chernenko.

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
 * Unit test of ModerationNewChange.
 */

namespace MediaWiki\Moderation\Tests;

use Content;
use ContentHandler;
use ExtensionRegistry;
use Language;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagger;
use MediaWiki\Moderation\Hook\HookRunner;
use MediaWiki\Moderation\IConsequenceManager;
use MediaWiki\Moderation\InsertRowIntoModerationTableConsequence;
use MediaWiki\Moderation\ModerationBlockCheck;
use MediaWiki\Moderation\ModerationNewChange;
use MediaWiki\Moderation\ModerationNotifyModerator;
use MediaWiki\Moderation\ModerationPreload;
use MediaWiki\Moderation\PendingEdit;
use MediaWiki\Moderation\SendNotificationEmailConsequence;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use RecentChange;
use User;
use Wikimedia\TestingAccessWrapper;
use WikiPage;
use WikitextContent;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class ModerationNewChangeTest extends ModerationUnitTestCase
{
    /**
     * Check values of all fields after ModerationNewChange object has just been constructed.
     * @param bool $isBlocked True if the user is marked as spammer, false otherwise.
     * @dataProvider dataProviderConstructor
     * @covers MediaWiki\Moderation\ModerationNewChange
     */
    public function testConstructor($isBlocked)
    {
        $preloadId = "{MockedPreloadId}";
        $ip = "10.11.12.13";
        $xff = "10.12.14.16";
        $agent = "Some-User-Agent/1.0";
        $userId = 567;
        $username = "Test user";

        $this->overrideConfigValue("LanguageCode", "qqx");

        $request = new FauxRequest();
        $request->setHeaders([
            "User-Agent" => $agent,
            "X-Forwarded-For" => $xff,
        ]);
        $request->setIP($ip);

        $title = Title::makeTitle(NS_HELP_TALK, "UTPage-" . rand(0, 100000));
        $user = $this->createMock(User::class);

        $user
            ->expects($this->any())
            ->method("getRequest")
            ->willReturn($request);
        $user->expects($this->any())->method("getId")->willReturn($userId);
        $user->expects($this->any())->method("getName")->willReturn($username);

        '@phan-var User $user';

        $change = $this->makeNewChange($title, $user, function (
            $consequenceManager,
            $preload,
            $hookRunner,
            $notifyModerator,
            $blockCheck,
        ) use ($isBlocked, $preloadId, $user) {
            $blockCheck
                ->expects($this->once())
                ->method("isModerationBlocked")
                ->willReturn($isBlocked);
            $preload
                ->expects($this->once())
                ->method("setUser")
                ->with($this->identicalTo($user));
            $preload
                ->expects($this->once())
                ->method("getId")
                ->with($this->identicalTo(true))
                ->willReturn($preloadId);
        });

        '@phan-var ModerationNewChange $change';

        $expectedFields = [
            "mod_user" => $userId,
            "mod_user_text" => $username,
            "mod_namespace" => $title->getNamespace(),
            "mod_title" => $title->getDBKey(),
            "mod_type" => "edit",

            "mod_preload_id" => $preloadId,
            "mod_ip" => $ip,
            "mod_header_ua" => $agent,
            "mod_header_xff" => $xff,

            "mod_comment" => "",
            "mod_text" => "",
            "mod_page2_title" => "",

            "mod_cur_id" => 0,
            "mod_minor" => 0,
            "mod_bot" => 0,
            "mod_new" => 0,
            "mod_last_oldid" => 0,
            "mod_old_len" => 0,
            "mod_new_len" => 0,
            "mod_rejected_by_user" => 0,
            "mod_rejected_batch" => 0,
            "mod_preloadable" => 0,
            "mod_conflict" => 0,
            "mod_merged_revid" => 0,
            "mod_page2_namespace" => 0,
            "mod_stash_key" => null,

            "mod_rejected" => $isBlocked ? 1 : 0,
            "mod_rejected_auto" => $isBlocked ? 1 : 0,
            "mod_rejected_by_user_text" => $isBlocked
                ? "(moderation-blocker)"
                : null,
        ];

        $fields = $change->getFields();
        foreach ($expectedFields as $expectedField => $expectedValue) {
            $this->assertSame(
                $expectedValue,
                $change->getField($expectedField),
            );
            $this->assertSame($expectedValue, $fields[$expectedField]);
        }

        $this->assertNull($change->getField("no_such_field"));
        $this->assertArrayNotHasKey("no_such_field", $fields);

        $maxTimePassed = 2;
        $timePassed =
            (int) wfTimestamp(TS_UNIX) -
            (int) wfTimestamp(TS_UNIX, $change->getField("mod_timestamp"));
        $this->assertLessThan(
            $maxTimePassed,
            $timePassed,
            "mod_timestamp of NewChange that was just created is more than $maxTimePassed seconds in the past.",
        );
    }

    /**
     * Provide datasets for testConstructor() runs.
     * @return array
     */
    public function dataProviderConstructor()
    {
        return [
            "not a spammer" => [false],
            "marked as spammer" => [true],
        ];
    }

    /**
     * Check that setters like setBot() modify the necessary database fields.
     * @param string $method One of "setMinor", "setBot" or "setSummary".
     * @param string $dbKeyField Database field name, e.g. "mod_comment".
     * @param array $testValues Map: [ argumentToSetter => expectedDatabaseFieldValue ].
     * @dataProvider dataProviderSetters
     * @covers MediaWiki\Moderation\ModerationNewChange
     */
    public function testSetters($method, $dbKeyField, $testValues)
    {
        $change = $this->makeNewChange();

        '@phan-var ModerationNewChange $change';

        foreach ($testValues as $keyValuePair) {
            [$argument, $expectedFieldValue] = $keyValuePair;

            $result = $change->$method($argument);
            $this->assertSame($change, $result);

            $this->assertSame(
                $expectedFieldValue,
                $change->getField($dbKeyField),
                "Field $dbKeyField is not $expectedFieldValue after $method($argument).",
            );
        }
    }

    /**
     * Provide datasets for testSetters() runs.
     * @return array
     */
    public function dataProviderSetters()
    {
        return [
            "setMinor()" => [
                "setMinor",
                "mod_minor",
                [[true, 1], [false, 0], [true, 1]],
            ],
            "setBot()" => [
                "setBot",
                "mod_bot",
                [[true, 1], [false, 0], [true, 1]],
            ],
            "setSummary()" => [
                "setSummary",
                "mod_comment",
                [
                    "Some comment" => "Some comment",
                    "Another comment" => "Another comment",
                    "" => "",
                ],
            ],
        ];
    }

    /**
     * Check that move() sets the necessary database fields.
     * @covers MediaWiki\Moderation\ModerationNewChange
     */
    public function testMove()
    {
        $newTitle = Title::makeTitle(
            NS_PROJECT,
            "UTRenamedPage-" . rand(0, 100000),
        );

        $change = $this->makeNewChange(null, null, null, ["addChangeTags"]);
        $change
            ->expects($this->once())
            ->method("addChangeTags")
            ->with($this->identicalTo("move"));

        '@phan-var ModerationNewChange $change';

        // Run the tested method.
        $result = $change->move($newTitle);
        $this->assertSame($change, $result);

        $this->assertSame("move", $change->getField("mod_type"));
        $this->assertSame(
            $newTitle->getNamespace(),
            $change->getField("mod_page2_namespace"),
        );
        $this->assertSame(
            $newTitle->getDBKey(),
            $change->getField("mod_page2_title"),
        );
    }

    /**
     * Check that upload() sets the necessary database fields.
     * @covers MediaWiki\Moderation\ModerationNewChange
     */
    public function testUpload()
    {
        $stashKey = "someStashKey123";

        $change = $this->makeNewChange(null, null, null, ["addChangeTags"]);
        $change
            ->expects($this->once())
            ->method("addChangeTags")
            ->with($this->identicalTo("upload"));

        '@phan-var ModerationNewChange $change';

        // Run the tested method.
        $result = $change->upload($stashKey);
        $this->assertSame($change, $result);

        // Uploads have mod_type=edit (they modify the page that contains file description).
        $this->assertSame("edit", $change->getField("mod_type"));
        $this->assertSame($stashKey, $change->getField("mod_stash_key"));
    }

    /**
     * Check that edit() sets the necessary database fields.
     * @param int|null $oldSize Length of existing page (in bytes) or null (if page doesn't exist).
     * @dataProvider dataProviderEdit
     * @covers MediaWiki\Moderation\ModerationNewChange
     */
    public function testEdit($oldSize)
    {
        $newContent = $this->createMock(Content::class);
        $newContentAdjusted = $this->createMock(Content::class);
        $newContentAfterPst = $this->createMock(Content::class);

        '@phan-var Content $newContent';

        $section = "{MockedSection}";
        $sectionText = "{MockedSectionText}";
        $pageId = 12345;
        $pageLatest = 6789;
        $newSize = 1200;
        $newText = "{MockedNewText}";

        if ($oldSize !== null) {
            $pageExists = true;

            $oldContent = $this->createMock(Content::class);
            $oldContent
                ->expects($this->once())
                ->method("getSize")
                ->willReturn($oldSize);
        } else {
            $pageExists = false;
            $oldContent = null;
        }

        $newContentAfterPst
            ->expects($this->once())
            ->method("serialize")
            ->willReturn($newText);
        $newContentAfterPst
            ->expects($this->once())
            ->method("getSize")
            ->willReturn($newSize);

        $wikiPage = $this->createMock(WikiPage::class);
        $wikiPage
            ->expects($this->once())
            ->method("exists")
            ->willReturn($pageExists);
        $wikiPage->expects($this->once())->method("getId")->willReturn($pageId);
        $wikiPage
            ->expects($this->once())
            ->method("getLatest")
            ->willReturn($pageLatest);

        $wikiPage
            ->expects($this->once())
            ->method("getContent")
            ->with($this->identicalTo(RevisionRecord::RAW))
            ->willReturn($oldContent);

        '@phan-var WikiPage $wikiPage';

        $change = $this->makeNewChange(null, null, null, [
            "addChangeTags",
            "applySectionToNewContent",
            "preSaveTransform",
        ]);
        $change
            ->expects($this->once())
            ->method("addChangeTags")
            ->with($this->identicalTo("edit"));
        $change
            ->expects($this->once())
            ->method("applySectionToNewContent")
            ->with(
                $this->identicalTo($newContent),
                $this->identicalTo($section),
                $this->identicalTo($sectionText),
            )
            ->willReturn($newContentAdjusted);
        $change
            ->expects($this->once())
            ->method("preSaveTransform")
            ->with($this->identicalTo($newContentAdjusted))
            ->willReturn($newContentAfterPst);

        '@phan-var ModerationNewChange $change';

        // Run the tested method.
        $result = $change->edit($wikiPage, $newContent, $section, $sectionText);
        $this->assertSame($change, $result);

        $this->assertSame("edit", $change->getField("mod_type"));
        $this->assertSame($pageId, $change->getField("mod_cur_id"));
        $this->assertSame($pageExists ? 0 : 1, $change->getField("mod_new"));
        $this->assertSame($pageLatest, $change->getField("mod_last_oldid"));

        $this->assertSame($oldSize ?? 0, $change->getField("mod_old_len"));
        $this->assertSame($newSize, $change->getField("mod_new_len"));
        $this->assertSame($newText, $change->getField("mod_text"));
    }

    /**
     * Provide datasets for testEdit() runs.
     * @return array
     */
    public function dataProviderEdit()
    {
        return [
            'page doesn\'t exist' => [null],
            "page exists" => [450],
            "page exists and is empty" => [0],
        ];
    }

    /**
     * Check that applySectionToNewContent() doesn't change $newContent if $section is empty string.
     * @covers MediaWiki\Moderation\ModerationNewChange
     */
    public function testApplySectionNoSection()
    {
        $newContent = $this->createMock(Content::class);
        $change = $this->makeNewChange();

        // Run the tested method.
        $wrapper = TestingAccessWrapper::newFromObject($change);
        $adjustedContent = $wrapper->applySectionToNewContent(
            $newContent,
            "",
            "Unused parameter",
        );

        $this->assertSame($newContent, $adjustedContent);
    }

    /**
     * Check that applySectionToNewContent() doesn't change $newContent if there is no pending edit.
     * @covers MediaWiki\Moderation\ModerationNewChange
     */
    public function testApplySectionNoPendingEdit()
    {
        $title = Title::newFromText("UTPage-" . rand(0, 100000));
        $newContent = $this->createMock(Content::class);

        $change = $this->makeNewChange($title, null, function (
            $consequenceManager,
            $preload,
        ) use ($title) {
            $preload
                ->expects($this->once())
                ->method("findPendingEdit")
                ->with($this->identicalTo($title))
                ->willReturn(false);
        });

        // Run the tested method.
        $wrapper = TestingAccessWrapper::newFromObject($change);
        $adjustedContent = $wrapper->applySectionToNewContent(
            $newContent,
            "2",
            "Text of section 2",
        );

        $this->assertSame($newContent, $adjustedContent);
    }

    /**
     * Check that applySectionToNewContent() adjusts $newContent when this is needed.
     * @covers MediaWiki\Moderation\ModerationNewChange
     */
    public function testApplySectionAdjusted()
    {
        $section = 3;
        $sectionText = "Text of some section";
        $pendingText = "{MockedPendingText}";
        $model = CONTENT_MODEL_WIKITEXT;
        $title = Title::newFromText("UTPage-" . rand(0, 100000));

        $newContent = $this->createMock(Content::class);
        $newContent
            ->expects($this->once())
            ->method("getModel")
            ->willReturn($model);

        $expectedResult = $this->createMock(Content::class);
        $sectionContent = $this->createMock(Content::class);

        $pendingContent = $this->createMock(Content::class);
        $pendingContent
            ->expects($this->once())
            ->method("replaceSection")
            ->with(
                $this->identicalTo($section),
                $this->identicalTo($sectionContent),
                $this->identicalTo(""),
            )
            ->willReturn($expectedResult);

        // Mock ContentHandlerFactory service to keep track of makeContent() calls.
        $contentHandler = $this->createMock(ContentHandler::class);
        $contentHandler
            ->expects($this->exactly(2))
            ->method("unserializeContent")
            ->will(
                $this->returnValueMap([
                    [$pendingText, null, $pendingContent],
                    [$sectionText, null, $sectionContent],
                ]),
            );

        $contentHandlerFactory = $this->createMock(
            IContentHandlerFactory::class,
        );
        $contentHandlerFactory
            ->expects($this->any())
            ->method("getContentHandler")
            ->with($this->identicalTo($model))
            ->willReturn($contentHandler);

        $this->setService("ContentHandlerFactory", $contentHandlerFactory);

        $change = $this->makeNewChange($title, null, function (
            $consequenceManager,
            $preload,
        ) use ($title, $pendingText) {
            $pendingEdit = $this->createMock(PendingEdit::class);
            $pendingEdit
                ->expects($this->once())
                ->method("getText")
                ->willReturn($pendingText);

            $preload
                ->expects($this->once())
                ->method("findPendingEdit")
                ->with($this->identicalTo($title))
                ->willReturn($pendingEdit);
        });

        // Run the tested method.
        $wrapper = TestingAccessWrapper::newFromObject($change);
        $adjustedContent = $wrapper->applySectionToNewContent(
            $newContent,
            $section,
            $sectionText,
        );

        $this->assertSame($expectedResult, $adjustedContent);
    }

    /**
     * Check that addChangeTags() sets the necessary database fields.
     * @param string $expectedFieldValue Correct value of mod_tags field.
     * @param string[] $foundTags Mocked return value of findAbuseFilterTags().
     * @dataProvider dataProviderAddChangeTags
     * @covers MediaWiki\Moderation\ModerationNewChange
     */
    public function testAddChangeTags($expectedFieldValue, array $foundTags)
    {
        $title = Title::newFromText("UTPage-" . rand(0, 100000));
        $user = self::getTestUser()->getUser();
        $action = "makesandwich";

        $change = $this->makeNewChange($title, $user, null, [
            "findAbuseFilterTags",
        ]);
        $change
            ->expects($this->once())
            ->method("findAbuseFilterTags")
            ->with(
                $this->identicalTo($title),
                $this->identicalTo($user),
                $this->identicalTo($action),
            )
            ->willReturn($foundTags);

        '@phan-var ModerationNewChange $change';

        // Run the tested method.
        $wrapper = TestingAccessWrapper::newFromObject($change);
        $wrapper->addChangeTags($action);

        $this->assertSame($expectedFieldValue, $change->getField("mod_tags"));
    }

    /**
     * Provide datasets for testAddChangeTags() runs.
     * @return array
     */
    public function dataProviderAddChangeTags()
    {
        return [
            "no tags" => [null, []],
            "one tag" => ["Only tag", ["Only tag"]],
            "many tags" => [
                "First tag\nTag #2\nThird tag",
                ["First tag", "Tag #2", "Third tag"],
            ],
        ];
    }

    /**
     * Check that findAbuseFilterTags() can find AbuseFilter tags.
     * @covers MediaWiki\Moderation\ModerationNewChange
     */
    public function testFindAbuseFilterTags()
    {
        $this->skipIfNoAbuseFilter();

        $title = Title::newFromText("User talk:UTPage-" . rand(0, 100000));
        $user = self::getTestUser()->getUser();
        $action = "makesandwich";
        $expectedTags = ["edit-about-cats", "edit-about-dogs"];

        $changeTagger = $this->createMock(ChangeTagger::class);
        $changeTagger
            ->expects($this->once())
            ->method("getTagsForRecentChange")
            ->will(
                $this->returnCallback(function (
                    RecentChange $rc,
                    bool $clear,
                ) use ($title, $user, $action, $expectedTags) {
                    $this->assertFalse($clear);

                    $this->assertTrue(
                        $rc->getPage()->isSamePageAs($title),
                        "Wrong title",
                    );
                    $this->assertSame($user, $rc->getPerformerIdentity());
                    $this->assertSame(
                        $action,
                        $rc->getAttribute("rc_log_type"),
                    );
                    $this->assertSame(
                        $action,
                        $rc->getAttribute("rc_log_action"),
                    );

                    return $expectedTags;
                }),
            );
        $this->setService("AbuseFilterChangeTagger", $changeTagger);

        $change = $this->makeNewChange($title, $user);

        // Run the tested method.
        $wrapper = TestingAccessWrapper::newFromObject($change);
        $this->assertSame(
            $expectedTags,
            $wrapper->findAbuseFilterTags($title, $user, $action),
        );
    }

    /**
     * Check that preSaveTransform() correctly transforms Content object.
     * @covers MediaWiki\Moderation\ModerationNewChange
     */
    public function testPreSaveTransform()
    {
        $title = Title::newFromText("UTPage-" . rand(0, 100000));

        $content = new WikitextContent(
            "{{subst:FULLPAGENAME}}, [[Talk:Pipe trick|]]",
        );
        $expectedText =
            $title->getFullText() . ", [[Talk:Pipe trick|Pipe trick]]";

        $change = $this->makeNewChange($title);

        // Run the tested method.
        $wrapper = TestingAccessWrapper::newFromObject($change);
        $newContent = $wrapper->preSaveTransform($content);

        $this->assertSame($expectedText, $newContent->getText());
    }

    /**
     * Check that queue() performs expected actions.
     * @covers MediaWiki\Moderation\ModerationNewChange
     */
    public function testQueue()
    {
        $modid = 12345;
        $mockedFields = [
            "mod_field" => "some value",
            "mod_another_field" => "another value",
        ];

        $change = $this->makeNewChange(
            null,
            null,
            function ($consequenceManager, $preload, $hookRunner) use (
                $modid,
                $mockedFields,
            ) {
                $hookRunner
                    ->expects($this->once())
                    ->method("onModerationPending")
                    ->with(
                        $this->identicalTo($mockedFields),
                        $this->identicalTo($modid),
                    );
            },
            ["insert", "notify", "getFields"],
        );
        $change->expects($this->once())->method("insert")->willReturn($modid);
        $change
            ->expects($this->once())
            ->method("getFields")
            ->willReturn($mockedFields);
        $change
            ->expects($this->once())
            ->method("notify")
            ->with($this->identicalTo($modid));

        '@phan-var ModerationNewChange $change';

        // Run the tested method.
        $result = $change->queue();

        $this->assertSame($modid, $result);
    }

    /**
     * Check that insert() performs expected actions.
     * @covers MediaWiki\Moderation\ModerationNewChange
     */
    public function testInsert()
    {
        $modid = 12345;
        $mockedFields = [
            "mod_field" => "some value",
            "mod_another_field" => "another value",
        ];

        $change = $this->makeNewChange(
            null,
            null,
            function ($consequenceManager) use ($modid, $mockedFields) {
                $consequenceManager
                    ->expects($this->once())
                    ->method("add")
                    ->with(
                        $this->consequenceEqualTo(
                            new InsertRowIntoModerationTableConsequence(
                                $mockedFields,
                            ),
                        ),
                    )
                    ->willReturn($modid);
            },
            ["getFields"],
        );
        $change
            ->expects($this->once())
            ->method("getFields")
            ->willReturn($mockedFields);

        // Run the tested method.
        $wrapper = TestingAccessWrapper::newFromObject($change);
        $result = $wrapper->insert();

        $this->assertSame($modid, $result);
    }

    /**
     * Check that notify() performs expected actions.
     * @covers MediaWiki\Moderation\ModerationNewChange
     */
    public function testNotify()
    {
        $modid = 12345;
        $timestamp = "20100101001600";

        $change = $this->makeNewChange(
            null,
            null,
            function (
                $consequenceManager,
                $preload,
                $hookRunner,
                $notifyModerator,
            ) use ($timestamp) {
                $notifyModerator
                    ->expects($this->once())
                    ->method("setPendingTime")
                    ->with($this->identicalTo($timestamp));
            },
            ["getField", "sendNotificationEmail"],
        );

        $change
            ->method("getField")
            ->will(
                $this->returnValueMap([
                    ["mod_rejected_auto", 0],
                    ["mod_timestamp", $timestamp],
                ]),
            );

        $change
            ->expects($this->once())
            ->method("sendNotificationEmail")
            ->with($this->identicalTo($modid));

        // Run the tested method.
        $wrapper = TestingAccessWrapper::newFromObject($change);
        $wrapper->notify($modid);
    }

    /**
     * Check that notify() doesn't do anything if mod_rejected_auto=1.
     * @covers MediaWiki\Moderation\ModerationNewChange
     */
    public function testNotifySkippedRejectedAuto()
    {
        $modid = 12345;

        $change = $this->makeNewChange(
            null,
            null,
            function (
                $consequenceManager,
                $preload,
                $hookRunner,
                &$notifyModerator,
            ) {
                $notifyModerator = $this->createNoopMock(
                    ModerationNotifyModerator::class,
                );
            },
            ["getField", "sendNotificationEmail"],
        );
        $change
            ->expects($this->once())
            ->method("getField")
            ->with($this->identicalTo("mod_rejected_auto"))
            ->willReturn(1);

        $change->expects($this->never())->method("sendNotificationEmail");

        // Run the tested method.
        $wrapper = TestingAccessWrapper::newFromObject($change);
        $wrapper->notify($modid);
    }

    /**
     * Check that sendNotificationEmail() performs expected actions.
     * @param bool $isSendingExpected True if email must be sent, false otherwise.
     * @param array $configVars Configuration settings (if any), e.g. [ 'wgModerationEnable' => false ].
     * @param array $fieldValues Database fields of this NewChange, e.g. [ 'mod_new' => 1 ].
     * @dataProvider dataProviderSendNotificationEmail
     * @covers MediaWiki\Moderation\ModerationNewChange
     */
    public function testSendNotificationEmail(
        $isSendingExpected,
        array $configVars,
        array $fieldValues = [],
    ) {
        $modid = 12345;
        $title = Title::newFromText("UTPage-" . rand(0, 100000));
        $user = self::getTestUser()->getUser();

        $this->setMwGlobals($configVars);

        $change = $this->makeNewChange(
            $title,
            $user,
            function ($consequenceManager) use (
                $title,
                $user,
                $modid,
                $isSendingExpected,
            ) {
                if ($isSendingExpected) {
                    $consequenceManager
                        ->expects($this->once())
                        ->method("add")
                        ->with(
                            $this->consequenceEqualTo(
                                new SendNotificationEmailConsequence(
                                    $title,
                                    $user,
                                    $modid,
                                ),
                            ),
                        );
                } else {
                    // Nothing should be sent.
                    $consequenceManager->expects($this->never())->method("add");
                }
            },
            ["getField"],
        );
        $change
            ->expects($this->any())
            ->method("getField")
            ->will(
                $this->returnCallback(static function ($fieldName) use (
                    $fieldValues,
                ) {
                    return $fieldValues[$fieldName];
                }),
            );

        '@phan-var ModerationNewChange $change';

        // Run the tested method.
        $change->sendNotificationEmail($modid);
    }

    /**
     * Provide datasets for testSendNotificationEmail() runs.
     * @return array
     */
    public function dataProviderSendNotificationEmail()
    {
        return [
            "No notification: notifications disabled" => [
                false,
                [
                    "wgModerationNotificationEnable" => false,
                ],
            ],
            "No notification: recipient email not configured" => [
                false,
                [
                    "wgModerationNotificationEnable" => true,
                    "wgModerationEmail" => "",
                ],
            ],
            "No notification: NewOnly mode + edit in existing page" => [
                false,
                [
                    "wgModerationNotificationEnable" => true,
                    "wgModerationEmail" => "some.recipient@localhost",
                    "wgModerationNotificationNewOnly" => true,
                ],
                ["mod_new" => 0],
            ],
            "Must notify: NewOnly mode + new page" => [
                true,
                [
                    "wgModerationNotificationEnable" => true,
                    "wgModerationEmail" => "some.recipient@localhost",
                    "wgModerationNotificationNewOnly" => true,
                ],
                ["mod_new" => 1],
            ],
            "Must notify: no NewOnly mode + edit in existing page" => [
                true,
                [
                    "wgModerationNotificationEnable" => true,
                    "wgModerationEmail" => "some.recipient@localhost",
                    "wgModerationNotificationNewOnly" => false,
                ],
                ["mod_new" => 0],
            ],
        ];
    }

    /**
     * Skip current test if Extension:AbuseFilter is not installed.
     */
    private function skipIfNoAbuseFilter()
    {
        if (!ExtensionRegistry::getInstance()->isLoaded("Abuse Filter")) {
            $this->markTestSkipped(
                "Test skipped: AbuseFilter extension must be installed to run it.",
            );
        }
    }

    /**
     * Create ModerationNewChange using callback that receives all mocked dependencies.
     * @param Title|null $title
     * @param User|null $user
     * @param callable|null $setupMocks Callback that can configure MockObject dependencies.
     * @param string[] $methods Array of method names to mock (for MockBuilder::setMethods()).
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function makeNewChange(
        Title $title = null,
        User $user = null,
        callable $setupMocks = null,
        array $methods = [],
    ) {
        $manager = $this->createMock(IConsequenceManager::class);
        $blockCheck = $this->createMock(ModerationBlockCheck::class);
        $mocks = [
            $manager,
            $this->createMock(ModerationPreload::class),
            $this->createMock(HookRunner::class),
            $this->createMock(ModerationNotifyModerator::class),
            $this->createMock(ModerationBlockCheck::class),
            $this->createMock(Language::class),
        ];

        $arguments = [
            $title ?? Title::newFromText("UTPage-" . rand(0, 100000)),
            $user ?? self::getTestUser()->getUser(),
        ];
        array_push($arguments, ...$mocks);

        if ($setupMocks) {
            $setupMocks(...$mocks);
        } else {
            // Since we are not configuring a mock of ConsequenceManager,
            // it means that we expect no consequences to be added.
            $manager->expects($this->never())->method("add");
            $blockCheck
                ->expects($this->any())
                ->method("isModerationBlocked")
                ->willReturn(false);
        }

        return $this->getMockBuilder(ModerationNewChange::class)
            ->setConstructorArgs($arguments)
            ->onlyMethods($methods)
            ->getMock();
    }
}
