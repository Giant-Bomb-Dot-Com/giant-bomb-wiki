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
 * List of testsuite-related classes for MediaWiki Autoloader.
 */

namespace MediaWiki\Moderation\Tests;

global $wgAutoloadClasses;

// phpcs:disable Generic.Files.LineLength
$wgAutoloadClasses += [
    /* Common test/benchmark methods */
    ModerationTestHTML::class =>
        __DIR__ . "/../../common/ModerationTestHTML.php",
    ModerationTestUtil::class =>
        __DIR__ . "/../../common/ModerationTestUtil.php",

    /* Moderation Testsuite framework */
    IModerationTestsuiteEngine::class =>
        __DIR__ . "/engine/IModerationTestsuiteEngine.php",
    IModerationTestsuiteResponse::class =>
        __DIR__ . "/IModerationTestsuiteResponse.php",
    ModerationTestsuiteBagOStuff::class =>
        __DIR__ . "/ModerationTestsuiteBagOStuff.php",
    ModerationTestCase::class => __DIR__ . "/ModerationTestCase.php",
    ModerationTestSetRegex::class => __DIR__ . "/ModerationTestSetRegex.php",
    ModerationTestsuiteApiBot::class =>
        __DIR__ . "/bot/ModerationTestsuiteApiBot.php",
    ModerationTestsuiteApiBotResponse::class =>
        __DIR__ . "/bot/response/ModerationTestsuiteApiBotResponse.php",
    ModerationTestsuiteBot::class =>
        __DIR__ . "/bot/ModerationTestsuiteBot.php",
    ModerationTestsuiteBotResponse::class =>
        __DIR__ . "/bot/response/ModerationTestsuiteBotResponseTrait.php",
    ModerationTestsuiteEngine::class =>
        __DIR__ . "/engine/ModerationTestsuiteEngine.php",
    ModerationTestsuiteCliEngine::class =>
        __DIR__ . "/engine/cli/ModerationTestsuiteCliEngine.php",
    ModerationTestsuiteEntry::class =>
        __DIR__ . "/ModerationTestsuiteEntry.php",
    ModerationTestsuiteHTML::class => __DIR__ . "/ModerationTestsuiteHTML.php",
    ModerationTestsuiteLogger::class =>
        __DIR__ . "/ModerationTestsuiteLogger.php",
    ModerationTestsuiteNonApiBot::class =>
        __DIR__ . "/bot/ModerationTestsuiteNonApiBot.php",
    ModerationTestsuiteNonApiBotResponse::class =>
        __DIR__ . "/bot/response/ModerationTestsuiteNonApiBotResponse.php",
    ModerationTestsuitePendingChangeTestSet::class =>
        __DIR__ . "/ModerationPendingChangeTestSet.php",
    ModerationTestsuiteResponse::class =>
        __DIR__ . "/ModerationTestsuiteResponse.php",
    ModerationTestsuiteTestSet::class => __DIR__ . "/ModerationTestSet.php",
];
// phpcs:enable
