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

interface ModerationPendingHook
{
    /**
     * This hook is called when Moderation has successfully saved an intercepted edit into the database.
     *
     * @param array $fields All database fields, e.g. [ 'mod_type' => 'edit', 'mod_rejected' => 1, ... ].
     * @param int $modid mod_id of the affected row.
     * @return bool|void
     *
     * @phan-param array<string,string|int> $fields
     */
    public function onModerationPending(array $fields, $modid);
}
