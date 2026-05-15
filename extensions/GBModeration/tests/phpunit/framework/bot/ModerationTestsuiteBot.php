<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2018-2024 Edward Chernenko.

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
 * Implements testsuite methods edit(), upload() and move().
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Title\Title;
use MWException;

abstract class ModerationTestsuiteBot
{
    /** @var ModerationTestsuite */
    private $t;

    /**
     * @param ModerationTestsuite $t
     */
    protected function __construct(ModerationTestsuite $t)
    {
        $this->t = $t;
    }

    /** @return ModerationTestsuite */
    private function getTestsuite()
    {
        return $this->t;
    }

    /**
     * Create a new bot.
     * @param string $method One of the following: 'api', 'nonApi'.
     * @param ModerationTestsuite $t
     * @return ModerationTestsuiteBot
     */
    public static function factory($method, ModerationTestsuite $t)
    {
        switch ($method) {
            case "any":
            case "api":
                return new ModerationTestsuiteApiBot($t);
            case "nonApi":
                return new ModerationTestsuiteNonApiBot($t);
        }

        throw new MWException("Unsupported method in " . __METHOD__);
    }

    /**
     * Perform a test edit.
     * @param string|null $title
     * @param string|null $text
     * @param string|null $summary
     * @param string|int $section One of the following: section number, empty string or 'new'.
     * @param array $extraParams Bot-specific parameters.
     * @return ModerationTestsuiteApiBotResponse|ModerationTestsuiteNonApiBotResponse
     */
    final public function edit(
        $title = null,
        $text = null,
        $summary = null,
        $section = "",
        array $extraParams = [],
    ) {
        $t = $this->getTestsuite();

        if (!$title) {
            $title = $this->generateRandomTitle();
        }

        if (!$text) {
            $text = $this->generateRandomText();
        }

        if (!$summary) {
            $summary = $this->generateEditSummary();
        }

        $result = $this->doEdit(
            $t,
            $title,
            $text,
            $summary,
            $section,
            $extraParams,
        );
        $t->setLastEdit($title, $summary, ["Text" => $text]);

        return $result;
    }

    /**
     * Perform a test move.
     * @param string $oldTitle
     * @param string $newTitle
     * @param string $reason
     * @param array $extraParams Bot-specific parameters.
     * @return ModerationTestsuiteApiBotResponse|ModerationTestsuiteNonApiBotResponse
     */
    final public function move(
        $oldTitle,
        $newTitle,
        $reason = "",
        array $extraParams = [],
    ) {
        $t = $this->getTestsuite();
        $result = $this->doMove(
            $t,
            $oldTitle,
            $newTitle,
            $reason,
            $extraParams,
        );
        $t->setLastEdit($oldTitle, $reason, ["NewTitle" => $newTitle]);

        return $result;
    }

    /**
     * Perform a test upload.
     * @param string|null $title
     * @param string|null $srcFilename
     * @param string|null $text
     * @param array $extraParams Bot-specific parameters.
     * @return ModerationTestsuiteApiBotResponse|ModerationTestsuiteNonApiBotResponse
     */
    final public function upload(
        $title = null,
        $srcFilename = null,
        $text = null,
        array $extraParams = [],
    ) {
        $t = $this->getTestsuite();

        if (!$title) {
            $title = $this->generateRandomTitle() . ".png";
        }

        if ($text === null) {
            # Empty string (no description) is allowed
            $text = $this->generateRandomText();
        }

        $srcPath = $t->findSourceFilename($srcFilename);
        $result = $this->doUpload($t, $title, $srcPath, $text, $extraParams);

        $t->setLastEdit(
            Title::newFromText($title, NS_FILE)->getFullText(),
            "" /* Summary wasn't used */,
            [
                "Text" => $text,
                "SHA1" => sha1_file($srcPath),
                "Source" => $srcPath,
            ],
        );

        return $result;
    }

    /**
     * Bot-specific (e.g. API or non-API) implementation of edit().
     * Make an edit via API.
     * @param ModerationTestsuite $t
     * @param string $title
     * @param string $text
     * @param string $summary
     * @param string|int $section
     * @param array $extraParams Bot-specific parameters.
     * @return ModerationTestsuiteApiBotResponse|ModerationTestsuiteNonApiBotResponse
     */
    abstract public function doEdit(
        ModerationTestsuite $t,
        $title,
        $text,
        $summary,
        $section,
        array $extraParams,
    );

    /**
     * Bot-specific (e.g. API or non-API) implementation of move().
     * @param ModerationTestsuite $t
     * @param string $oldTitle
     * @param string $newTitle
     * @param string $reason
     * @param array $extraParams Bot-specific parameters.
     * @return ModerationTestsuiteApiBotResponse|ModerationTestsuiteNonApiBotResponse
     */
    abstract public function doMove(
        ModerationTestsuite $t,
        $oldTitle,
        $newTitle,
        $reason,
        array $extraParams,
    );

    /**
     * Bot-specific (e.g. API or non-API) implementation of upload().
     * @param ModerationTestsuite $t
     * @param string $title
     * @param string $srcPath
     * @param string $text
     * @param array $extraParams Bot-specific parameters.
     * @return ModerationTestsuiteApiBotResponse|ModerationTestsuiteNonApiBotResponse
     */
    abstract public function doUpload(
        ModerationTestsuite $t,
        $title,
        $srcPath,
        $text,
        array $extraParams,
    );

    /**
     * Get sample page name (used when the test hasn't specified it).
     * @return string
     */
    private function generateRandomTitle()
    {
        // Simple string, no underscores
        return "Test page " . $this->t->uniqueSuffix();
    }

    /**
     * Get sample text of the page (used when the test hasn't specified it).
     * @return string
     */
    private function generateRandomText()
    {
        return "Hello, World!";
    }

    /**
     * Get sample edit summary (used when the test hasn't specified it).
     * @return string
     */
    private function generateEditSummary()
    {
        // NOTE: No wikitext! Plaintext only.
        // Otherwise we'll have to run it through the parser before
        // comparing to what's shown on Special:Moderation.

        return "Edit by the Moderation Testsuite";
    }
}
