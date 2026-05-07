<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2025 Edward Chernenko.

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
 * Backward compatibility functions to support older versions of MediaWiki.
 */

namespace MediaWiki\Moderation;

use Content;
use IContextSource;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ParserOptions;
use ParserOutput;
use User;
use WikiPage;

class ModerationCompatTools
{
    /**
     * Replace things like "~~~~" in $content.
     * @param Content $content
     * @param Title $title
     * @param User $user
     * @param ParserOptions $popts
     * @return Content
     */
    public static function preSaveTransform(
        Content $content,
        Title $title,
        User $user,
        ParserOptions $popts,
    ) {
        $contentTransformer = MediaWikiServices::getInstance()->getContentTransformer();
        return $contentTransformer->preSaveTransform(
            $content,
            $title,
            $user,
            $popts,
        );
    }

    /**
     * Create a WikiPage object from LinkTarget.
     * @param LinkTarget $title
     * @return WikiPage
     */
    public static function makeWikiPage(LinkTarget $title)
    {
        $factory = MediaWikiServices::getInstance()->getWikiPageFactory();
        return $factory->newFromLinkTarget($title);
    }

    /**
     * Get current action name (e.g. "edit" or "upload") from Context.
     * @param IContextSource $context
     * @return string
     */
    public static function getActionName(IContextSource $context)
    {
        return MediaWikiServices::getInstance()
            ->getActionFactory()
            ->getActionName($context);
    }

    /**
     * Use methods of Extension:CheckUser to locate the client IP within the XFF string.
     * @param string|bool $xff
     * @return array
     */
    public static function getClientIPfromXFF($xff)
    {
        $services = MediaWikiServices::getInstance();
        if ($services->has("CheckUserUtilityService")) {
            return $services
                ->get("CheckUserUtilityService")
                ->getClientIPfromXFF($xff);
        }

        // Extension:CheckUser is not installed.
        return [null, false, ""];
    }

    /**
     * Wrap edit comment in the necessary punctuation.
     * @param string $comment
     * @param LinkTarget|null $selfLinkTarget
     * @return string
     */
    public static function commentBlock($comment, $selfLinkTarget)
    {
        $commentFormatter = MediaWikiServices::getInstance()->getCommentFormatter();
        return $commentFormatter->formatBlock($comment, $selfLinkTarget);
    }

    /**
     * Get the list of categories in the ParserOutput object.
     * @param ParserOutput $pout
     * @return array<string,string>
     */
    public static function getParserOutputCategories(ParserOutput $pout)
    {
        // This is backward compatibility format, we don't really need to provide sortkeys here.
        return array_fill_keys($pout->getCategoryNames(), "");
    }

    /**
     * Get a Database object.
     * @param int $db Either DB_PRIMARY or DB_REPLICA.
     * @return \Wikimedia\Rdbms\IDatabase
     */
    public static function getDB($db)
    {
        return MediaWikiServices::getInstance()
            ->getDBLoadBalancer()
            ->getConnection($db);
    }
}
