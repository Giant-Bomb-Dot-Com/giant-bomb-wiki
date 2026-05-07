<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2021 Edward Chernenko.

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
 * Unit test of ModerationNotifyModerator.
 */

namespace MediaWiki\Moderation\Tests;

use BagOStuff;
use HashBagOStuff;
use IContextSource;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Moderation\EntryFactory;
use MediaWiki\Moderation\ModerationNotifyModerator;
use MediaWiki\Title\Title;
use OutputPage;
use RawMessage;
use User;
use Wikimedia\TestingAccessWrapper;

require_once __DIR__ . "/autoload.php";

class ModerationNotifyModeratorTest extends ModerationUnitTestCase
{
    /**
     * Test that invalidateCache() clears the cache of ModerationNotifyModerator.
     * @covers MediaWiki\Moderation\ModerationNotifyModerator
     */
    public function testInvalidateCache()
    {
        $linkRenderer = $this->createMock(LinkRenderer::class);
        $entryFactory = $this->createMock(EntryFactory::class);
        $cache = $this->createMock(BagOStuff::class);

        $cache
            ->expects($this->once())
            ->method("makeKey")
            ->with($this->identicalTo("moderation-newest-pending-timestamp"))
            ->willReturn("{MockedCacheKey}");
        $cache
            ->expects($this->once())
            ->method("delete")
            ->with($this->identicalTo("{MockedCacheKey}"));

        '@phan-var LinkRenderer $linkRenderer';
        '@phan-var EntryFactory $entryFactory';
        '@phan-var BagOStuff $cache';

        $notify = new ModerationNotifyModerator(
            $linkRenderer,
            $entryFactory,
            $cache,
        );
        $notify->invalidatePendingTime();
    }

    /**
     * Test that setPendingTime() populates "getPendingTime cache" of ModerationNotifyModerator.
     * @covers MediaWiki\Moderation\ModerationNotifyModerator
     */
    public function testSetPendingTime()
    {
        $linkRenderer = $this->createMock(LinkRenderer::class);
        $entryFactory = $this->createMock(EntryFactory::class);
        $cache = $this->createMock(BagOStuff::class);

        $timestamp = "20100102030405";

        $cache
            ->expects($this->once())
            ->method("makeKey")
            ->with($this->identicalTo("moderation-newest-pending-timestamp"))
            ->willReturn("{MockedCacheKey}");
        $cache
            ->expects($this->once())
            ->method("set")
            ->with(
                $this->identicalTo("{MockedCacheKey}"),
                $this->identicalTo($timestamp),
                $this->identicalTo(24 * 60 * 60),
            );

        '@phan-var LinkRenderer $linkRenderer';
        '@phan-var EntryFactory $entryFactory';
        '@phan-var BagOStuff $cache';

        $notify = new ModerationNotifyModerator(
            $linkRenderer,
            $entryFactory,
            $cache,
        );
        $notify->setPendingTime($timestamp);
    }

    /**
     * Test that setPendingTime() populates "getSeen cache" of ModerationNotifyModerator.
     * @covers MediaWiki\Moderation\ModerationNotifyModerator
     */
    public function testSetSeen()
    {
        $linkRenderer = $this->createMock(LinkRenderer::class);
        $entryFactory = $this->createMock(EntryFactory::class);
        $cache = $this->createMock(BagOStuff::class);
        $user = $this->createMock(User::class);

        $userId = 654321;
        $timestamp = "20100102030405";

        $user->expects($this->once())->method("getId")->willReturn($userId);

        $cache
            ->expects($this->once())
            ->method("makeKey")
            ->with(
                $this->identicalTo("moderation-seen-timestamp"),
                $this->identicalTo("$userId"),
            )
            ->willReturn("{MockedCacheKey}");
        $cache
            ->expects($this->once())
            ->method("set")
            ->with(
                $this->identicalTo("{MockedCacheKey}"),
                $this->identicalTo($timestamp),
                $this->identicalTo(7 * 24 * 60 * 60),
            );

        '@phan-var LinkRenderer $linkRenderer';
        '@phan-var EntryFactory $entryFactory';
        '@phan-var BagOStuff $cache';
        '@phan-var User $user';

        $notify = new ModerationNotifyModerator(
            $linkRenderer,
            $entryFactory,
            $cache,
        );
        $notify->setSeen($user, $timestamp);
    }

