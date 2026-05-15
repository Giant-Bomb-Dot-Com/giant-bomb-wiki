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
 * Helper script to run MediaWiki as a command line script.
 *
 * @see ModerationTestsuiteCliEngine::cliExecute()
 * @note this runs before Setup.php, configuration is unknown,
 * and we can't use any of the MediaWiki classes.
 * We just populate $_GET, $_POST, etc. and include "index.php".
 */

use MediaWiki\Moderation\Tests\ModerationTestsuiteMockAutoLoader;

$wgModerationTestsuiteCliDescriptor = unserialize(stream_get_contents(STDIN));

CliInvoke::singleton()->prepareEverything();

// Actually run MediaWiki. This can't be done from within the class.
try {
    /*--------------------------------------------------------------*/
    include $wgModerationTestsuiteCliDescriptor["isApi"]
        ? "api.php"
        : "index.php";
    /*--------------------------------------------------------------*/
} catch (Exception $e) {
    CliInvoke::singleton()->handleException($e);
}

/*---------------------------------------------------------------------------*/

// phpcs:ignore MediaWiki.Files.ClassMatchesFilename.WrongCase
class CliInvoke
{
    /** @var CliInvoke Singleton instance */
    protected static $instance = null;

    /** @var string */
    protected $exceptionText = "";

    /** */
    protected function __construct() {}

    /**
     * Returns a singleton instance of CliInvoke
     * @return CliInvoke
     */
    public static function singleton()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Returns request parameters received from CliEngine.
     * @param string $key
     * @return mixed
     */
    protected function getDescriptor($key)
    {
        global $wgModerationTestsuiteCliDescriptor;
        return $wgModerationTestsuiteCliDescriptor[$key];
    }

    /** Unpack all variables known from descriptor, e.g. $_POST */
    protected function unpackVars()
    {
        foreach (["_GET", "_POST", "_COOKIE"] as $var) {
            $GLOBALS[$var] = $this->getDescriptor($var);
        }
    }

    /** Unpack $_FILES */
    protected function unpackFiles()
    {
        global $wgModerationTestsuiteCliUploadData; // To be used in [InvokedWikiSettings.php]
        $wgModerationTestsuiteCliUploadData = [];

        foreach ($this->getDescriptor("files") as $uploadKey => $tmpFilename) {
            $curlFile = new CURLFile($tmpFilename);
            $wgModerationTestsuiteCliUploadData[$uploadKey] = [
                "name" => "whatever", # Not used anywhere
                "type" => $curlFile->getMimeType(),
                "tmp_name" => $curlFile->getFilename(),
                "size" => filesize($curlFile->getFilename()),
                "error" => 0,
            ];
        }
    }

    /**
     * Use [InvokedWikiSettings.php] instead of [LocalSettings.php].
     * The point of this file is to override $wgRequest to be FauxRequest (not WebRequest),
     * because we can only extract headers from FauxResponse.
     * @note this consumes $wgModerationTestsuiteCliDescriptor['httpHeaders']
     */
    protected function overrideLocalSettings()
    {
        define("MW_CONFIG_FILE", __DIR__ . "/InvokedWikiSettings.php");

        require_once __DIR__ . "/MockAutoLoader.php"; # Intercepts header() calls
        /*
			Replace all calls to header() PHP function (which does nothing in CLI mode)
			with calls to wfModerationTestsuiteMockedHeader() (defined inside InvokedWikiSettings.php),
			so that our tests can inspect HTTP headers.

			This needs to be done here, not in InvokedWikiSettings.php, because HttpStatus class
			(which needs this replacement) is autoloaded before InvokedWikiSettings.php.
		*/
        ModerationTestsuiteMockAutoLoader::replaceFunction(
            "header",
            "\\wfModerationTestsuiteMockedHeader",
        );
        ModerationTestsuiteMockAutoLoader::replaceFunction(
            '( $this->headerFunc )',
            "\\wfModerationTestsuiteMockedHeader",
        );
    }

    /**
     * Turn off display_errors (enabled by DevelopmentSettings.php),
     * we don't need PHP errors to be mixed with the response.
     */
    protected function configurePhp()
    {
        ini_set("display_errors", "0");
        ini_set("log_errors", "1");
    }

    /**
     * Redirect STDOUT into a newly created temporary file.
     * @return string Name of the temporary file.
     * @see getOriginalStdout()
     */
    protected function redirectStdoutToTemporaryFile()
    {
        // phpcs:ignore MediaWiki.NamingConventions.ValidGlobalName.allowedPrefix
        global $STDOUT;
        $tmpFileName = tempnam(sys_get_temp_dir(), "testsuite.stdout");

        fclose(STDOUT);
        $STDOUT = fopen($tmpFileName, "a");

        return $tmpFileName;
    }

    /**
     * Configure and run everything.
     */
    public function prepareEverything()
    {
        $this->unpackVars();
        $this->unpackFiles();
        $this->overrideLocalSettings();
        $this->configurePhp();

        $originalStdout = fopen("php://stdout", "w");
        $tmpFileName = $this->redirectStdoutToTemporaryFile();

        // Because MWLBFactory calls exit() instead of throwing an exception.
        register_shutdown_function(
            [$this, "onCompletion"],
            $tmpFileName,
            $originalStdout,
        );
    }

    /**
     * Remember the fact that exception has happened.
     * @param Exception $e
     */
    public function handleException(Exception $e)
    {
        $this->exceptionText = (string) $e;
    }

    /**
     * Shutdown function (called when the script is about to exit).
     * @param string $tmpFileName Temporary file where STDOUT has been redirected.
     * @param resource $originalStdout The result should be written here.
     */
    public function onCompletion($tmpFileName, $originalStdout)
    {
        // phpcs:ignore MediaWiki.NamingConventions.ValidGlobalName.allowedPrefix
        global $STDOUT;

        // Capture all output
        while (ob_get_status()) {
            ob_end_flush();
        }

        fclose($STDOUT);

        $capturedContent = file_get_contents($tmpFileName);
        unlink($tmpFileName); // No longer needed

        $result = [
            "capturedContent" => $capturedContent,
            "exceptionText" => $this->exceptionText,

            // Results of trackHook(). This is added to descriptor in InvokedWikiSettings.php.
            "capturedHooks" => $this->getDescriptor("capturedHooks"),
        ];

        // If an exception happened before wfModerationTestsuiteSetup(),
        // then $request wouldn't be a FauxResponse yet (and is therefore useless for CliEngine).
        $response = RequestContext::getMain()->getRequest()->response();
        if ($response instanceof MediaWiki\Request\FauxResponse) {
            $result["FauxResponse"] = $response;
        }

        fwrite($originalStdout, serialize($result));
    }
}
