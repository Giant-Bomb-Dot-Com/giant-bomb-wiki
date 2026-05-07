<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2025 Edward Chernenko.

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
 * Unit test of TimestampTools.
 */

namespace MediaWiki\Moderation\Tests;

use IContextSource;
use Language;
use MediaWiki\Moderation\TimestampTools;
use MWTimestamp;
use User;
use Wikimedia\TestingAccessWrapper;

require_once __DIR__ . "/autoload.php";

class TimestampToolsTest extends ModerationUnitTestCase
{
    /**
     * Test the results of TimestampTools::format().
     * @dataProvider dataProviderFormat
     * @param string $timestamp First parameter passed to format().
     * @param string $mockedAdjustedTimestamp Mocked value of "userAdjust($timestamp)".
     * @param string $mockedAdjustedToday Mocked value of "userAdjust(wfTimestampNow())".
     * @param bool $expectTimeOnly Whether format() should return date+time (false) or time (true).
     *
     * @covers MediaWiki\Moderation\TimestampTools
     */
    public function testFormat(
        $timestamp,
        $mockedAdjustedTimestamp,
        $mockedAdjustedToday,
        $expectTimeOnly,
    ) {
        $context = $this->createMock(IContextSource::class);
        $lang = $this->createMock(Language::class);
        $user = $this->createMock(User::class);

        $context
            ->expects($this->once())
            ->method("getLanguage")
            ->willReturn($lang);
        $context->expects($this->once())->method("getUser")->willReturn($user);

        $callIndex = 0;
        $lang
            ->expects($this->exactly(2))
            ->method("userAdjust")
            ->will(
                $this->returnCallback(function ($param) use (
                    $timestamp,
                    $mockedAdjustedToday,
                    $mockedAdjustedTimestamp,
                    &$callIndex,
                ) {
                    if (++$callIndex == 1) {
                        // First call to $lang->userAdjust() is used to calculate $today from wfTimestampNow().
                        // Ensure that $param is not too far away from NOW. Allow 2 seconds of difference.
                        $secondsDiff = abs(
                            (int) wfTimestamp(TS_UNIX, 0) -
                                (int) wfTimestamp(TS_UNIX, $param),
                        );
                        $this->assertLessThan(
                            2,
                            $secondsDiff,
                            'When calculating $today, timestamp passed to userAdjust() was too different from NOW.',
                        );
                        return $mockedAdjustedToday;
                    }

                    // Second call to $lang->userAdjust() is used on first parameter of format().
                    // Note: format() can accept any format of timestamp (e.g. TS_POSTGRES),
                    // but it MUST be converted, as userAdjust() can only receive TS_MW.
                    $this->assertSame(wfTimestamp(TS_MW, $timestamp), $param);
                    return $mockedAdjustedTimestamp;
                }),
            );

        if ($expectTimeOnly) {
            $mockedResult =
                "{mocked result: time only " . rand(0, 100000) . "}";
            $lang
                ->expects($this->once())
                ->method("userTime")
                ->with($this->identicalTo($timestamp))
                ->willReturn($mockedResult);
            $lang->expects($this->never())->method("userTimeAndDate");
        } else {
            $mockedResult =
                "{mocked result: time AND date " . rand(0, 100000) . "}";
            $lang
                ->expects($this->once())
                ->method("userTimeAndDate")
                ->with($this->identicalTo($timestamp))
                ->willReturn($mockedResult);
            $lang->expects($this->never())->method("userTime");
        }

        '@phan-var IContextSource $context';
        '@phan-var Language $lang';

        $timestampTools = new TimestampTools();
        $result = $timestampTools->format($timestamp, $context);
        $this->assertSame($mockedResult, $result);

        // Additionally test the internal cache of TimestampTools ($skippedToday).
        $skippedToday = TestingAccessWrapper::newFromObject($timestampTools)
            ->skippedToday;
        if ($expectTimeOnly) {
            $this->assertFalse(
                $skippedToday,
                "After today's timestamp \$skippedToday was incorrectly set to true.",
            );
        } else {
            $this->assertTrue(
                $skippedToday,
                "After encountering a non-today's timestamp \$skippedToday wasn't set to true.",
            );

            // Additionally test the behavior of cache.
            // In this mode userTimeAndDate() is always called, and all checks from isToday() are skipped.
            $context = $this->createMock(IContextSource::class);
            $lang = $this->createMock(Language::class);

            $context
                ->expects($this->once())
                ->method("getLanguage")
                ->willReturn($lang);
            $context
                ->expects($this->once())
                ->method("getUser")
                ->willReturn($user);

            $lang->expects($this->never())->method("userTime");
            $lang
                ->expects($this->once())
                ->method("userTimeAndDate")
                ->with($this->identicalTo($timestamp))
                ->willReturn($mockedResult);

            '@phan-var IContextSource $context';
            '@phan-var Language $lang';

            $result = $timestampTools->format($timestamp, $context);
            $this->assertSame($mockedResult, $result);
        }
    }

