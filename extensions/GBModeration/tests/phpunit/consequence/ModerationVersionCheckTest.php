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
 * Unit test of ModerationVersionCheck and ModerationCompatTools.
 */

namespace MediaWiki\Moderation\Tests;

use BagOStuff;
use EmptyBagOStuff;
use HashBagOStuff;
use MediaWiki\MediaWikiServices;
use MediaWiki\Moderation\ModerationVersionCheck;
use Wikimedia\Rdbms\DBConnRef;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IMaintainableDatabase;
use Wikimedia\TestingAccessWrapper;

require_once __DIR__ . "/autoload.php";

class ModerationVersionCheckTest extends ModerationUnitTestCase
{
    /**
     * Ensure that all returned values are correct for the most recent DB schema.
     * Because a testsuite always runs on a newly created DB, B/C values should not be returned.
     *
     * @covers MediaWiki\Moderation\ModerationVersionCheck
     */
    public function testModernSchema()
    {
        $this->markTestSkipped(
            "testModernSchema(): not needed: all feature check methods were removed " .
                "in Moderation 1.6.0, and there were no DB schema changes since then.",
        );

        $loadBalancer = MediaWikiServices::getInstance()->getDBLoadBalancer();

        $versionCheck = new ModerationVersionCheck(
            new HashBagOStuff(),
            $loadBalancer,
        );
        $this->setService("Moderation.VersionCheck", $versionCheck);
    }

    /**
     * Verify that static methods like areTagsSupported() work for different DbUpdatedVersion values.
     * @param string $method Name of static method in the ModerationVersionCheck:: class.
     * @param string $version
     * @param mixed $expectedResult
     * @dataProvider dataProviderFeatureChecks
     * @covers MediaWiki\Moderation\ModerationVersionCheck
     */
    public function testFeatureChecks($method, $version, $expectedResult)
    {
        $this->markTestSkipped(
            "testModernSchema(): not needed: all feature check methods were removed " .
                "in Moderation 1.6.0, and there were no DB schema changes since then.",
        );

        $this->mockDbUpdatedVersion($version);
        $this->assertSame(
            $expectedResult,
            ModerationVersionCheck::$method(),
            "Result of $method() doesn't match expected when DbUpdatedVersion=$version",
        );
    }

    /**
     * Provide datasets for testFeatureChecks() runs.
     * @return array
     */
    public function dataProviderFeatureChecks()
    {
        return [];
    }

    /**
     * Verify that getDbUpdatedVersion() checks the cache and (if found) returns the cached value.
     * @covers MediaWiki\Moderation\ModerationVersionCheck
     */
    public function testDbUpdatedVersionFromCache()
    {
        $loadBalancer = $this->createMock(ILoadBalancer::class);
        $cache = $this->createMock(BagOStuff::class);
        $cache
            ->expects($this->once())
            ->method("makeKey")
            ->with($this->identicalTo("moderation-lastDbUpdateVersion"))
            ->willReturn("{MockedCacheKey}");

        $cache
            ->expects($this->once())
            ->method("get")
            ->with($this->identicalTo("{MockedCacheKey}"))
            ->willReturn("{MockedResult}");

        $cache->expects($this->never())->method("set");

        '@phan-var BagOStuff $cache';
        '@phan-var ILoadBalancer $loadBalancer';

        $versionCheck = new ModerationVersionCheck($cache, $loadBalancer);
        $result = TestingAccessWrapper::newFromObject(
            $versionCheck,
        )->getDbUpdatedVersion();

        $this->assertSame(
            "{MockedResult}",
            $result,
            "Unexpected result from getDbUpdatedVersion()",
        );
    }

    /**
     * Verify that getDbUpdatedVersion() uses *Uncached() method if value is not found in cache.
     * @covers MediaWiki\Moderation\ModerationVersionCheck
     */
    public function testDbUpdatedVersionNotFoundInCache()
    {
        $cache = $this->createMock(BagOStuff::class);
        $cache
            ->expects($this->once())
            ->method("makeKey")
            ->with($this->identicalTo("moderation-lastDbUpdateVersion"))
            ->willReturn("{MockedCacheKey}");

        $cache
            ->expects($this->once())
            ->method("get")
            ->with($this->identicalTo("{MockedCacheKey}"))
            ->willReturn(false); // Not found in cache

        $cache
            ->expects($this->once())
            ->method("set")
            ->with(
                $this->identicalTo("{MockedCacheKey}"),
                $this->identicalTo("{MockedResult}"),
                $this->identicalTo(86400),
            );

        $mockedConnRef = $this->createMock(DBConnRef::class);

        $loadBalancer = $this->createMock(ILoadBalancer::class);
        $loadBalancer
            ->expects($this->once())
            ->method("getMaintenanceConnectionRef")
            ->with($this->identicalTo(DB_REPLICA))
            ->willReturn($mockedConnRef);

        '@phan-var BagOStuff $cache';
        '@phan-var ILoadBalancer $loadBalancer';

        // Create a partial mock: getDbUpdatedVersionUncached() is mocked, but all other methods are real.
        $versionCheck = $this->getMockBuilder(ModerationVersionCheck::class)
            ->setConstructorArgs([$cache, $loadBalancer])
            ->onlyMethods(["getDbUpdatedVersionUncached"])
            ->getMock();

        $versionCheck
            ->expects($this->once())
            ->method("getDbUpdatedVersionUncached")
            ->with($this->identicalTo($mockedConnRef))
            ->willReturn("{MockedResult}");

        $result = TestingAccessWrapper::newFromObject(
            $versionCheck,
        )->getDbUpdatedVersion();
        $this->assertSame(
            "{MockedResult}",
            $result,
            "Unexpected result from getDbUpdatedVersion()",
        );
    }

