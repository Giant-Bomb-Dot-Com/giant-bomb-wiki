<?php

namespace MediaWiki\Extension\AlgoliaSearch;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\UserIdentity;
use Title;
use WikiPage;

class AlgoliaHooks
{
    /**
     * Hook: PageSaveComplete
     * Sync page to Algolia after save
     */
    public static function onPageSaveComplete(
        WikiPage $wikiPage,
        UserIdentity $user,
        string $summary,
        int $flags,
        RevisionRecord $revisionRecord,
        EditResult $editResult,
    ): void {
        $config = MediaWikiServices::getInstance()->getMainConfig();

        if (!(bool) $config->get("AlgoliaSearchEnabled")) {
            return;
        }

        // use the WikiPage's redirect state; Title::isRedirect() can be
        // stale right after the save
        self::reindexTitle(
            $wikiPage->getTitle(),
            $config,
            $wikiPage->isRedirect(),
        );
    }

    /**
     * Hook: FileUpload
     * Reindex entries using an uploaded/re-uploaded image so thumbnails
     * don't sit stale until the next page edit.
     */
    public static function onFileUpload(
        $file,
        $reupload = false,
        $hasDescription = false,
    ): void {
        $config = MediaWikiServices::getInstance()->getMainConfig();

        if (!(bool) $config->get("AlgoliaSearchEnabled")) {
            return;
        }

        $fileTitle = $file ? $file->getTitle() : null;
        if (!$fileTitle) {
            return;
        }

        // defer — don't block the upload response on SMW reads + Algolia calls
        DeferredUpdates::addCallableUpdate(static function () use (
            $fileTitle,
            $config,
        ) {
            self::reindexTitles(
                self::getEntryTitlesUsingFile($fileTitle, $config),
                $config,
            );
        });
    }

    /**
     * Hook: PageMoveComplete
     * Reindex under the new title (objectID is the stable page id), or drop
     * the record if the page moved out of scope.
     */
    public static function onPageMoveComplete(
        $old,
        $new,
        $user,
        $pageid,
        $redirid,
        $reason,
        $revision,
    ): void {
        $config = MediaWikiServices::getInstance()->getMainConfig();

        if (!(bool) $config->get("AlgoliaSearchEnabled")) {
            return;
        }

        $newTitle = Title::newFromLinkTarget($new);
        if (
            $newTitle &&
            $newTitle->getNamespace() === NS_MAIN &&
            self::getTypeFromTitle($newTitle, $config) !== null &&
            !$newTitle->isRedirect()
        ) {
            self::reindexTitle($newTitle, $config);
            return;
        }

        // Moved out of indexable scope (or into a redirect) -> drop the record.
        if ((int) $pageid > 0) {
            self::deleteObjectByPageId((int) $pageid, $config);
        }
    }

    /**
     * Hook: PageUndeleteComplete
     * Restore the record when an entry is undeleted.
     */
    public static function onPageUndeleteComplete(
        $page,
        $restorer,
        $reason,
        $restoredRev,
        $logEntry,
        $restoredRevisionCount,
        $created,
        $restoredPageIds,
    ): void {
        $config = MediaWikiServices::getInstance()->getMainConfig();

        if (!(bool) $config->get("AlgoliaSearchEnabled")) {
            return;
        }

        $title = Title::newFromPageIdentity($page);
        if ($title) {
            self::reindexTitle($title, $config);
        }
    }

    /**
     * Map + upsert one entry title. Pass $isRedirect when the caller has
     * fresh state (Title::isRedirect() can be stale right after a save).
     */
    private static function reindexTitle(
        ?Title $title,
        $config,
        ?bool $isRedirect = null,
    ): void {
        $record = self::mapEntryRecord($title, $config, $isRedirect);
        if ($record !== null) {
            self::saveRecords([$record], $config);
        }
    }

    /**
     * Batch variant — one saveObjects call for many titles (file uploads).
     *
     * @param Title[] $titles
     */
    private static function reindexTitles(array $titles, $config): void
    {
        $records = [];
        foreach ($titles as $title) {
            $record = self::mapEntryRecord($title, $config);
            if ($record !== null) {
                $records[] = $record;
            }
        }
        if ($records) {
            self::saveRecords($records, $config);
        }
    }

    /**
     * Guards + record build; null when not an indexable entry.
     */
    private static function mapEntryRecord(
        ?Title $title,
        $config,
        ?bool $isRedirect = null,
    ): ?array {
        if (!$title || $title->getNamespace() !== NS_MAIN) {
            return null;
        }

        if ($isRedirect ?? $title->isRedirect()) {
            return null;
        }

        $type = self::getTypeFromTitle($title, $config);
        if ($type === null) {
            return null;
        }

        try {
            return RecordMapper::mapRecord($type, $title);
        } catch (\Throwable $e) {
            wfLogWarning(
                "AlgoliaSearch: Failed to map page " .
                    $title->getPrefixedText() .
                    ": " .
                    $e->getMessage(),
            );
            return null;
        }
    }

