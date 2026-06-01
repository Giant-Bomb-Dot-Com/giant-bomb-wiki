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
 * Replacement of LocalSettings.php loaded by [cliInvoke.php].
 *
 * @phan-file-suppress PhanUndeclaredGlobalVariable
 * @phan-file-suppress PhanUndeclaredVariableDim
 */

// Unfortunately we have to inform MediaWiki that this is being called from unit test,
// or else it won't allow us to use MediaWikiServices::allowGlobalInstanceAfterUnitTests(),
// which is necessary to use Hooks::register() very early (for SetupAfterCache hook, etc.).
define("MW_PHPUNIT_TEST", true);

# Load the usual LocalSettings.php
require_once "$IP/LocalSettings.php";

use MediaWiki\Auth\AuthManager;
use MediaWiki\MediaWikiServices;
use MediaWiki\Moderation\Tests\ModerationTestsuiteBagOStuff;
use MediaWiki\Moderation\Tests\ModerationTestsuiteCliApiMain;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\FauxResponse;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\DatabaseDomain;

# Replace Memcached with our caching class. This is needed for Parallel PHPUnit testing,
# where "flush_all" Memcached command is not applicable (it would delete keys of another thread).
require_once __DIR__ . "/../../ModerationTestsuiteBagOStuff.php";
$wgObjectCaches[CACHE_MEMCACHED] = [
    "class" => ModerationTestsuiteBagOStuff::class,
    "loggroup" => "memcached",
    "filename" => "/dev/shm/modtest.cache",
];

// Same as in [tests/common/TestSetup.php]. Makes tests faster.
$wgSessionPbkdf2Iterations = 1;

// Sanity check: disallow deprecated session management via session_id(), etc.
$wgPHPSessionHandling = "disable";

if ($wgModerationTestsuiteCliDescriptor["expectedUser"][0] === 0) {
    // Testsuite has requested that we operate as an anonymous user, so we must disable
    // the temporary accounts (otherwise MediaWiki would automatically register anonymous users).
    $wgAutoCreateTempUser["enabled"] = false;
}

/* Apply variables requested by ModerationTestsuiteCliEngine::setMwConfig() */
foreach ($wgModerationTestsuiteCliDescriptor["config"] as $name => $value) {
    if ($name == "DBprefix" && $wgDBtype == "postgres") {
        // Setting $wgDBprefix with PostgreSQL is not allowed.
        // So we have to wait for database to be initialized from configs
        // (e.g. until SetupAfterCache hook, which is called after all configuration is read),
        // and then redefine the prefix via LoadBalancerFactory.

        $oldDomain = WikiMap::getCurrentWikiDbDomain();
        $newDomain = new DatabaseDomain(
            $oldDomain->getDatabase(),
            $oldDomain->getSchema(),
            $value, // Typically "unittest_"
        );
        $GLOBALS["wgCachePrefix"] = $newDomain->getId();

        wfFakeHooksRegister("SetupAfterCache", static function () use (
            $newDomain,
        ) {
            $lbFactory = MediaWiki\MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
            $lbFactory->redefineLocalDomain($newDomain);
        });

        // This approach causes a deprecation warning (which must be suppressed).
        $reflection = new ReflectionProperty("MWDebug", "deprecationFilters");
        $reflection->setAccessible(true);
        $deprecationFilters = $reflection->getValue();

        $deprecationFilters["/Deprecated cross-wiki access.*/"] = null;
        $reflection->setValue($deprecationFilters);
    } else {
        $GLOBALS["wg$name"] = $value;
    }
}

/**
 * Replacement for header() function that (unlike header() itself) always records headers
 * inside the FauxResponse of global WebRequest (thus allowing our tests to inspect them).
 * @see CliInvoke::overrideLocalSettings
 * @param string $string
 * @param bool $replace
 * @param null|int $http_response_code
 */
function wfModerationTestsuiteMockedHeader(
    $string,
    $replace = true,
    $http_response_code = null,
) {
    $response = RequestContext::getMain()->getRequest()->response();
    if (!($response instanceof FauxResponse)) {
        // This is WebRequest, meaning header() was called before wfModerationTestsuiteSetup(),
        // typically due to some early initialization error.
        return;
    }

    $response->header($string, $replace, $http_response_code);
}

/**
 * Sanity check: log "which user is currently logged in",
 * and ensure that request is executed on behalf on an expected user.
 */
function wfModerationTestsuiteCliLogin()
{
    global $wgModerationTestsuiteCliDescriptor;
    [$expectedId, $expectedName] = $wgModerationTestsuiteCliDescriptor[
        "expectedUser"
    ];

    $user = RequestContext::getMain()->getUser();
    if ($user->getId() != $expectedId || $user->getName() != $expectedName) {
        $user = User::newFromName($expectedName, false);

        // Login as $user. If this user doesn't exist, it will be created.
        $manager = MediaWikiServices::getInstance()->getAuthManager();
        $status = $manager->autoCreateUser(
            $user,
            AuthManager::AUTOCREATE_SOURCE_SESSION,
            true,
        );
        if (
            !$status->isOK() &&
            !($expectedId === 0 && $status->hasMessage("noname"))
        ) {
            throw new MWException(
                "Failed to login as User:$expectedName (#$expectedId).",
            );
        }

        RequestContext::getMain()->setUser($user);
    }

    $request = RequestContext::getMain()->getRequest();
    foreach ($request->getValues() as $key => $val) {
        if ($val === "{CliEngine:Token:CSRF}") {
            $request->setVal($key, $user->getEditToken());
        }
    }

    $event = array_merge(
        [
            "_entrypoint" => MW_ENTRY_POINT,
            "_LoggedInAs" =>
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
                "], hasModeratorAccess=" .
                ($user->isAllowed("moderation") ? "yes" : "no") .
                ", canSkipModeration=" .
                ($user->isAllowed("skip-moderation") ? "yes" : "no"),
        ],
        $request->getValues(),
    );
    wfDebugLog(
        "ModerationTestsuite",
        FormatJson::encode($event, true, FormatJson::ALL_OK),
    );
}

