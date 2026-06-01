<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2024 Edward Chernenko.

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
 * Consequence that either adds a page to the user's watchlist or removes it from the watchlist.
 */

namespace MediaWiki\Moderation;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use User;

class WatchOrUnwatchConsequence implements IConsequence
{
    /** @var bool */
    protected $watch;

    /** @var Title */
    protected $title;

    /** @var User */
    protected $user;

    /**
     * @param bool $watch If true, page will be watched. If false, page will be unwatched.
     * @param Title $title
     * @param User $user
     */
    public function __construct($watch, Title $title, User $user)
    {
        $this->watch = $watch;
        $this->title = $title;
        $this->user = $user;
    }

    /**
     * Execute the consequence.
     */
    public function run()
    {
        $watchlistManager = MediaWikiServices::getInstance()->getWatchlistManager();
        $watchlistManager->setWatch(
            $this->watch,
            $this->user,
            $this->title,
            null,
        );
    }
}