    /**
     * Verify that invalidateCache() clears the cache.
     * @covers MediaWiki\Moderation\ModerationVersionCheck
     */
    public function testInvalidateCache()
    {
        $loadBalancer = $this->createMock(ILoadBalancer::class);
        $cache = $this->createMock(BagOStuff::class);
        $cache
            ->expects($this->once())
            ->method("makeKey")
            ->with($this->identicalTo("moderation-lastDbUpdateVersion"))
            ->willReturn("{MockedCacheKey}");

        $cache
            ->expects($this->once())
            ->method("delete")
            ->with($this->identicalTo("{MockedCacheKey}"));

        '@phan-var BagOStuff $cache';
        '@phan-var ILoadBalancer $loadBalancer';

        $versionCheck = new ModerationVersionCheck($cache, $loadBalancer);
        $this->setService("Moderation.VersionCheck", $versionCheck);

        ModerationVersionCheck::invalidateCache();
    }

    /**
     * Verify that getDbUpdatedVersionUncached() correctly detects the DB schema version.
     * @param string $expectedResult
     * @param string $dbType Mocked result of $db->getType()
     * @param array $fieldExists E.g. [ 'mod_type' => true ]
     * @dataProvider dataProviderDbUpdatedVersionUncached
     * @covers MediaWiki\Moderation\ModerationVersionCheck
     */
    public function testDbUpdatedVersionUncached(
        $expectedResult,
        $dbType,
        array $fieldExists,
    ) {
        // Mock the database (which is a parameter of getDbUpdatedVersionUncached).
        $db = $this->createMock(IMaintainableDatabase::class);
        $db->expects($this->any())->method("getType")->willReturn($dbType);

        $db->expects($this->any())
            ->method("fieldExists")
            ->with($this->identicalTo("moderation"))
            ->will(
                $this->returnCallback(static function ($_, $field) use (
                    $fieldExists,
                ) {
                    return $fieldExists[$field] ?? false;
                }),
            );

        $loadBalancer = $this->createMock(ILoadBalancer::class);
        '@phan-var ILoadBalancer $loadBalancer';

        $versionCheck = new ModerationVersionCheck(
            new EmptyBagOStuff(),
            $loadBalancer,
        );

        $wrapper = TestingAccessWrapper::newFromObject($versionCheck);
        $result = $wrapper->getDbUpdatedVersionUncached($db);

        $this->assertSame(
            $expectedResult,
            $result,
            "Unexpected result from getDbUpdatedVersionUncached()",
        );
    }

    /**
     * Provide datasets for testDbUpdatedVersionUncached() runs.
     * @return array
     */
    public function dataProviderDbUpdatedVersionUncached()
    {
        return [
            "1.6.0 with MySQL" => ["1.6.0", "mysql", []],
            "1.6.0 with PosgtreSQLl" => ["1.6.0", "postgres", []],
        ];
    }

    /**
     * Replace VersionCheck service with a mock that returns $version from getDbUpdatedVersion().
     * @param string $version
     */
    private function mockDbUpdatedVersion($version)
    {
        $loadBalancer = $this->createMock(ILoadBalancer::class);

        '@phan-var ILoadBalancer $loadBalancer';

        $versionCheck = $this->getMockBuilder(ModerationVersionCheck::class)
            ->setConstructorArgs([new EmptyBagOStuff(), $loadBalancer])
            ->onlyMethods(["getDbUpdatedVersion"])
            ->getMock();
        $versionCheck
            ->expects($this->once())
            ->method("getDbUpdatedVersion")
            ->willReturn($version);
        $this->setService("Moderation.VersionCheck", $versionCheck);
    }
}
