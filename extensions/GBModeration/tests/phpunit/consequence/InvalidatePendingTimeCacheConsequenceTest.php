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
 * Unit test of InvalidatePendingTimeCacheConsequence.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\InvalidatePendingTimeCacheConsequence;
use MediaWiki\Moderation\ModerationNotifyModerator;

require_once __DIR__ . "/autoload.php";

class InvalidatePendingTimeCacheConsequenceTest extends ModerationUnitTestCase
{
    /**
     * Verify that InvalidatePendingTimeCacheConsequence invalidates the cache
     * used by ModerationNotifyModerator::getPendingTime().
     * @covers MediaWiki\Moderation\InvalidatePendingTimeCacheConsequence
     * @covers MediaWiki\Moderation\ModerationNotifyModerator
     */
    public function testPendingTimeCacheInvalidated()
    {
        $notify = $this->createMock(ModerationNotifyModerator::class);
        $notify->expects($this->once())->method("invalidatePendingTime");

        $this->setService("Moderation.NotifyModerator", $notify);

        // Create and run the Consequence.
        $consequence = new InvalidatePendingTimeCacheConsequence();
        $consequence->run();
    }
}
