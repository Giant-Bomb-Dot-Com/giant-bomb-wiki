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
 * Defines the format of [[Special:Log/moderation]]
 */

namespace MediaWiki\Moderation;

use LogFormatter;
use MediaWiki\Linker\Linker;
use MediaWiki\Title\Title;
use Message;
use SpecialPage;
use User;

class ModerationLogFormatter extends LogFormatter
{
    /**
     * @inheritDoc
     */
    public function getMessageParameters()
    {
        $params = parent::getMessageParameters();

        $type = $this->entry->getSubtype();
        $entryParams = $this->entry->getParameters();

        $linkRenderer = $this->getLinkRenderer();

        if ($type === "approve") {
            $revId = $entryParams["revid"];
            $link = $linkRenderer->makeLink(
                $this->entry->getTarget(),
                $this->msg("moderation-log-diff")->params($revId)->text(),
                [
                    "title" => $this->msg(
                        "tooltip-moderation-approved-diff",
                    )->plain(),
                ],
                ["diff" => $revId],
            );
            $params[3] = Message::rawParam($link);
        } elseif ($type === "approve-move") {
            $title = Title::newFromText($entryParams["4::target"]);
            $params[3] = Message::rawParam($linkRenderer->makeLink($title));
            $params[4] = Message::rawParam(
                Linker::userLink(
                    $entryParams["user"],
                    $entryParams["user_text"],
                ),
            );
        } elseif ($type === "reject") {
            $modId = $entryParams["modid"];
            $link = $linkRenderer->makeKnownLink(
                SpecialPage::getTitleFor("Moderation"),
                $this->msg("moderation-log-change")->params($modId)->text(),
                [
                    "title" => $this->msg(
                        "tooltip-moderation-rejected-change",
                    )->plain(),
                ],
                ["modaction" => "show", "modid" => $modId],
            );
            $params[3] = Message::rawParam($link);

            $userLink = Linker::userLink(
                $entryParams["user"],
                $entryParams["user_text"],
            );
            $params[4] = Message::rawParam($userLink);
        } elseif ($type === "editchange") {
            $modId = $entryParams["modid"];
            $link = $linkRenderer->makeKnownLink(
                SpecialPage::getTitleFor("Moderation"),
                $this->msg("moderation-log-change")->params($modId)->text(),
                [], // TODO: add tooltip
                ["modaction" => "show", "modid" => $modId],
            );
            $params[3] = Message::rawParam($link);
        } elseif ($type === "merge") {
            $revId = $entryParams["revid"];
            $modId = $entryParams["modid"];

            $link = $linkRenderer->makeKnownLink(
                SpecialPage::getTitleFor("Moderation"),
                $this->msg("moderation-log-change")->params($modId)->text(),
                [
                    "title" => $this->msg(
                        "tooltip-moderation-rejected-change",
                    )->plain(),
                ],
                ["modaction" => "show", "modid" => $modId],
            );
            $params[3] = Message::rawParam($link);

            $link = $linkRenderer->makeLink(
                $this->entry->getTarget(),
                $this->msg("moderation-log-diff")->params($revId)->text(),
                [
                    "title" => $this->msg(
                        "tooltip-moderation-approved-diff",
                    )->plain(),
                ],
                ["diff" => $revId],
            );
            $params[4] = Message::rawParam($link);
        } elseif (
            $type === "approveall" ||
            $type === "rejectall" ||
            $type === "block" ||
            $type === "unblock"
        ) {
            $title = $this->entry->getTarget();
            $user = User::newFromName($title->getText(), false);

            $link = Linker::userLink($user->getId(), $user->getName());
            $params[2] = Message::rawParam($link);
        }

        return $params;
    }

    /**
     * List of Titles to be fed to LinkBatch (to check their existence).
     * @return array
     *
     * @phan-return array<Title>
     */
    public function getPreloadTitles()
    {
        $type = $this->entry->getSubtype();
        $params = $this->entry->getParameters();

        $titles = [];

        if ($type === "reject") {
            /* moderation/reject:
             userlink [[User:B]] in "A rejected edit N by [User B]" */
            if ($params["user"]) {
                # Not anonymous
                $titles[] = Title::makeTitle(NS_USER, $params["user_text"]);
            }
        } elseif ($type === "approve-move") {
            /* moderation/approve-move:
             link [[Y]] in "A approved moving X to Y" */
            $titles[] = Title::newFromText($params["4::target"]);
        }

        return $titles;
    }
}
