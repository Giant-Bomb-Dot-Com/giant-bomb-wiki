<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020 Edward Chernenko.

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
 * Unit test of ConsequenceManager.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\ConsequenceManager;
use MediaWiki\Moderation\IConsequence;

require_once __DIR__ . "/autoload.php";

class ConsequenceManagerTest extends ModerationUnitTestCase
{
    /**
     * Test that ConsequenceManager::add() immediately runs the Consequence and returns its result.
     * @covers MediaWiki\Moderation\ConsequenceManager
     */
    public function testAddConsequence()
    {
        $expectedResult = 12345;

        $consequence = $this->createMock(IConsequence::class);
        $consequence
            ->expects($this->once())
            ->method("run")
            ->willReturn($expectedResult);

        '@phan-var IConsequence $consequence';

        $manager = new ConsequenceManager();
        $this->assertSame($expectedResult, $manager->add($consequence));
    }
}
