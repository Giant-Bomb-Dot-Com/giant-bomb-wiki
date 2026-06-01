<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2018-2024 Edward Chernenko.

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
 * Intercepts header() calls in PHP CLI.
 */

namespace MediaWiki\Moderation\Tests;

class ModerationTestsuiteMockAutoLoader
{
    protected const NOMOCK_CACHE_FILENAME = ".NOMOCK_CACHE.mocked.php";

    /** @var ModerationTestsuiteMockAutoLoader Singleton instance */
    protected static $instance = null;

    /**
     * @var array List of function rewrites. Populated by replaceFunction() calls.
     * Format: [ 'oldFunctionName1' => 'newFunctionName1', ... ]
     */
    protected $replacements = [];

    /**
     * @var array List of classnames which have already been mocked.
     * Format: [ 'WebRequest' => true, 'FauxRequest' => true, ... ]
     */
    private $alreadyMockedClasses = [];

    protected function __construct()
    {
        /* Populate nomock cache (list of classes that don't need mocking) */
        $nomockClasses = [];
        if (file_exists(self::NOMOCK_CACHE_FILENAME)) {
            $nomockClasses = explode(
                "\n",
                file_get_contents(self::NOMOCK_CACHE_FILENAME),
            );
        }

        $this->alreadyMockedClasses = array_fill_keys($nomockClasses, true);
    }

    /**
     * Cache the fact that $className doesn't need mocking.
     * @param string $className
     */
    protected function cacheThatNoMockIsNeeded($className)
    {
        $fp = fopen(self::NOMOCK_CACHE_FILENAME, "a");
        fwrite($fp, $className . "\n");
        fclose($fp);
    }

    /**
     * Returns a singleton instance of ModerationTestsuiteMockAutoLoader
     * @return ModerationTestsuiteMockAutoLoader
     */
    public static function singleton()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Replace all calls to function A with calls to function B.
     * @param string $oldFunctionName
     * @param string $newFunctionName
     */
    public static function replaceFunction($oldFunctionName, $newFunctionName)
    {
        $self = self::singleton();
        $self->replacements[$oldFunctionName] = $newFunctionName;
    }

    /**
     * Autoload the mock-modified file instead of the original file.
     * @param string $className
     */
    public function autoload($className)
    {
        global $wgAutoloadClasses, $wgAutoloadLocalClasses;

        if (isset($this->alreadyMockedClasses[$className])) {
            return; /* Already mocked. Will be loaded by AutoLoader from MediaWiki. */
        }
        $this->alreadyMockedClasses[$className] = true;

        # First, we ask MediaWiki to find the PHP file for us.
        $classMap = array_merge(
            $wgAutoloadClasses ?: [],
            $wgAutoloadLocalClasses ?: [],
        );
        if (!isset($classMap[$className])) {
            return; /* Original file not found */
        }

        # Second, we modify the file and tell PHP to use rewritten file
        $origFilename = $classMap[$className];
        $newFilename = preg_replace(
            '/([^\/]+)\.php$/',
            '.$1.mocked.php',
            $origFilename,
        );

        if (
            !file_exists($newFilename) ||
            filemtime($newFilename) < filemtime($origFilename)
        ) {
            $oldText = file_get_contents($origFilename);
            $newText = $this->rewriteFile($oldText);

            if ($newText == $oldText) {
                // Cache this fact, so that each run of [cliInvoke.php]
                // wouldn't have to recheck this.
                $this->cacheThatNoMockIsNeeded($className);
                return; /* No changes, can use the original file */
            }

            file_put_contents($newFilename, $newText);
        }

        # Third, we let MediaWiki know that $newFilename should be loaded for $className
        if (isset($wgAutoloadLocalClasses[$className])) {
            $wgAutoloadLocalClasses[$className] = $newFilename;
        }
        if (isset($wgAutoloadClasses[$className])) {
            $wgAutoloadClasses[$className] = $newFilename;
        }
    }

    /**
     * Rewrite the PHP code, replacing the calls to intercepted functions with mocks.
     * @param string $text Original PHP source code.
     * @return string Modified source code.
     */
    protected function rewriteFile($text)
    {
        $functionNameRegex = implode(
            "|",
            array_map("preg_quote", array_keys($this->replacements)),
        );
        if (!$functionNameRegex) {
            return $text; /* replaceFunction() was never used */
        }

        return preg_replace_callback(
            "/^.*\s(" . $functionNameRegex . ")\s*\(/m",
            function ($matches) {
                [$line, $functionName] = $matches;
                if (strpos($line, "function") !== false) {
                    // Don't rewrite function definitions, e.g. HttpStatus::header()
                    // when rewriting "header" function.
                    return $line;
                }

                return preg_replace(
                    "/(\s)" . preg_quote($functionName) . "/",
                    '$1' . $this->replacements[$functionName],
                    $line,
                );
            },
            $text,
        );
    }
}

spl_autoload_register([
    ModerationTestsuiteMockAutoLoader::singleton(),
    "autoload",
]);
