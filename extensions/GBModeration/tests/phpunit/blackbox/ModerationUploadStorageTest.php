<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2019-2024 Edward Chernenko.

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
 * Ensures that ModerationUploadStorage works for existing uploads and new uploads.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\ModerationCompatTools;
use MediaWiki\Moderation\ModerationUploadStorage;
use UploadStash;
use User;
use Wikimedia\TestingAccessWrapper;

require_once __DIR__ . "/../framework/ModerationTestsuite.php";

/**
 * @group Database
 */
class ModerationUploadStorageTest extends ModerationTestCase
{
    /**
     * Check that ModerationUploadStorage::getStash() migrates old uploads from per-upload stashes
     * into a centralized stash (owned by a reserved user).
     * @covers MediaWiki\Moderation\ModerationUploadStorage::getStash()
     */
    public function testMigrationFromPerUploaderStashes(ModerationTestsuite $t)
    {
        $numberOfUploads = 5;

        // Queue several edits: some of them uploads, some non-uploads.
        $t->loginAs($t->unprivilegedUser);
        for ($i = 1; $i <= $numberOfUploads * 2; $i++) {
            if ($i % 2) {
                $result = $t->doTestUpload(
                    "Test image $i " . $t->uniqueSuffix() . ".png",
                );
            } else {
                $result = $t->doTestEdit("Test page $i");
            }
            $this->assertTrue($result->isIntercepted());
        }

        // Emulate a situation in Moderation 1.4.5 and older,
        // where "User:ModerationUploadStorage" didn't exist,
        // and the owner of UploadStash was the uploader of that file.
        // This is what triggers the migration.
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $dbw->delete(
            "user",
            ["user_name" => ModerationUploadStorage::USERNAME],
            __METHOD__,
        );
        $dbw->delete(
            "actor",
            ["actor_name" => ModerationUploadStorage::USERNAME],
            __METHOD__,
        );

        foreach ($dbw->select("moderation", "*", "", __METHOD__) as $row) {
            $dbw->update(
                "uploadstash",
                ["us_user" => $row->mod_user],
                ["us_key" => $row->mod_stash_key],
                __METHOD__,
            );
            if ($row->mod_stash_key) {
                $this->assertSame(1, $dbw->affectedRows());
            }
        }

        // Double-check that "User:ModerationUploadStorage" user doesn't exist.
        $this->assertTrue(
            User::newFromName(ModerationUploadStorage::USERNAME)->isAnon(),
        );

        // Now verify that  ModerationUploadStorage::getStash() triggers the following:
        // 1) creation of "User:ModerationUploadStorage",
        // 2) migration of images from per-uploader stashes into the stash of this new user.
        $stash = ModerationUploadStorage::getStash();
        $this->assertInstanceOf(UploadStash::class, $stash);

        $stashAccessWrapper = TestingAccessWrapper::newFromObject($stash);
        $stashOwner = $stashAccessWrapper->user;

        $this->assertSame(
            ModerationUploadStorage::USERNAME,
            $stashOwner->getName(),
        );
        $this->assertFalse($stashOwner->isAnon());
        $this->assertTrue($stashOwner->isSystemUser());

        $keys = $dbw->selectFieldValues(
            "moderation",
            "mod_stash_key",
            "",
            __METHOD__,
        );
        $keys = array_filter($keys);
        $this->assertCount($numberOfUploads, $keys);

        foreach ($keys as $stashKey) {
            $this->assertSelect(
                "uploadstash",
                "us_user",
                ["us_key" => $stashKey],
                [[$stashOwner->getId()]], // Expected value of us_user
            );
        }
    }
}
