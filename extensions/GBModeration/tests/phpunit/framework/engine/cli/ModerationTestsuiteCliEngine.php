<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2017-2025 Edward Chernenko.

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
 * Testsuite engine that runs index.php/api.php in PHP CLI.
 */

namespace MediaWiki\Moderation\Tests;

use CURLFile;
use Exception;
use FormatJson;
use LogicException;
use MediaWiki\Shell\Shell;
use MWException;
use User;

class ModerationTestsuiteCliEngine extends ModerationTestsuiteEngine
{
    /** @var array [ 'name' => 'value' ] */
    protected $cookies = [];

    /** @var array [ 'name' => 'value' ] - $wg* variables from setMwConfig() */
    protected $config = [];

    /** @var array [ 'name_of_hook' => postfactum_callback ] */
    protected $trackedHooks = [];

    /** @var string[] */
    private $ignoredDeprecations = [
        // MediaWiki 1.44 only: incorrect deprecation caused by MediaWiki core itself.
        "Use of MediaWiki\\Skin\\Skin::appendSpecialPagesLinkIfAbsent was deprecated",
    ];

    /**
     * Forget the login cookies (if any), thus becoming an anonymous user.
     */
    protected function logoutInternal()
    {
        $this->cookies = [];
    }

    /**
     * Sets MediaWiki global variable.
     * @param string $name Name of variable without the $wg prefix.
     * @param mixed $value
     */
    public function setMwConfig($name, $value)
    {
        $this->config[$name] = $value;
    }

    /**
     * Detect invocations of the hook and capture the parameters that were passed to it.
     * @param string $name Name of the hook, e.g. "ModerationPending".
     * @param callable $postfactumCallback Information about received parameters.
     */
    public function trackHook($name, callable $postfactumCallback)
    {
        $this->trackedHooks[$name] = $postfactumCallback;
    }

    /**
     * CliEngine doesn't need any login logic - it auto-logins as the current user.
     * @param User $user @phan-unused-param
     */
    protected function loginAsInternal(User $user) {}

    /**
     * Placeholder for edit token - will be replaced by correct token in [InvokedWikiSettings.php].
     * @return string
     */
    public function getEditToken()
    {
        return "{CliEngine:Token:CSRF}";
    }

