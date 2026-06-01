<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2017-2024 Edward Chernenko.

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
 * Abstract parent class for sending requests (HTTP or API) to MediaWiki.
 *
 * The only implementation is CliEngine (which invokes MediaWiki as a command-line script).
 * In the past, there was also RealHttpEngine (which was sending real HTTP requests via network),
 * but it's no longer possible in modern MediaWiki (which isolates tests from the real database).
 */

namespace MediaWiki\Moderation\Tests;

use FormatJson;
use MediaWiki\MediaWikiServices;
use MediaWiki\Moderation\ModerationCompatTools;
use MediaWiki\Moderation\ModerationVersionCheck;
use MWException;
use User;

abstract class ModerationTestsuiteEngine implements IModerationTestsuiteEngine
{
    /**
     * @var array
     * HTTP headers to add to all requests, e.g. [ 'User-Agent' => '...' ]
     *
     * @phan-var array<string,string>
     */
    protected $reqHeaders = [];

    /**
     * @var User|null
     * Requests should be executed on behalf of this user.
     * Engine should throw an exception if login as this user didn't succeed.
     */
    private $currentUser = null;

    /**
     * Create engine object.
     * @return IModerationTestsuiteEngine
     */
    public static function factory()
    {
        return new ModerationTestsuiteCliEngine();
    }

    /**
     * Add an arbitrary HTTP header to all outgoing requests.
     * @param string $name
     * @param string $value
     */
    public function setHeader($name, $value)
    {
        $this->reqHeaders[$name] = $value;
    }

    /**
     * Returns array of all HTTP headers.
     * @return array
     */
    protected function getRequestHeaders()
    {
        return $this->reqHeaders + [
            "Content-Type" => "multipart/form-data",
        ];
    }

    /**
     * Obtain an aggregating Logger that doesn't print anything unless the test has failed.
     * @return ModerationTestsuiteLogger
     */
    protected function getLogger()
    {
        return new ModerationTestsuiteLogger("ModerationTestsuite");
    }

    /**
     * Execute HTTP request and return a result.
     * @param string $url
     * @param string $method
     * @param array $postData
     * @return IModerationTestsuiteResponse
     */
    final public function httpRequest(
        $url,
        $method = "GET",
        array $postData = [],
    ) {
        $logger = $this->getLogger();

        $user = $this->loggedInAs();
        $logger->info("[http] Sending HTTP request", [
            "method" => $method,
            "url" => $url,
            "postData" => $postData,
            "loggedInAs" =>
                $user->getName() .
                " (#" .
                $user->getId() .
                "), groups=[" .
                implode(
                    ", ",
                    MediaWikiServices::getInstance()
                        ->getUserGroupManager()
                        ->getUserGroups($user),
                ) .
                "]",
        ]);

        $req = $this->httpRequestInternal($url, $method, $postData);

        // Log results of the requests.
        $content = $req->getContent();
        $contentType = $req->getResponseHeader("Content-Type") ?? "";

        $loggedContent = [];

        if ($req->isRedirect()) {
            $loggedContent["redirect"] = $req->getResponseHeader("Location");
        } elseif (strpos($contentType, "text/html") !== false) {
            // Log will be too large for Travis if we dump the entire HTML,
            // so we only print main content and value of the <title> tag.
            $html = new ModerationTestsuiteHTML();
            $html->loadString($content);

            $loggedContent["title"] = $html->getTitle();

            if ($html->getMainContent()) {
                $mainText = $html->getMainText();

                // Strip excessive newlines in MainText
                $loggedContent["mainText"] = preg_replace(
                    "/\n{3,}/",
                    "\n\n",
                    $mainText,
                );
            } else {
                // MainContent element can be unavailable if this is some non-standard HTML page,
                // e.g. error 404 from showimg when simulating "missing-stash-image" error.
                $loggedContent["noMainContent"] = true;
                $loggedContent["rawContent"] = $content;
            }

            $notice = $html->getNewMessagesNotice();
            if ($notice) {
                $loggedContent["newMessagesNotice"] = $notice;
            }
        } elseif (strpos($contentType, "application/json") !== false) {
            $status = FormatJson::parse($content, FormatJson::FORCE_ASSOC);
            if ($status->isOK()) {
                $json = $status->getValue();
                if (isset($json["batchcomplete"])) {
                    // Useless part of the response, hide it to make logs shorter.
                    unset($json["batchcomplete"]);
                }

                $loggedContent["json"] = $json;
            } else {
                $loggedContent["invalidJson"] = $content;
            }
        } elseif (preg_match("/^(image|application\/ogg)/", $contentType)) {
            $loggedContent["binaryResponseOmitted"] = true;
            $loggedContent["sizeBytes"] = strlen($content);
        } else {
            $loggedContent = [
                "unknownContentType" => true,
                "rawContent" => $content,
            ];
        }

        $logger->info("[http] Received HTTP response", [
            "code" => $req->getStatus(),
            "contentType" => $contentType,
            "content" => $loggedContent,
        ]);

        return $req;
    }

