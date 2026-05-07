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
 * Subclass of MediaWikiIntegrationTestCase that prints TestsuiteLogger debug messages for failed tests.
 */

namespace MediaWiki\Moderation\Tests;

use ExtensionRegistry;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWikiIntegrationTestCase;
use Throwable;

class ModerationTestCase extends MediaWikiIntegrationTestCase
{
    use TempUserTestTrait;

    /** @var ModerationTestsuite|null */
    private $testsuite = null;

    /**
     * Get the ModerationTestsuite object that should be used in the current test.
     * @return ModerationTestsuite
     */
    public function getTestsuite()
    {
        if (!$this->testsuite) {
            $this->testsuite = new ModerationTestsuite();
        }

        return $this->testsuite;
    }

    /**
     * Get a newly initialized (completely clean) ModerationTestsuite object (empty DB, etc.).
     * @return ModerationTestsuite
     */
    protected function makeNewTestsuite()
    {
        $this->testsuite = null;
        return $this->getTestsuite();
    }

    /**
     * Reimplementation of setMwGlobals() via ModerationTestsuite::setMwConfig().
     * This is indirectly used by QueueTest (via MediaWikiIntegrationTestCase::setGroupPermissions())
     * to temporarily allow anonymous uploads.
     *
     * @inheritDoc
     */
    protected function setMwGlobals($pairs, $value = null)
    {
        if (is_string($pairs)) {
            $pairs = [$pairs => $value];
        }

        // Set the configuration "client-side" (in PHPUnit test).
        parent::setMwGlobals($pairs);

        // Set the configuration "server-side" (via CliEngine::setMwConfig()).
        foreach ($pairs as $key => $value) {
            if ($key == "wgContLang") {
                // We can't send Language object via CliEngine,
                // because it can contain non-serializable parts (e.g. callbacks).
                $key = "wgLanguageCode";
                $value = $value->getCode();
            }

            $key = preg_replace("/^wg/", "", $key); // setMwConfig() expects no "wg" prefix
            $this->getTestsuite()->setMwConfig($key, $value);
        }
    }

    /**
     * Skip the test if MediaWiki extension is not installed.
     * @param string $extensionName E.g. "CheckUser".
     * @throws \PHPUnit\Framework\SkippedTestError
     */
    public function requireExtension($extensionName)
    {
        if (!ExtensionRegistry::getInstance()->isLoaded($extensionName)) {
            $this->markTestSkipped(
                "Test skipped: " .
                    $extensionName .
                    " extension must be installed to run it.",
            );
        }
    }

    /**
     * Dump the logs related to the current test.
     * @inheritDoc
     */
    protected function onNotSuccessfulTest(Throwable $e): void
    {
        switch (get_class($e)) {
            case "PHPUnit\Framework\SkippedTestError":
                break; // Don't need logs of skipped tests

            default:
                ModerationTestsuiteLogger::printBuffer();
        }

        parent::onNotSuccessfulTest($e);
    }

    /**
     * Forget the logs related to previous tests.
     */
    protected function setUp(): void
    {
        ModerationTestsuiteLogger::prepareCleanBuffer($this->getName());
        parent::setUp();

        ModerationTestUtil::ignoreKnownDeprecations($this);

        $name = $this->getName();
        if (
            $name == "testValidCovers" ||
            $name == "testMediaWikiIntegrationTestCaseParentSetupCalled"
        ) {
            // These meta-tests are basically linters,
            // they don't need ModerationTestsuite object or clean database.
            return;
        }

        /*
			Provide "ModerationTestsuite $t" to all test methods via Dependency Injection.
			This also cleans the database, etc.

			Tests with @depends are excluded, because they might need to inspect an existing
			environment after the previous test, and creating new ModerationTestsuite object
			would clean the database.
		*/
        $hasDependencies = count($this->requires()) > 0;
        if (!$hasDependencies) {
            $this->setDependencyInput([$this->makeNewTestsuite()]);
        }

        // ModerationTestsuite already sets language to "qqx" when running tests "server-side"
        // (via CliEngine). However, to double-check results of PreSaveTransform, etc.,
        // it's necessary to also set Content Language to 'qqx' on the PHPUnit side too.
        $this->overrideConfigValue("LanguageCode", "qqx");

        // Workaround for MediaWiki 1.43+ clearing $wgScriptPath during tests
        global $wgModerationTestsuiteScriptPath;
        if ($wgModerationTestsuiteScriptPath) {
            $this->setMwGlobals(
                "wgScriptPath",
                $wgModerationTestsuiteScriptPath,
            );
            $this->setMwGlobals(
                "wgScript",
                "$wgModerationTestsuiteScriptPath/index.php",
            );
        }
    }
}
