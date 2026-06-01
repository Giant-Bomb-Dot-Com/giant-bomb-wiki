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
 * Register services like ActionFactory in MediaWikiServices container.
 */

namespace MediaWiki\Moderation;

use CachedBagOStuff;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Moderation\Hook\HookRunner;
use ObjectCache;
use RequestContext;
use SpecialPage;

// @codeCoverageIgnoreStart
// (PHPUnit doesn't support @covers for out-of-class code)
// See also: T248172 "Allow static methods to be used for wiring"

return [
    "Moderation.ActionFactory" => static function (
        MediaWikiServices $services,
    ): ActionFactory {
        return new ActionFactory(
            $services->getService("Moderation.EntryFactory"),
            $services->getService("Moderation.ConsequenceManager"),
            $services->getService("Moderation.CanSkip"),
            $services->getService("Moderation.EditFormOptions"),
            $services->getService("Moderation.ActionLinkRenderer"),
            $services->getRepoGroup(),
            $services->getContentLanguage(),
            $services->getRevisionRenderer(),
            $services->getReadOnlyMode(),
        );
    },
    "Moderation.ActionLinkRenderer" => static function (
        MediaWikiServices $services,
    ): ActionLinkRenderer {
        return new ActionLinkRenderer(
            RequestContext::getMain(),
            $services->getLinkRenderer(),
            SpecialPage::getTitleFor("Moderation"),
        );
    },
    "Moderation.ApproveHook" => static function (
        MediaWikiServices $services,
    ): ModerationApproveHook {
        return new ModerationApproveHook(
            LoggerFactory::getInstance("ModerationApproveHook"),
            $services->getChangeTagsStore(),
        );
    },
    "Moderation.CanSkip" => static function (
        MediaWikiServices $services,
    ): ModerationCanSkip {
        return new ModerationCanSkip(
            new ServiceOptions(
                ModerationCanSkip::CONSTRUCTOR_OPTIONS,
                $services->getMainConfig(),
            ),
            $services->getService("Moderation.ApproveHook"),
        );
    },
    "Moderation.ConsequenceManager" => static function (): ConsequenceManager {
        return new ConsequenceManager();
    },
    "Moderation.EditFormOptions" => static function (
        MediaWikiServices $services,
    ): EditFormOptions {
        return new EditFormOptions(
            $services->getService("Moderation.ConsequenceManager"),
        );
    },
    "Moderation.EntryFactory" => static function (
        MediaWikiServices $services,
    ): EntryFactory {
        return new EntryFactory(
            $services->getLinkRenderer(),
            $services->getService("Moderation.ActionLinkRenderer"),
            $services->getService("Moderation.TimestampTools"),
            $services->getService("Moderation.ConsequenceManager"),
            $services->getService("Moderation.CanSkip"),
            $services->getService("Moderation.ApproveHook"),
            $services->getContentHandlerFactory(),
            $services->getRevisionLookup(),
        );
    },
    "Moderation.HookRunner" => static function (
        MediaWikiServices $services,
    ): HookRunner {
        return new HookRunner($services->getHookContainer());
    },
    "Moderation.NewChangeFactory" => static function (
        MediaWikiServices $services,
    ): NewChangeFactory {
        return new NewChangeFactory(
            $services->getService("Moderation.ConsequenceManager"),
            $services->getService("Moderation.Preload"),
            $services->getService("Moderation.HookRunner"),
            $services->getService("Moderation.NotifyModerator"),
            $services->getContentLanguage(),
        );
    },
    "Moderation.NotifyModerator" => static function (
        MediaWikiServices $services,
    ): ModerationNotifyModerator {
        return new ModerationNotifyModerator(
            $services->getLinkRenderer(),
            $services->getService("Moderation.EntryFactory"),
            ObjectCache::getLocalClusterInstance(),
        );
    },
    "Moderation.Preload" => static function (
        MediaWikiServices $services,
    ): ModerationPreload {
        return new ModerationPreload(
            $services->getService("Moderation.EntryFactory"),
            $services->getService("Moderation.ConsequenceManager"),
        );
    },
    "Moderation.RollbackResistantQuery" => static function (
        MediaWikiServices $services,
    ): RollbackResistantQuery {
        return new RollbackResistantQuery($services->getDBLoadBalancer());
    },
    "Moderation.TimestampTools" => static function (): TimestampTools {
        return new TimestampTools();
    },
    "Moderation.VersionCheck" => static function (
        MediaWikiServices $services,
    ): ModerationVersionCheck {
        return new ModerationVersionCheck(
            new CachedBagOStuff(ObjectCache::getLocalClusterInstance()),
            $services->getDBLoadBalancer(),
        );
    },
];

// @codeCoverageIgnoreEnd