/**
 * Register the hook handler very early (when HookContainer is not yet initialized).
 * @param string $hookName
 * @param callable $handler
 */
function wfFakeHooksRegister($hookName, callable $handler)
{
    MediaWikiServices::allowGlobalInstanceAfterUnitTests();
    MediaWikiServices::getInstance()
        ->getHookContainer()
        ->register($hookName, $handler);
}

function wfModerationTestsuiteSetup()
{
    global $wgModerationTestsuiteCliDescriptor,
        $wgModerationTestsuiteCliUploadData,
        $wgRequest,
        $wgAutoloadClasses;

    $wgAutoloadClasses[ModerationTestsuiteCliApiMain::class] =
        __DIR__ . "/ModerationTestsuiteCliApiMain.php";

    /*
		Override $wgRequest. It must be a FauxRequest
		(because you can't extract response headers from WebResponse,
		only from FauxResponse)
	*/
    // phpcs:disable MediaWiki.Usage.SuperGlobalsUsage.SuperGlobals
    $request = new FauxRequest(
        $_POST + $_GET, // $data
        $_SERVER["REQUEST_METHOD"] == "POST", // $wasPosted
    );
    $request->setRequestURL($_SERVER["REQUEST_URI"]);
    // phpcs:enable
    $request->setHeaders($wgModerationTestsuiteCliDescriptor["httpHeaders"]);
    $request->setCookies($_COOKIE, "");

    /* Use $request as the global WebRequest object */
    RequestContext::getMain()->setRequest($request);
    $wgRequest = $request;

    $request->setUploadData($wgModerationTestsuiteCliUploadData);

    /*
		Install hook to replace ApiMain class
			with ModerationTestsuiteCliApiMain (subclass of ApiMain)
			that always prints the result, even in "internal mode".
	*/
    wfFakeHooksRegister("ApiBeforeMain", static function (ApiMain &$apiMain) {
        $apiMain = new ModerationTestsuiteCliApiMain(
            $apiMain->getContext(),
            true,
        );

        wfModerationTestsuiteCliLogin();

        // Because the hook ModerationApiHooks::onApiBeforeMain() creates a DerivativeRequest,
        // and this DerivativeRequest hasn't been modified by wfModerationTestsuiteCliLogin(),
        // we need to replace the CSRF token placeholder with a correct token.
        $request = $apiMain->getContext()->getRequest();
        if ($request->getVal("token") === "{CliEngine:Token:CSRF}") {
            $editToken = RequestContext::getMain()
                ->getRequest()
                ->getVal("token");
            $request->setVal("token", $editToken);
        }

        return true;
    });

    wfFakeHooksRegister("BeforeInitialize", static function (
        &$unused1,
        &$unused2,
        &$unused3,
        &$user,
    ) {
        wfModerationTestsuiteCliLogin();

        // Make sure that handlers of BeforeInitialize hook (if any) will run as this new user.
        $user = RequestContext::getMain()->getUser();

        return true;
    });

    /*
		Initialize the session from the session cookie (if such cookie exists).
		FIXME: determine why exactly didn't SessionManager do this automatically.
	*/
    wfFakeHooksRegister("SetupAfterCache", static function () {
        /* Earliest hook where $wgCookiePrefix (needed by getCookie())
         is available (when not set in LocalSettings.php)  */
        $request = RequestContext::getMain()->getRequest();
        $sessionId = $request->getCookie("_session");
        if ($sessionId) {
            $manager = MediaWiki\Session\SessionManager::singleton();
            $session =
                $manager->getSessionById($sessionId, true) ?:
                $manager->getEmptySession();
            $request->setSessionId($session->getSessionId());
        }
    });

    /*
		Track hooks, as requested by ModerationTestsuiteCliEngine::trackHook()
	*/
    $wgModerationTestsuiteCliDescriptor["capturedHooks"] = [];
    foreach ($wgModerationTestsuiteCliDescriptor["trackedHooks"] as $hook) {
        $wgModerationTestsuiteCliDescriptor["capturedHooks"][$hook] = [];

        wfFakeHooksRegister($hook, static function () use ($hook) {
            global $wgModerationTestsuiteCliDescriptor;

            // The testsuite would want to analyze types of received parameters,
            // and well as parameter values (assuming they can be serialized).
            $params = func_get_args();
            $paramTypes = array_map(static function ($param) {
                $type = gettype($param);
                return $type == "object" ? get_class($param) : $type;
            }, $params);

            $paramsJson = FormatJson::encode(
                array_map(static function ($param) {
                    // @phan-suppress-next-line PhanUndeclaredClassInstanceof
                    if (
                        $param instanceof WikiPage ||
                        $param instanceof MediaWiki\Page\WikiPage
                    ) {
                        // WikiPage can't be directly serialized, so we replace it with full page name.
                        // @phan-suppress-next-line PhanUndeclaredClassMethod
                        return $param->getTitle()->getFullText();
                    }

                    return $param;
                }, $params),
            );

            $wgModerationTestsuiteCliDescriptor["capturedHooks"][$hook][] = [
                $paramTypes,
                $paramsJson,
            ];

            return true;
        });
    }

    wfFakeHooksRegister("AlternateUserMailer", static function () {
        // Prevent any emails from actually being sent during the testsuite runs.
        return false;
    });
}

wfModerationTestsuiteSetup();
