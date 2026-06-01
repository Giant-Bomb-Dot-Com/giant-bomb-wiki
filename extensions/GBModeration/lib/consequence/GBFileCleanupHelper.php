<?php

namespace MediaWiki\Moderation;

use MediaWiki\MediaWikiServices;
use ManualLogEntry;
use Title;
use User;

class GBFileCleanupHelper
{
    /**
     * Shared logic to expunge a file and its page upon rejection.
     */
    public static function deleteFileIfExists(
        $modTitle,
        User $moderator,
        $modId,
    ) {
        // Force the title into the File namespace
        $title = Title::makeTitleSafe(NS_FILE, $modTitle);

        if (!$title) {
            return;
        }

        $services = MediaWikiServices::getInstance();
        $reason = "Moderated upload rejected - cleanup (mod_id: $modId)";

        // 1. Delete the physical binary
        $file = $services->getRepoGroup()->findFile($title);
        if ($file && $file->exists()) {
            $file->deleteFile($reason, $moderator);
        }

        // 2. Delete the description page
        $wikiPage = $services->getWikiPageFactory()->newFromTitle($title);
        if ($wikiPage->exists()) {
            $wikiPage->doDeleteArticleReal($reason, $moderator);
        }

        // 3. FORCE THE LOG ENTRY
        // This ensures the reason shows up in "Main public logs"
        $logEntry = new ManualLogEntry("delete", "delete");
        $logEntry->setPerformer($moderator);
        $logEntry->setTarget($title);
        $logEntry->setComment($reason);

        // Insert into the 'logging' table and publish to RecentChanges
        $logId = $logEntry->insert();
        $logEntry->publish($logId);
    }
}
