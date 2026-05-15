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
 * Mocked version of ConsequenceManager for tests. Allows to easily examine queued consequences.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\IConsequence;
use MediaWiki\Moderation\IConsequenceManager;
use MWException;

class MockConsequenceManager implements IConsequenceManager
{
    /**
     * @var IConsequence[]
     */
    protected $consequences = [];

    /**
     * Mocked return values of run(). Populated by mockResult() and consumed by add().
     * @var array
     * @phan-var array<class-string,mixed[]>
     */
    protected $mockedResults = [];

    /**
     * Mocked version of add(): record the Consequence without running it, return mocked result.
     * @param IConsequence $consequence
     * @return mixed|null Mocked return value (if mockResult() was used before add()), if any.
     */
    public function add(IConsequence $consequence)
    {
        $this->consequences[] = $consequence;

        // Return the mocked return value (if any).
        $class = get_class($consequence);
        if (isset($this->mockedResults[$class])) {
            return array_shift($this->mockedResults[$class]);
        }
    }

    /**
     * Get the list of all previously queued Consequence objects. Can be used in tests.
     * @return IConsequence[]
     */
    public function getConsequences()
    {
        return $this->consequences;
    }

    /**
     * Add $result into a queue of return values that are consequentially returned by add() calls.
     * @param string $class Fully qualified class name of the Consequence, e.g. SomeConsequence::class.
     * @param mixed $result Value to return.
     *
     * @phan-param class-string $class
     */
    public function mockResult($class, $result)
    {
        if (!class_exists($class)) {
            throw new MWException(__METHOD__ . ": unknown class $class.");
        }

        if (!isset($this->mockedResults[$class])) {
            $this->mockedResults[$class] = [];
        }

        $this->mockedResults[$class][] = $result;
    }
}
