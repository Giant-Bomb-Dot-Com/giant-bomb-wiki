<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2018-2025 Edward Chernenko.

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
 * Parent trait for TestSet objects used in the Moderation testsuite.
 *
 * This trait must be included into ModerationTestCase subclasses.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\ModerationCompatTools;
use MediaWiki\Title\Title;
use MWTimestamp;
use PHPUnit\Framework\AssertionFailedError;
use stdClass;

/**
 * @method static void assertSame($a, $b, string $message='')
 * @method static void assertLessThanOrEqual($a, $b, string $message='')
 * @method static void assertGreaterThan($a, $b, string $message='')
 */
trait ModerationTestsuiteTestSet
{
    /**
     * Run this TestSet from input of dataProvider.
     * @param array $options Parameters of test, e.g. [ 'user' => '...', 'title' => '...' ].
     */
    public function runSet(array $options)
    {
        // Clone the set before each test.
        // This is needed to prevent properties from being preserved between runs.
        $set = clone $this;

        $set->applyOptions($options);
        $set->makeChanges();
        $set->assertResults();
    }

    /*--------------------------------------------------------------------------------------*/
    /* These abstract methods should be implemented in TestCase the uses ModerationTestSet. */

    /**
     * Initialize this TestSet from the input of dataProvider.
     * @param array $options
     */
    abstract protected function applyOptions(array $options);

    /**
     * Execute this TestSet, making the edit with requested parameters.
     */
    abstract protected function makeChanges();

    /**
     * Assert whether the situation after the edit is correct or not.
     */
    abstract protected function assertResults();

    /*----------------------------------------------------------------------------------------*/
    /* These abstract methods are provided by ModerationTestCase and PHPUnit-related classes. */

    /** @return ModerationTestsuite */
    abstract public function getTestsuite();

    /*-------------------------------------------------------------------*/

    /**
     * Assert that $timestamp is a realistic time for changes made during this test.
     * @param string $timestamp Timestamp in any format that is understood by MWTimestamp.
     */
    protected function assertTimestampIsRecent($timestamp)
    {
        // How many seconds ago are allowed without failing the assertion.
        $allowedRangeInSeconds = 60;

        $ts = new MWTimestamp($timestamp);
        $timestamp = $ts->getTimestamp(TS_MW);

        $this->assertLessThanOrEqual(
            wfTimestampNow(),
            $timestamp,
            "assertTimestampIsRecent(): timestamp of existing change is in the future.",
        );

        $ts = new MWTimestamp();
        $ts->timestamp->modify("- $allowedRangeInSeconds seconds");
        $minTimestamp = $ts->getTimestamp(TS_MW);

        $this->assertGreaterThan(
            $minTimestamp,
            $timestamp,
            "assertTimestampIsRecent(): timestamp of recently made change is too far in the past.",
        );
    }

    /**
     * Convert timestamp from TS_DB format (needed for PostgreSQL) to MediaWiki format.
     * @param string $fieldName Name of the field, e.g. "mod_timestamp".
     * @param string $rawValue Value of the field.
     * @return string Converted value.
     */
    private static function convertField($fieldName, $rawValue)
    {
        if ($fieldName != "mod_timestamp") {
            return $rawValue;
        }

        $ts = new MWTimestamp($rawValue);
        return $ts->getTimestamp(TS_MW);
    }

    /**
     * Assert that recent row in 'moderation' SQL table consists of $expectedFields.
     * @param array $expectedFields Key-value list of all mod_* fields.
     * @throws AssertionFailedError
     * @return stdClass $row
     */
    protected function assertRowEquals(array $expectedFields)
    {
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $row = $dbw->selectRow("moderation", "*", "", __METHOD__);

        // Create sorted arrays Expected and Actual and ensure no difference between them.

        $expected = [];
        $actual = [];

        foreach ($expectedFields as $key => $expectedValue) {
            $actualValue = self::convertField($key, $row->$key);

            if (is_numeric($actualValue)) {
                // DB::selectRow() returns numbers as strings, so we need to cast them to numbers,
                // or else assertSame() would fail.
                // E.g. "1" => 1.
                $actualValue += 0;
            }

            if ($expectedValue instanceof ModerationTestSetRegex) {
                $regex = (string) $expectedValue;
                if (preg_match($regex, $actualValue)) {
                    // This is a trick to display a simple diff of Expected/Actual arrays,
                    // even though some of the $expectedFields are regexes (not constants).
                    $actualValue .= " (regex: " . $regex . ")";
                    $expected[$key] = $actualValue;
                } else {
                    $actualValue .= " (DOESN'T MATCH REGEX)";
                    $expected[$key] = $regex;
                }
            } else {
                if ($key == "mod_timestamp") {
                    // Convert from database format (different in PostgreSQL) to MediaWiki format.
                    $ts = new MWTimestamp($actualValue);
                    $actualValue = $ts->getTimestamp(TS_MW);
                }

                $expected[$key] = self::convertField($key, $expectedValue);
            }

            $actual[$key] = $actualValue;
        }

        asort($expected);
        asort($actual);

        $this->assertSame(
            $expected,
            $actual,
            "Database row doesn't match expected.",
        );

        return $row;
    }

    /**
     * Create an existing page (or file) before the test.
     * @param Title $title
     * @param string $initialText
     * @param string|null $filename If not null, upload another file (NOT $filename) before test.
     */
    protected function precreatePage(
        Title $title,
        $initialText,
        $filename = null,
    ) {
        $t = $this->getTestsuite();
        $t->loginAs($t->moderator);

        if ($filename) {
            // Important: $filename is the file that will be uploaded by the test itself.
            // We want to pre-upload a different file here, so that attempts
            // to later approve $filename wouldn't fail with (fileexists-no-change).
            $anotherFilename =
                strpos($filename, "image100x100.png") === false
                    ? "image100x100.png"
                    : "image640x50.png";

            $t->getBot("api")->upload(
                $title->getText(),
                $anotherFilename,
                $initialText,
            );
        } else {
            // Normal page (not an upload).
            ModerationTestUtil::fastEdit(
                $title,
                $initialText,
                "", // edit summary doesn't matter
                $t->moderator,
            );
        }
    }
}
