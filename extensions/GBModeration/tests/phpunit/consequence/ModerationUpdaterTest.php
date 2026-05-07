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
 * Unit test of ModerationUpdater.
 */

namespace MediaWiki\Moderation\Tests;

use DatabaseUpdater;
use MediaWiki\Moderation\ModerationUpdater;
use MediaWiki\Moderation\ModerationVersionCheck;
use Wikimedia\Rdbms\IMaintainableDatabase;

require_once __DIR__ . "/autoload.php";

class ModerationUpdaterTest extends ModerationUnitTestCase
{
    /**
     * Verify that our LoadExtensionSchemaUpdates hook adds the necessary database updates.
     * @param string $databaseType E.g. "mysql" or "postgres".
     * @param bool $isIndexUnique False to simulate moderation_load not being unique, true otherwise.
     * @dataProvider dataProviderHook
     * @covers MediaWiki\Moderation\ModerationUpdater
     */
    public function testHook($databaseType, $isIndexUnique = true)
    {
        $updater = $this->createMock(DatabaseUpdater::class);
        $db = $this->createMock(IMaintainableDatabase::class);

        $updater->expects($this->any())->method("getDB")->willReturn($db);
        $db->expects($this->any())
            ->method("getType")
            ->willReturn($databaseType);
        $db->expects($this->any())
            ->method("tableExists")
            ->with($this->identicalTo("moderation"))
            ->willReturn(true);
        $db->expects($this->any())
            ->method("indexUnique")
            ->with(
                $this->identicalTo("moderation"),
                $this->identicalTo("moderation_load"),
            )
            ->willReturn($isIndexUnique);

        $updater
            ->expects($this->once())
            ->method("addExtensionTable")
            ->with($this->identicalTo("moderation"), $this->fileExists());

        if ($databaseType === "mysql") {
            $counter = $this->exactly(2);
            $updater
                ->expects($counter)
                ->method("addExtensionField")
                ->willReturnCallback(function ($table, $field, $filename) use (
                    $counter,
                ) {
                    $expectedField = ["mod_tags", "mod_type"][
                        $counter->getInvocationCount() - 1
                    ];

                    $this->assertSame("moderation", $table);
                    $this->assertSame($expectedField, $field);
                    $this->assertFileExists($filename);
                });

            $updater
                ->expects($this->once())
                ->method("modifyExtensionField")
                ->willReturnCallback(function ($table, $field, $filename) {
                    $this->assertSame("moderation", $table);
                    $this->assertSame("mod_title", $field);
                    $this->assertFileExists($filename);
                });
        }

        $counter = $this->exactly($isIndexUnique ? 1 : 2);
        $updater
            ->expects($counter)
            ->method("addExtensionUpdate")
            ->willReturnCallback(function ($update) use (
                $counter,
                $isIndexUnique,
            ) {
                if (!$isIndexUnique && $counter->getInvocationCount() === 1) {
                    $this->assertSame("applyPatch", $update[0]);
                    $this->assertFileExists($update[1]);
                    $this->assertTrue($update[2]);
                } else {
                    $this->assertSame(
                        [ModerationVersionCheck::class . "::invalidateCache"],
                        $update,
                    );
                }
            });

        '@phan-var DatabaseUpdater $updater';

        $hookHandler = new ModerationUpdater();
        $this->assertNotFalse(
            $hookHandler->onLoadExtensionSchemaUpdates($updater),
            'Handler of LoadExtensionSchemaUpdates hook shouldn\'t return false.',
        );
    }

    /**
     * Provide datasets for testHook() runs.
     * @return array
     */
    public function dataProviderHook()
    {
        return [
            "MySQL" => ["mysql"],
            "PostgreSQL" => ["postgres"],
            "SQLite" => ["sqlite"],
            "MySQL (moderation_load index is not UNIQUE)" => ["mysql", false],
        ];
    }
}
