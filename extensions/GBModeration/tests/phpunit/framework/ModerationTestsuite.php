<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2015-2025 Edward Chernenko.

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
*/

namespace MediaWiki\Moderation\Tests;

use CommentStoreComment;
use MediaWiki\MediaWikiServices;
use MediaWiki\Moderation\ModerationCompatTools;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use MWException;
use Stringable;
use TestUser;
use User;
use Wikimedia\Rdbms\DBConnRef;

require_once __DIR__ . "/autoload.php";

/**
 * @file
 * Automated testsuite of Extension:Moderation.
 */

class ModerationTestsuite
{
    public const TEST_PASSWORD = "123456";
    public const DEFAULT_USER_AGENT = "MediaWiki Moderation Testsuite";

    /** @var IModerationTestsuiteEngine */
    protected $engine;

    /** @var ModerationTestsuiteHTML */
    public $html;

    /** @var array Misc. information about the last edit, as populated by setLastEdit() */
    public $lastEdit = [];

    public function __construct()
    {
        $this->engine = ModerationTestsuiteEngine::factory();

        $this->prepareDbForTests();

        $this->html = new ModerationTestsuiteHTML($this->engine);
        $this->setUserAgent(self::DEFAULT_USER_AGENT);

        # With "qqx" language selected, messages are replaced with
        # their names, so parsing process is translation-independent.
        $this->setMwConfig("LanguageCode", "qqx");
    }

    /**
     * Send an API request to the wiki.
     * @param array $apiQuery
     * @return array
     * @phan-param array<string,mixed> $apiQuery
     */
    public function query($apiQuery)
    {
        return $this->engine->query($apiQuery);
    }

    /**
     * Send a HTTP GET request to the wiki.
     * @param string $url
     * @return IModerationTestsuiteResponse
     */
    public function httpGet($url)
    {
        return $this->engine->httpRequest($url, "GET");
    }

    /**
     * Send a HTTP POST request to the wiki.
     * @param string $url
     * @param array $postData
     * @return IModerationTestsuiteResponse
     * @phan-param array<string,string> $postData
     */
    public function httpPost($url, array $postData = [])
    {
        return $this->engine->httpRequest($url, "POST", $postData);
    }

    /**
     * Get a valid edit token.
     * @return string
     */
    public function getEditToken()
    {
        return $this->engine->getEditToken();
    }

    /**
     * Sets MediaWiki global variable.
     * @param string $name Name of variable without the $wg prefix.
     * @param mixed $value
     */
    public function setMwConfig($name, $value)
    {
        $this->engine->setMwConfig($name, $value);
    }

    /**
     * @var array
     * @phan-var array<string,array<array{0:string[],1:mixed[]}>>
     */
    protected $capturedHooks = [];

    /**
     * Detect invocations of the hook and capture the parameters that were passed to it.
     * @param string $name Name of the hook, e.g. "ModerationPending".
     * @see getCapturedHooks()
     */
    public function trackHook($name)
    {
        $this->capturedHooks[$name] = [];
        $this->engine->trackHook($name, function ($paramTypes, $params) use (
            $name,
        ) {
            $this->capturedHooks[$name][] = [$paramTypes, $params];
        });
    }

    /**
     * Return information about every call to the hook that happened since trackHook() was called.
     * @param string $name Name of the hook, e.g. "ModerationPending"
     * @return array Array of invocations, where each invocation is an array of two elements:
     * 1) array of received parameter types,
     * 2) array of received parameters. (Note: non-serializable parameters will be empty)
     *
     * @phan-return array<array{0:string[],1:mixed[]}>
     */
    public function getCapturedHooks($name)
    {
        return $this->capturedHooks[$name] ?? [];
    }

    /**
     * Add an arbitrary HTTP header to all outgoing requests.
     * @param string $name
     * @param string $value
     */
    public function setHeader($name, $value)
    {
        $this->engine->setHeader($name, $value);
    }

    /**
     * Set User-Agent header for all outgoing requests.
     * @param string $userAgent
     */
    public function setUserAgent($userAgent)
    {
        $this->setHeader("User-Agent", $userAgent);
    }

    #
    # Functions for parsing Special:Moderation.
    #

