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
 * Interface that represents HTTP response.
 */

namespace MediaWiki\Moderation\Tests;

interface IModerationTestsuiteResponse
{
    /**
     * @param string $headerName
     * @return string|null
     */
    public function getResponseHeader($headerName);

    /** @return int */
    public function getStatus();

    /** @return string */
    public function getContent();

    /** @return bool */
    public function isRedirect();
}
