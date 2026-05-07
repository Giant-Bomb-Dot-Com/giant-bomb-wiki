<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2017-2024 Edward Chernenko.

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
 * HTTP response to be analyzed by tests. Made from FauxResponse and content.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Request\FauxResponse;

class ModerationTestsuiteResponse implements IModerationTestsuiteResponse
{
    /** @var string Response text */
    protected $content;

    /** @var int HTTP return code, e.g. 200 */
    protected $httpCode;

    /** @var callable Implementation-specific callback used by getResponseHeader() */
    protected $getHeaderMethod;

    /**
     * @param string $content
     * @param int $httpCode
     * @param callable $getHeaderMethod
     */
    protected function __construct(
        $content,
        $httpCode,
        callable $getHeaderMethod,
    ) {
        $this->content = $content;
        $this->httpCode = $httpCode;
        $this->getHeaderMethod = $getHeaderMethod;
    }

    /**
     * Create response from FauxResponse and its content.
     * @param FauxResponse $mwResponse Response object after $mediaWiki->run
     * @param string $capturedContent Text printed by $mediaWiki->run
     * @return IModerationTestsuiteResponse
     */
    public static function newFromFauxResponse(
        FauxResponse $mwResponse,
        $capturedContent,
    ) {
        $httpCode = $mwResponse->getStatusCode();
        if (!$httpCode) {
            /* WebResponse doesn't set code for successful requests */
            if ($mwResponse->getHeader("Location")) {
                $httpCode = 302; /* Successful redirect */
            } else {
                $httpCode = 200; /* Successful non-redirect */
            }
        }

        return new self($capturedContent, $httpCode, [
            $mwResponse,
            "getHeader",
        ]);
    }

    /**
     * @param string $headerName
     * @return string|null
     */
    public function getResponseHeader($headerName)
    {
        return call_user_func($this->getHeaderMethod, $headerName);
    }

    /** @return int */
    public function getStatus()
    {
        return $this->httpCode;
    }

    /** @return string */
    public function getContent()
    {
        return $this->content;
    }

    /** @return bool */
    public function isRedirect()
    {
        return $this->httpCode >= 300 && $this->httpCode <= 303;
    }
}