    /**
     * Engine-specific implementation of httpRequest().
     * @param string $url
     * @param string $method
     * @param array $postData
     * @return IModerationTestsuiteResponse
     */
    abstract protected function httpRequestInternal(
        $url,
        $method,
        array $postData,
    );

    /**
     * Perform API request and return the resulting structure.
     * @param array $apiQuery
     * @return array
     * @note If $apiQuery contains 'token' => 'null', then 'token'
     * will be set to the current value of $editToken.
     */
    final public function query(array $apiQuery)
    {
        $apiQuery["format"] = "json";
        if (
            array_key_exists("token", $apiQuery) &&
            $apiQuery["token"] === null
        ) {
            $apiQuery["token"] = $this->getEditToken();
        }

        return $this->queryInternal($apiQuery);
    }

    /**
     * Default implementation of query(). Can be overridden in the engine subclass.
     * @param array $apiQuery
     * @return array
     */
    protected function queryInternal(array $apiQuery)
    {
        $req = $this->httpRequest(wfScript("api"), "POST", $apiQuery);
        return FormatJson::decode($req->getContent(), true);
    }

    /**
     * Determine the current user.
     * @return User
     */
    public function loggedInAs()
    {
        return $this->currentUser ?: User::newFromName("127.0.0.1", false);
    }

    /**
     * Become a logged-in user. Can be overridden in the engine subclass.
     * @param User $user
     */
    final public function loginAs(User $user)
    {
        $this->loginAsInternal($user);
        $this->currentUser = $user;
    }

    /**
     * Engine-specific implementation of loginAs().
     * @param User $user
     */
    abstract protected function loginAsInternal(User $user);

    /**
     * Forget the login cookies, etc. and become an anonymous user.
     */
    final public function logout()
    {
        $this->logoutInternal();
        $this->currentUser = null;
    }

    /**
     * Engine-specific implementation of logout().
     */
    abstract protected function logoutInternal();

    /**
     * Create an account and return User object.
     * Note: Will not login automatically (loginAs must be called).
     * @param string $username
     * @return User|null
     */
    public function createAccount($username)
    {
        # Step 1. Get the token.
        $q = [
            "action" => "query",
            "meta" => "tokens",
            "type" => "createaccount",
        ];
        $ret = $this->query($q);
        $token = $ret["query"]["tokens"]["createaccounttoken"];

        # Step 2. Actually create an account.
        $q = [
            "action" => "createaccount",
            "username" => $username,
            "password" => ModerationTestsuite::TEST_PASSWORD,
            "retype" => ModerationTestsuite::TEST_PASSWORD,
            "createtoken" => $token,
            "createreturnurl" => "http://localhost/" /* Not really used */,
        ];
        $ret = $this->query($q);

        if ($ret["createaccount"]["status"] != "PASS") {
            return null;
        }

        return User::newFromName($username, false) ?: null;
    }

    /**
     * Handle the fact that MediaWikiIntegrationTestCase tries to isolate us from the real database.
     *
     * MediaWiki 1.28+ started to agressively isolate tests from the real database,
     * which means that executed HTTP requests must also be in the sandbox.
     */
    public function escapeDbSandbox()
    {
        if (!(bool) getenv("PHPUNIT_USE_NORMAL_TABLES")) {
            throw new MWException(
                "To run Moderation testsuite, PHPUnit needs PHPUNIT_USE_NORMAL_TABLES=1 in enviroment.",
            );
        }

        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $this->setMwConfig("DBprefix", $dbw->tablePrefix());

        // Ensure that ModerationVersionCheck doesn't have an old version number in cache,
        // otherwise Moderation will assume that DB schema is outdated.
        ModerationVersionCheck::invalidateCache();
    }
}
