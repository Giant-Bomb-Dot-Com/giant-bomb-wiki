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
 * Implements modaction=(un)block on [[Special:Moderation]].
 */

namespace MediaWiki\Moderation;

use MediaWiki\Title\Title;
use OutputPage;

class ModerationActionBlock extends ModerationAction
{
    /**
     * @inheritDoc
     */
    public function outputResult(array $result, OutputPage $out)
    {
        /* Messages used here (for grep)
			moderation-block-ok
			moderation-unblock-ok
		*/
        $out->addWikiMsg(
            "moderation-" .
                ($result["action"] == "unblock" ? "un" : "") .
                "block-ok",
            $result["username"],
        );
    }

    public function execute()
    {
        $row = $this->entryFactory->loadRowOrThrow($this->id, [
            "mod_user AS user",
            "mod_user_text AS user_text",
        ]);

        if ($this->actionName == "block") {
            $somethingChanged = $this->consequenceManager->add(
                new BlockUserConsequence(
                    (int) $row->user,
                    $row->user_text,
                    $this->moderator,
                ),
            );
        } else {
            $somethingChanged = $this->consequenceManager->add(
                new UnblockUserConsequence($row->user_text),
            );
        }

        /*
			If the user was already (un)blocked and we attempt to (un)block,
			we silently ignore this (saying "successfully (un)blocked!" to moderator),
			because the desired outcome has been reached anyway.
			E.g. this can happen if the moderator clicked "Mark as spammer" twice.
		*/
        if ($somethingChanged) {
            $this->consequenceManager->add(
                new AddLogEntryConsequence(
                    $this->actionName == "block" ? "block" : "unblock",
                    $this->moderator,
                    Title::makeTitle(NS_USER, $row->user_text),
                ),
            );
        }

        return [
            "action" => $this->actionName,
            "username" => $row->user_text,
            "noop" => !$somethingChanged, // Already was blocked/unblocked
        ];
    }
}
