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
 * Implements modaction=approve(all) on [[Special:Moderation]].
 */

namespace MediaWiki\Moderation;

use OutputPage;

class ModerationActionApprove extends ModerationAction
{
    public function execute()
    {
        $ret =
            $this->actionName == "approve"
                ? $this->executeApproveOne()
                : $this->executeApproveAll();

        if ($ret["approved"]) {
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
        $out->addWikiMsg("moderation-approved-ok", count($result["approved"]));

        if (!empty($result["failed"])) {
            $out->addWikiMsg(
                "moderation-approved-errors",
                count($result["failed"]),
            );
        }
    }

    public function executeApproveOne()
    {
        $entry = $this->entryFactory->findApprovableEntry($this->id);
        $entry->approve($this->moderator);

        if (!$entry->isMove()) {
            $this->addReturnTitle($entry->getTitle());
        }

        return [
            "approved" => [$this->id],
        ];
    }

    public function executeApproveAll()
    {
        $userpage = $this->getUserpageOfPerformer();
        if (!$userpage) {
            throw new ModerationError("moderation-edit-not-found");
        }

        $entries = $this->entryFactory->findAllApprovableEntries(
            $userpage->getText(),
        );
        if (!$entries) {
            throw new ModerationError("moderation-nothing-to-approveall");
        }

        $approved = [];
        $failed = [];
        foreach ($entries as $entry) {
            $modid = $entry->getId();
            try {
                $entry->approve($this->moderator);
                $approved[$modid] = "";
            } catch (ModerationError $e) {
                $msg = $e->status->getMessage();
                $failed[$modid] = [
                    "code" => $msg->getKey(),
                    "info" => $msg->plain(),
                ];
            }
        }

        if ($approved) {
            $this->consequenceManager->add(
                new AddLogEntryConsequence(
                    "approveall",
                    $this->moderator,
                    $userpage,
                    ["4::count" => count($approved)],
                ),
            );
        }

        return [
            "approved" => $approved,
            "failed" => $failed,
        ];
    }
}
