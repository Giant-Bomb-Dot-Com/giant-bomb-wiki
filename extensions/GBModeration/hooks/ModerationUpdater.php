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
 * Creates/updates the SQL tables when 'update.php' is invoked.
 */

namespace MediaWiki\Moderation;

use DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\MediaWikiServices;

class ModerationUpdater implements LoadExtensionSchemaUpdatesHook
{
    /**
     * @param DatabaseUpdater $updater
     * @return bool|void
     */
    public function onLoadExtensionSchemaUpdates($updater)
    {
        $db = $updater->getDB();
        $dbType = $db->getType();

        $sqlDir = __DIR__ . "/../sql/" . $updater->getDB()->getType();

        /* Main database schema */
        $updater->addExtensionTable(
            "moderation",
            "$sqlDir/tables-generated.sql",
        );

        /* DB changes needed when updating Moderation from its previous version */
        if ($dbType == "postgres") {
            // PostgreSQL support was added in Moderation 1.4.12,
            // there were no schema changes since then.
        } elseif ($dbType == "mysql") {
            // ... to Moderation 1.1.29
            $updater->addExtensionField(
                "moderation",
                "mod_tags",
                "$sqlDir/patch-moderation-mod_tags.sql",
            );

            // ... to Moderation 1.1.31
            $updater->modifyExtensionField(
                "moderation",
                "mod_title",
                "$sqlDir/patch-fix-titledbkey.sql",
            );

            // ... to Moderation 1.2.9
            if (
                $db->tableExists("moderation", __METHOD__) &&
                !$db->indexUnique("moderation", "moderation_load", __METHOD__)
            ) {
                $updater->addExtensionUpdate([
                    "applyPatch",
                    "$sqlDir/patch-make-preload-unique.sql",
                    true,
                ]);
            }

            // ... to Moderation 1.2.17
            $updater->addExtensionField(
                "moderation",
                "mod_type",
                "$sqlDir/patch-moderation-mod_type.sql",
            );
        }

        // Workaround for T258159 (extension-provided services are not loaded during the Web Updater,
        // but Moderation.VersionCheck service is needed for invalidateCache() call below).
        // This code is not needed during normal installation (when running update.php via the console).
        $services = MediaWikiServices::getInstance();
        if (!$services->hasService("Moderation.VersionCheck")) {
            $services->loadWiringFiles([__DIR__ . "/ServiceWiring.php"]);
        }

        $updater->addExtensionUpdate([
            "MediaWiki\Moderation\ModerationVersionCheck::invalidateCache",
        ]);
    }
}
