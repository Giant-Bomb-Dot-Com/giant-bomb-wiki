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
 * Implements modaction=editchange on [[Special:Moderation]].
 *
 * Here moderator can modify the pending change before approving it.
 * Note: this is disabled by default, because pending changes don't have edit history,
 * so the moderator can accidentally erase their text.
 */

namespace MediaWiki\Moderation;

use Article;
use MediaWiki\Title\Title;
use OutputPage;

class ModerationActionEditChange extends ModerationAction
{
    public function requiresEditToken()
    {
        return false; // True in ModerationActionEditChangeSubmit
    }

    public function execute()
    {
        if (!$this->getConfig()->get("ModerationEnableEditChange")) {
            throw new ModerationError("moderation-unknown-modaction");
        }

        $fields = [
            "mod_namespace AS namespace",
            "mod_title AS title",
            "mod_text AS text",
            "mod_comment AS comment",
            "mod_type AS type",
        ];

        $row = $this->entryFactory->loadRowOrThrow($this->id, $fields);

        if (
            isset($row->type) &&
            $row->type != ModerationNewChange::MOD_TYPE_EDIT
        ) {
            throw new ModerationError("moderation-editchange-not-edit");
        }

        return [
            "id" => $this->id,
            "namespace" => $row->namespace,
            "title" => $row->title,
            "text" => $row->text,
            "summary" => $row->comment,
        ];
    }

    /**
     * @inheritDoc
     */
    public function outputResult(array $result, OutputPage $out)
    {
        $title = Title::makeTitle($result["namespace"], $result["title"]);
        $article = Article::newFromTitle($title, $this->getContext());

        $editPage = new ModerationEditChangePage($article);

        $editPage->setContextTitle($title);
        $editPage->textbox1 = $result["text"];
        $editPage->summary = $result["summary"];

        $editPage->showEditForm();

        $titleMsg = $this->msg(
            "moderation-editchange-title",
            $title->getFullText(),
        );
        $out->setPageTitle($titleMsg->escaped());
    }
}