    /**
     * @var array
     * Contents of folders of Special:Moderation after the last call of fetchSpecial().
     *
     * @phan-var array<string,ModerationTestsuiteEntry[]>
     */
    private $lastFetchedSpecial = [];

    /** @var ModerationTestsuiteEntry[] */
    public $new_entries;

    /** @var ModerationTestsuiteEntry[] */
    public $deleted_entries;

    /**
     * Returns correct URL of Special:Moderation.
     * @param array $query Query string parameters, e.g. [ 'modaction' => 'approve', 'id' => 123 ].
     * @return string
     * @phan-param array<string,string> $query
     */
    public function getSpecialURL($query = [])
    {
        $title = Title::newFromText("Moderation", NS_SPECIAL)->fixSpecialName();
        return wfAppendQuery($title->getLocalURL(), $query);
    }

    /**
     * Delete the results of previous fetchSpecial().
     * If fetchSpecial() is then called, all entries
     * in this folder will be considered new entries.
     * @param string $folder
     */
    public function assumeFolderIsEmpty($folder = "DEFAULT")
    {
        $this->lastFetchedSpecial[$folder] = [];
    }

    /**
     * Download and parse Special:Moderation. Diff its current
     * state with the previously downloaded/parsed state, and
     * populate the arrays \b $new_entries, \b $old_entries.
     * @note Logs in as $moderator.
     * @param string $folder
     */
    public function fetchSpecial($folder = "DEFAULT")
    {
        if (!$this->isModerator()) {
            /* Don't relogin in testModeratorNotAutomoderated() */
            $this->loginAs($this->moderator);
        }

        $query = ["limit" => 150];
        if ($folder != "DEFAULT") {
            $query["folder"] = $folder;
        }
        $url = $this->getSpecialURL($query);

        $html = $this->html->loadUrl($url);
        $spans = $html->getElementsByTagName("span");

        $entries = [];
        foreach ($spans as $span) {
            if (strpos($span->getAttribute("class"), "modline") !== false) {
                $e = new ModerationTestsuiteEntry($span);
                $entries[$e->id] = $e;
            }
        }

        if (array_key_exists($folder, $this->lastFetchedSpecial)) {
            $before = $this->lastFetchedSpecial[$folder];
        } else {
            $before = [];
        }
        $after = $entries;

        $this->new_entries = array_values(array_diff_key($after, $before));
        $this->deleted_entries = array_values(array_diff_key($before, $after));

        $this->lastFetchedSpecial[$folder] = $entries;
    }

    /**
     * Profiling assist function: make a profiling timer.
     * Usage:
     *	$timeSpent = $t->profiler();
     *	// Do something
     *	echo "It took $timeSpent seconds";
     * @return Stringable Value that can be cast to "seconds spent" formatted string.
     */
    protected function profiler()
    {
        return new class implements Stringable {
            protected $startTime;

            public function __construct()
            {
                $this->startTime = microtime(true);
            }

            public function __toString()
            {
                return sprintf("%.3f", microtime(true) - $this->startTime);
            }
        };
    }

    #
    # Database-related functions.
    #

    /**
     * @param string $name
     * @param string[] $groups
     * @return User
     */
    private function createTestUser($name, $groups = [])
    {
        $user = User::createNew($name);
        if (!$user) {
            throw new MWException(
                __METHOD__ . ": failed to create User:$name.",
            );
        }

        TestUser::setPasswordForUser($user, self::TEST_PASSWORD);

        $user->saveSettings();

        $userGroupManager = MediaWikiServices::getInstance()->getUserGroupManager();
        foreach ($groups as $group) {
            $userGroupManager->addUserToGroup($user, $group);
        }

        return $user;
    }

