<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2015-2025 Edward Chernenko.

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
 * Methods to parse/analyze single entry on Special:Moderation.
 */

namespace MediaWiki\Moderation\Tests;

use DomElement;
use DOMText;
use MediaWiki\Moderation\ModerationCompatTools;
use MWException;
use UnexpectedValueException;
use User;

/**
 * @class ModerationTestsuiteEntry
 * Represents one line on [[Special:Moderation]]
 */
class ModerationTestsuiteEntry
{
    /** @var int */
    public $id;

    /** @var string|null */
    public $user = null;

    /** @var string|null */
    public $title = null;

    /** @var string|null */
    public $page2Title = null;

    /** @var string|null */
    public $commentHtml = null;

    /** @var string|null */
    public $showLink = null;

    /** @var string|null */
    public $previewLink = null;

    /** @var string|null */
    public $editChangeLink = null;

    /** @var string|null */
    public $approveLink = null;

    /** @var string|null */
    public $approveAllLink = null;

    /** @var string|null */
    public $rejectLink = null;

    /** @var string|null */
    public $rejectAllLink = null;

    /** @var string|null */
    public $blockLink = null;

    /** @var string|null */
    public $unblockLink = null;

    /** @var string|null */
    public $mergeLink = null;

    /** @var string|null */
    public $mergedDiffLink = null;

    /** @var string|null */
    public $ip = null;

    /** @var string|null */
    public $rejected_by_user = null;

    /** @var bool */
    public $rejected_batch = false;

    /** @var bool */
    public $rejected_auto = false;

    /** @var bool */
    public $conflict = false;

    /** @var bool */
    public $isMove = false;

    /** @var bool */
    public $minor = false;

    /** @var bool */
    public $bot = false;

    /** @var bool */
    public $new = false;

    /** @var string Time of the timestamp, e.g. '08:30' */
    public $time = null;

    /** @var string Full human-readable timestamp, e.g. '12:20, 22 June 2018' */
    public $datetime = null;

    /**
     * @var bool
     * True if "can't merge: current user is not automoderated" is shown instead of Merge link.
     */
    public $noMergeNotAutomoderated = false;

    /** @var int Difference between old_len and new_len, e.g. -25 or +600 */
    public $charChange = null;

    /** @var bool True if the character change is highlighted (due to being large) */
    public $charChangeBold = false;

    /** @var string Raw HTML of this entry. Useless for tests, but handy for troubleshooting */
    public $rawHTML;

    /**
     * @param DomElement $span
     */
    public function __construct(DomElement $span)
    {
        if (strpos($span->getAttribute("class"), "modconflict") !== false) {
            $this->conflict = true;
        }

        foreach ($span->childNodes as $child) {
            $text = $child->textContent;
            if (strpos($text, "(moderation-rejected-auto)") !== false) {
                $this->rejected_auto = true;
            }

            if (strpos($text, "(moderation-rejected-batch)") !== false) {
                $this->rejected_batch = true;
            }

            if (strpos($text, "(moderation-move: ") !== false) {
                $this->isMove = true;
            }

            if (strpos($text, "(minoreditletter)") !== false) {
                $this->minor = true;
            }

            if (strpos($text, "(boteditletter)") !== false) {
                $this->bot = true;
            }

            if (strpos($text, "(newpageletter)") !== false) {
                $this->new = true;
            }

            if (
                strpos(
                    $text,
                    "(moderation-no-merge-link-not-automoderated)",
                ) !== false
            ) {
                $this->noMergeNotAutomoderated = true;
            }

            $matches = null;
            if (preg_match("/([0-9]{2}:[0-9]{2})[^.]*/", $text, $matches)) {
                $this->time = $matches[1];
                $this->datetime = trim($matches[0]);
            }

            $matches = null;
            if (
                preg_match(
                    "/\(rc-change-size: ([\-−0-9,]+)\)/",
                    $text,
                    $matches,
                )
            ) {
                $this->charChange = (int) str_replace(
                    [",", "−"],
                    ["", "-"],
                    $matches[1],
                );
                $this->charChangeBold = $child->tagName !== "span";
            }

            if (
                !($child instanceof DOMText) &&
                $child->getAttribute("class") === "comment"
            ) {
                $this->commentHtml = "";
                foreach ($child->childNodes as $grandchild) {
                    $this->commentHtml .= $grandchild->ownerDocument->saveXML(
                        $grandchild,
                    );
                }

                $this->commentHtml = preg_replace(
                    ["/^\(parentheses: /", '/\)$/'],
                    [],
                    $this->commentHtml,
                );
            }
        }

        $links = $span->getElementsByTagName("a");
        foreach ($links as $link) {
            if (strpos($link->getAttribute("class"), "mw-userlink") !== false) {
                $text = $link->textContent;

                # This is
                # 1) either the user who made an edit,
                # 2) or the moderator who rejected it.
                # Let's check the text BEFORE this link for
                # the presence of 'moderation-rejected-by'.

                if (
                    strpos(
                        $link->previousSibling->textContent,
                        "moderation-rejected-by",
                    ) !== false
                ) {
                    $this->rejected_by_user = $text;
                } else {
                    $this->user = $text;
                }

                continue;
            }

            $href = $link->getAttribute("href");
            switch ($link->nodeValue) {
                case "(moderation-show)":
                    $this->showLink = $href;
                    break;

                case "(moderation-preview)":
                    $this->previewLink = $href;
                    break;

                case "(moderation-editchange)":
                    $this->editChangeLink = $href;
                    break;

                case "(moderation-approve)":
                    $this->approveLink = $href;
                    break;

                case "(moderation-approveall)":
                    $this->approveAllLink = $href;
                    break;

                case "(moderation-reject)":
                    $this->rejectLink = $href;
                    break;

                case "(moderation-rejectall)":
                    $this->rejectAllLink = $href;
                    break;

                case "(moderation-merge)":
                    $this->mergeLink = $href;
                    break;

                case "(moderation-merged-link)":
                    $this->mergedDiffLink = $href;
                    break;

                case "(moderation-block)":
                    $this->blockLink = $href;
                    break;

                case "(moderation-unblock)":
                    $this->unblockLink = $href;
                    break;

                case "(moderation-whois-link-text)":
                    $matches = null;
                    if (
                        preg_match(
                            "/\(moderation-whois-link-url: ([^)]*)\)/",
                            $href,
                            $matches,
                        )
                    ) {
                        $this->ip = $matches[1];
                    }
                    break;

                default:
                    if (!$this->title) {
                        $this->title = $link->textContent;
                    } else {
                        $this->page2Title = $link->textContent;
                    }
            }
        }

        $matches = null;
        preg_match("/modid=([0-9]+)/", $this->getAnyLink(), $matches);
        $this->id = (int) $matches[1];

        // Save the entire entry as HTML (not really used in tests, but handy for troubleshooting)
        $this->rawHTML = $span->ownerDocument->saveXML($span);
    }

