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
 * Implements modaction=reject(all) on [[Special:Moderation]].
 */

namespace MediaWiki\Moderation;

use MediaWiki\Title\Title;
use OutputPage;

class ModerationActionReject extends ModerationAction
{
    public function execute()
    {
        $ret =
            $this->actionName == "reject"
                ? $this->executeRejectOne()
                : $this->executeRejectAll();

        if ($ret["rejected-count"]) {
            /* Clear the cache of "Most recent mod_timestamp of pending edit"
             - could have changed */
            $this->consequenceManager->add(
                new InvalidatePendingTimeCacheConsequence(),
            );
        }

        return $ret;
    }

    /**
     * @inheritDoc
     */
    public function outputResult(array $result, OutputPage $out)
    {
        $out->addWikiMsg("moderation-rejected-ok", $result["rejected-count"]);
    }

    public function executeRejectOne()
    {
        $row = $this->entryFactory->loadRowOrThrow($this->id, [
            "mod_namespace AS namespace",
            "mod_title AS title",
            "mod_user AS user",
            "mod_user_text AS user_text",
            "mod_rejected AS rejected",
            "mod_merged_revid AS merged_revid",
        ]);

        if ($row->rejected) {
            throw new ModerationError("moderation-already-rejected");
        }

        if ($row->merged_revid) {
            throw new ModerationError("moderation-already-merged");
        }

        $rejectedCount = $this->consequenceManager->add(
            new RejectOneConsequence($this->id, $this->moderator),
        );
        if (!$rejectedCount) {
            throw new ModerationError("moderation-edit-not-found");
        }

        $title = Title::makeTitle($row->namespace, $row->title);
        $this->consequenceManager->add(
            new AddLogEntryConsequence("reject", $this->moderator, $title, [
                "modid" => $this->id,
                "user" => (int) $row->user,
                "user_text" => $row->user_text,
            ]),
        );

        return [
            "rejected-count" => $rejectedCount,
        ];
    }

    public function executeRejectAll()
    {
        $userpage = $this->getUserpageOfPerformer();
        if (!$userpage) {
            throw new ModerationError("moderation-edit-not-found");
        }

        $rejectedCount = $this->consequenceManager->add(
            new RejectAllConsequence($userpage->getText(), $this->moderator),
        );
        if (!$rejectedCount) {
            throw new ModerationError("moderation-nothing-to-rejectall");
        }

        $this->consequenceManager->add(
            new AddLogEntryConsequence(
                "rejectall",
                $this->moderator,
                $userpage,
                ["4::count" => $rejectedCount],
            ),
        );

        return [
            "rejected-count" => $rejectedCount,
        ];
    }
}