    private static function saveRecords(array $records, $config): void
    {
        try {
            $index = AlgoliaClientFactory::getIndexFromConfig($config);
            if (!$index) {
                return;
            }
            $index->saveObjects($records);
        } catch (\Throwable $e) {
            wfLogWarning(
                "AlgoliaSearch: Failed to sync " .
                    count($records) .
                    " record(s): " .
                    $e->getMessage(),
            );
        }
    }

    /**
     * Remove the Algolia record for a page id (objectID "wiki:<id>").
     */
    private static function deleteObjectByPageId(int $pageId, $config): void
    {
        if ($pageId <= 0) {
            return;
        }
        try {
            $index = AlgoliaClientFactory::getIndexFromConfig($config);
            if (!$index) {
                return;
            }
            $index->deleteObjects(["wiki:" . $pageId]);
        } catch (\Throwable $e) {
            wfLogWarning(
                "AlgoliaSearch: Failed to delete object wiki:" .
                    $pageId .
                    ": " .
                    $e->getMessage(),
            );
        }
    }

    /**
     * Entry titles referencing a file (via imagelinks).
     *
     * @return Title[]
     */
    private static function getEntryTitlesUsingFile(
        Title $fileTitle,
        $config,
    ): array {
        $titles = [];
        try {
            $services = MediaWikiServices::getInstance();
            $dbr = $services
                ->getConnectionProvider()
                ->getReplicaDatabase();

            // one JOIN instead of a Title lookup per row; page_is_redirect
            // rides along so isRedirect() stays fresh
            $rows = $dbr
                ->newSelectQueryBuilder()
                ->select([
                    "page_id",
                    "page_namespace",
                    "page_title",
                    "page_is_redirect",
                ])
                ->from("imagelinks")
                ->join("page", null, "page_id = il_from")
                ->where([
                    "il_to" => $fileTitle->getDBkey(),
                    "il_from_namespace" => NS_MAIN,
                    "page_is_redirect" => 0,
                ])
                ->caller(__METHOD__)
                ->fetchResultSet();

            foreach ($rows as $row) {
                $title = Title::newFromRow($row);
                if (self::getTypeFromTitle($title, $config) !== null) {
                    $titles[] = $title;
                }
            }
        } catch (\Throwable $e) {
            wfLogWarning(
                "AlgoliaSearch: Failed to resolve pages for file " .
                    $fileTitle->getDBkey() .
                    ": " .
                    $e->getMessage(),
            );
        }

        return $titles;
    }

    /**
     * Hook: PageDeleteComplete
     * Remove page from Algolia after deletion
     */
    public static function onPageDeleteComplete(
        PageIdentity $page,
        \MediaWiki\Permissions\Authority $deleter,
        string $reason,
        int $pageID,
        RevisionRecord $deletedRev,
        \ManualLogEntry $logEntry,
        int $archivedRevisionCount,
    ): void {
        $config = MediaWikiServices::getInstance()->getMainConfig();

        if (!(bool) $config->get("AlgoliaSearchEnabled")) {
            return;
        }

        if ($page->getNamespace() !== NS_MAIN) {
            return;
        }

        $effectivePageId = $pageID > 0 ? $pageID : $page->getId();
        if ($effectivePageId <= 0) {
            return;
        }

        try {
            $index = AlgoliaClientFactory::getIndexFromConfig($config);
            if (!$index) {
                return;
            }

            $objectId = "wiki:" . $effectivePageId;
            $index->deleteObjects([$objectId]);
        } catch (\Throwable $e) {
            wfLogWarning(
                "AlgoliaSearch: Failed to delete object wiki:" .
                    $effectivePageId .
                    ": " .
                    $e->getMessage(),
            );
        }
    }

    public static function getTypeFromTitle(Title $title, $config): ?string
    {
        $prefixMap = (array) $config->get("AlgoliaTypePrefixMap");
        $titleText = $title->getText();

        foreach ($prefixMap as $type => $prefix) {
            $prefixWithSlash = $prefix . "/";
            if (strpos($titleText, $prefixWithSlash) === 0) {
                // Check it's a direct child (no further slashes)
                $remainder = substr($titleText, strlen($prefixWithSlash));
                if (strpos($remainder, "/") === false) {
                    return $type;
                }
            }
        }

        return null;
    }
}
