<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2025 Edward Chernenko.

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
 * Consequence that tags one revision with "moderation-merged" tag.
 */

namespace MediaWiki\Moderation;

use DeferredUpdates;
use MediaWiki\MediaWikiServices;

class TagRevisionAsMergedConsequence implements IConsequence
{
    /** @var int */
    protected $revid;

    /**
     * @param int $revid
     */
    public function __construct($revid)
    {
        $this->revid = $revid;
    }

    /**
     * Execute the consequence.
     */
    public function run()
    {
        DeferredUpdates::addCallableUpdate(function () {
            $changeTagsStore = MediaWikiServices::getInstance()->getChangeTagsStore();
            $changeTagsStore->addTags(
                "moderation-merged",
                null,
                $this->revid,
                null,
            );
        });
    }
}
