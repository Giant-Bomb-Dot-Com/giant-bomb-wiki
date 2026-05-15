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
 * Functions for seamless database updates between versions.
 */

namespace MediaWiki\Moderation;

use BagOStuff;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IMaintainableDatabase;

class ModerationVersionCheck
{
    /** @var BagOStuff */
    protected $cache;

    /** @var ILoadBalancer */
    protected $loadBalancer;

    /**
     * @param BagOStuff $cache
     * @param ILoadBalancer $loadBalancer
     */
    public function __construct(BagOStuff $cache, ILoadBalancer $loadBalancer)
    {
        $this->cache = $cache;
        $this->loadBalancer = $loadBalancer;
    }

    /*-------------------------------------------------------------------*/

    /**
     * Check if update.php was called after $versionOfModeration was installed.
     * @param string $versionOfModeration Version of Extension:Moderation, as listed in extension.json.
     * @return bool True if update.php was called, false otherwise.
     */
    protected static function wasDbUpdatedAfter($versionOfModeration)
    {
        $versionCheck = MediaWikiServices::getInstance()->getService(
            "Moderation.VersionCheck",
        );
        return version_compare(
            $versionOfModeration,
            $versionCheck->getDbUpdatedVersion(),
            "<=",
        );
    }

    /**
     * Returns memcached key used by getDbUpdatedVersion() and invalidateCache()
     * @return string
     */
    protected function getCacheKey()
    {
        return $this->cache->makeKey("moderation-lastDbUpdateVersion");
    }

    /**
     * Returns version that Moderation had during the latest invocation of update.php.
     * @return string Version number, e.g. "1.2.3".
     */
    protected function getDbUpdatedVersion()
    {
        $cacheKey = $this->getCacheKey();

        $result = $this->cache->get($cacheKey);
        if ($result === false) {
            /* Not found in the cache */
            $db = $this->loadBalancer->getMaintenanceConnectionRef(DB_REPLICA);
            $result = $this->getDbUpdatedVersionUncached($db);
            $this->cache->set($cacheKey, $result, 86400); /* 24 hours */
        }

        return $result;
    }

    /**
     * Uncached version of getDbUpdatedVersion().
     * @note Shouldn't be used outside of getDbUpdatedVersion()
     * @param IMaintainableDatabase $db @phan-unused-param
     * @return string Version number, e.g. "1.2.3".
     */
    protected function getDbUpdatedVersionUncached(IMaintainableDatabase $db)
    {
        // Support for older database schema (for Moderation 1.2.17 and earlier) has been dropped
        // in Moderation 1.6.0, and there were no DB schema changes since then.
        return "1.6.0";
    }

    /**
     * Invalidate cache of wasDbUpdatedAfter().
     * Called from update.php.
     * Note: this won't affect CACHE_ACCEL, update.php has no access to it.
     */
    public static function invalidateCache()
    {
        $versionCheck = MediaWikiServices::getInstance()->getService(
            "Moderation.VersionCheck",
        );
        $versionCheck->invalidateCacheInternal();
    }

    /**
     * Main logic of invalidateCache().
     */
    protected function invalidateCacheInternal()
    {
        $this->cache->delete($this->getCacheKey());
    }
}
