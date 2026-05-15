<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2021 Edward Chernenko.

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
 * Keeps track of things like section= parameter in EditForm, wpMergeID and "Watch this" checkbox.
 */

namespace MediaWiki\Moderation;

use MediaWiki\EditPage\EditPage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ReflectionProperty;
use RequestContext;
use SpecialPage;
use User;

class EditFormOptions
{
    /**
     * @var int|null
     * mod_id of the pending edit which is currently being merged (during modaction=merge)
     */
    protected $newMergeID = null;

    /** @var int|string Number of edited section, if any (populated in onEditFilter) */
    protected $section = "";

    /** @var string Text of edited section, if any (populated in onEditFilter) */
    protected $sectionText = "";

    /**
     * @var bool|null
     * Value of "Watch this page" checkbox, if any.
     * If true, pages passed to watchIfNeeded() will be Watched, if false, Unwatched.
     * If null, then neither Watching nor Unwatching is necessary.
     */
    protected $watchthis = null;

    /** @var IConsequenceManager */
    protected $consequenceManager;

    /**
     * @param IConsequenceManager $consequenceManager
     */
    public function __construct(IConsequenceManager $consequenceManager)
    {
        $this->consequenceManager = $consequenceManager;
    }

    /**
     * Used in extension.json to obtain this service as HookHandler.
     * @return EditFormOptions
     */
    public static function hookHandlerFactory()
    {
        return MediaWikiServices::getInstance()->getService(
            "Moderation.EditFormOptions",
        );
    }

    /**
     * EditFilter hook handler.
     * Save sections-related information, which will then be used in onMultiContentSave.
     * @param EditPage $editor
     * @param string $text
     * @param string $section
     * @param string &$error @phan-unused-param
     * @param string $summary @phan-unused-param
     * @return bool|void
     */
    public function onEditFilter(
        EditPage $editor,
        $text,
        $section,
        &$error,
        $summary,
    ) {
        if ($section !== "") {
            $this->section = $section;
            $this->sectionText = $text;
        }

        // HACK: as much as I dislike using Reflection in production code,
        // the only alternative is to copy a lot of code that calculates EditPage::$watchlist
        // from MediaWiki core, since none of this logic is accessible via public methods.
        // We might still have to do so in the future versions.
        $reflection = new ReflectionProperty($editor, "watchthis");
        $reflection->setAccessible(true);
        $watchthis = $reflection->getValue($editor);

        $this->watchthis = (bool) $watchthis;
    }

    /**
     * Detect "watch this" checkboxes on Special:Movepage and Special:Upload.
     * @param SpecialPage $special
     * @param string $subPage @phan-unused-param
     * @return bool|void
     */
    public function onSpecialPageBeforeExecute(SpecialPage $special, $subPage)
    {
        $title = $special->getPageTitle();
        $request = $special->getRequest();

        if ($title->isSpecial("Movepage")) {
            $this->watchthis = $request->getCheck("wpWatch");
        } elseif ($title->isSpecial("Upload")) {
            $this->watchthis = $request->getBool("wpWatchthis");
        }
    }

    /**
     * @param int $modid
     */
    public function setMergeID($modid)
    {
        $this->newMergeID = $modid;
    }

    /**
     * @return int
     */
    public function getMergeID()
    {
        if ($this->newMergeID) {
            return $this->newMergeID;
        }

        return RequestContext::getMain()->getRequest()->getInt("wpMergeID", 0);
    }

    /**
     * @return string
     */
    public function getSection()
    {
        return $this->section;
    }

    /**
     * @return string
     */
    public function getSectionText()
    {
        return $this->sectionText;
    }

    /**
     * Watch or Unwatch the pages depending on the current value of $watchthis.
     * @param User $user
     * @param Title[] $titles
     */
    public function watchIfNeeded(User $user, array $titles)
    {
        if ($this->watchthis === null) {
            // Neither Watch nor Unwatch were requested.
            return;
        }

        foreach ($titles as $title) {
            $this->consequenceManager->add(
                new WatchOrUnwatchConsequence($this->watchthis, $title, $user),
            );
        }
    }
}
