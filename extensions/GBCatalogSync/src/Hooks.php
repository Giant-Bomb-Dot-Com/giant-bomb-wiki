<?php

namespace MediaWiki\Extension\GBCatalogSync;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\UserIdentity;
use Title;
use WikiPage;

// pushes {guid, title, action} for game-page changes to the giant-bomb-next
// catalog-sync endpoint so catalog rows appear/refresh within seconds.
// fire-and-forget: runs post-send with one retry; the receiver is idempotent
// and a reconcile cron backstops anything this misses.
class Hooks
{
    private const GAME_PREFIX = "Games/";
    private const LEGACY_GUID_PATTERN = '/^\d{3,4}-\d{1,12}$/';
    private const UUID_GUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /**
     * Hook: PageSaveComplete
     */
    public static function onPageSaveComplete(
        WikiPage $wikiPage,
        UserIdentity $user,
        string $summary,
        int $flags,
        RevisionRecord $revisionRecord,
        EditResult $editResult,
    ): void {
        // null edits push on purpose: touching a page is the re-sync lever;
        // the receiver's cooldown absorbs the noise
        $action = $flags & EDIT_NEW ? "create" : "edit";
        self::pushForRevision($wikiPage->getTitle(), $revisionRecord, $action);
    }

    /**
     * Hook: PageDeleteComplete
     * Deletes are acked + ignored by the receiver today; sent for forward-compat.
     */
    public static function onPageDeleteComplete(
        $page,
        $deleter,
        string $reason,
        int $pageID,
        RevisionRecord $deletedRev,
        $logEntry,
        int $archivedRevisionCount,
    ): void {
        self::pushForRevision(
            Title::newFromPageIdentity($page),
            $deletedRev,
            "delete",
        );
    }

    /**
     * Hook: PageMoveComplete
     * Guid is the same either way; an in-scope target refreshes the title,
     * moving out of scope sends a delete.
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
        $newTitle = Title::newFromLinkTarget($new);
        if ($newTitle && self::isGameTitle($newTitle)) {
            $movedFrom = Title::newFromLinkTarget($old);
            self::pushForRevision(
                $newTitle,
                $revision,
                "move",
                $movedFrom ? self::extractSeedTitle("", $movedFrom) : null,
            );
            return;
        }
        $oldTitle = Title::newFromLinkTarget($old);
        if ($oldTitle) {
            self::pushForRevision($oldTitle, $revision, "delete");
        }
    }

    /**
     * Hook: PageUndeleteComplete
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
        self::pushForRevision(
            Title::newFromPageIdentity($page),
            $restoredRev,
            "create",
        );
    }

    /**
     * Hook: EditFilterMergedContent
     * Catalog rows key on guid, so a duplicate lets one page hijack
     * another's row. Reject saves that introduce a guid some other page
     * already owns; gbcatalogsync-guid-override (sysop/bot) skips the check
     * so merges and bot repairs still work. Fails open when SMW can't
     * answer.
     */
    public static function onEditFilterMergedContent(
        $context,
        $content,
        $status,
        $summary,
        $user,
        $minoredit,
    ) {
        $services = MediaWikiServices::getInstance();
        $config = $services->getMainConfig();
        if (!(bool) $config->get("GBCatalogSyncEnforceUniqueGuid")) {
            return true;
        }

        $title = $context->getTitle();
        if (!$title || !self::isGameTitle($title)) {
            return true;
        }
        if (
            $services
                ->getPermissionManager()
                ->userHasRight($user, "gbcatalogsync-guid-override")
        ) {
            return true;
        }

        $newText =
            $content && is_callable([$content, "getText"])
                ? (string) $content->getText()
                : "";
        $newGuid = self::extractGuid($newText);
        if ($newGuid === null) {
            return true;
        }

        // only a guid this edit introduces or changes; pre-existing
        // duplicates stay editable and are an audit problem, not a block
        if ($newGuid === self::currentGuid($title)) {
            return true;
        }

        $owner = self::findGuidOwner($newGuid, $title);
        if ($owner === null) {
            return true;
        }

        $status->fatal("gbcatalogsync-duplicate-guid", $newGuid, $owner);
        // expected hook rejection, not an internal error (same pattern as
        // AbuseFilter); 212 = EditPage::AS_HOOK_ERROR_EXPECTED
        $status->value = 212;
        return false;
    }

