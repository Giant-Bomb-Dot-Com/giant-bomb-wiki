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
 * Utility functions used in both benchmark and PHPUnit Testsuite.
 */

namespace MediaWiki\Moderation\Tests;

use CommentStoreComment;
use ContentHandler;
use IContextSource;
use Language;
use MediaWiki\MediaWikiServices;
use MediaWiki\Moderation\ModerationCompatTools;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use RequestContext;
use User;

class ModerationTestUtil
{
    /**
     * Suppress unneeded/temporary deprecation messages caused by compatibility with older MediaWiki.
     * @param MediaWikiIntegrationTestCase $tc @phan-unused-param
     */
    public static function ignoreKnownDeprecations(
        MediaWikiIntegrationTestCase $tc,
    ) {
        // Temporary (1.44 only): MediaWiki core itself calls this in Skin.php.
        $tc->hideDeprecated(
            "MediaWiki\\Skin\\Skin::appendSpecialPagesLinkIfAbsent",
        );
    }

    /**
     * Edit the page by directly modifying the database. Very fast.
     *
     * This is used for initialization of tests.
     * For example, if moveQueue benchmark needs 500 existing pages,
     * it would take forever for doEditContent() to create them all,
     * much longer than the actual benchmark.
     * @param Title $title
     * @param string $newText
     * @param string $summary
     * @param User|null $user
     */
    public static function fastEdit(
        Title $title,
        $newText = "Whatever",
        $summary = "",
        User $user = null,
    ) {
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);

        $page = ModerationCompatTools::makeWikiPage($title);
        $page->insertOn($dbw);

        if (!$user) {
            $user = User::newFromName("127.0.0.1", false);
        }

        $rev = new MutableRevisionRecord($title);

        $rev->setComment(CommentStoreComment::newUnsavedComment($summary));
        $rev->setUser($user);
        $rev->setTimestamp($dbw->timestamp());

        $content = ContentHandler::makeContent(
            $newText,
            null,
            CONTENT_MODEL_WIKITEXT,
        );
        $rev->setContent(SlotRecord::MAIN, $content);

        $store = MediaWikiServices::getInstance()->getRevisionStore();
        $storedRecord = $store->insertRevisionOn($rev, $dbw);

        $page->updateRevisionOn($dbw, $storedRecord);
    }

    /**
     * Render Special:Moderation with $params.
     * @param User $user
     * @param array $params
     * @param bool $wasPosted
     * @param IContextSource|null &$context Used context will be written here. @phan-output-reference
     * @return string HTML of the result.
     */
    public static function runSpecialModeration(
        User $user,
        array $params,
        $wasPosted = false,
        IContextSource &$context = null,
    ) {
        $page = MediaWikiServices::getInstance()
            ->getSpecialPageFactory()
            ->getPage("Moderation");

        $context = new RequestContext();
        $context->setRequest(new FauxRequest($params, $wasPosted));
        $context->setTitle($page->getPageTitle());
        $context->setUser($user);
        $context->setLanguage(self::getLanguageQqx());

        $page->setContext($context);
        $page->execute("");

        return $context->getOutput()->getHTML();
    }

    /**
     * Returns Language object for "qqx" pseudo-language (convenient for tests).
     * @return Language
     */
    public static function getLanguageQqx()
    {
        return MediaWikiServices::getInstance()
            ->getLanguageFactory()
            ->getLanguage("qqx");
    }

    /**
     * Shortcut for UrlUtils::parse(). Can use relative (non-expanded) URLs.
     * @param string $url
     * @return array
     * @phan-return array<string,string>
     */
    public static function parseUrl($url)
    {
        $urlUtils = MediaWikiServices::getInstance()->getUrlUtils();

        return $urlUtils->parse($urlUtils->expand($url) ?? "") ?? [];
    }
}
