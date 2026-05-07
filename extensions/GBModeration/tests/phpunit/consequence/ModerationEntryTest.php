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
 * Unit test of ModerationEntry.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\ModerationEntry;
use MediaWiki\Moderation\ModerationNewChange;
use MediaWiki\Title\Title;
use Wikimedia\TestingAccessWrapper;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class ModerationEntryTest extends ModerationUnitTestCase
{
    /**
     * Check the return value of isMove().
     * @param bool $expectedResult
     * @param string $type
     * @dataProvider dataProviderIsMove
     * @covers MediaWiki\Moderation\ModerationEntry
     */
    public function testIsMove($expectedResult, $type)
    {
        $entry = $this->makeEntry(["type" => $type]);
        $this->assertSame($expectedResult, $entry->isMove(), "isMove");
    }

    /**
     * Provide datasets for testIsMove() runs.
     * @return array
     */
    public function dataProviderIsMove()
    {
        return [
            "entry is a move" => [true, ModerationNewChange::MOD_TYPE_MOVE],
            "entry is an edit" => [false, ModerationNewChange::MOD_TYPE_EDIT],
        ];
    }

    /**
     * Check the return value of getTitle() and getPage2Title().
     * @param string $method Either "getTitle" or "getPage2Title".
     * @param string $namespaceField Name of field that contains the namespace.
     * @param string $dbKeyField Name of field that contains non-prefixed title (with underscores).
     * @dataProvider dataProviderGetTitle
     * @covers MediaWiki\Moderation\ModerationEntry
     */
    public function testGetTitle($method, $namespaceField, $dbKeyField)
    {
        $dbKey = "Some_page";
        $namespace = 10;
        $entry = $this->makeEntry([
            $namespaceField => $namespace,
            $dbKeyField => $dbKey,
        ]);

        $title = $entry->$method();
        $this->assertInstanceOf(Title::class, $title);
        $this->assertSame($namespace, $title->getNamespace());
        $this->assertSame($dbKey, $title->getDBKey());
    }

    /**
     * Provide datasets for testGetTitle() runs.
     * @return array
     */
    public function dataProviderGetTitle()
    {
        return [
            "getTitle()" => ["getTitle", "namespace", "title"],
            "getPage2Title()" => [
                "getPage2Title",
                "page2_namespace",
                "page2_title",
            ],
        ];
    }

    /**
     * Check the return value of getPage2Title() when the second title is empty.
     * @covers MediaWiki\Moderation\ModerationEntry
     */
    public function testNoPage2Title()
    {
        $entry = $this->makeEntry([
            "page2_namespace" => 10,
            "page2_title" => "",
        ]);

        $title = $entry->getPage2Title();
        $this->assertNull($title);
    }

    /**
     * Check the return value of getUser() for anonymous user.
     * @covers MediaWiki\Moderation\ModerationEntry
     */
    public function testGetAnonymousUser()
    {
        $ip = "10.11.12.13";
        $entry = $this->makeEntry(["user" => 0, "user_text" => $ip]);

        $user = TestingAccessWrapper::newFromObject($entry)->getUser();
        $this->assertTrue($user->isAnon(), "User::isAnon()");
        $this->assertSame($user->getName(), $ip, "User::getName()");
    }

    /**
     * Check the return value of getUser() for existing non-anonymous user.
     * @covers MediaWiki\Moderation\ModerationEntry
     */
    public function testGetRegisteredUser()
    {
        $expectedUser = self::getTestUser()->getUser();
        $entry = $this->makeEntry([
            "user" => $expectedUser->getId(),
            "user_text" => $expectedUser->getName(),
        ]);

        $user = TestingAccessWrapper::newFromObject($entry)->getUser();
        $this->assertSame(
            $expectedUser->getId(),
            $user->getId(),
            "User::getId()",
        );
        $this->assertSame(
            $expectedUser->getName(),
            $user->getName(),
            "User::getName()",
        );
    }

    /**
     * Check the return value of getUser() for deleted (not found in the database) non-anonymous user.
     * @covers MediaWiki\Moderation\ModerationEntry
     */
    public function testGetDeletedUser()
    {
        $userId = 12345;
        $username = "No such user";

        $entry = $this->makeEntry([
            "user" => $userId,
            "user_text" => $username,
        ]);

        $user = TestingAccessWrapper::newFromObject($entry)->getUser();
        $this->assertSame(0, $user->getId(), "User::getId()");
        $this->assertSame($username, $user->getName(), "User::getName()");
    }

    /**
     * Test the return value of ModerationEntry::getFields().
     * @covers MediaWiki\Moderation\ModerationEntry
     */
    public function testFields()
    {
        $expectedFields = [
            "mod_user AS user",
            "mod_user_text AS user_text",
            "mod_namespace AS namespace",
            "mod_title AS title",
            "mod_type AS type",
            "mod_page2_namespace AS page2_namespace",
            "mod_page2_title AS page2_title",
        ];

        $fields = ModerationEntry::getFields();
        $this->assertSame($expectedFields, $fields);
    }

    /**
     * Create instance of abstract class ModerationEntry from an array of row properties.
     * @param array $fields
     * @return ModerationEntry
     */
    private function makeEntry(array $fields)
    {
        return new class ((object) $fields) extends ModerationEntry {};
    }
}
