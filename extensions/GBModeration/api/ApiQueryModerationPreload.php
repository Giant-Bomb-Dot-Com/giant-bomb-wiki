<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2016-2024 Edward Chernenko.

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
 * API to preload edits which are pending moderation.
 *
 * This can be used by API-based JavaScript editors,
 * for example Extension:VisualEditor or Extension:MobileFrontend.
 */

namespace MediaWiki\Moderation;

use ApiMain;
use ApiQuery;
use ApiQueryBase;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

class ApiQueryModerationPreload extends ApiQueryBase
{
    /** @var ModerationPreload */
    protected $preload;

    /**
     * @param ApiQuery $query
     * @param string $moduleName
     * @param ModerationPreload $preload
     */
    public function __construct(
        ApiQuery $query,
        $moduleName,
        ModerationPreload $preload,
    ) {
        parent::__construct($query, $moduleName, "mp");

        $this->preload = $preload;
    }

    public function execute()
    {
        $params = $this->extractRequestParams();

        $page = $this->getTitleOrPageId($params);
        $title = $page->getTitle();

        /* Prepare the result array */
        $r = [
            "user" => $this->getUser()->getName(),
            "title" => $title->getFullText(),
            "pageid" => $title->getArticleID(),
        ];

        /* Load text which is currently awaiting moderation */
        $pendingEdit = $this->preload->findPendingEdit($title);
        if (!$pendingEdit) {
            $r["missing"] = ""; /* There is no pending edit */
        } else {
            $wikitext = $pendingEdit->getSectionText($params["section"] ?? "");

            if ($params["mode"] == "wikitext") {
                $r["wikitext"] = $wikitext;
            } elseif ($params["mode"] == "parsed") {
                $r["parsed"] = $this->parse($title, $wikitext);
            }

            $r["comment"] = $pendingEdit->getComment();
        }

        $this->getResult()->addValue("query", $this->getModuleName(), $r);
    }

    /**
     * Parse $wikitext and return the results.
     * @param Title $title
     * @param string $wikitext
     * @return array with keys 'text', 'categorieshtml', 'displaytitle'
     *
     * @phan-return array{text:string,categorieshtml:string,displaytitle:string}
     */
    protected function parse(Title $title, $wikitext)
    {
        $apiParams = [
            "action" => "parse",
            "text" => $wikitext,
            "title" => $title->getFullText(),
            "prop" => "text|categorieshtml|displaytitle",
            "disablepp" => "",
        ];

        $api = new ApiMain(
            new DerivativeRequest(
                $this->getRequest(),
                $apiParams,
                false, // GET request
            ),
        );
        $api->execute();

        $ret = $api->getResult()->getResultData(null, [
            "Strip" => "all",
        ]);

        $parsed = [];
        $parsed["text"] = $ret["parse"]["text"];
        $parsed["categorieshtml"] = $ret["parse"]["categorieshtml"];
        $parsed["displaytitle"] = $ret["parse"]["displaytitle"];

        return $parsed;
    }

    /**
     * @inheritDoc
     */
    public function getAllowedParams()
    {
        return [
            "mode" => [
                ParamValidator::PARAM_DEFAULT => "wikitext",
                ParamValidator::PARAM_TYPE => ["wikitext", "parsed"],
            ],
            "title" => null,
            "pageid" => [
                ParamValidator::PARAM_TYPE => "integer",
            ],
            "section" => null,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getExamplesMessages()
    {
        return [
            "action=query&prop=moderationpreload&mptitle=Cat" =>
                "apihelp-query+moderationpreload-wikitext-example",
            "action=query&prop=moderationpreload&mptitle=Dog&mpmode=parsed" =>
                "apihelp-query+moderationpreload-parsed-example",
            "action=query&prop=moderationpreload&mptitle=Cat&mpsection=2" =>
                "apihelp-query+moderationpreload-section-example",
        ];
    }
}
