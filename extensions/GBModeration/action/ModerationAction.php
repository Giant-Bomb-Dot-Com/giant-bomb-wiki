<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2014-2024 Edward Chernenko.

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
 * Parent class for all moderation actions.
 */

namespace MediaWiki\Moderation;

use ContextSource;
use IContextSource;
use Language;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRenderer;
use MediaWiki\Title\Title;
use OutputPage;
use Profiler;
use ReadOnlyError;
use ReadOnlyMode;
use RepoGroup;
use SpecialPage;
use User;
use Xml;

abstract class ModerationAction extends ContextSource
{
    /**
     * @var int Value of modid= request parameter.
     */
    protected $id;

    /**
     * @var string Name of modaction, e.g. "reject" or "approveall".
     */
    public $actionName;

    /**
     * @var User
     * Moderator who is enacting this action.
     */
    public $moderator;

    /**
     * @var LinkTarget[]
     * Titles of pages to be used for "Return to Page" links after the action is completed.
     */
    protected $returnTitles = [];

    /** @var EntryFactory */
    protected $entryFactory;

    /** @var IConsequenceManager */
    protected $consequenceManager;

    /** @var ModerationCanSkip */
    protected $canSkip;

    /** @var EditFormOptions */
    protected $editFormOptions;

    /** @var ActionLinkRenderer */
    protected $actionLinkRenderer;

    /** @var RepoGroup */
    protected $repoGroup;

    /** @var Language */
    protected $contentLanguage;

    /** @var RevisionRenderer */
    protected $revisionRenderer;

    /** @var ReadOnlyMode */
    protected $readOnlyMode;

    /**
     * Regular constructor with no "detect class from modaction=" logic. Use factory() instead.
     * @param IContextSource $context
     * @param EntryFactory $entryFactory
     * @param IConsequenceManager $consequenceManager
     * @param ModerationCanSkip $canSkip
     * @param EditFormOptions $editFormOptions
     * @param ActionLinkRenderer $actionLinkRenderer
     * @param RepoGroup $repoGroup
     * @param Language $contentLanguage
     * @param RevisionRenderer $revisionRenderer
     * @param ReadOnlyMode $readOnlyMode
     */
    public function __construct(
        IContextSource $context,
        EntryFactory $entryFactory,
        IConsequenceManager $consequenceManager,
        ModerationCanSkip $canSkip,
        EditFormOptions $editFormOptions,
        ActionLinkRenderer $actionLinkRenderer,
        RepoGroup $repoGroup,
        Language $contentLanguage,
        RevisionRenderer $revisionRenderer,
        ReadOnlyMode $readOnlyMode,
    ) {
        $this->setContext($context);

        $this->entryFactory = $entryFactory;
        $this->consequenceManager = $consequenceManager;
        $this->canSkip = $canSkip;
        $this->editFormOptions = $editFormOptions;
        $this->actionLinkRenderer = $actionLinkRenderer;
        $this->repoGroup = $repoGroup;
        $this->contentLanguage = $contentLanguage;
        $this->revisionRenderer = $revisionRenderer;
        $this->readOnlyMode = $readOnlyMode;

        $this->moderator = $this->getUser();

        $request = $this->getRequest();
        $this->actionName = $request->getVal("modaction");
        $this->id = $request->getInt("modid");
    }

    /**
     * @return array Action-specific API-friendly response, e.g. [ 'rejected' => '3' ].
     *
     * @phan-return array<string,mixed>
     */
    final public function run()
    {
        if ($this->requiresWrite()) {
            if ($this->readOnlyMode->isReadOnly()) {
                throw new ReadOnlyError();
            }

            /* Suppress default assertion from $wgTrxProfilerLimits
             ("no non-readonly SQL queries during GET request") */
            $trxProfiler = Profiler::instance()->getTransactionProfiler();
            $trxLimits = $this->getConfig()->get("TrxProfilerLimits");

            $trxProfiler->resetExpectations();
            $trxProfiler->setExpectations($trxLimits["POST"], __METHOD__);
        }

        return $this->execute();
    }

    /* The following methods can be overridden in the subclass */

    /**
     * Whether the URL of this action must contain CSRF token
     * @return bool
     */
    public function requiresEditToken()
    {
        return true;
    }

    /**
     * Whether this action requires the wiki not to be locked
     * @return bool
     */
    public function requiresWrite()
    {
        return true;
    }

    /**
     * Function called when the action is invoked.
     * @return array Array containing API response.
     * @throws ModerationError
     *
     * @phan-return array<string,mixed>
     */
    abstract public function execute();

    /**
     * Print the result of execute() in a human-readable way.
     * @param array $result Value returned by execute().
     * @param OutputPage $out OutputPage object.
     *
     * @phan-param array<string,mixed> $result
     */
    abstract public function outputResult(array $result, OutputPage $out);

    /**
     * Utility function. Get userpage of user who made this edit.
     * @return Title|false
     */
    protected function getUserpageOfPerformer()
    {
        $row = $this->entryFactory->loadRow($this->id, [
            "mod_user_text AS user_text",
        ]);
        return $row ? Title::makeTitle(NS_USER, $row->user_text) : false;
    }

    /**
     * Print "Return to Page" links to $out.
     * @param OutputPage $out
     */
    public function printReturnLinks(OutputPage $out): void
    {
        $linkRenderer = MediaWikiServices::getInstance()
            ->getLinkRendererFactory()
            ->create();
        $specialTitle = SpecialPage::getTitleFor("Moderation");
        $attr = ["id" => "mw-returnto"];

        foreach (array_merge([$specialTitle], $this->returnTitles) as $title) {
            // Can't use OutputPage::addReturnTo(), because it would create several elements
            // with the same id="mw-returnto", which wouldn't be valid HTML.
            $link = $out
                ->msg("returnto")
                ->rawParams($linkRenderer->makeKnownLink($title))
                ->escaped();
            $out->addHTML(Xml::tags("p", $attr, $link) . "\n");

            $attr = ["class" => "mw-returnto-extra"];
        }
    }

    /**
     * Add page to be used for "Return to Page" link.
     * @param LinkTarget $title
     */
    public function addReturnTitle(LinkTarget $title)
    {
        $this->returnTitles[] = $title;
    }
}
