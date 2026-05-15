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
 * Logger used by Moderation testsuite to dump the log of HTTP requests related to the failed test.
 */

namespace MediaWiki\Moderation\Tests;

use FormatJson;
use MediaWiki\Logger\LegacyLogger;

class ModerationTestsuiteLogger extends LegacyLogger
{
    /**
     * @var string
     * This name is mentioned by printBuffer() to avoid confusion "which test printed what".
     */
    protected static $currentTestName = "";

    /**
     * @var array[]
     * Accumulator of log entries.
     * If the test succeeds, they are silently ignored.
     * If the test fails, they are printed (to help with troubleshooting).
     *
     * @phan-var list<array{event:string}>
     */
    protected static $buffer = [];

    /**
     * Forget all stored entries. Meant to be used in ModerationTestCase::setUp().
     * @param string $newTestName Name of the new test (will be used when printing a buffer later).
     */
    public static function prepareCleanBuffer($newTestName = "")
    {
        self::$buffer = [];
        self::$currentTestName = $newTestName;
    }

    /**
     * Print all stored entries. Meant to be used in ModerationTestCase::onNotSuccessfulTest().
     */
    public static function printBuffer()
    {
        if (!self::$buffer) {
            return; // No log entries
        }

        $report = [
            "testName" => self::$currentTestName,
            "eventCount" => count(self::$buffer),
            "events" => self::$buffer,
        ];
        error_log(FormatJson::encode($report, true, FormatJson::ALL_OK));
    }

    /**
     * Add new log entry to accumulator.
     * @param int $level @phan-unused-param
     * @param mixed $message
     * @param array $context
     */
    public function log($level, $message, array $context = []): void
    {
        self::$buffer[] = ["event" => $message] + $context;
    }
}
