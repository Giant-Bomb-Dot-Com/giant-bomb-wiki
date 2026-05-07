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
 * Unit test of ServiceWiring.php.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\MediaWikiServices;
use MediaWiki\Moderation\ActionFactory;
use MediaWiki\Moderation\ActionLinkRenderer;
use MediaWiki\Moderation\ConsequenceManager;
use MediaWiki\Moderation\EditFormOptions;
use MediaWiki\Moderation\EntryFactory;
use MediaWiki\Moderation\Hook\HookRunner;
use MediaWiki\Moderation\ModerationApproveHook;
use MediaWiki\Moderation\ModerationCanSkip;
use MediaWiki\Moderation\ModerationNotifyModerator;
use MediaWiki\Moderation\ModerationPreload;
use MediaWiki\Moderation\ModerationVersionCheck;
use MediaWiki\Moderation\NewChangeFactory;
use MediaWiki\Moderation\RollbackResistantQuery;
use MediaWiki\Moderation\TimestampTools;

require_once __DIR__ . "/autoload.php";

class ServiceWiringTest extends ModerationUnitTestCase
{
    /**
     * Ensure that all Moderation services are instantiated without errors.
     * @dataProvider dataProviderGetService
     * @coversNothing Due to the current format of ServiceWiring.php (no classes). See T248172
     */
    public function testGetService($serviceName, $expectedClass)
    {
        $services = MediaWikiServices::getInstance();
        $result = $services->getService($serviceName);

        // Technically there are strict return value typehints, but we can't trust tested code.
        $this->assertInstanceOf($expectedClass, $result);
    }

    /**
     * Provide datasets for testGetService() runs.
     * @return array
     */
    public function dataProviderGetService()
    {
        return [
            ["Moderation.ActionFactory", ActionFactory::class],
            ["Moderation.ActionLinkRenderer", ActionLinkRenderer::class],
            ["Moderation.ApproveHook", ModerationApproveHook::class],
            ["Moderation.CanSkip", ModerationCanSkip::class],
            ["Moderation.ConsequenceManager", ConsequenceManager::class],
            ["Moderation.EditFormOptions", EditFormOptions::class],
            ["Moderation.EntryFactory", EntryFactory::class],
            ["Moderation.HookRunner", HookRunner::class],
            ["Moderation.NewChangeFactory", NewChangeFactory::class],
            ["Moderation.NotifyModerator", ModerationNotifyModerator::class],
            ["Moderation.Preload", ModerationPreload::class],
            [
                "Moderation.RollbackResistantQuery",
                RollbackResistantQuery::class,
            ],
            ["Moderation.TimestampTools", TimestampTools::class],
            ["Moderation.VersionCheck", ModerationVersionCheck::class],
        ];
    }
}
