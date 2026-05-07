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
 * Benchmark: how fast is HTML of Special:Moderation generated?
 *
 * Usage:
 *	php maintenance/run.php `pwd`/extensions/Moderation/tests/benchmarks/renderSpecial.php
 */

namespace MediaWiki\Moderation\Tests;

require_once __DIR__ . "/ModerationBenchmark.php";

class BenchmarkRenderSpecial extends ModerationBenchmark
{
    /**
     * Default number of loops.
     * @return int
     */
    public function getDefaultLoops()
    {
        return 50;
    }

    /**
     * How many rows to show on Special:Moderation.
     * @return int
     */
    public function getNumberOfEntries()
    {
        return 200;
    }

    /**
     * @param int $numberOfLoops @phan-unused-param
     */
    public function beforeBenchmark($numberOfLoops)
    {
        /* Prepopulate 'moderation' table */
        // phpcs:disable Generic.CodeAnalysis.ForLoopWithTestFunctionCall.NotAllowed
        for ($i = 0; $i <= $this->getNumberOfEntries(); $i++) {
            $this->fastQueue($this->getTestTitle($i));
        }
        // phpcs:enable

        $this->becomeModerator();
    }

    /**
     * @param int $i @phan-unused-param
     */
    public function doActualWork($i)
    {
        $this->runSpecialModeration([
            "limit" => $this->getNumberOfEntries(),
        ]);
    }
}

$maintClass = BenchmarkRenderSpecial::class;
require RUN_MAINTENANCE_IF_MAIN;
