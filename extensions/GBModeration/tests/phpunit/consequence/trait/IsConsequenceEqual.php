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
 * PHPUnit constraint to compare Consequence objects, as expected by assertThat() checks.
 */

namespace MediaWiki\Moderation\Tests;

use FormatJson;
use MediaWiki\Moderation\IConsequence;
use MediaWiki\Moderation\InsertRowIntoModerationTableConsequence;
use MediaWiki\Title\Title;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use User;
use WikiPage;

class IsConsequenceEqual extends Constraint
{
    /**
     * @var IConsequence
     */
    private $value;

    /**
     * @param IConsequence $consequence
     */
    public function __construct(IConsequence $consequence)
    {
        $this->value = $consequence;
    }

    /**
     * Returns a string representation of Consequence object.
     * Used in toString() and failureDescription().
     * @param IConsequence $consequence
     * @return string
     */
    private function stringify(IConsequence $consequence)
    {
        return FormatJson::encode(self::toArray($consequence), true);
    }

    /**
     * Returns a string representation of the constraint.
     * @return string
     */
    public function toString(): string
    {
        return "equals to " . $this->stringify($this->value);
    }

    /**
     * @param IConsequence $other
     * @return bool
     */
    protected function matches($other): bool
    {
        return self::toArray($this->value) === self::toArray($other);
    }

    /**
     * Returns the description of the failure
     * @param mixed $other
     * @return string
     */
    protected function failureDescription($other): string
    {
        return $this->stringify($other) . " " . $this->toString();
    }

    /**
     * Convert $consequence into a human-readable array of properties (for logging and comparison).
     * Properties with types like Title are replaced by [ className, mixed, ... ] arrays.
     * @param IConsequence $consequence
     * @return array
     */
    public static function toArray(IConsequence $consequence)
    {
        $fields = [];

        $rc = new ReflectionClass($consequence);
        foreach ($rc->getProperties() as $prop) {
            $prop->setAccessible(true);
            $value = $prop->getValue($consequence);

            $type = gettype($value);
            if ($type == "object" && !($value instanceof MockObject)) {
                if ($value instanceof Title) {
                    $value = ["Title", (string) $value];
                } elseif ($value instanceof WikiPage) {
                    $value = ["WikiPage", (string) $value->getTitle()];
                } elseif ($value instanceof User) {
                    $value = ["User", $value->getId(), $value->getName()];
                }
            }

            $name = $prop->getName();

            if (
                $consequence instanceof
                    InsertRowIntoModerationTableConsequence &&
                $name == "fields"
            ) {
                # Cast all values to strings. 2 and "2" are the same for DB::insert(),
                # so caller shouldn't have to write "mod_namespace => (string)2" explicitly.
                # We also don't want it to prevent Phan from typechecking inside $fields array.
                $value = array_map("strval", $value);
                ksort($value);

                // Having timestamps in normalized form leads to flaky comparison results,
                // because it's possible that "expected timestamp" was calculated
                // in a different second than mod_timestamp in an actual Consequence.
                $value["mod_timestamp"] = "<<< MOCKED TIMESTAMP >>>";
            }

            $fields[$name] = $value;
        }

        return [get_class($consequence), $fields];
    }
}