    /**
     * Get any link, assuming at least one exists.
     * @return string
     */
    public function getAnyLink()
    {
        /* Either Block or Unblock link always exists */
        $url = $this->blockLink ?? $this->unblockLink;
        if (!$url) {
            throw new MWException("getAnyLink(): no links found");
        }

        return $url;
    }

    /**
     * Get URL of the link $modaction.
     * @param string $modaction Name of modaction (e.g. 'rejectall') or 'mergedDiff'.
     * @return string
     */
    public function getActionLink($modaction)
    {
        switch ($modaction) {
            case "show":
                return $this->showLink;
            case "preview":
                return $this->previewLink;
            case "editchange":
                return $this->editChangeLink;
            case "approve":
                return $this->approveLink;
            case "approveall":
                return $this->approveAllLink;
            case "reject":
                return $this->rejectLink;
            case "rejectall":
                return $this->rejectAllLink;
            case "block":
                return $this->blockLink;
            case "unblock":
                return $this->unblockLink;
            case "merge":
                return $this->mergeLink;
            case "mergedDiff":
                return $this->mergedDiffLink;
        }

        throw new UnexpectedValueException(
            __METHOD__ . ": unknown modaction='$modaction'",
        );
    }

    /**
     * Scans $allEntries and returns an entry with mod_id=$id.
     * @param ModerationTestsuiteEntry[] $allEntries
     * @param int $id
     * @return ModerationTestsuiteEntry|null
     */
    public static function findById(array $allEntries, $id)
    {
        foreach ($allEntries as $e) {
            if ($e->id === $id) {
                return $e;
            }
        }
        return null;
    }

    /**
     * Scans $allEntries and returns an array of entries that were made by $user.
     * @param ModerationTestsuiteEntry[] $allEntries
     * @param User $user
     * @return ModerationTestsuiteEntry[]
     */
    public static function findByUser(array $allEntries, User $user)
    {
        $username = $user->getName();
        return array_values(
            array_filter($allEntries, static function ($entry) use ($username) {
                return $entry->user === $username;
            }),
        );
    }

    /**
     * Populates both $e->blockLink and $e->unblockLink,
     * even though only one link exists on Special:Moderation
     */
    public function fakeBlockLink()
    {
        $bl = $this->blockLink;
        $ul = $this->unblockLink;

        if ($bl && !$ul) {
            $this->unblockLink = preg_replace(
                "/modaction=block/",
                "modaction=unblock",
                $bl,
            );
        } elseif ($ul && !$bl) {
            $this->blockLink = preg_replace(
                "/modaction=unblock/",
                "modaction=block",
                $ul,
            );
        }
    }

    /**
     * Returns the URL of modaction=showimg for this entry.
     * @return string
     */
    public function expectedShowImgLink()
    {
        return $this->expectedActionLink("showimg", false);
    }

    /**
     * Returns the URL of modaction=$action for this entry.
     * @param string $action
     * @param bool $needsToken
     * @return string
     */
    public function expectedActionLink($action, $needsToken = true)
    {
        /* Either block or unblock link always exists */
        $url = $this->getAnyLink();
        if (!$needsToken) {
            $url = preg_replace('/[&?]token=(.*?)(&|$)/', "", $url);
        }

        return preg_replace(
            "/modaction=(block|unblock)/",
            "modaction=" . $action,
            $url,
        );
    }

    /**
     * Fetches this entry from the database and returns $field.
     * @param string $field Field name, e.g. "mod_new_len".
     * @return string
     */
    public function getDbField($field)
    {
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        return $dbw->selectField(
            "moderation",
            $field,
            ["mod_id" => $this->id],
            __METHOD__,
        );
    }

    /**
     * Returns mod_text of this entry (loaded from the database).
     * @return string
     */
    public function getDbText()
    {
        return $this->getDbField("mod_text");
    }

    /**
     * Modified this entry in the database.
     * @param array $updates List of updates, as expected by Database::update
     */
    public function updateDbRow(array $updates)
    {
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $dbw->update(
            "moderation",
            $updates,
            ["mod_id" => $this->id],
            __METHOD__,
        );
    }
}
