<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2014-2020 Edward Chernenko.

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
 * Provides makeLink() method for "modaction=something" links.
 */

namespace MediaWiki\Moderation;

use IContextSource;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\Title;

class ActionLinkRenderer
{
    /** @var IContextSource */
    protected $context;

    /** @var LinkRenderer */
    protected $linkRenderer;

    /** @var Title */
    protected $specialPageTitle;

    /**
     * @param IContextSource $context
     * @param LinkRenderer $linkRenderer
     * @param Title $specialPageTitle
     */
    public function __construct(
        IContextSource $context,
        LinkRenderer $linkRenderer,
        Title $specialPageTitle,
    ) {
        $this->context = $context;
        $this->linkRenderer = $linkRenderer;
        $this->specialPageTitle = $specialPageTitle;
    }

    /**
     * Generate HTML of the link to Special:Moderation?modaction=something.
     * @param string $action
     * @param int $id
     * @return string
     */
    public function makeLink($action, $id)
    {
        $params = ["modaction" => $action, "modid" => $id];
        if ($action != "show" && $action != "preview") {
            $params["token"] = $this->context->getUser()->getEditToken();
        }

        return $this->linkRenderer->makePreloadedLink(
            $this->specialPageTitle,
            $this->context->msg("moderation-" . $action)->plain(),
            "",
            [
                "title" => $this->context
                    ->msg("tooltip-moderation-" . $action)
                    ->plain(),
            ],
            $params,
        );
    }
}
