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
 * Subclass of EditPage used by modaction=editchange
 */

namespace MediaWiki\Moderation;

use MediaWiki\EditPage\EditPage;
use MediaWiki\Html\Html;
use MediaWiki\Title\Title;
use SpecialPage;

class ModerationEditChangePage extends EditPage
{
    /**
     * When user clicks Submit, handle this back to Special:Moderation.
     * @param Title $title @phan-unused-param
     * @return string URL
     */
    protected function getActionURL(Title $title)
    {
        return SpecialPage::getTitleFor("Moderation")->getLocalURL([
            "modid" => $this->getContext()->getRequest()->getVal("modid"),
            "modaction" => "editchangesubmit",
        ]);
    }

    /**
     * Add CSRF token.
     */
    protected function showFormAfterText()
    {
        $this->getContext()
            ->getOutput()
            ->addHTML(
                Html::hidden(
                    "token",
                    $this->getContext()->getUser()->getEditToken(),
                ),
            );
    }

    /**
     * Remove "Preview" and "Show changes" buttons (not yet implemented).
     * @param int &$tabindex
     * @return array
     */
    public function getEditButtons(&$tabindex)
    {
        $buttons = parent::getEditButtons($tabindex);

        unset($buttons["preview"]);
        unset($buttons["diff"]);

        return $buttons;
    }

    /**
     * Point "Cancel" button to Special:Moderation, not to the nonexistent article.
     * @return Title
     */
    public function getContextTitle()
    {
        return SpecialPage::getTitleFor("Moderation");
    }
}
