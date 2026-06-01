<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2014-2025 Edward Chernenko.

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
 * Implements modaction=show on [[Special:Moderation]].
 */

namespace MediaWiki\Moderation;

use OutputPage;
use Xml;

class ModerationActionShow extends ModerationAction
{
    public function requiresEditToken()
    {
        return false;
    }

    public function requiresWrite()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function outputResult(array $result, OutputPage $out)
    {
        $out->addModuleStyles(["mediawiki.diff.styles"]);
        $out->setPageTitle(
            $this->msg("difference-title", $result["title"])->escaped(),
        );

        if (isset($result["image-thumb-html"])) {
            $out->addHTML(
                Xml::tags(
                    "a",
                    [
                        "href" => $result["image-url"],
                    ],
                    $result["image-thumb-html"],
                ),
            );
        }

        if (isset($result["diff-html"])) {
            $out->addHTML($result["diff-html"]);
        } else {
            $out->addWikiMsg($result["nodiff-reason"]);
        }

        if (!isset($result["null-edit"])) {
            $out->addHTML(
                $this->actionLinkRenderer->makeLink("approve", $this->id),
            );
            $out->addHTML(" / ");
        }

        if (isset($result["rejected-message"])) {
            $out->addHTML($result["rejected-message"]);
        } else {
            $out->addHTML(
                $this->actionLinkRenderer->makeLink("reject", $this->id),
            );
        }
    }

    public function execute()
    {
        $result = [];

        $entry = $this->entryFactory->findViewableEntry($this->id);
        $title = $entry->getTitle();

        if ($entry->isUpload()) {
            $result["image-url"] = $entry->getImageURL();
            $result["image-thumb-html"] = $entry->getImageThumbHTML();
        }

        $diff = $entry->getDiffHTML($this->getContext());
        if ($diff) {
            $result["diff-html"] = $diff;
        } else {
            if ($entry->isUpload()) {
                $result["nodiff-reason"] = $title->exists()
                    ? "moderation-diff-reupload"
                    : "moderation-diff-upload-notext";
            } else {
                $result["nodiff-reason"] = "moderation-diff-no-changes";
                $result["null-edit"] = "";
            }
        }

        $rejectedBy = $entry->getRejectedBy();
        if ($rejectedBy) {
            $result["rejected-message"] = $rejectedBy;
        }

        $result["title"] = $title->getPrefixedText();
        return $result;
    }
}
