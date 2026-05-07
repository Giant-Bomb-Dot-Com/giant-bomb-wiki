<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2018-2020 Edward Chernenko.

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
 * ApiMain subclass for testing API requests via CliEngine.
 */

namespace MediaWiki\Moderation\Tests;

use ApiMain;

class ModerationTestsuiteCliApiMain extends ApiMain
{
    public function execute()
    {
        $this->executeActionWithErrorHandling(); /* Run the API. Automatically prints errors */

        if (!$this->getResult()->getResultData("error")) {
            /* Not an error: print the result */
            $this->setupExternalResponse(
                $this->getModule(),
                $this->extractRequestParams(),
            );
            $this->printResult(0);
        }
    }
}
