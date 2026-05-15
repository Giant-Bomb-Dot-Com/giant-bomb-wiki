<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2025 Edward Chernenko.

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
 * Unit test of WatchOrUnwatchConsequence.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\WatchOrUnwatchConsequence;
use MediaWiki\Title\Title;
use WatchedItem;
use WatchedItemStore;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class WatchOrUnwatchConsequenceTest extends ModerationUnitTestCase
{
    /**
     * Verify that WatchOrUnwatchConsequence watches/unwatches a page.
     * @covers MediaWiki\Moderation\WatchOrUnwatchConsequence
     * @param bool $watch
     * @param bool $noop If true, "watch this" will be tested on already watched page
     * (and "unwatch this" will be tested on non-watched page).
     * @dataProvider dataProviderWatchUnwatch
     */
    public function testWatchUnwatch($watch, $noop)
    {
        $title = Title::newFromText("UTPage-" . rand(0, 100000));
        $user = self::getTestUser()->getUser();

        $watchedItemStore = $this->createMock(WatchedItemStore::class);
        $isWatched = ($watch && $noop) || (!$watch && !$noop);

        $watchedItemStore
            ->expects($this->once())
            ->method("getWatchedItem")
            ->will(
                $this->returnCallback(function ($hookUser, $hookTitle) use (
                    $isWatched,
                    $user,
                    $title,
                ) {
                    $this->assertSame($user, $hookUser);
                    $this->assertTrue($hookTitle->isSameLinkAs($title));

                    return $isWatched
                        ? new WatchedItem($user, $title, null)
                        : false;
                }),
            );

        $watchHookFired = false;
        $this->setTemporaryHook("WatchArticle", function (
            $userIdentity,
            $wikiPage,
        ) use (&$watchHookFired, $title, $user) {
            $watchHookFired = true;

            $this->assertSame(
                $title->getFullText(),
                $wikiPage->getTitle()->getFullText(),
            );
            $this->assertSame($user->getName(), $userIdentity->getName());

            return true;
        });

        $unwatchHookFired = false;
        $this->setTemporaryHook("UnwatchArticle", function (
            $userIdentity,
            $wikiPage,
        ) use (&$unwatchHookFired, $title, $user) {
            $unwatchHookFired = true;

            $this->assertSame(
                $title->getFullText(),
                $wikiPage->getTitle()->getFullText(),
            );
            $this->assertSame($user->getName(), $userIdentity->getName());

            return true;
        });

        $this->setService("WatchedItemStore", $watchedItemStore);

        // Create and run the Consequence.
        $consequence = new WatchOrUnwatchConsequence($watch, $title, $user);
        $consequence->run();

        $this->assertSame(
            [
                // Expected results
                "WatchArticle hook fired" => !$noop && $watch,
                "UnwatchArticle hook fired" => !$noop && !$watch,
            ],
            [
                // Actual results
                "WatchArticle hook fired" => $watchHookFired,
                "UnwatchArticle hook fired" => $unwatchHookFired,
            ],
        );
    }

    /**
     * Provide datasets for dataProviderWatchUnwatch() runs.
     * @return array
     */
    public function dataProviderWatchUnwatch()
    {
        return [
            "watch" => [
                true, // True means "Watch", false means "Unwatch"
                false, // True means "noop test" (trying to watch already watched page)
            ],
            "unwatch" => [false, false],
            "watch (noop)" => [true, true],
            "unwatch (noop)" => [false, true],
        ];
    }
}
