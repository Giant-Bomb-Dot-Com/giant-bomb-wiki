<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2018-2024 Edward Chernenko.

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
 * Plugin for using Moderation with Extension:PageForms.
 */

namespace MediaWiki\Moderation;

use IContextSource;
use MediaWiki\Title\Title;

class ModerationPageForms
{
    /** @var ModerationPreload */
    protected $preload;

    /**
     * @param ModerationPreload $preload
     */
    public function __construct(ModerationPreload $preload)
    {
        $this->preload = $preload;
    }

    /**
     * Provide Extension:PageForms with text of pending revision when user is creating a new page.
     *
     * @param string &$preloadContent
     * @param Title|null $targetTitle
     * @param Title $formTitle @phan-unused-param
     * @return bool|void
     */
    // phpcs:ignore MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName, MediaWiki.Commenting.FunctionComment.MissingDocumentationPublic
    public function onPageForms__EditFormPreloadText(
        &$preloadContent,
        $targetTitle,
        $formTitle,
    ) {
        $this->preloadText($preloadContent, $targetTitle);
    }

    /**
     * Provide Extension:PageForms with text of pending revision when user is editing an existing page.
     *
     * @param string &$preloadContent
     * @param Title|null $targetTitle
     * @param Title $formTitle @phan-unused-param
     * @return bool|void
     */
    // phpcs:ignore MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName, MediaWiki.Commenting.FunctionComment.MissingDocumentationPublic
    public function onPageForms__EditFormInitialText(
        &$preloadContent,
        $targetTitle,
        $formTitle,
    ) {
        $this->preloadText($preloadContent, $targetTitle);
    }

    /**
     * Preload text of pending edit into the form of Special:FormEdit.
     *
     * This is used by two hooks:
     * PageForms::EditFormPreloadText (when creating new page)
     * PageForms::EditFormInitialText (when editing existing page)
     *
     * @param string &$preloadContent
     * @param Title|null $targetTitle
     */
    public function preloadText(&$preloadContent, $targetTitle)
    {
        if (!$targetTitle) {
            // We are on [[Special:FormEdit/A]], where A is the name of form.
            // Unlike [[Special:FormEdit/A/B]], user is currently not editing
            // a particular page "B", so there is nothing to preload.
            return;
        }

        $this->preload->onEditFormPreloadText($preloadContent, $targetTitle);
    }

    /**
     * ModerationContinueEditingLink hook.
     * Here we point "continue editing" link to FormEdit after using FormEdit.
     * @param string &$returnto
     * @param array &$returntoquery
     * @param Title $title @phan-unused-param
     * @param IContextSource $context
     * @return bool|void
     */
    public function onModerationContinueEditingLink(
        &$returnto,
        array &$returntoquery,
        Title $title,
        IContextSource $context,
    ) {
        if (!class_exists("PFForms")) {
            // Extension:PageForms is not installed.
            return;
        }

        // Are we editing via ?action=formedit?
        $action = ModerationCompatTools::getActionName($context);
        if ($action == "formedit") {
            $returntoquery = ["action" => "formedit"];
        } else {
            // Are we editing via Special:FormEdit?
            $specialTitle = Title::newFromText(
                $context->getRequest()->getVal("title"),
            );
            if ($specialTitle && $specialTitle->isSpecial("FormEdit")) {
                $returnto = $specialTitle->getFullText();
            }
        }
    }
}
