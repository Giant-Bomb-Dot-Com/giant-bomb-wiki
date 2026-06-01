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
 * Unit test of RollbackResistantQuery.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\RollbackResistantQuery;
use stdClass;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

require_once __DIR__ . "/autoload.php";

class RollbackResistantQueryTest extends ModerationUnitTestCase
{
    /**
     * @var callable|null
     * Populated by mock from mockLoadBalancer(). Cleared in setUp().
     */
    private $listenerFunc = null;

    /**
     * Verify that perform() runs its callback immediately.
     * @covers MediaWiki\Moderation\RollbackResistantQuery
     */
    public function testPerform()
    {
        $rrQuery = new RollbackResistantQuery($this->mockLoadBalancer());
        $this->assertNotNull(
            $this->listenerFunc,
            "TransactionListener wasn't installed.",
        );

        $cb = $this->makeCallCounter(1);
        $rrQuery->perform($cb);
    }

    /**
     * Verify that callback passed to perform() will be repeated on the database rollback.
     * @covers MediaWiki\Moderation\RollbackResistantQuery
     */
    public function testRepeatOnRollback()
    {
        $rrQuery = new RollbackResistantQuery($this->mockLoadBalancer());
        $this->assertNotNull(
            $this->listenerFunc,
            "TransactionListener wasn't installed.",
        );

        $cb = $this->makeCallCounter(2); // Must be called twice: 1) immediately, 2) after rollback
        $rrQuery->perform($cb);

        // Inform the listener that a DB rollback has happened.
        ($this->listenerFunc)(IDatabase::TRIGGER_ROLLBACK);
    }

    /**
     * Verify that a successful COMMIT erases any obligations to repeat the operations on ROLLBACK.
     * @covers MediaWiki\Moderation\RollbackResistantQuery
     */
    public function testCommitClearsEverything()
    {
        $rrQuery = new RollbackResistantQuery($this->mockLoadBalancer());
        $this->assertNotNull(
            $this->listenerFunc,
            "TransactionListener wasn't installed.",
        );

        $cb = $this->makeCallCounter(1);
        $rrQuery->perform($cb);

        // Inform the listener that a DB commit has happened.
        ($this->listenerFunc)(IDatabase::TRIGGER_COMMIT);

        // Inform the listener that a DB rollback has happened.
        // This won't cause $cb to be repeated, because COMMIT already made this query completed.
        ($this->listenerFunc)(IDatabase::TRIGGER_ROLLBACK);
    }

    /**
     * Verify that destroying the service doesn't leave a remaining TransactionListener callback.
     * @covers MediaWiki\Moderation\RollbackResistantQuery
     */
    public function testDestroyService()
    {
        $rrQuery = new RollbackResistantQuery($this->mockLoadBalancer());
        $this->assertNotNull(
            $this->listenerFunc,
            "TransactionListener wasn't installed.",
        );

        $rrQuery->destroy();
        $this->assertNull(
            $this->listenerFunc,
            "TransactionListener wasn't cleared after the service was destroyed.",
        );
    }

    /**
     * Verify that if perform() was called many times, then all callbacks are repeated on ROLLBACK.
     * @covers MediaWiki\Moderation\RollbackResistantQuery
     */
    public function testManyQueries()
    {
        $rrQuery = new RollbackResistantQuery($this->mockLoadBalancer());
        $this->assertNotNull(
            $this->listenerFunc,
            "TransactionListener wasn't installed.",
        );

        for ($i = 0; $i < 4; $i++) {
            $rrQuery->perform($this->makeCallCounter(2));
        }

        // Inform the listener that a DB rollback has happened.
        ($this->listenerFunc)(IDatabase::TRIGGER_ROLLBACK);
    }

    /**
     * Get a mocked LoadBalancer that will remember TransactionListener callback that was added to it.
     * @return ILoadBalancer
     */
    private function mockLoadBalancer()
    {
        // Mock the LoadBalancer to remember TransactionListener callback that was added to it.
        $loadBalancer = $this->createMock(ILoadBalancer::class);
        $loadBalancer
            ->expects($this->any())
            ->method("setTransactionListener")
            ->with(
                $this->identicalTo("moderation-on-rollback-or-commit"),
                $this->logicalOr($this->isType("callable"), $this->isNull()),
            )
            ->will(
                $this->returnCallback(function ($_, $callback) {
                    $this->listenerFunc = $callback;
                }),
            );

        '@phan-var ILoadBalancer $loadBalancer';

        return $loadBalancer;
    }

    /**
     * Returns a callback that will cause test failure if not called exactly $numberOfCalls times.
     * @param int $numberOfCalls
     * @return callable
     */
    private function makeCallCounter($numberOfCalls)
    {
        $callCounter = $this->getMockBuilder(stdClass::class)
            ->addMethods(["doSomething"])
            ->getMock();
        $callCounter
            ->expects($this->exactly($numberOfCalls))
            ->method("doSomething");

        return static function () use ($callCounter) {
            // @phan-suppress-next-line PhanUndeclaredMethod
            $callCounter->doSomething();
        };
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->listenerFunc = null;
    }
}
