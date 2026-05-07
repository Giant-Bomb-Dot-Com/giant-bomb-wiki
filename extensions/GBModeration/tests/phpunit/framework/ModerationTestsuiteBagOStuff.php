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
 * Selectively cleanable BagOStuff. Used for parallel PHPUnit testing.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Session\SessionManager;
use MediumSpecificBagOStuff;
use Wikimedia\ObjectCache\HashBagOStuff;

class ModerationTestsuiteBagOStuff extends MediumSpecificBagOStuff
{
    /**
     * @var HashBagOStuff|null
     * Singleton: no matter how many TestsuiteBagOStuff instances are made,
     * there will be only one store.
     */
    protected static $store = null;

    /**
     * @param array $params
     */
    public function __construct($params = [])
    {
        parent::__construct($params);

        if (self::$store) {
            // Already loaded.
            return;
        }

        // Load all entries into HashBagOStuff.
        $filename = self::defaultFileName();
        if (file_exists($filename)) {
            self::$store = unserialize(file_get_contents($filename));
        } else {
            self::$store = new HashBagOStuff();
        }

        register_shutdown_function([$this, "saveOnShutdown"]);
    }

    /**
     * Get name of the file where this cache will be stored.
     * @return string
     */
    protected static function defaultFileName()
    {
        $filename = "/dev/shm/modtest.cache";

        // When running multiple PHPUnit tests in parallel via Fastest,
        // this environment variable will be set to 1, 2, 3, ... for different threads.
        $thread = getenv("ENV_TEST_CHANNEL");
        if ($thread) {
            $filename .= ".thread$thread";
        }

        return $filename;
    }

    /**
     * Empty the on-disk cache. This is used by ModerationTestsuite for cache invalidation.
     */
    public static function flushAll()
    {
        $filename = self::defaultFileName();
        if (file_exists($filename)) {
            unlink($filename);
        }

        self::$store = null;
    }

    /**
     * Save this in-memory HashBagOStuff into the on-disk file.
     */
    public static function saveOnShutdown()
    {
        // SessionManager also uses register_shutdown_function() to save all active sessions,
        // and it's possible that SessionManager's shutdown handler would get called later.
        // So we must call save() explicitly to place session data info self::$store immediately.
        $session = SessionManager::getGlobalSession();
        $session->save();

        file_put_contents(self::defaultFileName(), serialize(self::$store));
    }

    // Proxy all get/set/delete methods to a singleton HashBagOStuff

    /** @inheritDoc */
    protected function doGet($key, $flags = 0, &$casToken = null)
    {
        $casToken = null;
        return self::$store->get($key, $flags);
    }

    /** @inheritDoc */
    protected function doSet($key, $value, $exptime = 0, $flags = 0)
    {
        return self::$store->set($key, $value, $exptime, $flags);
    }

    /** @inheritDoc */
    protected function doAdd($key, $value, $exptime = 0, $flags = 0)
    {
        return self::$store->add($key, $value, $exptime, $flags);
    }

    /** @inheritDoc */
    protected function doDelete($key, $flags = 0)
    {
        return self::$store->delete($key, $flags);
    }

    /** @inheritDoc */
    protected function doIncrWithInit($key, $exptime, $step, $init, $flags)
    {
        return self::$store->incrWithInit($key, $exptime, $step, $init, $flags);
    }

    /** @inheritDoc */
    public function makeKeyInternal($keyspace, $args)
    {
        $key = $keyspace;
        foreach ($args as $arg) {
            $key .= ":" . str_replace(":", "%3A", $arg);
        }
        return strtr($key, " ", "_");
    }
}