    /**
     * Create controlled environment before each test.
     * (as in "Destroy everything on testsuite's path")
     */
    private function prepareDbForTests()
    {
        if (class_exists("MediaWikiIntegrationTestCase")) {
            // Not in benchmark scripts
            // Handle the fact that MediaWikiIntegrationTestCase tries to isolate us from the real database,
            // which we must examine, because this entire testsuite does the blackbox testing,
            // making HTTP queries as the users would do.
            $this->engine->escapeDbSandbox();
        }

        $dbw = $this->getDB();

        /* Make sure the database is in a consistent state
         (after messy tests like RollbackResistantQueryTest.php) */
        if ($dbw->writesOrCallbacksPending()) {
            $dbw->commit(__METHOD__, "flush");
        }

        $tablesToTruncate = [
            "moderation",
            "moderation_block",
            "user",
            "user_groups",
            "user_newtalk",
            "user_properties",
            "page",
            "revision",
            "revision_comment_temp",
            "revision_actor_temp",
            "logging",
            "log_search",
            "text",
            "image",
            "uploadstash",
            "recentchanges",
            "watchlist",
            "change_tag",
            "tag_summary",
            "actor",
            "abuse_filter",
            "abuse_filter_action",
            "ip_changes",
            "cu_changes",
            "slots",
            "objectcache",
        ];
        if ($dbw->getType() == "postgres") {
            $tablesToTruncate[] = "mwuser";
            $tablesToTruncate[] = "pagecontent";
        }

        foreach ($tablesToTruncate as $table) {
            $this->truncateDbTable($table);
        }

        // Create test users like $t->moderator.
        $this->prepopulateDb();

        // Avoid stale data being reported by Title::getArticleId(), etc. on the test side
        // when running multiple sequential tests, e.g. in ModerationQueueTest.
        Title::clearCaches();

        # Clear our thread-aware cache before each test.
        ModerationTestsuiteBagOStuff::flushAll();

        // Clear the buffer of getCapturedHooks()
        $this->capturedHooks = [];
    }

    /**
     * Get a Database object that allows maintenance methods like fieldExists().
     * @return DBConnRef
     */
    protected function getDB()
    {
        return MediaWikiServices::getInstance()
            ->getDBLoadBalancer()
            ->getMaintenanceConnectionRef(DB_PRIMARY);
    }

    /**
     * Delete all contents of the SQL table.
     * @param string $table
     */
    protected function truncateDbTable($table)
    {
        $dbw = $this->getDB();
        if (!$dbw->tableExists($table, __METHOD__)) {
            return;
        }

        $dbw->truncateTable($table, __METHOD__);
    }

    /**
     * @var array
     * Cache created in makePrepopulateDbCache() and used in prepopulateDb().
     */
    protected static $prepopulateDbCache;

    /**
     * @var array
     * Names of database tables that should be cached in makePrepopulateDbCache().
     */
    public static $prepopulateDbNeededTables = [
        // Caching these tables ("before the test" state) allows us to avoid User::createNew()
        // and addUserToGroup() calls. These calls were doing exactly the same before every test.
        "actor",
        "user",
        "user_groups",
    ];

    /**
     * Determine primary key field of the table.
     * @param string $table
     * @return string|false Name of the field.
     */
    private function getKeyField($table)
    {
        $keyField = $table . "_id";

        $dbw = $this->getDB();
        if ($dbw->getType() == "postgres" && $table == "mwuser") {
            $keyField = "user_id";
        }

        if (!$dbw->fieldExists($table, $keyField)) {
            return false;
        }

        return $keyField;
    }

    /**
     * Create users like $t->moderator and $t->unprivilegedUser.
     */
    private function prepopulateDb()
    {
        if (!self::$prepopulateDbCache) {
            $this->makePrepopulateDbCache();
        }

        // Load from cache.
        $dbw = $this->getDB();
        $dbw->begin(__METHOD__);

        foreach (self::$prepopulateDbCache as $table => $rows) {
            // For PostgreSQL only, it's necessary to exclude the primary key field (e.g. user_id)
            // from INSERT query. Otherwise the sequence won't be incremented, and this happens:
            // 1) we insert multiple rows with user_id=1, user_id=2, etc.
            // 2) sequence thinks that next user_id should be 1.
            // 3) User::createNew() does INSERT without user_id, and sequence picks user_id=1.
            // 4) Because row with user_id=1 already exists, INSERT from User::createNew() fails.
            $keyField = false;
            if ($dbw->getType() == "postgres") {
                $keyField = $this->getKeyField($table);
            }

            foreach ($rows as $row) {
                $valueSaved = false;
                if ($keyField) {
                    // See above, needed for PostgreSQL sequence to be incremented.
                    $valueSaved = $row[$keyField];
                    unset($row[$keyField]);
                }

                // @phan-suppress-next-line SecurityCheck-SQLInjection - false positive
                $dbw->insert($table, $row, __METHOD__);
                if ($dbw->affectedRows() != 1) {
                    throw new MWException(
                        "createTestUsers: loading from cache failed.",
                    );
                }

                if ($valueSaved) {
                    // Sanity check: since we removed the primary key from INSERT query,
                    // make sure that automatically picked values are correct.
                    // What should makes them correct is how $prepopulateDbCache is sorted
                    // with "SELECT .. ORDER BY" in makePrepopulateDbCache()).
                    $insertId = $dbw->insertId();
                    if ($insertId != $valueSaved) {
                        throw new MWException(
                            "PostgreSQL: incorrect field ID: insertId=$insertId, " .
                                "expected $keyField=$valueSaved.",
                        );
                    }
                }
            }
        }
        $dbw->commit(__METHOD__);

        $this->moderator = User::newFromName("User 1");
        $this->moderatorButNotAutomoderated = User::newFromName("User 2");
        $this->automoderated = User::newFromName("User 3");
        $this->rollback = User::newFromName("User 4");
        $this->unprivilegedUser = User::newFromName("User 5");
        $this->unprivilegedUser2 = User::newFromName("User 6");
        $this->moderatorAndCheckuser = User::newFromName("User 7");
    }

