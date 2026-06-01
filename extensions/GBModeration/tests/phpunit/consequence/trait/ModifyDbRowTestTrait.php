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
 * Trait with makeDbRow() that creates 1 row in "moderation" table and returns its mod_id.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\InsertRowIntoModerationTableConsequence;
use MediaWiki\Moderation\ModerationCompatTools;
use MediaWiki\Moderation\ModerationNewChange;
use TestUser;
use User;

/**
 * @method TestUser getTestUser($groups=null)
 */
trait ModifyDbRowTestTrait
{
    /** @var User */
    protected $authorUser;

    /**
     * Create a row in "moderation" SQL table. Returns mod_id of this new row.
     * @param array $fields Additional mod_* fields to override default values.
     * @return int
     */
    public function makeDbRow(array $fields = [])
    {
        $fields += self::getDefaultFields();
        return (new InsertRowIntoModerationTableConsequence($fields))->run();
    }

    /**
     * Create several rows in "moderation" SQL table.
     * @param int $count
     * @return int[] Array of mod_id values of newly created rows.
     */
    public function makeSeveralDbRows($count = 6)
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = $this->makeDbRow();
        }

        return $ids;
    }

    /**
     * Returns default fields of one row in "moderation" table.
     * Same as ModerationTestsuitePendingChangeTestSet::getDefaultFields() in blackbox testsuite.
     * @return array
     */
    public function getDefaultFields()
    {
        if (!$this->authorUser) {
            $this->authorUser = $this->getTestUser()->getUser();
        }

        $dbr = ModerationCompatTools::getDB(DB_REPLICA);
        return [
            "mod_timestamp" => $dbr->timestamp(),
            "mod_user" => $this->authorUser->getId(),
            "mod_user_text" => $this->authorUser->getName(),
            "mod_cur_id" => 0,
            "mod_namespace" => rand(0, 1),
            "mod_title" => "Test page " . rand(0, 100000),
            "mod_comment" => "Some reason " . rand(0, 100000),
            "mod_minor" => 0,
            "mod_bot" => 0,
            "mod_new" => 1,
            "mod_last_oldid" => 0,
            "mod_ip" => "127.1.2.3",
            "mod_old_len" => 0,
            "mod_new_len" => 8, // Length of mod_text, see below
            "mod_header_xff" => null,
            "mod_header_ua" => "TestsuiteUserAgent/1.0." . rand(0, 100000),
            "mod_preload_id" => "]fake",
            "mod_rejected" => 0,
            "mod_rejected_by_user" => 0,
            "mod_rejected_by_user_text" => null,
            "mod_rejected_batch" => 0,
            "mod_rejected_auto" => 0,
            "mod_preloadable" => 0,
            "mod_conflict" => 0,
            "mod_merged_revid" => 0,
            "mod_text" => "New text " . rand(0, 100000),
            "mod_stash_key" => "",
            "mod_tags" => null,
            "mod_type" => ModerationNewChange::MOD_TYPE_EDIT,
            "mod_page2_namespace" => 0,
            "mod_page2_title" => "Test page 2",
        ];
    }
}
