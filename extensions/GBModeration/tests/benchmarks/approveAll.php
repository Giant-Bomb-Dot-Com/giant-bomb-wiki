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
 * Benchmark: how fast is ApproveAll on Special:Moderation?
 *
 * Usage:
 *	php maintenance/run.php `pwd`/extensions/Moderation/tests/benchmarks/approveAll.php
 */

namespace MediaWiki\Moderation\Tests;

use DeferredUpdates;
use MediaWiki\Moderation\ModerationCompatTools;
use User;
use Wikimedia\Assert\Assert;
use Wikimedia\IPUtils;

require_once __DIR__ . "/ModerationBenchmark.php";

class BenchmarkApproveAll extends ModerationBenchmark
{
    /**
     * @var int[]
     * mod_id of rows where ApproveAll should be applied
     */
    public $ids = [];

    /**
     * Default number of loops.
     * @return int
     */
    public function getDefaultLoops()
    {
        return 10;
    }

    /**
     * How many rows to approve with one approveall.
     * @return int
     */
    public function getEditsPerUser()
    {
        return 10;
    }

    /**
     * @param int $numberOfUsers
     */
    public function beforeBenchmark($numberOfUsers)
    {
        /* Prepopulate 'moderation' table */
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $editsPerUser = $this->getEditsPerUser();

        for ($i = 0; $i < $numberOfUsers; $i++) {
            $fakeIP = IPUtils::formatHex(base_convert("$i", 10, 16));
            $user = User::newFromName($fakeIP, false);

            $dbw->delete(
                "moderation",
                ["mod_user_text" => $fakeIP],
                __METHOD__,
            );

            $modid = 0;
            for ($j = 0; $j < $editsPerUser; $j++) {
                $modid = $this->fastQueue(
                    $this->getTestTitle($i + $j * $numberOfUsers),
                    "Whatever",
                    "",
                    $user,
                );
            }

            $this->ids[$i] = $modid; /* Only one mod_id per User */
        }

        $this->becomeModerator();
    }

    /**
     * @param int $i
     */
    public function doActualWork($i)
    {
        // Prevent DeferredUpdates::tryOpportunisticExecute() from running updates immediately
        // @phan-suppress-next-line PhanUnusedVariable
        $cleanup = DeferredUpdates::preventOpportunisticUpdates();

        $html = $this->runSpecialModeration([
            "modaction" => "approveall",
            "modid" => $this->ids[$i],
            "token" => $this->getUser()->getEditToken(),
        ]);

        // Run the DeferredUpdates
        DeferredUpdates::doUpdates();

        Assert::postcondition(
            strpos(
                $html,
                "(moderation-approved-ok: " . $this->getEditsPerUser() . ")",
            ) !== false,
            "ApproveAll failed",
        );
    }
}

$maintClass = BenchmarkApproveAll::class;
require RUN_MAINTENANCE_IF_MAIN;
