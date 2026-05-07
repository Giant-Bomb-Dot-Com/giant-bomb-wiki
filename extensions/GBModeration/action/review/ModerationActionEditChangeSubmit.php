<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2018-2021 Edward Chernenko.

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
 * Implements modaction=editchangesubmit on [[Special:Moderation]].
 *
 * @see ModerationActionEditChange - handles the edit form
 */

namespace MediaWiki\Moderation;

use ContentHandler;
use MediaWiki\Title\Title;
use OutputPage;
use ParserOptions;
use User;

class ModerationActionEditChangeSubmit extends ModerationAction
{
    public function execute()
    {
        if (!$this->getConfig()->get("ModerationEnableEditChange")) {
            throw new ModerationError("moderation-unknown-modaction");
        }

        $where = [
            "mod_id" => $this->id,
            // Disallow modification of non-edits, e.g. pending page moves.
            "mod_type" => ModerationNewChange::MOD_TYPE_EDIT,
        ];

        $row = $this->entryFactory->loadRowOrThrow($where, [
            "mod_namespace AS namespace",
            "mod_title AS title",
            "mod_user AS user",
            "mod_user_text AS user_text",
            "mod_text AS text",
            "mod_comment AS comment",
        ]);

        $request = $this->getRequest();

        $title = Title::makeTitle($row->namespace, $row->title);
        $originalAuthor = $row->user
            ? User::newFromId($row->user)
            : User::newFromName($row->user_text, false);

        $newText = $request->getVal("wpTextbox1", "");
        $newComment = $request->getVal("wpSummary", "");

        /* Apply preSaveTransform to the submitted text */
        $newContent = ContentHandler::makeContent($newText, $title);
        $pstContent = ModerationCompatTools::preSaveTransform(
            $newContent,
            $title,
            $originalAuthor,
            ParserOptions::newFromUserAndLang(
                $originalAuthor,
                $this->contentLanguage,
            ),
        );

        $newText = $pstContent->serialize();
        $newLen = $pstContent->getSize();

        $somethingChanged =
            $newText != $row->text || $newComment != $row->comment;

        if ($somethingChanged) {
            // Something changed.
            $this->consequenceManager->add(
                new ModifyPendingChangeConsequence(
                    $this->id,
                    $newText,
                    $newComment,
                    $newLen,
                ),
            );
            $this->consequenceManager->add(
                new AddLogEntryConsequence(
                    "editchange",
                    $this->moderator,
                    $title,
                    ["modid" => $this->id],
                ),
            );
        }

        return [
            "id" => $this->id,
            "success" => true,
            "noop" => !$somethingChanged,
        ];
    }

    /**
     * @param array $result @phan-unused-param
     * @param OutputPage $out
     */
    public function outputResult(array $result, OutputPage $out)
    {
        $out->addWikiMsg("moderation-editchange-ok");
    }
}
