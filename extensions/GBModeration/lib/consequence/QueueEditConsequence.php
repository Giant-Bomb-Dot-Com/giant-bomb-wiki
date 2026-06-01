<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020 Edward Chernenko.

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
 * Consequence that writes new edit into the moderation queue.
 */

namespace MediaWiki\Moderation;

use Content;
use MediaWiki\MediaWikiServices;
use User;
use WikiPage;

class QueueEditConsequence implements IConsequence
{
    /** @var WikiPage */
    protected $page;

    /** @var User */
    protected $user;

    /** @var Content */
    protected $content;

    /** @var string */
    protected $summary;

    /** @var string */
    protected $section;

    /** @var string */
    protected $sectionText;

    /** @var bool */
    protected $isBot;

    /** @var bool */
    protected $isMinor;

    /**
     * @param WikiPage $page
     * @param User $user
     * @param Content $content
     * @param string $summary
     * @param string $section
     * @param string $sectionText
     * @param bool $isBot
     * @param bool $isMinor
     */
    public function __construct(
        WikiPage $page,
        User $user,
        Content $content,
        $summary,
        $section,
        $sectionText,
        $isBot,
        $isMinor,
    ) {
        $this->page = $page;
        $this->user = $user;
        $this->content = $content;
        $this->summary = $summary;
        $this->section = $section;
        $this->sectionText = $sectionText;
        $this->isBot = $isBot;
        $this->isMinor = $isMinor;
    }

    /**
     * Execute the consequence.
     * @return int mod_id of affected row.
     */
    public function run()
    {
        $factory = MediaWikiServices::getInstance()->getService(
            "Moderation.NewChangeFactory",
        );
        $change = $factory->makeNewChange($this->page->getTitle(), $this->user);
        return $change
            ->edit(
                $this->page,
                $this->content,
                $this->section,
                $this->sectionText,
            )
            ->setBot($this->isBot)
            ->setMinor($this->isMinor)
            ->setSummary($this->summary)
            ->queue();
    }
}