    /**
     * Creates the test users via the proper MediaWiki functions (without $prepopulateDbCache).
     * Results are placed into $prepopulateDbCache. They are later used in prepopulateDb().
     */
    private function makePrepopulateDbCache()
    {
        $this->createTestUser("User 1", ["moderator", "automoderated"]);
        $this->createTestUser("User 2", ["moderator"]);
        $this->createTestUser("User 3", ["automoderated"]);
        $this->createTestUser("User 4", ["rollback"]);
        $this->createTestUser("User 5", []);
        $this->createTestUser("User 6", []);
        $this->createTestUser("User 7", ["moderator", "checkuser"]);

        $dbw = $this->getDB();

        self::$prepopulateDbCache = [];
        foreach (self::$prepopulateDbNeededTables as $table) {
            if (
                $table == "user" &&
                $dbw->getType() == "postgres" &&
                !$dbw->tableExists("user", __METHOD__)
            ) {
                $table = "mwuser";
            }

            self::$prepopulateDbCache[$table] = [];

            $keyField = $this->getKeyField($table);
            $options = $keyField ? ["ORDER BY" => $keyField] : [];

            $res = $dbw->select($table, "*", "", __METHOD__, $options);
            foreach ($res as $row) {
                $fields = get_object_vars($row);
                self::$prepopulateDbCache[$table][] = $fields;
            }

            // Truncate the table. prepopulateDb() will load it from cache.
            $this->truncateDbTable($table);
        }
    }

    /**
     * Utility function to check if CACHE_MEMCACHED actually works.
     * This is used to skip ReadOnly tests when memcached is unavailable
     * (because CACHE_DB doesn't work in ReadOnly mode).
     * @return bool
     */
    public function doesMemcachedWork()
    {
        // Obsolete: CliEngine now uses ModerationTestsuiteBagOStuff for CACHE_MEMCACHED.
        return true;
    }

    #
    # High-level test functions.
    #

    /** @var User */
    public $moderator;

    /** @var User */
    public $moderatorButNotAutomoderated;

    /** @var User */
    public $rollback;

    /** @var User */
    public $automoderated;

    /** @var User */
    public $unprivilegedUser;

    /** @var User */
    public $unprivilegedUser2;

    /** @var User */
    public $moderatorAndCheckuser;

    /**
     * @return User
     */
    public function loggedInAs()
    {
        return $this->engine->loggedInAs();
    }

    public function isModerator()
    {
        return in_array($this->loggedInAs()->getId(), [
            $this->moderator->getId(),
            $this->moderatorButNotAutomoderated->getId(),
            $this->moderatorAndCheckuser->getId(),
        ]);
    }

    /**
     * Instruct the testsuite that further requests should be sent on behalf of $user.
     * @param User $user
     */
    public function loginAs(User $user)
    {
        if ($user->getId() == $this->loggedInAs()->getId()) {
            return; /* Nothing to do, already logged in */
        }

        if ($user->isAnon()) {
            $this->logout();
            return;
        }

        $this->engine->loginAs($user);
    }

