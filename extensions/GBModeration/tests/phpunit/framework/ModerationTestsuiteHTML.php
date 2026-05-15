<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2015-2024 Edward Chernenko.

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
 * Subclass of ModerationTestHTML with networking methods like loadUrl(), loadReq(), etc.
 */

namespace MediaWiki\Moderation\Tests;

use MWException;

class ModerationTestsuiteHTML extends ModerationTestHTML
{
    /** @var IModerationTestsuiteEngine|null */
    protected $engine;

    /**
     * @param IModerationTestsuiteEngine|null $engine
     */
    public function __construct(IModerationTestsuiteEngine $engine = null)
    {
        $this->engine = $engine;
    }

    /**
     * Load the HTML document from URL.
     * @param string $url
     * @return self
     */
    public function loadUrl($url)
    {
        if (!$this->engine) {
            throw new MWException(
                "This ModerationTestsuiteHTML object can't use load(\$url), " .
                    "it was created without ModerationTestsuiteEngine.",
            );
        }

        $req = $this->engine->httpRequest($url, "GET");
        return $this->loadReq($req);
    }

    /**
     * Load the HTML document from the result of $t->httpGet(), $t->httpPost()
     * @param IModerationTestsuiteResponse $req
     * @return self
     */
    public function loadReq(IModerationTestsuiteResponse $req)
    {
        return $this->loadString($req->getContent());
    }

    /**
     * Fetch the edit form and return the text in #wpTextbox1.
     * @param string $title The page to be opened for editing.
     * @return string|null
     */
    public function getPreloadedText($title)
    {
        $url = wfAppendQuery(wfScript("index"), [
            "title" => $title,
            "action" => "edit",
        ]);
        $elem = $this->loadUrl($url)->getElementById("wpTextbox1");
        return $elem ? trim($elem->textContent) : null;
    }
}
