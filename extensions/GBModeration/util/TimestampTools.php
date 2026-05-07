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
 * Displays mod_timestamp on Special:Moderation.
 */

namespace MediaWiki\Moderation;

use IContextSource;
use Language;
use MWTimestamp;

class TimestampTools
{
    /**
     * @var bool
     * If true, return value of format() will always be "time+date" pair.
     * If false, format() will detect today's timestamps and return "time only" for it.
     *
     * This is performance optimization: because results on Special:Moderation are sorted
     * by timestamp (DESC), calls to format() will be in the same order,
     * so if format() discovers a non-today's timestamp (e.g. yesterday), then we know
     * that today's timestamps wont't be in the future calls to format().
     *
     * So on the first non-today's timestamp we set $skippedToday=true, and then omit the checks.
     */
    protected $skippedToday = false;

    /**
     * @var string
     * Cache used by isToday(): result of userAdjust(wfTimestampNow()).
     */
    protected $today = "";

    /** @var string|null Cache used by canReapproveRejected() */
    protected $earliestReapprovableTimestamp = null;

    /**
     * Returns human-readable version of $timestamp.
     * @param mixed $timestamp
     * @param IContextSource $context
     * @return string
     */
    public function format($timestamp, IContextSource $context)
    {
        $lang = $context->getLanguage();
        $user = $context->getUser();

        if (!$this->skippedToday && $this->isToday($timestamp, $lang)) {
            /* Only time */
            return $lang->userTime($timestamp, $user);
        }

        /* Time and date */
        $this->skippedToday = true;
        return $lang->userTimeAndDate($timestamp, $user);
    }

    /**
     * Returns true if $timestamp is today, false otherwise.
     * @param string $timestamp
     * @param Language $lang
     * @return bool
     */
    protected function isToday($timestamp, Language $lang)
    {
        if (!$this->today) {
            $this->today = (string) $lang->userAdjust(wfTimestampNow());
        }

        // MediaWiki timestamps (14 digits), respecting the timezone selected by current user.
        // First 8 symbols are YYYYMMDD. If they are the same, then the day is the same.
        $timestamp = (string) $lang->userAdjust(wfTimestamp(TS_MW, $timestamp));
        return strncmp($this->today, $timestamp, 8) == 0;
    }

    /**
     * Returns true if $timestamp is recent enough to be reapproved after rejection, false otherwise.
     * @param string $timestamp
     * @return bool
     */
    public function canReapproveRejected($timestamp)
    {
        if ($this->earliestReapprovableTimestamp === null) {
            global $wgModerationTimeToOverrideRejection;

            $ts = new MWTimestamp();
            $ts->timestamp->modify(
                "-" . intval($wgModerationTimeToOverrideRejection) . " seconds",
            );
            $this->earliestReapprovableTimestamp = $ts->getTimestamp(TS_MW);
        }

        $ts = new MWTimestamp($timestamp);
        $timestampOfEntry = $ts->getTimestamp(TS_MW);

        return $timestampOfEntry > $this->earliestReapprovableTimestamp;
    }
}
