<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2021 Edward Chernenko.

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
 * Consequence that marks all pending changes of one User as rejected.
 */

namespace MediaWiki\Moderation;

use User;

class RejectAllConsequence implements IConsequence
{
    /** @var string */
    protected $username;

    /** @var User */
    protected $moderator;

    /**
     * @param string $username
     * @param User $moderator
     */
    public function __construct($username, User $moderator)
    {
        $this->username = $username;
        $this->moderator = $moderator;
    }

    /**
     * Execute the consequence.
     * @return int Number of newly rejected edits.
     */
    public function run()
    {
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);

        $res = $dbw->select(
            "moderation",
            ["mod_id", "mod_title"],
            [
                "mod_user_text" => $this->username,
                "mod_rejected" => 0,
                "mod_merged_revid" => 0,
            ],
            __METHOD__,
        );

        $dbw->update(
            "moderation",
            [
                "mod_rejected" => 1,
                "mod_rejected_by_user" => $this->moderator->getId(),
                "mod_rejected_by_user_text" => $this->moderator->getName(),
                "mod_rejected_batch" => 1,
                "mod_preloadable=mod_id",
            ],
            [
                "mod_user_text" => $this->username,
                "mod_rejected" => 0,
                "mod_merged_revid" => 0,
            ],
            __METHOD__,
        );

        $affected = $dbw->affectedRows();

        if ($res && $res->numRows() > 0) {
            foreach ($res as $row) {
                GBFileCleanupHelper::deleteFileIfExists(
                    $row->mod_title,
                    $this->moderator,
                    $row->mod_id,
                );
            }
        }

        return $affected;
    }
}
