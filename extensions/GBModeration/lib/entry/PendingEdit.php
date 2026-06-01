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
 * Object that represents one preloadable edit that is currently awaiting moderation.
 */

namespace MediaWiki\Moderation;

use ContentHandler;
use MediaWiki\Title\Title;

class PendingEdit
{
    /** @var Title */
    protected $title;

    /**
     * @var int
     * mod_id of this pending edit.
     */
    protected $id;

    /** @var string */
    protected $text;

    /** @var string */
    protected $comment;

    /**
     * @param Title $title
     * @param int $id
     * @param string $text
     * @param string $comment
     */
    public function __construct(Title $title, $id, $text, $comment)
    {
        $this->title = $title;
        $this->id = $id;
        $this->text = $text;
        $this->comment = $comment;
    }

    /**
     * Get Title of this pending edit.
     * @return Title
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Get mod_id of this pending edit.
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get text of this pending edit.
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * Get edit summary of this pending edit.
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * Get text of one section of this pending edit.
     * @param string|int $sectionId Section identifier as a number or string (e.g. 0, 1 or 'T-1').
     * @return string
     */
    public function getSectionText($sectionId)
    {
        if ($sectionId === "") {
            // Return full text (no particular section was requested).
            return $this->text;
        }

        $fullContent = ContentHandler::makeContent($this->text, $this->title);
        $sectionContent = $fullContent->getSection($sectionId);
        if ($sectionContent) {
            return $sectionContent->serialize();
        }

        // Return full text (requested section wasn't found).
        return $this->text;
    }
}
