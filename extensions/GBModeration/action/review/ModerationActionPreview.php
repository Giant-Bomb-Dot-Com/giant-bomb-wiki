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
 * Implements modaction=preview on [[Special:Moderation]].
 */

namespace MediaWiki\Moderation;

use MediaWiki\MediaWikiServices;
use OutputPage;

class ModerationActionPreview extends ModerationAction
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
        $out->setPageTitle(
            $this->msg("moderation-preview-title", $result["title"])->escaped(),
        );
        $out->addHTML($result["html"]);
        $out->addCategoryLinks($result["categories"]);
    }

    public function execute()
    {
        $entry = $this->entryFactory->findViewableEntry($this->id);
        $title = $entry->getTitle();

        $renderedRevision = $this->revisionRenderer->getRenderedRevision(
            $entry->getPendingRevision(),
        );
        $pout = $renderedRevision->getRevisionParserOutput();

        // Remove edit section links.
        $pipeline = MediaWikiServices::getInstance()->getDefaultOutputPipeline();
        $pout = $pipeline->run($pout, null, [
            "enableSectionEditLinks" => false,
        ]);

        return [
            "title" => $title->getPrefixedText(),
            "html" => $pout->getRawText(),
            "categories" => ModerationCompatTools::getParserOutputCategories(
                $pout,
            ),
        ];
    }
}
