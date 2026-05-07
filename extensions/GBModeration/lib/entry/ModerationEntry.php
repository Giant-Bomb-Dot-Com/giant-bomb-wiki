<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2018-2025 Edward Chernenko.

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
 * Parent class for objects that represent one row in the 'moderation' SQL table.
 */

namespace MediaWiki\Moderation;

use MediaWiki\Title\Title;
use stdClass;
use User;

abstract class ModerationEntry
{
    /**
     * @var stdClass
     * Return value of Database::selectRow()
     */
    private $row;

    /** @var User Author of this change */
    private $user = null;

    /** @var Title Page affected by this change */
    private $title = null;

    /** @return stdClass */
    protected function getRow()
    {
        return $this->row;
    }

    /**
     * @param stdClass $row
     */
    public function __construct($row)
    {
        $this->row = $row;
    }

    /**
     * Get the list of fields needed for selecting $row from database.
     * This method can be overridden in subclass to add more fields.
     * @return array
     */
    public static function getFields()
    {
        return [
            "mod_user AS user",
            "mod_user_text AS user_text",
            "mod_namespace AS namespace",
            "mod_title AS title",
            "mod_type AS type",
            "mod_page2_namespace AS page2_namespace",
            "mod_page2_title AS page2_title",
        ];
    }

    /**
     * Returns true if this is a move, false otherwise.
     * @return bool
     */
    public function isMove()
    {
        return $this->row->type == ModerationNewChange::MOD_TYPE_MOVE;
    }

    /**
     * Returns author of this change (User object).
     * @param int $flags User::READ_* constant bitfield.
     * @return User
     */
    protected function getUser($flags = 0)
    {
        if ($this->user === null) {
            $row = $this->getRow();
            $user = $row->user
                ? User::newFromId($row->user)
                : User::newFromName($row->user_text, false);

            /* User could have been recently renamed or deleted.
             Make sure we have the correct data. */
            $user->load($flags);
            if ($user->getId() == 0 && $row->user != 0) {
                /* User was deleted,
                 e.g. via [maintenance/removeUnusedAccounts.php] */
                $user->setName($row->user_text);
            }

            $this->user = $user;
        }

        return $this->user;
    }

    /**
     * @return Title of the page affected by this change.
     */
    public function getTitle()
    {
        if ($this->title === null) {
            $row = $this->getRow();
            $this->title = Title::makeTitle($row->namespace, $row->title);
        }

        return $this->title;
    }

    /**
     * Returns Title of the second affected page (if any) or null (for mod_type=edit, etc.).
     * E.g. new name of the article when renaming it.
     * @return Title|null
     */
    public function getPage2Title()
    {
        $row = $this->getRow();
        if (!$row->page2_title) {
            return null;
        }

        return Title::makeTitle($row->page2_namespace, $row->page2_title);
    }
}
