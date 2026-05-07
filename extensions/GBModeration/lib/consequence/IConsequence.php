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
 * Interface for Consequence object - one high-level operation like "send notification email".
 */

namespace MediaWiki\Moderation;

/**
 * The point of Consequence object is to make writing decoupled unit tests as easy as possible,
 * by mocking the ConsequenceManager and observing which Consequence objects are added into it,
 * and providing a separate unit test for each Consequence class.
 *
 * For this to work, the following conventions must be followed:
 *
 * 1) Consequence object is immutable and is completely defined by parameters of its constructor.
 * 2) Two consequences with same class and parameters are expected to do EXACTLY the same.
 * 3) Setter methods are forbidden. run() must use information that was received by constructor.
 * 4) Consequence is a high-level operation. It shouldn't do anything overly generic,
 * or else its unit test would need too many checks for cases that we don't even use.
 * (GOOD example: BlockUser($name), BAD example: ModifyDbRow($fields, $where) - too low-level")
 * 5) run() is allowed (but not required) to return a value (e.g. "bool $wasSuccessful" or Status).
 * 6) run() shouldn't include checks like "do we even need to run this Consequence?".
 * This should be decided by the code that calls ConsequenceManager::add(), and if some Consequence
 * doesn't need to be invoked, then it shouldn't be added to ConsequenceManager to begin with.
 * 7) Parameters must be easily comparable/serializable. Title and User are acceptable, but complex
 * and difficult-to-construct objects like EditPage or ContextSource can't be parameters.
 */
interface IConsequence
{
    /**
     * Execute the consequence.
     */
    public function run();
}
