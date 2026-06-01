<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2018-2024 Edward Chernenko.

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
 * Displays "Upload successful! Sent for moderation!" on Special:Upload.
 *
 * When we return "moderation-edit-queued" Status from upload/move hooks,
 * forms like Special:Upload and Special:MovePage often portray this as
 * some kind of fatal error. For example, MovePage says "Permission error:
 * queued for moderation", which is not user-friendly at all.
 *
 * To avoid that, we throw ErrorPageError exception that completely
 * rewrites the response page, saying that Upload/Move were a success,
 * followed by explanation about moderation.
 */

namespace MediaWiki\Moderation;

use ErrorPageError;
use RequestContext;

class ModerationQueuedSuccessException extends ErrorPageError
{
    /**
     * Throw this exception if the user is on a special page that needs it.
     * @param string $msg
     * @param array $params
     */
    public static function throwIfNeeded($msg, array $params = [])
    {
        $title = RequestContext::getMain()->getTitle();
        if (!$title) {
            return; /* We are in the maintenance script */
        }

        /* Special:{Upload,MovePage} treat "moderation-{image,move}-queued"
			as an error.
			Let's display a user-friendly results page instead. */
        if ($title->isSpecial("Upload") || $title->isSpecial("Movepage")) {
            throw new self($msg, $params);
        }
    }

    /**
     * @inheritDoc
     */
    public function isLoggable()
    {
        /* This is a successful action, not an error,
         it doesn't belong in the error log */
        return false;
    }

    /**
     * @inheritDoc
     */
    public function __construct($msg, array $params)
    {
        parent::__construct("moderation", $msg, $params);
    }
}
