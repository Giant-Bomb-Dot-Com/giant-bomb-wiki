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
 * Benchmark: how fast are moves queued for moderation?
 *
 * Usage:
 *	php maintenance/run.php `pwd`/extensions/Moderation/tests/benchmarks/queueMove.php
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\MediaWikiServices;
use MediaWiki\Page\MovePageFactory;
use MediaWiki\Title\Title;
use Wikimedia\Assert\Assert;

require_once __DIR__ . "/ModerationBenchmark.php";

class BenchmarkQueueMove extends ModerationBenchmark
{
    /** @var MovePageFactory */
    private $movePageFactory;

    /**
     * @param int $i
     * @return Title
     */
    public function getOldTitle($i)
    {
        return $this->getTestTitle("Old title " . $i);
    }

    /**
     * @param int $i
     * @return Title
     */
    public function getNewTitle($i)
    {
        return $this->getTestTitle("New title " . $i);
    }

    /**
     * @param int $numberOfLoops
     */
    public function beforeBenchmark($numberOfLoops)
    {
        $this->movePageFactory = MediaWikiServices::getInstance()->getMovePageFactory();

        /* Create $numberOfLoops pages to be moved */
        for ($i = 0; $i <= $numberOfLoops; $i++) {
            $this->fastEdit($this->getOldTitle($i));
        }
    }

    /**
     * @param int $i
     */
    public function doActualWork($i)
    {
        $mp = $this->movePageFactory->newMovePage(
            $this->getOldTitle($i),
            $this->getNewTitle($i),
        );
        $status = $mp->move($this->getUser(), "Reason for moving #" . $i);

        Assert::postcondition(
            $status->getMessage()->getKey() == "moderation-move-queued",
            "Move not queued",
        );
    }
}

$maintClass = BenchmarkQueueMove::class;
require RUN_MAINTENANCE_IF_MAIN;
