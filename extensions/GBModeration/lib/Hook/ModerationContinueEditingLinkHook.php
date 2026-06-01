<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2021 Edward Chernenko.

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
 *
 */

namespace MediaWiki\Moderation\Hook;

use IContextSource;
use MediaWiki\Linker\LinkTarget;

interface ModerationContinueEditingLinkHook
{
    /**
     * Called when the user who just saved an edit is redirected back to the article.
     * Can be used to modify the URL of that redirect.
     *
     * @param string &$returnto
     * @param array &$returntoquery
     * @param LinkTarget $title
     * @param IContextSource $context
     * @return bool|void
     */
    public function onModerationContinueEditingLink(
        string &$returnto,
        array &$returntoquery,
        LinkTarget $title,
        IContextSource $context,
    );
}
