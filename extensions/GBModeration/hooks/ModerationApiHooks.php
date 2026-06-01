<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2017-2022 Edward Chernenko.

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
 * Hooks related to edits/uploads via API.
 */

namespace MediaWiki\Moderation;

use ApiBase;
use ApiMain;
use ContentHandler;
use DerivativeContext;
use MediaWiki\Api\Hook\ApiCheckCanExecuteHook;
use MediaWiki\Hook\ApiBeforeMainHook;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\SpecialPage\Hook\WgQueryPagesHook;
use User;

class ModerationApiHooks implements
    ApiBeforeMainHook,
    ApiCheckCanExecuteHook,
    WgQueryPagesHook
{
    /** @var ModerationCanSkip */
    protected $canSkip;

    /** @var ModerationPreload */
    protected $preload;

    /**
     * @param ModerationCanSkip $canSkip
     * @param ModerationPreload $preload
     */
    public function __construct(
        ModerationCanSkip $canSkip,
        ModerationPreload $preload,
    ) {
        $this->canSkip = $canSkip;
        $this->preload = $preload;
    }

    /**
     * ApiCheckCanExecute hook handler.
     *
     * Disable ApiFileRevert (this API doesn't run any pre-upload
     * hooks, thus allowing to bypass moderation).
     * @param ApiBase $module
     * @param User $user
     * @param string &$message
     * @return bool|void
     */
    public function onApiCheckCanExecute($module, $user, &$message)
    {
        if ($this->canSkip->canUploadSkip($user)) {
            return; /* No need to limit automoderated users */
        }

        $moduleName = $module->getModuleName();
        switch ($moduleName) {
            case "filerevert":
                $message = "moderation-revert-not-allowed";
                return false;

            case "imagerotate":
                $message = "moderation-imagerotate-not-allowed";
                return false;
        }
    }

    /**
     * ApiBeforeMain hook handler.
     * Make sure that
     * 1) api.php?action=edit&appendtext=... will append to the pending version.
     * 2) api.php?action=edit&section=N won't complain 'nosuchsection' if
     * section N exists in the pending version.
     * @param ApiMain &$main
     * @return bool|void
     */
    public function onApiBeforeMain(&$main)
    {
        $request = $main->getRequest();
        if ($request->getVal("action") != "edit") {
            return; /* Nothing to do */
        }

        $section = $request->getVal("section", "");
        $prepend = $request->getVal("prependtext", "");
        $append = $request->getVal("appendtext", "");

        if (!$prepend && !$append && !$section) {
            return; /* Usual api.php?action=edit&text= works correctly with Moderation */
        }

        $pageObj = $main->getTitleOrPageId(
            $request->getValues("title", "pageid"),
        );
        $title = $pageObj->getTitle();

        $pendingEdit = $this->preload->findPendingEdit($title);
        if (!$pendingEdit) {
            return; /* No pending version - ApiEdit will handle this correctly */
        }

        $oldContent = ContentHandler::makeContent(
            $pendingEdit->getText(),
            $title,
        );
        $content = $oldContent;
        if ($section) {
            if ($section == "new") {
                $content = ContentHandler::makeContent("", $title);
            } else {
                $content = $oldContent->getSection($section);
                if (!$content) {
                    $main->dieWithError(
                        "There is no section {$section}.",
                        "nosuchsection",
                    );
                }
            }
        }

        $text = $content->serialize();

        /* Now we remove appendtext/prependtext from WebRequest object
			and make ApiEdit think that this is a usual action=edit&text=... call.

			Otherwise ApiEdit will attempt to prepend/append to the last revision
			of the page, not to the preloaded revision.
		*/
        $query = $request->getValues();
        if (!isset($query["text"])) {
            $query["text"] = $prepend . $text . $append;
        }
        unset($query["prependtext"]);
        unset($query["appendtext"]);

        $query["text"] = rtrim($query["text"]);

        if ($section) {
            /* We also remove section=N parameter,
				because if section N doesn't exist in the page,
				ApiEditPage will incorrectly complain "nosuchsection"
				(even when section N exists in the pending version).
			*/
            $sectionTitle = $query["sectiontitle"] ?? "";

            $newSectionContent = ContentHandler::makeContent(
                $query["text"],
                $title,
            );
            $newContent = $oldContent->replaceSection(
                $section,
                $newSectionContent,
                $sectionTitle,
            );

            $query["text"] = $newContent->serialize();
            unset($query["section"]);
            unset($query["sectiontitle"]);
        }

        $context = new DerivativeContext($main->getContext());
        $context->setRequest(new DerivativeRequest($request, $query, true));

        $main->setContext($context);

        /* Let ApiEdit handle the rest */
    }

    /**
     * Adds qppage=Moderation to api.php?action=query&list=querypage.
     * @param array &$pages
     * @return bool|void
     */
    public function onWgQueryPages(&$pages)
    {
        $pages[] = [SpecialModeration::class, "Moderation"];
    }
}
