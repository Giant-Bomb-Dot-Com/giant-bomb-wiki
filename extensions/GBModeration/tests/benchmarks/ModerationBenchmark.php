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
 * Parent class for benchmark scripts.
 */

namespace MediaWiki\Moderation\Tests;

use CommentStoreComment;
use ContentHandler;
use Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Moderation\ModerationCompatTools;
use MediaWiki\Moderation\ModerationNewChange;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use Status;
use User;

require_once __DIR__ . "/../common/ModerationTestUtil.php";

abstract class ModerationBenchmark extends Maintenance
{
    /**
     * @var string
     * Prefix that is always prepended to all titles, etc.
     * 	This ensures that two benchmarks won't work with the same pages,
     *	causing errors like "benchmark #1 was creating a new page,
     *	benchmark #2 was editing existing page,
     *	leading to performance of #1 and #2 being different".
     */
    private $uniquePrefix = null;

    /**
     * @var User
     * User who is always created before the benchmark.
     */
    private $testUser = null;

    /**
     * @return User object of test account.
     */
    public function getUser()
    {
        return $this->testUser;
    }

    /**
     * Returns Title object for testing.
     * @param string|int $suffix Full text of the title, e.g. "Talk:Welsh corgi".
     * @return Title
     *
     * During this benchmark, same value is returned for same $suffix,
     * but another benchmark will get a different Title.
     */
    public function getTestTitle($suffix = "1")
    {
        $nonprefixedTitle = Title::newFromText($suffix);
        return Title::makeTitle(
            $nonprefixedTitle->getNamespace(),
            $this->uniquePrefix . $nonprefixedTitle->getText(),
        );
    }

    /**
     * This function will be benchmarked by execute().
     * @param int $iterationNumber
     */
    abstract public function doActualWork($iterationNumber);

    /**
     * Initialize everything before the tests. Called once.
     * @param int $numberOfLoops @phan-unused-param
     */
    public function beforeBenchmark($numberOfLoops)
    {
        /* Nothing to do.
			Will be redefined by benchmarks if they need this. */
    }

    /**
     * Same as beforeBenchmark, but is called getDefaultLoops() times.
     * @param int $iterationNumber Number of the loop (integer starting with 0). @phan-unused-param
     */
    public function beforeBenchmarkPrepareLoop($iterationNumber)
    {
        /* Nothing to do.
			Will be redefined by benchmarks if they need this. */
    }

    /**
     * Default number of loops.
     * @return int
     */
    public function getDefaultLoops()
    {
        return 500;
    }

    /**
     * Main function: test the performance of doActualWork().
     */
    public function execute()
    {
        $services = MediaWikiServices::getInstance();
        $services->getActorStore()->setAllowCreateIpActors(true);

        $user = User::newSystemUser("Benchmark User", [
            "steal" => true,
        ]);

        $userGroupManager = $services->getUserGroupManager();
        foreach ($userGroupManager->getUserGroups($user) as $existingGroup) {
            $userGroupManager->removeUserFromGroup($user, $existingGroup);
        }

        $this->uniquePrefix = wfTimestampNow() . "_" . rand() . "_";
        $this->testUser = $user;

        /* Prepare the initial conditions */
        $loops = $this->getDefaultLoops();

        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $dbw->startAtomic(__METHOD__);

        $this->beforeBenchmark($loops);
        for ($i = 0; $i <= $loops; $i++) {
            $this->beforeBenchmarkPrepareLoop($i);
        }

        $dbw->endAtomic(__METHOD__);

        $loopsInPercent = ceil($loops / 100);

        /* Run doActualWork() several times */
        $startTime = microtime(true);
        for ($i = 0; $i < $loops; $i++) {
            if ($i % $loopsInPercent == 0) {
                printf("\r%.1f%%", (100 * $i) / $loops);
            }

            $this->doActualWork($i);
        }
        $endTime = microtime(true);

        /* Performance report */
        printf(
            "\r%s: did %d iterations in %.3f seconds\n",
            get_class($this),
            $loops,
            $endTime - $startTime,
        );
    }

    /**
     * Edit the page (convenience function to be used by benchmarks).
     * @param Title $title
     * @param string $newText
     * @param string $summary
     * @param User|null $user
     * @return Status object.
     */
    public function edit(
        Title $title,
        $newText = "Whatever",
        $summary = "",
        User $user = null,
    ) {
        $page = ModerationCompatTools::makeWikiPage($title);
        $content = ContentHandler::makeContent(
            $newText,
            null,
            CONTENT_MODEL_WIKITEXT,
        );
        $summary = CommentStoreComment::newUnsavedComment($summary);

        if (!$user) {
            $user = User::newFromName("127.0.0.1", false);
        }

        $updater = $page->newPageUpdater($user);
        $updater->setContent(SlotRecord::MAIN, $content);
        $updater->saveRevision($summary, EDIT_INTERNAL);

        return $updater->getStatus();
    }

    /**
     * Edit the page by directly modifying the database. Very fast.
     *
     * This is used for initialization of tests.
     * For example, if moveQueue benchmark needs 500 existing pages,
     * it would take forever for doEditContent() to create them all,
     * much longer than the actual benchmark.
     *
     * @param Title $title
     * @param string $newText
     * @param string $summary
     * @param User|null $user
     */
    public function fastEdit(
        Title $title,
        $newText = "Whatever",
        $summary = "",
        User $user = null,
    ) {
        ModerationTestUtil::fastEdit($title, $newText, $summary, $user);
    }

    /**
     * Queue the page by directly modifying the database. Very fast.
     * This is used for initialization of tests.
     * @param Title $title
     * @param string $newText
     * @param string $summary
     * @param User|null $user
     * @return int mod_id of the newly inserted row.
     */
    public function fastQueue(
        Title $title,
        $newText = "Whatever",
        $summary = "",
        User $user = null,
    ) {
        $page = ModerationCompatTools::makeWikiPage($title);
        if (!$user) {
            $user = User::newFromName("127.0.0.1", false);
        }

        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $dbw->replace(
            "moderation",
            [
                [
                    "mod_preloadable",
                    "mod_type",
                    "mod_namespace",
                    "mod_title",
                    "mod_preload_id",
                ],
            ],
            [
                "mod_timestamp" => $dbw->timestamp(),
                "mod_user" => $user->getId(),
                "mod_user_text" => $user->getName(),
                "mod_namespace" => $title->getNamespace(),
                "mod_cur_id" => $page->getId(),
                "mod_title" => $title->getDBKey(),
                "mod_comment" => $summary,
                "mod_last_oldid" => $page->getLatest(),
                "mod_preload_id" =>
                    $this->uniquePrefix . "-fake-" . $user->getName(), # Fake
                "mod_text" => $newText, # No preSaveTransform or serialization
                "mod_type" => ModerationNewChange::MOD_TYPE_EDIT,
                "mod_preloadable" => 0,
                "mod_ip" => "127.0.0.1",
            ],
            __METHOD__,
        );

        return $dbw->insertId();
    }

    /**
     * Add current test user to "moderator" group.
     */
    public function becomeModerator()
    {
        $userGroupManager = MediaWikiServices::getInstance()->getUserGroupManager();
        $userGroupManager->addUserToGroup($this->getUser(), "moderator");
    }

    /**
     * Render Special:Moderation with $params.
     * @param array $params
     * @param bool $wasPosted
     * @return string HTML of the result.
     */
    public function runSpecialModeration(array $params, $wasPosted = false)
    {
        return ModerationTestUtil::runSpecialModeration(
            $this->getUser(),
            $params,
            $wasPosted,
        );
    }
}
