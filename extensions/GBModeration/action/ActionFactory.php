<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2022 Edward Chernenko.

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
 * Factory that can construct ModerationAction from Context.
 */

namespace MediaWiki\Moderation;

use IContextSource;
use Language;
use MediaWiki\Revision\RevisionRenderer;
use ReadOnlyMode;
use RepoGroup;

class ActionFactory
{
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
        $this->entryFactory = $entryFactory;
        $this->consequenceManager = $consequenceManager;
        $this->canSkip = $canSkip;
        $this->editFormOptions = $editFormOptions;
        $this->actionLinkRenderer = $actionLinkRenderer;
        $this->repoGroup = $repoGroup;
        $this->contentLanguage = $contentLanguage;
        $this->revisionRenderer = $revisionRenderer;
        $this->readOnlyMode = $readOnlyMode;
    }

    /**
     * List of all known modactions and their PHP classes.
     * @var array
     *
     * @phan-var array<string,class-string>
     */
    protected $knownActions = [
        "approveall" => ModerationActionApprove::class,
        "approve" => ModerationActionApprove::class,
        "block" => ModerationActionBlock::class,
        "editchange" => ModerationActionEditChange::class,
        "editchangesubmit" => ModerationActionEditChangeSubmit::class,
        "merge" => ModerationActionMerge::class,
        "preview" => ModerationActionPreview::class,
        "rejectall" => ModerationActionReject::class,
        "reject" => ModerationActionReject::class,
        "show" => ModerationActionShow::class,
        "showimg" => ModerationActionShowImage::class,
        "unblock" => ModerationActionBlock::class,
    ];

    /**
     * Construct new ModerationAction.
     * @param IContextSource $context
     * @return ModerationAction
     * @throws ModerationError
     */
    public function makeAction(IContextSource $context)
    {
        $action = $context->getRequest()->getVal("modaction");
        $class = $this->knownActions[$action] ?? null;

        if (!$class) {
            throw new ModerationError("moderation-unknown-modaction");
        }

        return new $class(
            $context,
            $this->entryFactory,
            $this->consequenceManager,
            $this->canSkip,
            $this->editFormOptions,
            $this->actionLinkRenderer,
            $this->repoGroup,
            $this->contentLanguage,
            $this->revisionRenderer,
            $this->readOnlyMode,
        );
    }
}
