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
 * Subclass of MediaWikiIntegrationTestCase that is used for Consequence tests. (NOT for blackbox tests)
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Moderation\IConsequence;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWikiIntegrationTestCase;

class ModerationUnitTestCase extends MediaWikiIntegrationTestCase
{
    use TempUserTestTrait;

    public function setUp(): void
    {
        parent::setUp();

        ModerationTestUtil::ignoreKnownDeprecations($this);
    }

    /**
     * Get PHPUnit constraint to compare consequences.
     * @param IConsequence $value
     * @return IsConsequenceEqual
     */
    public static function consequenceEqualTo(
        IConsequence $value,
    ): IsConsequenceEqual {
        return new IsConsequenceEqual($value);
    }

    /**
     * Asserts that two consequences are equal.
     * @param IConsequence $expected
     * @param IConsequence $actual
     * @param string $message
     */
    public static function assertConsequence(
        $expected,
        $actual,
        string $message = "",
    ): void {
        static::assertThat(
            $actual,
            new IsConsequenceEqual($expected),
            $message,
        );
    }

    /**
     * Assert that $expectedConsequences are exactly the same as $actualConsequences.
     * @param IConsequence[] $expectedConsequences
     * @param IConsequence[] $actualConsequences
     */
    public static function assertConsequencesEqual(
        array $expectedConsequences,
        array $actualConsequences,
    ) {
        self::assertSame(
            array_map("get_class", $expectedConsequences),
            array_map("get_class", $actualConsequences),
            "List of consequences doesn't match expected.",
        );

        for ($i = 0; $i < count($expectedConsequences); $i++) {
            $expected = $expectedConsequences[$i];
            self::assertConsequence(
                $expected,
                $actualConsequences[$i],
                "Parameters of consequence " .
                    get_class($expected) .
                    " don't match expected.",
            );
        }
    }

    /**
     * Assert that no consequences were added to $manager.
     * @param MockConsequenceManager $manager
     */
    public static function assertNoConsequences(MockConsequenceManager $manager)
    {
        self::assertConsequencesEqual([], $manager->getConsequences());
    }

    /**
     * Get timestamp in the past (N seconds ago).
     * @param int $secondsAgo
     * @return string MediaWiki timestamp (14 digits).
     */
    protected function pastTimestamp($secondsAgo = 10000)
    {
        return wfTimestamp(TS_MW, (int) wfTimestamp() - $secondsAgo);
    }

    /**
     * Install new MockConsequenceManager for the duration of the test.
     * @return MockConsequenceManager
     */
    public function mockConsequenceManager()
    {
        $manager = new MockConsequenceManager();
        $this->setService("Moderation.ConsequenceManager", $manager);

        return $manager;
    }
}