    /**
     * Instruct the testsuite that further requests should be sent on behalf of anonymous user.
     */
    public function logout()
    {
        $this->engine->logout();
    }

    /**
     * Create an account and return User object.
     * Note: Will not login automatically (loginAs must be called).
     * @param string $username
     * @return User|null
     */
    public function createAccount($username)
    {
        return $this->engine->createAccount($username);
    }

    /**
     * Perform a test move.
     * @param string $oldTitle
     * @param string $newTitle
     * @param string $reason
     * @param array $extraParams Bot-specific parameters.
     * @return ModerationTestsuiteNonApiBotResponse
     */
    public function doTestMove(
        $oldTitle,
        $newTitle,
        $reason = "",
        array $extraParams = [],
    ) {
        return $this->getBot("nonApi")->move(
            $oldTitle,
            $newTitle,
            $reason,
            $extraParams,
        );
    }

    /**
     * Place information about newly made change into lastEdit[] array.
     * @param string $title Full page name (including namespace).
     * @param string $summary Edit comment.
     * @param array $extraData
     */
    public function setLastEdit($title, $summary, array $extraData = [])
    {
        $this->lastEdit = $extraData + [
            "User" => $this->loggedInAs()->getName(),
            "Title" => $title,
            "Summary" => $summary,
        ];
    }

    /**
     * Create a new bot.
     * @param string $method One of the following: 'api', 'nonApi'.
     * @return ModerationTestsuiteBot
     */
    public function getBot($method)
    {
        return ModerationTestsuiteBot::factory($method, $this);
    }

    /**
     * Perform a test edit.
     * @param string|null $title
     * @param string|null $text
     * @param string|null $summary
     * @param string|int $section One of the following: section number, empty string or 'new'.
     * @param array $extraParams Bot-specific parameters.
     * @return ModerationTestsuiteNonApiBotResponse
     */
    public function doTestEdit(
        $title = null,
        $text = null,
        $summary = null,
        $section = "",
        $extraParams = [],
    ) {
        return $this->getBot("nonApi")->edit(
            $title,
            $text,
            $summary,
            $section,
            $extraParams,
        );
    }

    /**
     * @var int
     * See doNTestEditsWith()
     */
    public $TEST_EDITS_COUNT = 3;

    /**
     * Do 2*N alternated edits - N by $user1 and N by $user2.
     * Number of edits is $TEST_EDITS_COUNT.
     * If $user2 is null, only makes N edits by $user1.
     * @param User $user1
     * @param User|null $user2
     * @param string $prefix1
     * @param string $prefix2
     */
    public function doNTestEditsWith(
        $user1,
        $user2 = null,
        $prefix1 = "Page",
        $prefix2 = "AnotherPage",
    ) {
        for ($i = 0; $i < $this->TEST_EDITS_COUNT; $i++) {
            $this->loginAs($user1);
            $this->doTestEdit($prefix1 . $i);

            if ($user2) {
                $this->loginAs($user2);
                $this->doTestEdit($prefix2 . $i);
            }
        }
    }

    /**
     * Makes one edit and returns its correct entry.
     * @note Logs in as $moderator.
     * @param string|null $title
     * @return ModerationTestsuiteEntry
     */
    public function getSampleEntry($title = null)
    {
        $this->fetchSpecial();
        $this->loginAs($this->unprivilegedUser);
        $this->doTestEdit($title);
        $this->fetchSpecial();

        return $this->new_entries[0];
    }

    /**
     * Get random string to be appended to filenames, etc. to avoid filename conflicts.
     * @return string
     */
    public function uniqueSuffix()
    {
        return rand(0, 100000) . "T" . microtime(true);
    }

    /**
     * Perform a test upload.
     * @param string|null $title
     * @param string|null $srcFilename
     * @param string|null $text
     * @param array $extraParams Bot-specific parameters.
     * @return ModerationTestsuiteNonApiBotResponse
     */
    public function doTestUpload(
        $title = null,
        $srcFilename = null,
        $text = null,
        array $extraParams = [],
    ) {
        return $this->getBot("nonApi")->upload(
            $title,
            $srcFilename,
            $text,
            $extraParams,
        );
    }

