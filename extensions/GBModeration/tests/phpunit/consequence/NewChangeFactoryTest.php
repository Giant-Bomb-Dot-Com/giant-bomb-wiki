<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2021 Edward Chernenko.

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
 * Unit test of NewChangeFactory.
 */

namespace MediaWiki\Moderation\Tests;

use Language;
use MediaWiki\Moderation\Hook\HookRunner;
use MediaWiki\Moderation\IConsequenceManager;
use MediaWiki\Moderation\ModerationNewChange;
use MediaWiki\Moderation\ModerationNotifyModerator;
use MediaWiki\Moderation\ModerationPreload;
use MediaWiki\Moderation\NewChangeFactory;
use MediaWiki\Title\Title;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class NewChangeFactoryTest extends ModerationUnitTestCase
{
    /**
     * Test that NewChangeFactory can create ModerationNewChange objects.
     * @covers MediaWiki\Moderation\NewChangeFactory
     */
    public function testFactory()
    {
        $consequenceManager = $this->createMock(IConsequenceManager::class);
        $preload = $this->createMock(ModerationPreload::class);
        $hookRunner = $this->createMock(HookRunner::class);
        $notifyModerator = $this->createMock(ModerationNotifyModerator::class);
        $contentLanguage = $this->createMock(Language::class);

        $factory = new NewChangeFactory(
            // @phan-suppress-next-line PhanTypeMismatchArgument
            $consequenceManager,
            $preload,
            $hookRunner,
            $notifyModerator,
            $contentLanguage,
        );

        $title = Title::newFromText("Category:UTPage-" . rand(0, 100000));
        $user = self::getTestUser()->getUser();

        // Run the tested method.
        $change = $factory->makeNewChange($title, $user);

        $this->assertInstanceOf(ModerationNewChange::class, $change);
        $this->assertSame(
            $title->getNamespace(),
            $change->getField("mod_namespace"),
        );
        $this->assertSame($title->getDBKey(), $change->getField("mod_title"));
        $this->assertSame($user->getName(), $change->getField("mod_user_text"));
    }
}