    /**
     * Provide datasets for testFormat() runs.
     * @return array
     */
    public function dataProviderFormat()
    {
        return [
            "today: expecting ONLY time" => [
                // $timestamp is "3 January 5:00", $today is "3 January 8:00", no time correction.
                "20120103050000", // $timestamp
                "20120103050000", // $mockedAdjustedTimestamp
                "20120103080000", // $mockedAdjustedToday
                true, // $expectTimeOnly
            ],
            "not today: expecting time AND date" => [
                "20120102050000", // $timestamp
                "20120102050000", // $mockedAdjustedTimestamp
                "20120103080000", // $mockedAdjustedToday
                false, // $expectTimeOnly
            ],
            "today without adjustment, but not today after adjustment: expecting time AND date" => [
                "20120103010000", // $timestamp
                "20120102210000", // $mockedAdjustedTimestamp: timezone UTC-4
                "20120103080000", // $mockedAdjustedToday
                false, // $expectTimeOnly
            ],
            "not today without adjustment, but today after adjustment: expecting ONLY time" => [
                "20120102210000", // $timestamp
                "20120103010000", // $mockedAdjustedTimestamp: timezone UTC+4
                "20120103080000", // $mockedAdjustedToday
                true, // $expectTimeOnly
            ],

            "today (timestamp in PostgreSQL format): expecting ONLY time" => [
                "2012-01-03 05:00:00+00", // $timestamp
                "20120103050000", // $mockedAdjustedTimestamp
                "20120103080000", // $mockedAdjustedToday
                true, // $expectTimeOnly
            ],
            "not today (timestamp in PostgreSQL format): expecting time AND date" => [
                "2012-01-02 05:00:00+00", // $timestamp
                "20120102050000", // $mockedAdjustedTimestamp
                "20120103080000", // $mockedAdjustedToday
                false, // $expectTimeOnly
            ],
        ];
    }

    /**
     * Check the return value of canReapproveRejected().
     * @param bool $expectedResult
     * @param int $timeDiffFromNow How long ago did "timestamp" take place.
     * @param int $maxTimeDiffFromNow Value of $wgModerationTimeToOverrideRejection.
     * @dataProvider dataProviderCanReapproveRejected
     * @covers MediaWiki\Moderation\TimestampTools
     */
    public function testCanReapproveRejected(
        $expectedResult,
        $timeDiffFromNow,
        $maxTimeDiffFromNow,
    ) {
        $this->overrideConfigValue(
            "ModerationTimeToOverrideRejection",
            $maxTimeDiffFromNow,
        );

        $timestampTools = new TimestampTools();
        $result = $timestampTools->canReapproveRejected(
            $this->pastTimestamp($timeDiffFromNow),
        );
        $this->assertSame($expectedResult, $result, "canReapproveRejected");

        // Additionally test the internal cache of TimestampTools ($earliestReapprovableTimestamp).
        $earliest = TestingAccessWrapper::newFromObject($timestampTools)
            ->earliestReapprovableTimestamp;
        $this->assertNotNull(
            $earliest,
            'canReapproveRejected() didn\'t create the cache.',
        );
        $this->assertEqualsWithDelta(
            intval(MWTimestamp::now(TS_UNIX)) - $maxTimeDiffFromNow,
            MWTimestamp::convert(TS_UNIX, $earliest),
            5, // This assertion should succeed if canReapproveRejected() was called less than 5 seconds ago.
            'Value of $earliestReapprovableTimestamp is too far away from current time.',
        );
    }

    /**
     * Provide datasets for testCanReapproveRejected() runs.
     * @return array
     */
    public function dataProviderCanReapproveRejected()
    {
        return [
            "allowed: rejected 1000 seconds ago, default time to override (2 weeks)" => [
                true,
                1000,
                1209600,
            ],
            "allowed: rejected 500 seconds ago, time to override is 501 second" => [
                true,
                500,
                501,
            ],
            "NOT allowed: rejected 500 seconds ago, time to override is 499 seconds" => [
                false,
                500,
                499,
            ],
        ];
    }
}
