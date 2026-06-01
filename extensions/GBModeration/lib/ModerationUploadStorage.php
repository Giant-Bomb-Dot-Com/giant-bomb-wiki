<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2019-2021 Edward Chernenko.

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
 * Methods to place intercepted uploaded files into UploadStash and retrieve them back.
 */

namespace MediaWiki\Moderation;

use MediaWiki\MediaWikiServices;
use MWException;
use UploadStash;
use User;

class ModerationUploadStorage
{
    public const USERNAME = "ModerationUploadStash";

    /**
     * Move all old uploads from UploadStash of their uploader into a centralized UploadStash.
     * @param User $user
     */
    protected static function migrateFromPerUploaderStashes(User $user)
    {
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $dbw->update(
            "uploadstash",
            [
                "us_user" => $user->getId(),
            ],
            [
                // This is an unindexed query, but it only happens once.
                "us_key IN " .
                $dbw->buildSelectSubquery(
                    "moderation",
                    "DISTINCT mod_stash_key",
                    "",
                    __METHOD__,
                ),
            ],
            __METHOD__,
        );
    }

    /**
     * Get the reserved user account that owns the UploadStash used by Moderation.
     * @return User
     */
    public static function getOwner()
    {
        $user = User::newSystemUser(self::USERNAME, [
            "create" => false,
            "steal" => false,
        ]);
        if (!$user) {
            // If we are here, this means that our reserved user doesn't exist (and never did).
            // This is a one-time moment to migrate from old per-uploader Stash storage
            // to a modern approach (one UploadStash that belongs to our reserved user).
            $user = User::newSystemUser(self::USERNAME, [
                "steal" => true,
            ]);
            if (!$user) {
                throw new MWException(__METHOD__ . ": unable to create user.");
            }

            self::migrateFromPerUploaderStashes($user);
        }

        return $user;
    }

    /**
     * Get the UploadStash where the files are stored.
     * @return UploadStash
     */
    public static function getStash()
    {
        $repo = MediaWikiServices::getInstance()
            ->getRepoGroup()
            ->getLocalRepo();
        return $repo->getUploadStash(self::getOwner());
    }
}
