<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2024 Edward Chernenko.

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
 * Consequence that modifies text and edit summary of one change in the moderation queue.
 */

namespace MediaWiki\Moderation;

class ModifyPendingChangeConsequence implements IConsequence
{
    /** @var int */
    protected $modid;

    /** @var string */
    protected $newText;

    /** @var string */
    protected $newComment;

    /** @var int */
    protected $newLen;

    /**
     * @param int $modid
     * @param string $newText
     * @param string $newComment
     * @param int $newLen
     */
    public function __construct($modid, $newText, $newComment, $newLen)
    {
        $this->modid = $modid;
        $this->newText = $newText;
        $this->newComment = mb_strcut($newComment, 0, 250);
        $this->newLen = $newLen;
    }

    /**
     * Execute the consequence.
     */
    public function run()
    {
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $dbw->update(
            "moderation",
            [
                "mod_text" => $this->newText,
                "mod_new_len" => $this->newLen,
                "mod_comment" => $this->newComment,
            ],
            [
                "mod_id" => $this->modid,
            ],
            __METHOD__,
        );
    }
}