    /**
     * Resolve $srcFilename into an absolute path.
     * Used in tests: '1.png' is found at [tests/resources/1.png].
     * @param string $srcFilename
     * @return string
     */
    public static function findSourceFilename($srcFilename)
    {
        if (!$srcFilename) {
            $srcFilename = "image100x100.png";
        }

        if (substr($srcFilename, 0, 1) != "/") {
            $srcFilename = __DIR__ . "/../../resources/" . $srcFilename;
        }

        return realpath($srcFilename);
    }

    /**
     * Get up to $count moderation log entries via API (most recent first).
     * @param int $count
     * @return array
     */
    public function apiLogEntries($count = 100)
    {
        $ret = $this->query([
            "action" => "query",
            "list" => "logevents",
            "letype" => "moderation",
            "lelimit" => $count,
        ]);
        return $ret["query"]["logevents"];
    }

    /**
     * Get up to $count moderation log entries NOT via API (most recent first).
     * @param int $count
     * @return array
     */
    public function nonApiLogEntries($count = 100)
    {
        $title = Title::newFromText(
            "Log/moderation",
            NS_SPECIAL,
        )->fixSpecialName();
        $url = wfAppendQuery($title->getLocalURL(), [
            "limit" => $count,
        ]);
        $html = $this->html->loadUrl($url);

        $events = [];
        $list_items = $html->getElementsByTagName("li");
        foreach ($list_items as $li) {
            $class = $li->getAttribute("class");
            if (strpos($class, "mw-logline-moderation") !== false) {
                $matches = null;
                if (
                    preg_match(
                        '/\(logentry-moderation-([^:]+): (.*)\)\s*$/',
                        $li->textContent,
                        $matches,
                    )
                ) {
                    $events[] = [
                        "type" => $matches[1],
                        "params" => explode(", ", $matches[2]),
                    ];
                }
            }
        }
        return $events;
    }

    /**
     * Get information about last revision of page $title.
     * @param string $title
     * @return array|false
     *
     * @phan-return array{revid:int,*:string,user:string,comment:string,timestamp:string}|false
     */
    public function getLastRevision($title)
    {
        $page = ModerationCompatTools::makeWikiPage(Title::newFromText($title));
        $rev = $page->getRevisionRecord();
        if (!$rev) {
            return false;
        }

        $username = $rev->getUser()->getName();
        $comment = $rev->getComment() ?? "";
        if ($comment instanceof CommentStoreComment) {
            $comment = $comment->text;
        }

        // Response in the same format as returned by api.php?action=query&prop=revisions.
        $result = [
            "*" => strval($page->getContent()->serialize()),
            "user" => $username,
            "revid" => $rev->getId(),
            "comment" => $comment,
            "timestamp" => wfTimestamp(TS_ISO_8601, $rev->getTimestamp()) ?: "",
        ];
        if ($rev->isMinor()) {
            $result["minor"] = "";
        }

        return $result;
    }

    /**
     * Remove "token=" from URL and return its new HTML title.
     * @param string $url
     * @return string|null
     */
    public function noTokenTitle($url)
    {
        $bad_url = preg_replace("/token=[^&]*/", "", $url);
        return $this->html->loadUrl($bad_url)->getTitle();
    }

    /**
     * Corrupt "token=" in URL and return its new HTML title.
     * @param string $url
     * @return string|null
     */
    public function badTokenTitle($url)
    {
        $bad_url = preg_replace("/(token=)([^&]*)/", '\1WRONG\2', $url);
        return $this->html->loadUrl($bad_url)->getTitle();
    }

    /**
     * Queue an edit that would cause an edit conflict when approved.
     * @param string $title
     * @param string $origText
     * @param string $textOfUser1
     * @param string $textOfUser2
     * @return ModerationTestsuiteEntry
     */
    public function causeEditConflict(
        $title,
        $origText,
        $textOfUser1,
        $textOfUser2,
    ) {
        $this->loginAs($this->automoderated);
        $this->doTestEdit($title, $origText);

        $this->loginAs($this->unprivilegedUser);
        $this->doTestEdit($title, $textOfUser1);

        $this->loginAs($this->automoderated);
        $this->doTestEdit($title, $textOfUser2);

        $this->fetchSpecial();
        return $this->new_entries[0];
    }

