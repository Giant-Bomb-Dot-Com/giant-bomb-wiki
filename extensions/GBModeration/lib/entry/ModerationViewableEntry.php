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
 * Formatter for displaying entry in modaction=show.
 */

namespace MediaWiki\Moderation;

use ContentHandler;
use IContextSource;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Linker\Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use SpecialPage;
use stdClass;
use UploadStashException;
use Xml;

class ModerationViewableEntry extends ModerationEntry
{
    /** @var LinkRenderer */
    protected $linkRenderer;

    /** @var IContentHandlerFactory */
    protected $contentHandlerFactory;

    /** @var RevisionLookup */
    protected $revisionLookup;

    /**
     * @param stdClass $row
     * @param LinkRenderer $linkRenderer
     * @param IContentHandlerFactory $contentHandlerFactory
     * @param RevisionLookup $revisionLookup
     */
    public function __construct(
        $row,
        LinkRenderer $linkRenderer,
        IContentHandlerFactory $contentHandlerFactory,
        RevisionLookup $revisionLookup,
    ) {
        parent::__construct($row);

        $this->linkRenderer = $linkRenderer;
        $this->contentHandlerFactory = $contentHandlerFactory;
        $this->revisionLookup = $revisionLookup;
    }

    /**
     * Get the list of fields needed for selecting $row from database.
     * @return array
     */
    public static function getFields()
    {
        return array_merge(parent::getFields(), [
            "mod_last_oldid AS last_oldid",
            "mod_text AS text",
            "mod_stash_key AS stash_key",
            "mod_rejected_by_user AS rejected_by_user",
            "mod_rejected_by_user_text AS rejected_by_user_text",
        ]);
    }

    /**
     * True if this is an upload, false otherwise.
     * @return bool
     */
    public function isUpload()
    {
        $row = $this->getRow();
        return $row->stash_key ? true : false;
    }

    /**
     * Returns HTML of "Rejected by NameOfModerator" message (if rejected) or false (if not rejected).
     * @return string|false
     */
    public function getRejectedBy()
    {
        $row = $this->getRow();
        if (!$row->rejected_by_user) {
            return false;
        }

        return wfMessage(
            "moderation-rejected-by",
            Linker::userLink(
                $row->rejected_by_user,
                $row->rejected_by_user_text,
            ),
            $row->rejected_by_user_text, // plain username for {{gender:}} syntax
        )->text();
    }

    /**
     * Returns HTML of the diff.
     * @param IContextSource $context Any object that contains current context.
     * @return string
     */
    public function getDiffHTML(IContextSource $context)
    {
        $row = $this->getRow();
        $title = $this->getTitle();

        if ($row->stash_key && $title->exists()) {
            // Reupload ($row->text is always blank for reuploads,
            // and they don't change the page text)
            return "";
        }

        if ($this->isMove()) {
            // "Page A moved into B"
            return $context
                ->msg("movepage-page-moved")
                ->rawParams(
                    $this->linkRenderer->makeLink($title),
                    $this->linkRenderer->makeLink($this->getPage2Title()),
                )
                ->parseAsBlock();
        }

        $handler = $this->contentHandlerFactory->getContentHandler(
            $title->getContentModel(),
        );

        $de = $handler->createDifferenceEngine($context);
        $de->setRevisions(
            $this->getPreviousRevision(),
            $this->getPendingRevision(),
        );

        $diff = $de->getDiffBody();
        if (!$diff) {
            return "";
        }

        // TODO: add more information into headers (username, timestamp etc.), as in usual diffs

        return $de->addHeader(
            $diff,
            $context->msg("moderation-diff-header-before")->text(),
            $context->msg("moderation-diff-header-after")->text(),
        );
    }

    /**
     * Get an already existing (not pending) revision that is "previous" for this pending change.
     * @return RevisionRecord
     */
    public function getPreviousRevision()
    {
        $title = $this->getTitle();
        $row = $this->getRow();

        $rev = null;
        if ($row->last_oldid) {
            // Page existed at the moment when this edit was queued
            $rev = $this->revisionLookup->getRevisionById($row->last_oldid);
        }

        if (!$rev) {
            $rev = new MutableRevisionRecord($title);
            $rev->setContent(
                SlotRecord::MAIN,
                ContentHandler::makeContent("", $title),
            );
        }

        return $rev;
    }

    /**
     * Get a not-yet-saved revision with the same content as this pending change.
     * @return RevisionRecord
     */
    public function getPendingRevision()
    {
        $title = $this->getTitle();
        $row = $this->getRow();

        $rev = new MutableRevisionRecord($title);
        $rev->setContent(
            SlotRecord::MAIN,
            ContentHandler::makeContent($row->text, $title),
        );

        return $rev;
    }

    /**
     * Returns false if this file is not an image (e.g. OGG file), true otherwise.
     * @return bool
     */
    protected function isImage()
    {
        $row = $this->getRow();
        $stash = ModerationUploadStorage::getStash();

        try {
            $meta = $stash->getMetadata($row->stash_key);
            $type = $meta["us_media_type"];
        } catch (UploadStashException $_) {
            return false; /* File not found. */
        }

        return $type == "BITMAP" || $type == "DRAWING";
    }

    /**
     * Returns URL of modaction=showimg for this upload.
     * @param bool $isThumb True for thumbnail, false for full-sized image.
     * @return string
     */
    public function getImageURL($isThumb = false)
    {
        $row = $this->getRow();

        $q = [
            "modaction" => "showimg",
            "modid" => (string) $row->id,
        ];
        if ($isThumb) {
            $q["thumb"] = "1";
        }

        $specialTitle = SpecialPage::getTitleFor("Moderation");
        return $specialTitle->getLinkURL($q);
    }

    /**
     * Returns HTML of the image thumbnail.
     * @return string
     */
    public function getImageThumbHTML()
    {
        if (!$this->isUpload()) {
            return ""; /* Not an upload */
        }

        if (!$this->isImage()) {
            # Not an image, so no thumbnail is needed.
            # Just print a filename.
            return $this->getTitle()->getFullText();
        }

        return Xml::element("img", [
            "src" => $this->getImageURL(true),
        ]);
    }
}
