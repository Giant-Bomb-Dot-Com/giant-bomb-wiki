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
 * Implements [[Special:Moderation]].
 */

namespace MediaWiki\Moderation;

use ErrorPageError;
use MediaWiki\Cache\LinkBatchFactory;
use QueryPage;
use Skin;
use stdClass;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IResultWrapper;
use Xml;

class SpecialModeration extends QueryPage
{
    /**
     * @var string
     * Currently selected folder (when viewing the moderation table)
     */
    public $folder;

    /**
     * @var array
     * Maps folder names to their SQL filtering conditions for Database::select().
     *
     * @phan-var array<string,array>
     */
    public $folders_list = [
        "pending" => [
            # Not yet moderated
            "mod_rejected" => 0,
            "mod_merged_revid" => 0,
        ],
        "rejected" => [
            # Rejected by the moderator
            "mod_rejected" => 1,
            "mod_rejected_auto" => 0,
            "mod_merged_revid" => 0,
        ],
        "merged" => [
            # Manually merged (after the edit conflict on approval attempt)
            "mod_merged_revid <> 0",
        ],
        "spam" => [
            # Rejected automatically
            "mod_rejected_auto" => 1,
        ],
    ];

    /**
     * @var string
     * Name of default folder.
     */
    public $default_folder = "pending";

    /** @var ActionFactory */
    protected $actionFactory;

    /** @var EntryFactory */
    protected $entryFactory;

    /** @var ModerationNotifyModerator */
    protected $notifyModerator;

    /** @var LinkBatchFactory */
    protected $linkBatchFactory;

    /**
     * @param ActionFactory $actionFactory
     * @param EntryFactory $entryFactory
     * @param ModerationNotifyModerator $notifyModerator
     * @param LinkBatchFactory $linkBatchFactory
     */
    public function __construct(
        ActionFactory $actionFactory,
        EntryFactory $entryFactory,
        ModerationNotifyModerator $notifyModerator,
        LinkBatchFactory $linkBatchFactory,
    ) {
        parent::__construct("Moderation", "moderation");

        $this->actionFactory = $actionFactory;
        $this->entryFactory = $entryFactory;
        $this->notifyModerator = $notifyModerator;
        $this->linkBatchFactory = $linkBatchFactory;
    }

    /**
     * @inheritDoc
     */
    protected function getGroupName()
    {
        return "spam";
    }

    /**
     * @inheritDoc
     */
    public function isSyndicated()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isCacheable()
    {
        return false;
    }

    /**
     * @param IDatabase $db @phan-unused-param
     * @param IResultWrapper $res
     */
    protected function preprocessResults($db, $res)
    {
        /* Check all pages for whether they exist or not -
         improves performance of makeLink() in ModerationEntryFormatter */
        $batch = $this->linkBatchFactory->newLinkBatch();
        foreach ($res as $row) {
            ModerationEntryFormatter::addToLinkBatch($row, $batch);
        }
        $batch->execute();

        $res->seek(0);
    }

    /**
     * @inheritDoc
     */
    protected function linkParameters()
    {
        return ["folder" => $this->folder];
    }

    /**
     * @inheritDoc
     */
    protected function getPageHeader()
    {
        $linkRenderer = $this->getLinkRenderer();

        $folderLinks = [];
        foreach (array_keys($this->folders_list) as $f_name) {
            $label = $this->msg("moderation-folder-" . $f_name)->plain();

            if ($f_name == $this->folder) {
                $folderLinks[] = Xml::element(
                    "strong",
                    ["class" => "selflink"],
                    $label,
                );
            } else {
                $folderLinks[] = $linkRenderer->makePreloadedLink(
                    $this->getPageTitle(),
                    $label,
                    "",
                    [
                        "title" => $this->msg(
                            "tooltip-moderation-folder-" . $f_name,
                        )->plain(),
                    ],
                    ["folder" => $f_name],
                );
            }
        }

        return Xml::tags(
            "div",
            ["class" => "mw-moderation-folders"],
            implode(" | ", $folderLinks),
        );
    }

    /**
     * @param string|null $param @phan-unused-param
     */
    public function execute($param)
    {
        // Throw an exception if current user doesn't have "moderation" right.
        $this->checkPermissions();

        $this->setHeaders();
        $this->outputHeader();
        $this->getOutput()->setPreventClickjacking(true);

        if ($this->getRequest()->getVal("modaction")) {
            // Some action was requested.
            $this->runModerationAction();
            return;
        }

        // Show the list of pending edits.
        $this->showChangesList();
    }

    /**
     * Show the list of pending changes in the current folder of Special:Moderation.
     */
    public function showChangesList()
    {
        $out = $this->getOutput();
        $out->addModuleStyles([
            "ext.moderation.special.css",
            "mediawiki.interface.helpers.styles",
        ]);
        $out->addWikiMsg("moderation-text");

        if ($this->getConfig()->get("ModerationUseAjax")) {
            $out->addModules("ext.moderation.special.ajax");
        }

        /* Close "New changes await moderation" notification until new changes appear */
        $this->notifyModerator->setSeen($this->getUser(), wfTimestampNow());

        // The rest will be handled by QueryPage::execute()
        parent::execute(null);
    }

    /**
     * Run ModerationAction.
     */
    public function runModerationAction()
    {
        $A = $this->actionFactory->makeAction($this->getContext());
        if ($A->requiresEditToken()) {
            $token = $this->getRequest()->getVal("token");
            if (!$this->getUser()->matchEditToken($token)) {
                throw new ErrorPageError(
                    "sessionfailure-title",
                    "sessionfailure",
                );
            }
        }

        $result = $A->run();

        $out = $this->getOutput();
        $A->outputResult($result, $out);
        $A->printReturnLinks($out);
    }

    /**
     * @inheritDoc
     */
    protected function getOrderFields()
    {
        return ["mod_timestamp"];
    }

    /**
     * @inheritDoc
     */
    public function getQueryInfo()
    {
        $this->folder = $this->getRequest()->getVal(
            "folder",
            $this->default_folder,
        );
        if (!array_key_exists($this->folder, $this->folders_list)) {
            $this->folder = $this->default_folder;
        }

        $conds = $this->folders_list[$this->folder];
        $index = "moderation_folder_" . $this->folder;

        return array_merge_recursive(ModerationEntryFormatter::getQueryInfo(), [
            "conds" => $conds,
            "options" => [
                "USE INDEX" => [
                    "moderation" => $index,
                ],
            ],
            "fields" => ["mod_id AS value"], // Expected by ApiQueryQueryPage
        ]);
    }

    /**
     * @param Skin $skin @phan-unused-param
     * @param stdClass $row Result row
     * @return string
     */
    protected function formatResult($skin, $row)
    {
        return $this->entryFactory
            ->makeFormatter($row, $this->getContext())
            ->getHTML();
    }
}