    /**
     * Check the result of getNotificationHTML().
     * @param bool $expectShown If true, our notification is expected to be shown.
     * @param array $opt
     * @dataProvider dataProviderGetNotificationHTML
     *
     * @covers MediaWiki\Moderation\ModerationNotifyModerator
     */
    public function testGetNotificationHTML($expectShown, array $opt)
    {
        $isModerator = $opt["isModerator"] ?? true;
        $isSpecialModeration = $opt["isSpecialModeration"] ?? false;
        $pendingTimeCached = $opt["pendingTimeCached"] ?? false;
        $pendingTimeUncached = $opt["pendingTimeUncached"] ?? false;
        $seenTime = $opt["seenTime"] ?? false;

        $linkRenderer = $this->createMock(LinkRenderer::class);
        $entryFactory = $this->createMock(EntryFactory::class);
        $title = $this->createMock(Title::class);
        $user = $this->createMock(User::class);
        $context = $this->createMock(IContextSource::class);

        $userId = 456;
        $user->expects($this->any())->method("getId")->willReturn($userId);

        $user
            ->expects($this->once())
            ->method("isAllowed")
            ->with($this->identicalTo("moderation"))
            ->willReturn($isModerator);

        $context->expects($this->any())->method("getUser")->willReturn($user);
        $context->expects($this->any())->method("getTitle")->willReturn($title);

        $cache = new HashBagOStuff();
        $cache->set(
            $cache->makeKey("moderation-newest-pending-timestamp"),
            $pendingTimeCached,
        );
        $cache->set(
            $cache->makeKey("moderation-seen-timestamp", "$userId"),
            $seenTime,
        );

        $expectedCacheContents = [];
        $expectedHTML = "";

        if (!$isModerator) {
            // Hook won't be installed.
            $title->expects($this->never())->method("isSpecial");
        } else {
            $title
                ->expects($this->once())
                ->method("isSpecial")
                ->with($this->identicalTo("Moderation"))
                ->willReturn($isSpecialModeration);

            if (!$isSpecialModeration && $pendingTimeCached === false) {
                // PendingTime wasn't found in the cache, so it will be loaded from the database.
                $entryFactory
                    ->expects($this->once())
                    ->method("loadRow")
                    ->with(
                        $this->identicalTo([
                            "mod_rejected" => 0,
                            "mod_merged_revid" => 0,
                        ]),
                        $this->identicalTo(["mod_timestamp AS timestamp"]),
                        $this->identicalTo(DB_REPLICA),
                        $this->identicalTo([
                            "USE INDEX" => "moderation_folder_pending",
                        ]),
                    )
                    ->willReturn(
                        $pendingTimeUncached
                            ? (object) [
                                "timestamp" => $pendingTimeUncached,
                            ]
                            : false,
                    );

                // Obtained $pendingTimeUncached should be stored in the cache (used in assertions below).
                $cacheKey = $cache->makeKey(
                    "moderation-newest-pending-timestamp",
                );
                $expectedCacheContents[$cacheKey] = $pendingTimeUncached ?: 0;
            }
        }

        if ($expectShown) {
            $context
                ->expects($this->once())
                ->method("msg")
                ->with($this->identicalTo("moderation-new-changes-appeared"))
                ->willReturn(new RawMessage("{TextOfNotificationLink}"));

            $linkRenderer
                ->expects($this->once())
                ->method("makeLink")
                ->with(
                    $this->IsInstanceOf(Title::class),
                    $this->identicalTo("{TextOfNotificationLink}"),
                )
                ->will(
                    $this->returnCallback(function (Title $title, $text) {
                        $this->assertTrue(
                            $title->isSpecial("Moderation"),
                            "Link in the notification doesn't point to Special:Moderation.",
                        );
                        return "{NotificationLink}";
                    }),
                );

            $expectedHTML = "{NotificationLink}";
        }

        '@phan-var LinkRenderer $linkRenderer';
        '@phan-var EntryFactory $entryFactory';
        '@phan-var IContextSource $context';

        // Install the newly constructed ModerationNotifyModerator object as a service.
        $notify = new ModerationNotifyModerator(
            $linkRenderer,
            $entryFactory,
            $cache,
        );
        $this->setService("Moderation.NotifyModerator", $notify);

        // Run the tested method.
        $wrapper = TestingAccessWrapper::newFromObject($notify);
        $result = $wrapper->getNotificationHTML($context);
        $this->assertSame(
            $expectedHTML,
            $result,
            "Unexpected result of getNotificationHTML().",
        );

        // Analyze the situation afterwards.
        foreach ($expectedCacheContents as $key => $val) {
            $this->assertSame($val, $cache->get($key), "Cache[$key] != $val");
        }
    }