    // guid on the page's latest revision, null for new pages
    private static function currentGuid(Title $title): ?string
    {
        try {
            $page = MediaWikiServices::getInstance()
                ->getWikiPageFactory()
                ->newFromTitle($title);
            $content = $page ? $page->getContent() : null;
            $text =
                $content && is_callable([$content, "getText"])
                    ? (string) $content->getText()
                    : "";
            return self::extractGuid($text);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Prefixed title of another NS_MAIN page (not a subobject) already
     * holding this guid, or null. SMW lags a save via the job queue, so a
     * just-saved duplicate can slip through the race window -- acceptable,
     * the reconcile/audit side catches those.
     */
    private static function findGuidOwner(string $guid, Title $title): ?string
    {
        try {
            $store = \SMW\StoreFactory::getStore();
            $subjects = $store->getPropertySubjects(
                \SMW\DIProperty::newFromUserLabel("Has guid"),
                new \SMWDIBlob($guid),
            );
            foreach ($subjects as $subject) {
                if (
                    $subject->getNamespace() !== NS_MAIN ||
                    $subject->getSubobjectName() !== ""
                ) {
                    continue;
                }
                $ownerTitle = $subject->getTitle();
                if ($ownerTitle && !$ownerTitle->equals($title)) {
                    return $ownerTitle->getPrefixedText();
                }
            }
        } catch (\Throwable $e) {
            // smw unavailable -> fail open rather than blocking edits
        }
        return null;
    }

    private static function pushForRevision(
        ?Title $title,
        ?RevisionRecord $revision,
        string $action,
        ?string $oldTitle = null,
    ): void {
        if (!$title || !self::isGameTitle($title)) {
            return;
        }
        // bulk CLI runs (imports, re-migrations) use the one-time prime path
        // instead; deferred updates run inline per page there and a slow
        // receiver would serialize the whole run
        if (defined("MW_ENTRY_POINT") && MW_ENTRY_POINT === "cli") {
            return;
        }
        $config = MediaWikiServices::getInstance()->getMainConfig();
        if (!(bool) $config->get("GBCatalogSyncEnabled")) {
            return;
        }
        if (!$revision) {
            return;
        }

        $text = self::getWikitext($revision);
        $guid = self::extractGuid($text);
        if ($guid === null) {
            return;
        }

        $endpoint = trim((string) $config->get("GBCatalogSyncEndpoint"));
        $key = trim((string) $config->get("GBCatalogSyncKey"));
        if ($endpoint === "" || $key === "") {
            return;
        }

        $payload = [
            "guid" => $guid,
            "title" => self::extractSeedTitle($text, $title),
            "action" => $action,
        ];
        if ($oldTitle !== null) {
            // moves only: makes renames greppable on the receiver
            $payload["oldTitle"] = $oldTitle;
        }

        // post-send so the save path never waits on the site
        DeferredUpdates::addCallableUpdate(static function () use (
            $payload,
            $endpoint,
            $key,
        ) {
            self::send($payload, $endpoint, $key);
        });
    }

    // one retry on 5xx/transport failure; a 4xx means the receiver rejected
    // us -> log only
    private static function send(
        array $payload,
        string $endpoint,
        string $key,
    ): void {
        $body = json_encode($payload);
        if ($body === false) {
            return;
        }
        $factory = MediaWikiServices::getInstance()->getHttpRequestFactory();

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $httpStatus = 0;
            try {
                $request = $factory->create(
                    $endpoint,
                    [
                        "method" => "POST",
                        "postData" => $body,
                        "timeout" => 5,
                        "connectTimeout" => 3,
                    ],
                    __METHOD__,
                );
                $request->setHeader("Content-Type", "application/json");
                $request->setHeader("x-gb-internal-key", $key);

                $status = $request->execute();
                if ($status->isOK()) {
                    return;
                }
                $httpStatus = $request->getStatus();
            } catch (\Throwable $e) {
            }

            // transport failures (timeout, refused) leave getStatus() at its
            // "200 Ok" default, so only a definite 4xx is non-retryable
            $retryable = $httpStatus < 400 || $httpStatus >= 500;
            if (!$retryable || $attempt === 2) {
                $label =
                    $httpStatus >= 400
                        ? "HTTP {$httpStatus}"
                        : "transport error";
                wfLogWarning(
                    "GBCatalogSync: push failed ({$label}) guid=" .
                        $payload["guid"] .
                        " action=" .
                        $payload["action"],
                );
                return;
            }
        }
    }

    /**
     * NS_MAIN direct child of Games/ -- same scope test as the Algolia push.
     * Excludes Games/<name>/Releases and other subpages; slash-titled games
     * are a known gap the reconcile side covers.
     */
    public static function isGameTitle(Title $title): bool
    {
        if ($title->getNamespace() !== NS_MAIN) {
            return false;
        }
        $text = $title->getText();
        if (strpos($text, self::GAME_PREFIX) !== 0) {
            return false;
        }
        return strpos(substr($text, strlen(self::GAME_PREFIX)), "/") === false;
    }

    /**
     * The page's | Guid= template param; null when absent or junk-shaped.
     * First VALID match wins, so a commented-out or vandalized value earlier
     * in the page can't shadow the real infobox param.
     */
    public static function extractGuid(string $text): ?string
    {
        if (
            $text === "" ||
            !preg_match_all('/\|\s*Guid\s*=\s*([^\n|}]+)/i', $text, $m)
        ) {
            return null;
        }
        foreach ($m[1] as $raw) {
            $guid = preg_replace('/[\s}]+$/', "", trim($raw)) ?? "";
            if (preg_match(self::LEGACY_GUID_PATTERN, $guid)) {
                // zero-id guids ("3030-0") are junk the receiver 400s
                if (!preg_match('/-0+$/', $guid)) {
                    return $guid;
                }
                continue;
            }
            if (preg_match(self::UUID_GUID_PATTERN, $guid)) {
                return $guid;
            }
        }
        return null;
    }

    /**
     * Seed only -- the receiver re-hydrates the real title shortly after.
     * | Name= param when present, else the title leaf minus any legacy
     * disambig id ("Sprout 64629" -> "Sprout").
     */
    public static function extractSeedTitle(string $text, Title $title): string
    {
        if (
            $text !== "" &&
            preg_match('/\|\s*Name\s*=\s*([^\n|}]+)/i', $text, $m)
        ) {
            $name = preg_replace('/[\s}]+$/', "", trim($m[1])) ?? "";
            if ($name !== "") {
                return $name;
            }
        }
        $leaf = $title->getText();
        if (strpos($leaf, self::GAME_PREFIX) === 0) {
            $leaf = substr($leaf, strlen(self::GAME_PREFIX));
        }
        $leaf = str_replace("_", " ", $leaf);
        return preg_replace('/[ _]\d{5,}$/', "", rtrim($leaf)) ?? $leaf;
    }

    private static function getWikitext(RevisionRecord $revision): string
    {
        try {
            $content = $revision->getContent(
                SlotRecord::MAIN,
                RevisionRecord::RAW,
            );
            // getText() lives on TextContent; avoid instanceof so the class's
            // 1.43 namespace move can't silently break this
            return $content && is_callable([$content, "getText"])
                ? (string) $content->getText()
                : "";
        } catch (\Throwable $e) {
            return "";
        }
    }
}
