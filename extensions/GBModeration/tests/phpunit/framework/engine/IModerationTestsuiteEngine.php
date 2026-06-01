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
 * Basic interface of sending requests (HTTP or API) to MediaWiki.
 */

namespace MediaWiki\Moderation\Tests;

use User;

interface IModerationTestsuiteEngine
{
    /**
     * Perform API request and return the resulting structure.
     * @param array $apiQuery
     * @return array
     * @note If $apiQuery contains 'token' => 'null', then 'token'
     * will be set to the current value of $editToken.
     */
    public function query(array $apiQuery);

    /**
     * Add an arbitrary HTTP header to all outgoing requests.
     * @param string $name
     * @param string $value
     */
    public function setHeader($name, $value);

    /**
     * Become a logged-in user.
     * @param User $user
     */
    public function loginAs(User $user);

    /**
     * Become an anonymous user.
     */
    public function logout();

    /**
     * Determine the current user.
     * @return User
     */
    public function loggedInAs();

    /**
     * Create an account and return User object.
     * Note: Will not login automatically (loginAs must be called).
     * @param string $username
     * @return User|null
     */
    public function createAccount($username);

    /**
     * Obtain edit token.
     * @return string
     */
    public function getEditToken();

    /**
     * Execute HTTP request and return a result.
     * @param string $url
     * @param string $method
     * @param array $postData
     * @return IModerationTestsuiteResponse
     *
     * @phan-param array<string,string> $postData
     * @phan-param 'GET'|'POST' $method
     */
    public function httpRequest($url, $method = "GET", array $postData = []);

    /**
     * Sets MediaWiki global variable.
     * @param string $name Name of variable without the $wg prefix.
     * @param mixed $value
     */
    public function setMwConfig($name, $value);

    /**
     * Detect invocations of the hook and capture the parameters that were passed to it.
     * @param string $name Name of the hook, e.g. "ModerationPending".
     * @param callable $postfactumCallback Receives array of received parameter types and array
     * of received parameters. Non-serializable parameters will be empty.
     * @note This callback is called after httpRequest() has already been completed.
     * @note If there were several invocations of the hook, callback is called for each of them.
     *
     * @phan-param callable(string[],array) $postfactumCallback
     */
    public function trackHook($name, callable $postfactumCallback);

    /**
     * Switch to unittest_ database. Used in ModerationTestsuite::prepareDbForTests().
     */
    public function escapeDbSandbox();
}
