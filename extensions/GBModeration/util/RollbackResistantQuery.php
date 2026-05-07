<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2017-2021 Edward Chernenko.

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
 * Performs database query that is not rolled back by MWException.
 */

namespace MediaWiki\Moderation;

use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Services\DestructibleService;

class RollbackResistantQuery implements DestructibleService
{
    /** @var callable[] All callables that were passed to perform() */
    protected $operations = [];

    /** @var ILoadBalancer */
    protected $loadBalancer;

    /**
     * @param ILoadBalancer $loadBalancer
     */
    public function __construct(ILoadBalancer $loadBalancer)
    {
        $this->loadBalancer = $loadBalancer;

        // Install hooks that can detect a database rollback.
        $this->loadBalancer->setTransactionListener(
            "moderation-on-rollback-or-commit",
            function ($trigger) {
                if ($trigger == IDatabase::TRIGGER_ROLLBACK) {
                    // Redo all operations, because rollback just undid them.
                    foreach ($this->operations as $func) {
                        $func();
                    }
                } elseif ($trigger == IDatabase::TRIGGER_COMMIT) {
                    // COMMIT was successful (previous queries will no longer be rolled back),
                    // so there is no longer any need to repeat them.
                    $this->operations = [];
                }
            },
        );
    }

    /**
     * Unbind the listener, so that it wouldn't be called with reference to the destroyed service.
     */
    public function destroy()
    {
        $this->loadBalancer->setTransactionListener(
            "moderation-on-rollback-or-commit",
            null,
        );
    }

    /**
     * Perform some database operation that won't be undone by Database::rollback().
     * @param callable $func A callback that uses methods like DB::insert(), etc.
     */
    public function perform(callable $func)
    {
        // The query is invoked immediately. If rollback() happens, the query will be repeated.
        $func();

        // All operations will be re-run after rollback()
        $this->operations[] = $func;
    }
}
