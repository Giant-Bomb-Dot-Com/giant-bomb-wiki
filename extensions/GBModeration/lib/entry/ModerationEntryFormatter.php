<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2018-2025 Edward Chernenko.

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
 * Formatter for displaying entry on Special:Moderation.
 */

namespace MediaWiki\Moderation;

use ChangesList;
use IContextSource;
use LinkBatch;
use MediaWiki\Linker\Linker;
use MediaWiki\Linker\LinkRenderer;
use Message;
use RequestContext;
use stdClass;
use User;
use Wikimedia\IPUtils;
use Xml;

class ModerationEntryFormatter extends ModerationEntry
{
    /** @var IContextSource */
    protected $context;

    /** @var LinkRenderer */
    protected $linkRenderer;

    /** @var ActionLinkRenderer */
    protected $actionLinkRenderer;

    /** @var TimestampTools */
    protected $timestampTools;

    /** @var ModerationCanSkip */
    protected $canSkip;

    /**
     * @param stdClass $row
     * @param IContextSource $context
     * @param LinkRenderer $linkRenderer
     * @param ActionLinkRenderer $actionLinkRenderer
     * @param TimestampTools $timestampTools
     * @param ModerationCanSkip $canSkip
     */
    public function __construct(
        $row,
        IContextSource $context,
        LinkRenderer $linkRenderer,
        ActionLinkRenderer $actionLinkRenderer,
        TimestampTools $timestampTools,
        ModerationCanSkip $canSkip,
    ) {
        parent::__construct($row);

        $this->context = $context;
        $this->linkRenderer = $linkRenderer;
        $this->actionLinkRenderer = $actionLinkRenderer;
        $this->timestampTools = $timestampTools;
        $this->canSkip = $canSkip;
    }

    /**
     * @return User object of moderator.
     */
    public function getModerator()
    {
        return $this->context->getUser();
    }

    /**
     * Same as wfMessage(), but respects local context.
     * @param mixed $key
     * @param mixed ...$args
     * @return Message
     */
    public function msg($key, ...$args)
    {
        return $this->context->msg($key, ...$args);
    }

    /**
     * Add all titles needed by getHTML() to $batch.
     * This method is for QueryPage::preprocessResults().
     * It optimizes makeLink() calls by detecting all redlinks in one SQL query.
     * @param stdClass $row
     * @param LinkBatch $batch
     */
    public static function addToLinkBatch($row, LinkBatch $batch)
    {
        /* Check the affected article */
        $batch->add($row->namespace, $row->title);

        /* Check userpages - improves performance of Linker::userLink().
			Not needed for anonymous users,
			because their userLink() points to Special:Contributions.
		*/
        if ($row->user) {
            $batch->add(NS_USER, $row->user_text);
        }

        if ($row->rejected_by_user) {
            $batch->add(NS_USER, $row->rejected_by_user_text);
        }

        /* Check NewTitle for page moves.
         It will probably be a redlink, but we have to be sure. */
        if (!empty($row->page2_title)) {
            $batch->add($row->page2_namespace, $row->page2_title);
        }
    }

    /**
     * Returns QueryInfo for $db->select(), as expected by QueryPage::getQueryInfo().
     * @return array
     */
    public static function getQueryInfo()
    {
        return [
            "tables" => ["moderation", "moderation_block"],
            "fields" => self::getFields(),
            "conds" => [],
            "options" => [
                "USE INDEX" => [
                    "moderation_block" => "moderation_block_address",
                ],
            ],
            "join_conds" => [
                "moderation_block" => [
                    "LEFT JOIN",
                    ["mb_address=mod_user_text"],
                ],
            ],
        ];
    }

    /**
     * Get the list of fields needed for selecting $row from database.
     * @return array
     */
    public static function getFields()
    {
        $fields = array_merge(parent::getFields(), [
            "mod_id AS id",
            "mod_timestamp AS timestamp",
            "mod_comment AS comment",
            "mod_minor AS minor",
            "mod_bot AS bot",
            "mod_new AS new",
            "mod_old_len AS old_len",
            "mod_new_len AS new_len",
            "mod_rejected AS rejected",
            "mod_rejected_by_user AS rejected_by_user",
            "mod_rejected_by_user_text AS rejected_by_user_text",
            "mod_rejected_batch AS rejected_batch",
            "mod_rejected_auto AS rejected_auto",
            "mod_conflict AS conflict",
            "mod_merged_revid AS merged_revid",
            "mb_id AS blocked",
        ]);

        if (
            RequestContext::getMain()
                ->getUser()
                ->isAllowed("moderation-checkuser")
        ) {
            $fields[] = "mod_ip AS ip";
        }

        return $fields;
    }

