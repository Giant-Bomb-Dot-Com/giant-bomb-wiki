<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020 Edward Chernenko.

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
 * Consequence that marks one pending change as having an edit conflict.
 */

namespace MediaWiki\Moderation;

class MarkAsConflictConsequence implements IConsequence
{
    /** @var int */
    protected $modid;

    /**
     * @param int $modid
     */
    public function __construct($modid)
    {
        $this->modid = $modid;
    }

    /**
     * Execute the consequence.
     */
    public function run()
    {
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $dbw->update(
            "moderation",
            ["mod_conflict" => 1],
            ["mod_id" => $this->modid],
            __METHOD__,
        );
    }
}
