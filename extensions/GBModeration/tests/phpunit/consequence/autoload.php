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
 * List of classes used by Consequence tests (these classes are provided to MediaWiki Autoloader).
 */

namespace MediaWiki\Moderation\Tests;

global $wgAutoloadClasses;

$wgAutoloadClasses += [
    ModerationTestHTML::class =>
        __DIR__ . "/../../common/ModerationTestHTML.php",
    ModerationTestUtil::class =>
        __DIR__ . "/../../common/ModerationTestUtil.php",
    ActionTestTrait::class => __DIR__ . "/trait/ActionTestTrait.php",
    MakeEditTestTrait::class => __DIR__ . "/trait/MakeEditTestTrait.php",
    MockLinkRendererTrait::class =>
        __DIR__ . "/trait/MockLinkRendererTrait.php",
    MockLoadRowTestTrait::class => __DIR__ . "/trait/MockLoadRowTestTrait.php",
    MockModerationActionTrait::class =>
        __DIR__ . "/trait/MockModerationActionTrait.php",
    MockRevisionLookupTestTrait::class =>
        __DIR__ . "/trait/MockRevisionLookupTestTrait.php",
    IsConsequenceEqual::class => __DIR__ . "/trait/IsConsequenceEqual.php",
    MockConsequenceManager::class => __DIR__ . "/MockConsequenceManager.php",
    ModerationUnitTestCase::class => __DIR__ . "/ModerationUnitTestCase.php",
    ModifyDbRowTestTrait::class => __DIR__ . "/trait/ModifyDbRowTestTrait.php",
    UploadTestTrait::class => __DIR__ . "/trait/UploadTestTrait.php",
];
