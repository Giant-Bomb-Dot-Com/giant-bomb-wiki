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
 * Benchmark: how fast are edits queued for moderation?
 *
 * Usage:
 *	php maintenance/run.php `pwd`/extensions/Moderation/tests/benchmarks/queueEdit.php
 */

namespace MediaWiki\Moderation\Tests;

use Wikimedia\Assert\Assert;

require_once __DIR__ . "/ModerationBenchmark.php";

class BenchmarkQueueEdit extends ModerationBenchmark
{
    /**
     * @param int $i
     */
    public function doActualWork($i)
    {
        $status = $this->edit(
            $this->getTestTitle("Test page " . $i),
            "Test content " . $i,
            "Test summary " . $i,
        );

        Assert::postcondition(
            $status->getMessage()->getKey() == "moderation-edit-queued",
            "Edit not queued",
        );
    }
}

$maintClass = BenchmarkQueueEdit::class;
require RUN_MAINTENANCE_IF_MAIN;