    /**
     * Provide datasets for testGetNotificationHTML() runs.
     * @return array
     */
    public function dataProviderGetNotificationHTML()
    {
        return [
            "Not shown (not a moderator)" => [false, ["isModerator" => false]],
            "Not shown (on Special:Moderation)" => [
                false,
                ["isSpecialModeration" => true],
            ],
            "Not shown (no pending edits, according to cache)" => [
                false,
                ["pendingTimeCached" => 0],
            ],
            "Not shown (no pending edits, according to the database)" => [
                false,
                ["pendingTimeUncached" => 0],
            ],
            "Not shown (SeenTime is newer than the most recent pending edit)" => [
                false,
                [
                    "pendingTimeUncached" => "2010010203040506",
                    "seenTime" => "2015010203040506",
                ],
            ],
            'Shown (SeenTime is unknown, which means that this moderator hasn\'t visited for a while)' => [
                true,
                [
                    "pendingTimeUncached" => "2010010203040506",
                    "seenTime" => false,
                ],
            ],
            "Shown (SeenTime is less than pendingTimeUncached)" => [
                true,
                [
                    "pendingTimeUncached" => "2010010203040506",
                    "seenTime" => "2005010203040506",
                ],
            ],
            "Shown (SeenTime is less than pendingTimeCached)" => [
                true,
                [
                    "pendingTimeCached" => "2010010203040506",
                    "seenTime" => "2005010203040506",
                ],
            ],
        ];
    }

    /**
     * Create a partial mock of ModerationNotifyModerator class with all mocked parameters.
     * @param string[] $methodsToMock
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function makePartialNotifyMock(array $methodsToMock)
    {
        $linkRenderer = $this->createMock(LinkRenderer::class);
        $entryFactory = $this->createMock(EntryFactory::class);
        $cache = $this->createMock(BagOStuff::class);

        // Create partial mock: getNotificationHTML() gets overridden, but everything else is not.
        return $this->getMockBuilder(ModerationNotifyModerator::class)
            ->setConstructorArgs([$linkRenderer, $entryFactory, $cache])
            ->onlyMethods($methodsToMock)
            ->getMockForAbstractClass();
    }

    /**
     * Verify that GetNewMessagesAlert hook shows result of getNotificationHTML().
     * @param bool $mustNotify If true, getNotificationHTML() will return non-empty string.
     * @dataProvider dataProviderNotifyHook
     *
     * @covers MediaWiki\Moderation\ModerationNotifyModerator
     */
    public function testNotifyHook($mustNotify)
    {
        $out = $this->createMock(OutputPage::class);
        $user = $this->createMock(User::class);

        // Create partial mock: getNotificationHTML() gets overridden, but everything else is not.
        $notify = $this->makePartialNotifyMock(["getNotificationHTML"]);

        $notify
            ->expects($this->once())
            ->method("getNotificationHTML")
            ->with($this->identicalTo($out))
            ->willReturn($mustNotify ? "{MockedResult}" : "");

        '@phan-var ModerationNotifyModerator $notify';
        '@phan-var OutputPage $out';
        '@phan-var User $user';

        // Call the tested hook handler directly.
        $newMessagesAlert = "{ThirdPartyNotification}";
        $notify->onGetNewMessagesAlert($newMessagesAlert, [], $user, $out);

        $this->assertSame(
            $mustNotify
                ? "{ThirdPartyNotification}\n{MockedResult}"
                : "{ThirdPartyNotification}",
            $newMessagesAlert,
            'Value of $newMessagesAlert after GetNewMessagesAlert hook doesn\'t match expected.',
        );
    }

    /**
     * Provide datasets for testNotifyHook() runs.
     * @return array
     */
    public function dataProviderNotifyHook()
    {
        return [
            'Don\'t need to add notification' => [false],
            "Must add notification" => [true],
        ];
    }

    /**
     * Verify that EchoCanAbortNewMessagesAlert hook returns false ("Echo can't disable notification").
     * @covers MediaWiki\Moderation\ModerationNotifyModerator
     */
    public function testEchoHook()
    {
        $notify = $this->makePartialNotifyMock([]);
        '@phan-var ModerationNotifyModerator $notify';

        $this->assertFalse(
            $notify->onEchoCanAbortNewMessagesAlert(),
            "EchoCanAbortNewMessagesAlert hook must return false.",
        );
    }
}
