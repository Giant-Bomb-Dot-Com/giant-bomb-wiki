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
 * Unit test of EntryFactory.
 */

namespace MediaWiki\Moderation\Tests;

use IContextSource;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Moderation\ActionLinkRenderer;
use MediaWiki\Moderation\EntryFactory;
use MediaWiki\Moderation\IConsequenceManager;
use MediaWiki\Moderation\ModerationApprovableEntry;
use MediaWiki\Moderation\ModerationApproveHook;
use MediaWiki\Moderation\ModerationCanSkip;
use MediaWiki\Moderation\ModerationCompatTools;
use MediaWiki\Moderation\ModerationEntryEdit;
use MediaWiki\Moderation\ModerationEntryFormatter;
use MediaWiki\Moderation\ModerationEntryMove;
use MediaWiki\Moderation\ModerationEntryUpload;
use MediaWiki\Moderation\ModerationError;
use MediaWiki\Moderation\ModerationViewableEntry;
use MediaWiki\Moderation\PendingEdit;
use MediaWiki\Moderation\TimestampTools;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\Title;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class EntryFactoryTest extends ModerationUnitTestCase
{
    use ModifyDbRowTestTrait;

    /**
     * Test that EntryFactory can create ModerationEntryFormatter, ModerationViewableEntry, etc.
     * @covers MediaWiki\Moderation\EntryFactory
     */
    public function testFactory()
    {
        $context = $this->createMock(IContextSource::class);

        '@phan-var IContextSource $context';

        $factory = $this->makeFactory();

        // Test makeFormatter()
        $row = (object) ["param1" => "value1", "param2" => "value2"];
        $formatter = $factory->makeFormatter($row, $context);
        $this->assertInstanceOf(ModerationEntryFormatter::class, $formatter);

        // Test makeViewableEntry()
        $row = (object) ["param1" => "value1", "param2" => "value2"];
        $viewableEntry = $factory->makeViewableEntry($row);
        $this->assertInstanceOf(ModerationViewableEntry::class, $viewableEntry);

        // Test makeApprovableEntry()
        $row = (object) ["type" => "move", "stash_key" => null];
        $approvableEntry = $factory->makeApprovableEntry($row);
        $this->assertInstanceOf(ModerationEntryMove::class, $approvableEntry);

        $row = (object) ["type" => "edit", "stash_key" => null];
        $approvableEntry = $factory->makeApprovableEntry($row);
        $this->assertInstanceOf(ModerationEntryEdit::class, $approvableEntry);

        $row = (object) [
            "type" => "edit",
            "stash_key" => "some non-empty stash key",
        ];
        $approvableEntry = $factory->makeApprovableEntry($row);
        $this->assertInstanceOf(ModerationEntryUpload::class, $approvableEntry);
    }

    /**
     * Test loadRow() and loadRowOrThrow().
     * @param string $testedMethod Either 'loadRow' or 'loadRowOrThrow'.
     * @param bool $isFound True to use correct mod_id of existing row. False to use incorrect id.
     * @param bool $useWhere If true, first parameter ($where) will be an array. If false, mod_id.
     * @param int $dbType Either DB_PRIMARY or DB_REPLICA.
     * @dataProvider dataProviderLoadRow
     *
     * @covers MediaWiki\Moderation\EntryFactory
     */
    public function testLoadRow($testedMethod, $isFound, $useWhere, $dbType)
    {
        $expectedUA = "SampleUserAgent/1.0." . rand(0, 100000);
        $expectedIP = "10.11.12.13";

        if ($isFound) {
            $modid = $this->makeDbRow([
                "mod_header_ua" => $expectedUA,
                "mod_ip" => $expectedIP,
            ]);
        } else {
            // Simulate "row not found" error.
            $modid = 12345;

            if ($testedMethod == "loadRowOrThrow") {
                $this->expectExceptionObject(
                    new ModerationError("moderation-edit-not-found"),
                );
            }
        }

        // Test both selection by $where (same as $where parameter for DB::select())
        // and by mod_id (integer).
        $where = $useWhere ? ["mod_header_ua" => $expectedUA] : $modid;
        $fields = ["mod_header_ua AS header_ua", "mod_ip AS ip"];

        $factory = $this->makeFactory();
        $row = $factory->$testedMethod($where, $fields, $dbType);

        if ($isFound) {
            $this->assertNotFalse($row);
            $this->assertSame($expectedUA, $row->header_ua);
            $this->assertSame($expectedIP, $row->ip);

            if ($useWhere) {
                // We haven't listed "id" in $fields, and parameter $where is an array (not mod_id).
                $this->assertSame(
                    0,
                    $row->id,
                    "Field \$row->id is not 0, even though $testedMethod() didn't receive an id, " .
                        "and \$fields parameter didn't contain \"id\" field either.",
                );
            } else {
                $this->assertSame(
                    $modid,
                    $row->id,
                    "Incorrect \$row->id in return value of $testedMethod.",
                );
            }

            $this->assertSame(
                ["header_ua", "ip", "id"],
                array_keys(get_object_vars($row)),
                "List of properties in \$row (return value of $testedMethod).",
            );
        } else {
            $this->assertFalse(
                $row,
                "The row shouldn't exist, but $testedMethod didn't return false.",
            );
        }
    }

    /**
     * Provide datasets for testLoadRow() runs.
     * @return array
     */
    public function dataProviderLoadRow()
    {
        return [
            // Selecting by mod_id
            'situation when loadRow($id) finds a row' => [
                "loadRow",
                true,
                false,
                DB_PRIMARY,
            ],
            'situation when loadRow($id) doesn\'t find a row' => [
                "loadRow",
                false,
                false,
                DB_PRIMARY,
            ],
            'situation when loadRowOrThrow($id) finds a row' => [
                "loadRowOrThrow",
                true,
                false,
                DB_PRIMARY,
            ],
            'situation when loadRowOrThrow($id) doesn\'t find a row' => [
                "loadRowOrThrow",
                false,
                false,
                DB_PRIMARY,
            ],
            // Selecting by $where array
            'situation when loadRow($where) finds a row' => [
                "loadRow",
                true,
                true,
                DB_PRIMARY,
            ],
            'situation when loadRow($where) doesn\'t find a row' => [
                "loadRow",
                false,
                true,
                DB_PRIMARY,
            ],
            'situation when loadRowOrThrow($where) finds a row' => [
                "loadRowOrThrow",
                true,
                true,
                DB_PRIMARY,
            ],
            'situation when loadRowOrThrow($where) doesn\'t find a row' => [
                "loadRowOrThrow",
                false,
                true,
                DB_PRIMARY,
            ],
        ];
    }

    /**
     * Test whether $options parameter is passed to DB::select() by loadRow() and loadRowOrThrow().
     * @param string $testedMethod Either 'loadRow' or 'loadRowOrThrow'.
     * @dataProvider dataProviderLoadRowOptions
     *
     * @covers MediaWiki\Moderation\EntryFactory
     */
    public function testLoadRowOptions($testedMethod)
    {
        // Let's make two rows and check if "ORDER BY" in $options
        // will allow us to select 1 row we need.
        $this->makeDbRow(["mod_namespace" => 4]);
        $this->makeDbRow(["mod_namespace" => 10]);

        $factory = $this->makeFactory();

        $anyWhere = ["mod_namespace >= 0"];
        $row = $factory->$testedMethod(
            $anyWhere,
            ["mod_namespace AS value"],
            DB_PRIMARY,
            ["ORDER BY" => "mod_namespace"],
        );
        $this->assertSame("4", $row->value);

        $row = $factory->$testedMethod(
            $anyWhere,
            ["mod_namespace AS value"],
            DB_PRIMARY,
            ["ORDER BY" => "mod_namespace DESC"],
        );
        $this->assertSame("10", $row->value);
    }

    /**
     * Provide datasets for testLoadRowOptions() runs.
     * @return array
     */
    public function dataProviderLoadRowOptions()
    {
        return [
            "loadRow" => ["loadRow"],
            "loadRowOrThrow" => ["loadRowOrThrow"],
        ];
    }

    /**
     * Test findApprovableEntry() and findViewableEntry().
     * @param string $testedMethod Name of method, e.g. "findViewableEntry".
     * @param bool $isFound True to use correct mod_id of existing row. False to use incorrect id.
     * @param string $expectedClass Return value of $testedMethod should be object of this class.
     * @dataProvider dataProviderFindEntryById
     *
     * @covers MediaWiki\Moderation\EntryFactory
     */
    public function testFindEntryById($testedMethod, $isFound, $expectedClass)
    {
        if ($isFound) {
            $modid = $this->makeDbRow();
        } else {
            // Simulate "row not found" error.
            $modid = 12345;
            $this->expectExceptionObject(
                new ModerationError("moderation-edit-not-found"),
            );
        }

        $factory = $this->makeFactory();
        $entry = $factory->$testedMethod($modid);

        $this->assertInstanceOf($expectedClass, $entry);
    }

    /**
     * Provide datasets for testfindEntryById() runs.
     * @return array
     */
    public function dataProviderFindEntryById()
    {
        return [
            "findViewableEntry() finds an entry" => [
                "findViewableEntry",
                true,
                ModerationViewableEntry::class,
            ],
            'findViewableEntry() doesn\'t find an entry' => [
                "findViewableEntry",
                false,
                ModerationViewableEntry::class,
            ],
            "findApprovableEntry() finds an entry" => [
                "findApprovableEntry",
                true,
                ModerationApprovableEntry::class,
            ],
            'findApprovableEntry() doesn\'t find an entry' => [
                "findApprovableEntry",
                false,
                ModerationApprovableEntry::class,
            ],
        ];
    }

    /**
     * Test findPendingEdit()
     * @param bool $isFound True to use existing row. False to simulate "row not found" situation.
     * @dataProvider dataProviderFindPendingEdit
     *
     * @covers MediaWiki\Moderation\EntryFactory
     */
    public function testFindPendingEdit($isFound)
    {
        $title = Title::newFromText("Talk:UTPage-" . rand(0, 100000));
        $preloadId = "sample preload ID " . rand(0, 1000000);
        $expectedComment = "edit summary " . rand(0, 1000000);
        $expectedText = "new text " . rand(0, 1000000);

        $fields = [
            "mod_preloadable" => 0, // mod_preloadable=0 means "prelodable"
            "mod_namespace" => $title->getNamespace(),
            "mod_title" => $title->getDBKey(),
            "mod_preload_id" => $preloadId,
            "mod_type" => "edit",
            "mod_comment" => $expectedComment,
            "mod_text" => $expectedText,
        ];
        $modid = $isFound ? $this->makeDbRow($fields) : null;

        $factory = $this->makeFactory();
        $pendingEdit = $factory->findPendingEdit($preloadId, $title);

        if ($isFound) {
            $this->assertNotFalse(
                $pendingEdit,
                "findPendingEdit() returned false when an edit should have been found.",
            );
            $this->assertInstanceOf(PendingEdit::class, $pendingEdit);

            $this->assertSame($title, $pendingEdit->getTitle(), "Wrong title");
            $this->assertSame($modid, $pendingEdit->getId(), "Wrong mod_id");
            $this->assertSame(
                $expectedComment,
                $pendingEdit->getComment(),
                "wrong comment",
            );
            $this->assertSame(
                $expectedText,
                $pendingEdit->getText(),
                "Wrong text",
            );
        } else {
            $this->assertFalse(
                $pendingEdit,
                "findPendingEdit() didn't return false when nothing should have been found.",
            );
        }
    }

    /**
     * Provide datasets for testFindPendingEdit() runs.
     * @return array
     */
    public function dataProviderFindPendingEdit()
    {
        return [
            "findPendingEdit() finds an edit" => [true],
            'findPendingEdit() doesn\'t find an edit' => [false],
        ];
    }

    /**
     * Test findAllApprovableEntries().
     * @param array $ineligibleFieldValues Changes to DB fields that should make edit non-selectable.
     * @dataProvider dataProviderFindAllApprovableEntries
     * @covers MediaWiki\Moderation\EntryFactory
     */
    public function testFindAllApprovableEntries(array $ineligibleFieldValues)
    {
        $this->authorUser = self::getTestUser()->getUser();
        [$idsToFind, $idsToSkip] = array_chunk($this->makeSeveralDbRows(7), 4);

        // Make $idsToSkip ineligible for selecting (e.g. due to having another mod_user_text).
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $dbw->update(
            "moderation",
            $ineligibleFieldValues,
            ["mod_id" => $idsToSkip],
            __METHOD__,
        );

        $factory = $this->makeFactory();
        $entries = $factory->findAllApprovableEntries(
            $this->authorUser->getName(),
        );

        $this->assertCount(count($idsToFind), $entries);
        $this->assertContainsOnlyInstancesOf(
            ModerationApprovableEntry::class,
            $entries,
        );

        $foundIds = array_map(static function ($entry) {
            return $entry->getId();
        }, $entries);
        $this->assertArrayEquals($idsToFind, $foundIds);

        // TODO: test that DB::select() was using correct ORDER BY options. (by mocking LoadBalancer)
    }

    /**
     * Provide datasets for testFindAllApprovableEntries() runs.
     * @return array
     */
    public function dataProviderFindAllApprovableEntries()
    {
        return [
            "ensure that edits by another mod_user_text are not selected" => [
                ["mod_user_text" => "Another username"],
            ],
            "ensure that edits with mod_rejected=1 are not selected" => [
                ["mod_rejected" => 1],
            ],
            "ensure that edits with mod_conflict=1 are not selected" => [
                ["mod_conflict" => 1],
            ],
        ];
    }

    /**
     * Create one EntryFactory with mocked parameters.
     * @return EntryFactory
     */
    private function makeFactory()
    {
        $linkRenderer = $this->createMock(LinkRenderer::class);
        $actionLinkRenderer = $this->createMock(ActionLinkRenderer::class);
        $timestampTools = $this->createMock(TimestampTools::class);
        $consequenceManager = $this->createMock(IConsequenceManager::class);
        $canSkip = $this->createMock(ModerationCanSkip::class);
        $approveHook = $this->createMock(ModerationApproveHook::class);
        $contentHandlerFactory = $this->createMock(
            IContentHandlerFactory::class,
        );
        $revisionLookup = $this->createMock(RevisionLookup::class);

        '@phan-var LinkRenderer $linkRenderer';
        '@phan-var ActionLinkRenderer $actionLinkRenderer';
        '@phan-var TimestampTools $timestampTools';
        '@phan-var IConsequenceManager $consequenceManager';
        '@phan-var ModerationCanSkip $canSkip';
        '@phan-var ModerationApproveHook $approveHook';
        '@phan-var IContentHandlerFactory $contentHandlerFactory';
        '@phan-var RevisionLookup $revisionLookup';

        return new EntryFactory(
            $linkRenderer,
            $actionLinkRenderer,
            $timestampTools,
            $consequenceManager,
            $canSkip,
            $approveHook,
            $contentHandlerFactory,
            $revisionLookup,
        );
    }
}