    /**
     * Get cuc_agent of the last entry in "cu_changes" table.
     * @return string|null User-agent.
     */
    public function getCUCAgent()
    {
        $agents = $this->getCUCAgents(1);
        return array_pop($agents);
    }

    /**
     * Get cuc_agent of the last entries in "cu_changes" table.
     * @param int $limit How many entries to select.
     * @return string[] List of user-agents.
     */
    public function getCUCAgents($limit)
    {
        $dbw = $this->getDB();
        return $dbw->selectFieldValues(
            "cu_changes",
            "cuc_agent",
            "",
            __METHOD__,
            [
                "ORDER BY" => "cuc_id DESC",
                "LIMIT" => $limit,
            ],
        );
    }

    /**
     * Get cule_agent of the last entries in "cu_log_event" table.
     * @param int $limit How many entries to select.
     * @return string[] List of user-agents.
     */
    public function getCULEAgents($limit)
    {
        $dbw = $this->getDB();
        return $dbw->selectFieldValues(
            "cu_log_event",
            "cule_agent",
            "",
            __METHOD__,
            [
                "ORDER BY" => "cule_id DESC",
                "LIMIT" => $limit,
            ],
        );
    }

    /**
     * Create AbuseFilter rule that will assign tags to all edits.
     * @param string[] $tags
     * @return int ID of the newly created filter.
     */
    public function addTagAllAbuseFilter(array $tags)
    {
        $user = User::newSystemUser("MediaWiki default");
        $dbw = $this->getDB();

        $fields = [
            "af_pattern" => "1",
            "af_timestamp" => $dbw->timestamp(),
            "af_enabled" => 1,
            "af_comments" => "",
            "af_public_comments" => "Assign tags to all edits",
            "af_hidden" => 0,
            "af_hit_count" => 0,
            "af_throttled" => 0,
            "af_deleted" => 0,
            "af_actions" => "tag",
            "af_global" => 0,
            "af_group" => "default",
            "af_actor" => $user->getActorId(),
        ];

        $dbw->insert("abuse_filter", $fields, __METHOD__);
        $filterId = $dbw->insertId();

        $dbw->insert(
            "abuse_filter_action",
            [
                "afa_filter" => $filterId,
                "afa_consequence" => "tag",
                "afa_parameters" => implode("\n", $tags),
            ],
            __METHOD__,
        );

        return $filterId;
    }

    /**
     * Disable AbuseFilter rule #$filterId.
     * @param int $filterId
     */
    public function disableAbuseFilter($filterId)
    {
        $dbw = $this->getDB();
        $dbw->update(
            "abuse_filter",
            ["af_enabled" => 0],
            ["af_id" => $filterId],
            __METHOD__,
        );
    }

    /**
     * Assert that API response $ret contains error $expectedErrorCode.
     * @param string $expectedErrorCode
     * @param array $ret
     * @param MediaWikiIntegrationTestCase $tcase
     */
    public function assertApiError(
        $expectedErrorCode,
        array $ret,
        MediaWikiIntegrationTestCase $tcase,
    ) {
        $tcase->assertArrayHasKey("error", $ret);
        $tcase->assertSame($expectedErrorCode, $ret["error"]["code"]);
    }

    /**
     * Call version_compare on MW_VERSION.
     * @param string $compareWith
     * @param string $operator Comparison operator, e.g. '<' or '>='.
     * @return bool
     */
    public static function mwVersionCompare($compareWith, $operator)
    {
        return version_compare(MW_VERSION, $compareWith, $operator);
    }

    /**
     * Sleep before the reupload, so that it wouldn't fail due to archive name collision.
     *
     * Archived image names are based on time (up to the second), so if two uploads happen
     * within the same second, only the first would succeed.
     */
    public function sleepUntilNextSecond()
    {
        usleep(1000 * 1000 - gettimeofday()["usec"]);
    }

    /**
     * Apply ModerationBlock to $user.
     * @param User $user
     */
    public function modblock(User $user)
    {
        $dbw = $this->getDB();
        $dbw->insert(
            "moderation_block",
            [
                "mb_address" => $user->getName(),
                "mb_user" => $user->getId(),
                "mb_by" => 0,
                "mb_by_text" => "Some moderator",
                "mb_timestamp" => $dbw->timestamp(),
            ],
            __METHOD__,
        );
    }
}