    /**
     * Simulate HTTP request by running index.php/api.php of MediaWiki in a controlled environment.
     * @param string $url
     * @param string $method
     * @param array $postData
     * @return IModerationTestsuiteResponse
     * @throws MWException
     */
    protected function httpRequestInternal($url, $method, array $postData)
    {
        global $wgServerName, $IP;

        /* Handle CURL uploads in $postData */
        $files = [];
        foreach ($postData as $key => $val) {
            if ($val instanceof CURLFile) {
                /* Create a temporary copy of this file,
                 so that the original file won't be deleted after the upload */
                $tmpFilename =
                    tempnam(sys_get_temp_dir(), "testsuite.upload") .
                    basename($val->getFilename());
                copy($val->getFilename(), $tmpFilename);

                $files[$key] = $tmpFilename;
                unset($postData[$key]); // CURLFile is not serializable
            }
        }

        # Descriptor is the information to be passed to [cliInvoke.php].
        $descriptor = [
            "_COOKIE" => $this->cookies,
            "_GET" => [],
            "_POST" => $postData,
            "files" => $files,
            "httpHeaders" => $this->getRequestHeaders() + [
                "Host" => $wgServerName,
            ],
            "config" => $this->config,
            "trackedHooks" => array_keys($this->trackedHooks),

            // Request will be executed on behalf of this user.
            "expectedUser" => [
                $this->loggedInAs()->getId(),
                $this->loggedInAs()->getName(),
            ],
        ];

        /* We must parse $url here,
			because invokeFromScript() is called before reading LocalSettings.php
			and therefore won't have access to things like $wgServer or UrlUtils service. */
        $bits = ModerationTestUtil::parseUrl($url);
        if ($bits["host"] !== $wgServerName) {
            throw new LogicException(
                "CliEngine can only access the wiki itself " .
                    "($wgServerName), not another host ({$bits["host"]})",
            );
        }

        if (isset($bits["query"])) {
            $descriptor["_GET"] = wfCgiToArray($bits["query"]);
        }

        [$scriptName, $pathInfo] = $this->safelyExtractPathInfo($bits["path"]);

        $descriptor["isApi"] = $scriptName == wfScript("api");

        $documentRoot = str_replace(dirname(wfScript()), "", $IP);
        $scriptFilename = realpath($IP . "/" . basename($scriptName));

        /* Prepare the environment for calling cliInvoke.php.
			To be sure we have everything,
			we provide all variables that CGI/1.1 would provide
			(except CONTENT_TYPE/CONTENT_LENGTH,
			because the child process populates $_POST directly).
		*/
        $env = [
            "QUERY_STRING" => $bits["query"] ?? "",
            "REQUEST_METHOD" => $method,
            "SCRIPT_NAME" => $scriptName,
            "SCRIPT_FILENAME" => $scriptFilename,
            "REQUEST_URI" =>
                $bits["path"] .
                (isset($bits["query"]) ? "?" . $bits["query"] : ""),
            "PATH_INFO" => $pathInfo,
            "DOCUMENT_ROOT" => $documentRoot,
            "SERVER_PROTOCOL" => "HTTP/1.1",
            "GATEWAY_INTERFACE" => "CGI/1.1",
            "REMOTE_ADDR" => "127.0.0.1",
            "REMOTE_PORT" => 12345, # Fake
            "SERVER_ADDR" => "127.0.0.1",
            "SERVER_PORT" => 80,
            "SERVER_NAME" => "$wgServerName",
            "SERVER_SOFTWARE" => "FakeHttpServer/1.0.0",
            "REDIRECT_STATUS" => 200,
            "HTTP_HOST" => "$wgServerName",
        ];

        // This variable is needed for parallel builds via Fastest
        // (it runs PHPUnit in multiple threads,
        // and each thread uses its own database)
        $env["ENV_TEST_CHANNEL"] = getenv("ENV_TEST_CHANNEL");

        $limits = [
            "memory" => -1,
            "filesize" => -1,
            "time" => -1,
            "walltime" => -1,
        ];

        $ret = Shell::command([])
            ->params([PHP_BINARY, __DIR__ . "/cliInvoke.php"])
            // @phan-suppress-next-line PhanTypeMismatchArgument
            ->environment($env)
            ->limits($limits)
            ->privateUserNamespace()
            ->input(serialize($descriptor))
            ->execute();

        $errorOutput = $ret->getStderr();
        if ($errorOutput) {
            $errorOutput = $this->filterDeprecations($errorOutput);
            if ($errorOutput) {
                // Allow PHPUnit to complain about this.
                print "\n" . $errorOutput;
            }
        }

        $errorContext =
            "from $env[REQUEST_METHOD] [$url], postData=[" .
            wfArrayToCgi($descriptor["_POST"]) .
            "]";

        $output = $ret->getStdout();
        try {
            $result = unserialize($output);
        } catch (Exception $_) {
            $this->getLogger()->error(
                "[CliEngine] Non-serialized text printed by cliInvoke.php",
                [
                    "printedOutput" => $output,
                ],
            );
            throw new MWException(
                "Non-serialized text printed by cliInvoke.php $errorContext",
            );
        }

        if (!empty($result["exceptionText"])) {
            $this->getLogger()->error("[CliEngine] Exception detected", [
                "text" => $result["exceptionText"],
            ]);
        }

        if (!isset($result["FauxResponse"])) {
            $this->getLogger()->error("[CliEngine] No FauxResponse", [
                "capturedContent" => $result["capturedContent"],
            ]);
            throw new MWException("no FauxResponse $errorContext");
        }

        /* Remember the newly set cookies */
        $response = $result["FauxResponse"];
        $this->cookies =
            array_map(static function ($info) {
                return $info["value"];
            }, $response->getCookies()) + $this->cookies;

        /* Call any postfactum callbacks that were requested by trackHook() */
        foreach ($this->trackedHooks as $hook => $callback) {
            foreach ($result["capturedHooks"][$hook] as $invocation) {
                [$paramTypes, $paramsJson] = $invocation;
                $params = FormatJson::decode($paramsJson, true);

                $callback($paramTypes, $params);
            }
        }

        return ModerationTestsuiteResponse::newFromFauxResponse(
            $response,
            $this->filterDeprecations($result["capturedContent"]),
        );
    }

    /**
     * Remove deprecation warnings (if any) from the text of the response.
     * @param string $text
     * @return string
     */
    protected function filterDeprecations($text)
    {
        $text = preg_replace_callback(
            '/^(PHP |)Deprecated:.*$/m',
            function ($matches) {
                $warning = $matches[0];
                foreach ($this->ignoredDeprecations as $ignoredDeprecation) {
                    if (strpos($warning, $ignoredDeprecation) !== false) {
                        return "";
                    }
                }

                print "\nCliEngine: " . $warning;
                return "";
            },
            $text,
        );

        return trim($text);
    }

    /**
     * Safely split $bits['path'] into SCRIPT_NAME and PATH_INFO
     * @param string $relPath
     * @return array that contains two strings: script name (e.g. "api.php") and pathinfo.
     */
    protected function safelyExtractPathInfo($relPath)
    {
        $allowedScripts = array_map("wfScript", ["index", "api"]);

        [$scriptName] = preg_split("/(?<=\.php)/", $relPath);
        if (!in_array($scriptName, $allowedScripts)) {
            /* If the script isn't whitelisted,
             whole $relPath is treated as PATH_INFO */
            $scriptName = wfScript("index");
            $pathInfo = $relPath;
        } else {
            $pathInfo = substr($relPath, strlen($scriptName));
        }

        return [$scriptName, $pathInfo];
    }
}
