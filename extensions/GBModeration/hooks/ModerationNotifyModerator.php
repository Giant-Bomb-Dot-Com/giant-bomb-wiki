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
 * Hooks that notify the moderators that new pending edit has appeared in the moderation queue.
 */

namespace MediaWiki\Moderation;

use BagOStuff;
use IContextSource;
use MediaWiki\Hook\GetNewMessagesAlertHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use OutputPage;
use SpecialPage;
use User;

class ModerationNotifyModerator implements GetNewMessagesAlertHook
{
    /** @var LinkRenderer */
    protected $linkRenderer;

    /** @var EntryFactory */
    protected $entryFactory;

    /** @var BagOStuff */
    protected $cache;

    /**
     * @param LinkRenderer $linkRenderer
     * @param EntryFactory $entryFactory
     * @param BagOStuff $cache
     */
    public function __construct(
        LinkRenderer $linkRenderer,
        EntryFactory $entryFactory,
        BagOStuff $cache,
    ) {
        $this->linkRenderer = $linkRenderer;
        $this->entryFactory = $entryFactory;
        $this->cache = $cache;
    }

    /**
     * Used in extension.json to obtain this service as HookHandler.
     * @return ModerationNotifyModerator
     */
    public static function hookHandlerFactory()
    {
        return MediaWikiServices::getInstance()->getService(
            "Moderation.NotifyModerator",
        );
    }

    /**
     * EchoCanAbortNewMessagesAlert hook.
     * Here we prevent Extension:Echo from suppressing our notification.
     * @return bool
     */
    public function onEchoCanAbortNewMessagesAlert()
    {
        return false;
    }

    /**
     * GetNewMessagesAlert hook.
     * Shows in-wiki notification "new edits are pending moderation" to moderators.
     * @param string &$newMessagesAlert
     * @param array $newtalks @phan-unused-param
     * @param User $user @phan-unused-param
     * @param OutputPage $out
     * @return bool|void
     */
    public function onGetNewMessagesAlert(
        &$newMessagesAlert,
        $newtalks,
        $user,
        $out,
    ) {
        $notificationHtml = $this->getNotificationHTML($out);
        if ($notificationHtml) {
            $newMessagesAlert .= "\n" . $notificationHtml;
        }
    }

    /**
     * Get the HTML of "new edits are pending" notification. Empty string if notification isn't needed.
     * @param IContextSource $context
     * @return string
     */
    protected function getNotificationHTML(IContextSource $context)
    {
        $user = $context->getUser();
        if (!$user->isAllowed("moderation")) {
            return ""; /* Not a moderator */
        }

        if ($context->getTitle()->isSpecial("Moderation")) {
            return ""; /* No need to show on Special:Moderation */
        }

        /* Determine the most recent mod_timestamp of pending edit */
        $pendingTime = $this->getPendingTime();
        if (!$pendingTime) {
            return ""; /* No pending changes */
        }

        /*
			Determine if $user visited Special:Moderation after $pendingTime.

			NOTE: $seenTime being false means that moderator hasn't visited
			Special:Moderation for 7 days, so we always notify.
		*/
        $seenTime = $this->getSeen($user);
        if ($seenTime && $seenTime >= $pendingTime) {
            return ""; /* No new changes appeared after this moderator last visited Special:Moderation */
        }

        return $this->linkRenderer->makeLink(
            SpecialPage::getTitleFor("Moderation"),
            $context->msg("moderation-new-changes-appeared")->plain(),
        );
    }

    /**
     * Returns memcached key used by getPendingTime()/setPendingTime()
     * @return string
     */
    protected function getPendingCacheKey()
    {
        return $this->cache->makeKey("moderation-newest-pending-timestamp");
    }

    /**
     * Returns most recent mod_timestamp of pending edit.
     * @return string
     */
    protected function getPendingTime()
    {
        $cacheKey = $this->getPendingCacheKey();

        $result = $this->cache->get($cacheKey);
        if ($result === false) {
            /* Not found in the cache */
            $result = $this->getPendingTimeUncached();
            if (!$result) {
                /* Situation "there are no pending edits" must also be cached */
                $result = 0;
            }

            // 24 hours, but can be explicitly renewed by setPendingTime()
            $this->cache->set($cacheKey, $result, 86400);
        }

        return $result;
    }

    /**
     * Uncached version of getPendingTime(). Shouldn't be used outside of getPendingTime().
     * @return string|false
     */
    protected function getPendingTimeUncached()
    {
        $row = $this->entryFactory->loadRow(
            ["mod_rejected" => 0, "mod_merged_revid" => 0],
            ["mod_timestamp AS timestamp"],
            DB_REPLICA,
            ["USE INDEX" => "moderation_folder_pending"],
        );
        return $row ? $row->timestamp : false;
    }

    /**
     * Update the cache of getPendingTime() with more actual value.
     * @param string $newTimestamp
     */
    public function setPendingTime($newTimestamp)
    {
        $this->cache->set(
            $this->getPendingCacheKey(),
            $newTimestamp,
            86400,
        ); /* 24 hours */
    }

    /**
     * Clear the cache of getPendingTime().
     * Used instead of setPendingTime() when we don't know $newTimestamp,
     * e.g. in modaction=rejectall.
     */
    public function invalidatePendingTime()
    {
        $this->cache->delete($this->getPendingCacheKey());
    }

    /**
     * Returns memcached key used by getSeen()/setSeen()
     * @param User $user
     * @return string
     */
    protected function getSeenCacheKey(User $user)
    {
        return $this->cache->makeKey(
            "moderation-seen-timestamp",
            (string) $user->getId(),
        );
    }

    /**
     * Get newest mod_timestamp seen by $user (if known) or false.
     * @param User $user
     * @return string|false
     */
    protected function getSeen(User $user)
    {
        return $this->cache->get($this->getSeenCacheKey($user));
    }

    /**
     * Remember the newest mod_timestamp seen by $user.
     * @param User $user
     * @param string $timestamp
     */
    public function setSeen(User $user, $timestamp)
    {
        $this->cache->set(
            $this->getSeenCacheKey($user),
            $timestamp,
            604800,
        ); /* 7 days */
    }
}
