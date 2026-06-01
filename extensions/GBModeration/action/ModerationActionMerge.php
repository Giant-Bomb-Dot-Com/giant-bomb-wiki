<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2014-2021 Edward Chernenko.

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
 * Implements modaction=merge on [[Special:Moderation]].
 */

namespace MediaWiki\Moderation;

use Article;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Title\Title;
use OutputPage;

class ModerationActionMerge extends ModerationAction
{
    public function execute()
    {
        $row = $this->entryFactory->loadRowOrThrow($this->id, [
            "mod_namespace AS namespace",
            "mod_title AS title",
            "mod_user_text AS user_text",
            "mod_text AS text",
            "mod_conflict AS conflict",
            "mod_merged_revid AS merged_revid",
        ]);

        if (!$row->conflict) {
            throw new ModerationError("moderation-merge-not-needed");
        }

        if ($row->merged_revid) {
            throw new ModerationError("moderation-already-merged");
        }

        // In order to merge, moderator must also be automoderated
        if (!$this->canSkip->canEditSkip($this->moderator, $row->namespace)) {
            throw new ModerationError("moderation-merge-not-automoderated");
        }

        return [
            "id" => $this->id,
            "namespace" => $row->namespace,
            "title" => $row->title,
            "text" => $row->text,
            "summary" => $this->msg("moderation-merge-comment", $row->user_text)
                ->inContentLanguage()
                ->plain(),
        ];
    }

    /**
     * @param array $result
     * @param OutputPage $out @phan-unused-param
     */
    public function outputResult(array $result, OutputPage $out)
    {
        $title = Title::makeTitle($result["namespace"], $result["title"]);
        $article = Article::newFromTitle($title, $this->getContext());

        $this->editFormOptions->setMergeID($result["id"]);

        $editPage = new EditPage($article);

        $editPage->isConflict = true;
        $editPage->setContextTitle($title);
        $editPage->textbox1 = $result["text"];
        $editPage->summary = $result["summary"];

        $editPage->showEditForm();
    }
}
