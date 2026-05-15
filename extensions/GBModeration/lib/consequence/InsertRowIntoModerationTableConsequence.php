<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2025 Edward Chernenko.

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
 * Consequence that inserts new row into the "moderation" SQL table.
 */

namespace MediaWiki\Moderation;

use MediaWiki\MediaWikiServices;

class InsertRowIntoModerationTableConsequence implements IConsequence
{
    /** @var array */
    protected $fields;

    /**
     * @param array $fields
     */
    public function __construct(array $fields)
    {
        $this->fields = $fields;
    }

    /**
     * Execute the consequence.
     * @return int mod_id of affected row.
     */
    public function run()
    {
        $uniqueFields = [
            "mod_preloadable",
            "mod_namespace",
            "mod_title",
            "mod_preload_id",
            "mod_type",
        ];

        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);

        $rrQuery = MediaWikiServices::getInstance()->getService(
            "Moderation.RollbackResistantQuery",
        );
        $rrQuery->perform(function () use ($dbw, $uniqueFields) {
            $set = array_diff_key($this->fields, array_flip($uniqueFields));
            $dbw->upsert(
                "moderation",
                $this->fields,
                [$uniqueFields],
                $set,
                "InsertRowIntoModerationTableConsequence::run",
            );
        });

        return $dbw->insertId();
    }
}
