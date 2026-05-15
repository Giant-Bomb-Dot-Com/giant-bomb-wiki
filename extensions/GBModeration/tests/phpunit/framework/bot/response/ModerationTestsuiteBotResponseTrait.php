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
 * Trait for classes that represent return value of bot methods edit(), move(), upload().
 */

namespace MediaWiki\Moderation\Tests;

trait ModerationTestsuiteBotResponse
{
    /** @var bool */
    private $isIntercepted;

    /** @var bool */
    private $isBypassed;

    /** @var string|null */
    private $error;

    /**
     * Construct new BotResponse object.
     * @param array|IModerationTestsuiteResponse $nativeResponse Depends on bot type (API or not).
     * @param bool $isIntercepted
     * @param bool $isBypassed
     * @param string|null $error
     * @return ModerationTestsuiteApiBotResponse|ModerationTestsuiteNonApiBotResponse
     */
    public static function factory(
        $nativeResponse,
        $isIntercepted,
        $isBypassed,
        $error,
    ) {
        // @phan-suppress-next-line PhanUndeclaredMethod
        $r = new static($nativeResponse);
        $r->isIntercepted = $isIntercepted;
        $r->isBypassed = $isBypassed;
        $r->error = $error;

        return $r;
    }

    /**
     * Check if this action was intercepted (and queued) by Moderation.
     * @return bool
     */
    public function isIntercepted()
    {
        return $this->isIntercepted;
    }

    /**
     * Check if this action has bypassed Moderation (was applied immediately).
     * @return bool
     */
    public function isBypassed()
    {
        return $this->isBypassed;
    }

    /**
     * Returns the error string (if any) or null (if the operation was successful).
     * @return string|null
     */
    public function getError()
    {
        return $this->error;
    }
}