    /**
     * Returns HTML of formatted line for Special:Moderation.
     * @return string
     */
    public function getHTML()
    {
        global $wgModerationPreviewLink, $wgModerationEnableEditChange;

        $linkRenderer = $this->linkRenderer;
        $actionLinkRenderer = $this->actionLinkRenderer;

        $row = $this->getRow();
        $title = $this->getTitle();

        $class = "modline";
        $line = "";

        // Show/Preview links. Not needed for moves, because they don't change the text.
        if (!$this->isMove()) {
            $line .= "(" . $actionLinkRenderer->makeLink("show", $row->id);

            if ($wgModerationPreviewLink) {
                $line .=
                    " | " . $actionLinkRenderer->makeLink("preview", $row->id);
            }

            if ($wgModerationEnableEditChange) {
                $line .=
                    " | " .
                    $actionLinkRenderer->makeLink("editchange", $row->id);
            }

            $line .= ") . . ";
        }

        if ($row->minor) {
            $line .= $this->msg("minoreditletter")->plain();
        }
        if ($row->bot) {
            $line .= $this->msg("boteditletter")->plain();
        }
        if ($row->new) {
            $line .= $this->msg("newpageletter")->plain();
        }
        $line .= " ";

        $pageLink = $linkRenderer->makeLink($title);
        if ($this->isMove()) {
            /* "Page A renamed into B" */
            $page2Link = $linkRenderer->makeLink($this->getPage2Title());

            $line .= $this->msg("moderation-move")
                ->rawParams($pageLink, $page2Link)
                ->plain();
        } else {
            /* Normal edit (or upload) */
            $line .= $pageLink;
        }

        $line .= " ";

        $line .= $this->timestampTools->format($row->timestamp, $this->context);

        $line .= " . . ";
        $line .= ChangesList::showCharacterDifference(
            $row->old_len,
            $row->new_len,
            $this->context,
        );
        $line .= " . . ";
        $line .= Linker::userLink($row->user, $row->user_text);

        $ip = null;
        if (isset($row->ip)) {
            $ip = $row->ip;
        } elseif ($row->user == 0 && IPUtils::isValid($row->user_text)) {
            $ip = $row->user_text;
        }

        if ($ip) {
            /* Add Whois link to this IP. */
            $url = $this->msg("moderation-whois-link-url", $ip)->plain();
            $text = $this->msg("moderation-whois-link-text")->plain();

            $link = Linker::makeExternalLink($url, $text);
            $line .= Xml::tags(
                "sup",
                ["class" => "whois plainlinks"],
                "[$link]",
            );
        }

        $line .=
            " " . ModerationCompatTools::commentBlock($row->comment, $title);

        if (!$row->merged_revid) {
            $line .= " [";
            if ($row->conflict) {
                $class .= " modconflict";

                // In order to merge, moderator must also be automoderated
                if (
                    $this->canSkip->canEditSkip(
                        $this->getModerator(),
                        $row->namespace,
                    )
                ) {
                    $line .= $actionLinkRenderer->makeLink("merge", $row->id);
                } else {
                    $line .= $this->msg(
                        "moderation-no-merge-link-not-automoderated",
                    )->plain();
                }
            } else {
                if (
                    !$row->rejected ||
                    $this->timestampTools->canReapproveRejected($row->timestamp)
                ) {
                    $line .= $actionLinkRenderer->makeLink("approve", $row->id);
                }

                # Note: you can use "Approve all" on rejected edit,
                # but it will only affect not-yet-rejected edits.
                # To avoid confusion, link "Approve all" is not shown for rejected edits.
                if (!$row->rejected) {
                    $line .= " ";
                    $line .= $actionLinkRenderer->makeLink(
                        "approveall",
                        $row->id,
                    );
                }
            }

            if (!$row->rejected) {
                $line .= " . . ";
                $line .= $actionLinkRenderer->makeLink("reject", $row->id);
                $line .= " ";
                $line .= $actionLinkRenderer->makeLink("rejectall", $row->id);
            }
            $line .= "]";
        } else {
            $line .=
                " [" .
                $linkRenderer->makePreloadedLink(
                    $title,
                    $this->msg("moderation-merged-link")->plain(),
                    "",
                    [
                        "title" => $this->msg(
                            "tooltip-moderation-merged-link",
                        )->plain(),
                    ],
                    ["diff" => $row->merged_revid],
                ) .
                "]";
        }

        $line .= " . . [";
        $line .= $actionLinkRenderer->makeLink(
            $row->blocked ? "unblock" : "block",
            $row->id,
        );
        $line .= "]";

        if ($row->rejected) {
            $line .= " . . ";

            if ($row->rejected_by_user) {
                $line .= $this->msg(
                    "moderation-rejected-by",
                    Linker::userLink(
                        $row->rejected_by_user,
                        $row->rejected_by_user_text,
                    ),
                    $row->rejected_by_user_text, // plain username for {{gender:}} syntax
                )->text();
            } elseif ($row->rejected_auto) {
                $line .= $this->msg("moderation-rejected-auto")->plain();
            }

            if ($row->rejected_batch) {
                $line .=
                    " . . " . $this->msg("moderation-rejected-batch")->plain();
            }
        }

        $html = Xml::tags("span", ["class" => $class], $line);

        return $html;
    }
}
