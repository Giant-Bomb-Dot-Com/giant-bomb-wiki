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

use Content;
use Status;
use User;
use WikiPage;

interface ModerationInterceptHook
{
    /**
     * When non-automoderated user makes an edit, Moderation calls this hook before intercepting that edit.
     * If the hook returns false, this edit will be applied immediately (completely bypassing Moderation).
     * Otherwise Moderation will decide whether to intercept the edit or not.
     *
     * Arguments are the same as in PageContentSave hook, but $summary is always a string.
     * @param WikiPage $page
     * @param User $user
     * @param Content $content
     * @param string $summary
     * @param int $is_minor
     * @param mixed $is_watch Unused.
     * @param mixed $section Unused.
     * @param int $flags
     * @param Status $status
     * @return bool|void
     */
    public function onModerationIntercept(
        WikiPage $page,
        User $user,
        Content $content,
        string $summary,
        $is_minor,
        $is_watch,
        $section,
        $flags,
        Status $status,
    );
}
