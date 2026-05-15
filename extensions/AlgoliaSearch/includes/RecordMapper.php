<?php

namespace MediaWiki\Extension\AlgoliaSearch;

use MediaWiki\MediaWikiServices;
use FauxRequest;
use RequestContext;
use ApiMain;
use Title;

class RecordMapper
{
    public static function getSupportedTypes(): array
    {
        return [
            "Game",
            "Character",
            "Concept",
            "Accessory",
            "Location",
            "Person",
            "Franchise",
            "Platform",
            "Company",
            "Object",
        ];
    }

    public static function mapRecord(
        string $type,
        Title $title,
        array $context = [],
    ): ?array {
        if (!in_array($type, self::getSupportedTypes(), true)) {
            return null;
        }

        $pageId = $title->getArticleID();
        if (!$pageId) {
            return null;
        }

        $href = $title->getLocalURL();
        $slug = $title->getPrefixedURL();

        $objectId = "wiki:" . $pageId;

        $record = [
            "objectID" => $objectId,
            "type" => $type,
            "title" => self::computeDisplayTitle($title, $type),
            "slug" => $slug,
            "href" => $href,
            "excerpt" => null,
            "thumbnail" => null, // TODO: confirm final thumbnail field
            "categories" => self::getCategoriesForPageId($pageId),
            "tags" => [],
            "publishDate" => null,
            "_updatedAt" => null,
        ];

        // deck + thumbnail use the same SMW -> wikitext -> fallback chain across all types.
        // games are usually best-populated; other types had been silently falling through.
        $deck = self::getEntityDeck($title);
        if ($deck !== null && $deck !== "") {
            $record["excerpt"] = self::truncatePlaintext(
                strip_tags($deck),
                280,
            );
        }

        $record["thumbnail"] = self::getEntityImage($title);
        if ($record["excerpt"] === null || $record["excerpt"] === "") {
            $fromExtract = self::getExcerptForTitle($title);
            if (is_string($fromExtract) && $fromExtract !== "") {
                $record["excerpt"] = self::truncatePlaintext($fromExtract, 280);
            }
        }

        if (is_string($record["thumbnail"])) {
            $record["thumbnail"] = self::rewriteCdnUrl($record["thumbnail"]);
        }

        $timestamps = self::getRevisionTimestamps($title);
        $record["_updatedAt"] = $timestamps["latest"] ?? null;
        $record["publishDate"] = $timestamps["first"] ?? null;

        return $record;
    }

    private static function getEntityImage(Title $title): ?string
    {
        // 1. SMW Has image (set by every entity template)
        try {
            $store = \SMW\StoreFactory::getStore();
            $subject = \SMW\DIWikiPage::newFromTitle($title);
            $vals = $store->getPropertyValues(
                $subject,
                new \SMW\DIProperty("Has image"),
            );
            if ($vals) {
                $first = reset($vals);
                if ($first instanceof \SMWDIBlob) {
                    $resolved = self::resolveImageReference(
                        $first->getString(),
                        640,
                    );
                    if ($resolved) {
                        return $resolved;
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        // 2. wikitext Image= (loose match; covers pre-SMW-rebuild pages and any spacing).
        // [^\n|}] keeps us from gobbling the closing }} when Image= is the last parameter.
        $text = self::getPageWikitext($title);
        if (
            $text !== "" &&
            preg_match('/\|\s*Image\s*=\s*([^\n|}]+)/i', $text, $m)
        ) {
            $resolved = self::resolveImageReference(trim($m[1]), 640);
            if ($resolved) {
                return $resolved;
            }
        }

        // 3. mediawiki PageImages api
        $pageImage = self::getThumbnailForTitle($title);
        if ($pageImage) {
            return $pageImage;
        }

        // 4. legacy <div id="imageData"> json blob
        $legacyImage = LegacyImageHelper::findLegacyImageForTitle($title);
        if ($legacyImage !== null) {
            return $legacyImage["thumb"] ?? ($legacyImage["full"] ?? null);
        }

        return null;
    }

    // resolves a raw Has image / Image= value to a public url.
    // http(s) -> as-is; mw File: -> 640px thumb; else assume legacy gb cdn path.
    private static function resolveImageReference(
        string $value,
        int $width,
    ): ?string {
        // belt-and-suspenders: strip trailing whitespace / template-close braces so we
        // can't ever leak '}}' into a thumbnail URL even if upstream regex misbehaves.
        $value = preg_replace('/[\s}]+$/', "", trim($value)) ?? "";
        if ($value === "") {
            return null;
        }
        if (stripos($value, "http") === 0) {
            return $value;
        }
        // SMW stores spaces as '+' (per template's #replace); mw File: lookup wants spaces
        $fileName = str_replace("+", " ", $value);
        $thumb = self::resolveImageThumbUrl($fileName, $width);
        if ($thumb) {
            return $thumb;
        }
        // not in the local file repo; treat as legacy gb cdn path
        return "https://www.giantbomb.com/a/uploads/" . ltrim($value, "/");
    }

    private static function getEntityDeck(Title $title): ?string
    {
        try {
            $store = \SMW\StoreFactory::getStore();
            $subject = \SMW\DIWikiPage::newFromTitle($title);
            $vals = $store->getPropertyValues(
                $subject,
                new \SMW\DIProperty("Has deck"),
            );
            if ($vals) {
                $first = reset($vals);
                if ($first instanceof \SMWDIBlob) {
                    $deck = trim($first->getString());
                    if ($deck !== "") {
                        return $deck;
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        $text = self::getPageWikitext($title);
        if (
            $text !== "" &&
            preg_match('/\|\s*Deck\s*=\s*([^\n|}]+)/i', $text, $m)
        ) {
            $deck = preg_replace('/[\s}]+$/', "", trim($m[1])) ?? "";
            if ($deck !== "") {
                return $deck;
            }
        }

        return null;
    }

    private static function getPageWikitext(Title $title): string
    {
        static $cache = [];
        $key = $title->getPrefixedDBkey();
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        try {
            $page = MediaWikiServices::getInstance()
                ->getWikiPageFactory()
                ->newFromTitle($title);
            $content = $page ? $page->getContent() : null;
            return $cache[$key] = $content ? $content->getText() : "";
        } catch (\Throwable $e) {
            return $cache[$key] = "";
        }
    }

    private static function resolveImageThumbUrl(
        string $imageName,
        int $width = 640,
    ): ?string {
        $name = trim($imageName);
        if ($name === "") {
            return null;
        }
        if (stripos($name, "File:") !== 0) {
            $name = "File:" . $name;
        }
        $title = Title::newFromText($name);
        if (!$title) {
            return null;
        }
        $services = MediaWikiServices::getInstance();
        $file = $services->getRepoGroup()->findFile($title);
        if (!$file) {
            return null;
        }
        $thumbOutput = $file->transform(["width" => $width]);
        if ($thumbOutput && !$thumbOutput->isError()) {
            $url = $thumbOutput->getUrl();
            if ($url !== null) {
                return \wfExpandUrl($url, \PROTO_CANONICAL);
            }
        }
        $url = $file->getFullUrl();
        return is_string($url) && $url !== "" ? $url : null;
    }

    private static function rewriteCdnUrl(string $url): string
    {
        $config = MediaWikiServices::getInstance()->getMainConfig();
        $cdnBase = $config->get("AlgoliaImageCdnBase");
        if (!is_string($cdnBase) || $cdnBase === "") {
            return $url;
        }

        $bucketName = $config->get("AWSBucketName");
        if (!is_string($bucketName) || $bucketName === "") {
            return $url;
        }

        $gcsPrefix = "https://storage.googleapis.com/" . $bucketName . "/";
        if (strpos($url, $gcsPrefix) === 0) {
            return rtrim($cdnBase, "/") .
                "/" .
                substr($url, strlen($gcsPrefix));
        }

        return $url;
    }

    private static function truncatePlaintext(string $text, int $limit): string
    {
        $t = trim(preg_replace("/\s+/", " ", $text) ?? "");
        if (mb_strlen($t) <= $limit) {
            return $t;
        }
        $cut = mb_substr($t, 0, $limit);
        $space = mb_strrpos($cut, " ");
        if ($space !== false && $space >= (int) ($limit * 0.6)) {
            $cut = mb_substr($cut, 0, $space);
        }
        return rtrim($cut, " \t\n\r\0\x0B.,;:–—-_") . "…";
    }

    private static function computeDisplayTitle(
        Title $title,
        string $type,
    ): string {
        // smw Has name is the canonical display name set by entity templates
        try {
            $store = \SMW\StoreFactory::getStore();
            $subject = \SMW\DIWikiPage::newFromTitle($title);
            $vals = $store->getPropertyValues(
                $subject,
                new \SMW\DIProperty("Has name"),
            );
            if ($vals) {
                $first = reset($vals);
                if ($first instanceof \SMWDIBlob) {
                    $name = trim($first->getString());
                    if ($name !== "") {
                        return $name;
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        // fallback: strip entity prefix, trailing _NNNN legacy id, underscores
        $text = $title->getText();
        $services = MediaWikiServices::getInstance();
        $config = $services->getMainConfig();
        $map = (array) $config->get("AlgoliaTypePrefixMap");
        $prefix = $map[$type] ?? null;
        if (is_string($prefix) && $prefix !== "") {
            $prefixWithSlash = $prefix . "/";
            if (strpos($text, $prefixWithSlash) === 0) {
                $text = substr($text, strlen($prefixWithSlash));
            }
        }
        $parts = explode("/", $text);
        $leaf = end($parts);
        $candidate = $leaf !== false && $leaf !== "" ? $leaf : $text;
        $candidate = preg_replace('/_\d+$/', "", $candidate);
        return str_replace("_", " ", $candidate);
    }

    private static function getExcerptForTitle(Title $title): ?string
    {
        try {
            $params = [
                "action" => "query",
                "prop" => "extracts",
                "exintro" => 1,
                "explaintext" => 1,
                "exsentences" => 2,
                "titles" => $title->getPrefixedText(),
                "format" => "json",
            ];
            $data = self::runApiQuery($params);
            if (!$data || empty($data["query"]["pages"])) {
                return null;
            }
            foreach ($data["query"]["pages"] as $page) {
                if (isset($page["extract"]) && is_string($page["extract"])) {
                    $excerpt = trim($page["extract"]);
                    return $excerpt !== "" ? $excerpt : null;
                }
            }
        } catch (\Throwable $e) {
        }
        return null;
    }

    private static function getThumbnailForTitle(Title $title): ?string
    {
        try {
            $params = [
                "action" => "query",
                "prop" => "pageimages",
                "pithumbsize" => 640,
                "titles" => $title->getPrefixedText(),
                "format" => "json",
            ];
            $data = self::runApiQuery($params);
            if (!$data || empty($data["query"]["pages"])) {
                return null;
            }
            foreach ($data["query"]["pages"] as $page) {
                if (
                    isset($page["thumbnail"]["source"]) &&
                    is_string($page["thumbnail"]["source"])
                ) {
                    $url = trim($page["thumbnail"]["source"]);
                    return $url !== ""
                        ? \wfExpandUrl($url, \PROTO_CANONICAL)
                        : null;
                }
            }
        } catch (\Throwable $e) {
        }
        return null;
    }

    private static function getCategoriesForPageId(int $pageId): array
    {
        $services = MediaWikiServices::getInstance();
        $dbr = $services->getDBLoadBalancer()->getConnection(\DB_REPLICA);

        // Exclude hidden categories (tracking/maintenance categories marked with __HIDDENCAT__)
        $rows = $dbr
            ->newSelectQueryBuilder()
            ->select("cl_to")
            ->from("categorylinks")
            ->leftJoin("page", null, [
                "page_title = cl_to",
                "page_namespace" => NS_CATEGORY,
            ])
            ->leftJoin("page_props", null, [
                "pp_page = page_id",
                "pp_propname" => "hiddencat",
            ])
            ->where([
                "cl_from" => $pageId,
                "pp_value IS NULL",
            ])
            ->caller(__METHOD__)
            ->fetchResultSet();

        $categories = [];
        foreach ($rows as $row) {
            $categories[] = str_replace("_", " ", (string) $row->cl_to);
        }

        $config = $services->getMainConfig();
        $excludePatterns = (array) $config->get(
            "AlgoliaExcludeCategoryPatterns",
        );
        if ($excludePatterns) {
            $categories = array_filter($categories, static function (
                string $cat,
            ) use ($excludePatterns) {
                foreach ($excludePatterns as $pattern) {
                    if (preg_match($pattern, $cat)) {
                        return false;
                    }
                }
                return true;
            });
        }

        return array_values(array_unique($categories));
    }

    private static function getRevisionTimestamps(Title $title): array
    {
        $services = MediaWikiServices::getInstance();
        $revisionLookup = $services->getRevisionLookup();
        $latest = $revisionLookup->getKnownCurrentRevision($title);
        $first = null;
        $firstTs = null;
        if (method_exists($revisionLookup, "getFirstRevision")) {
            $first = $revisionLookup->getFirstRevision($title);
        }
        return [
            "first" => $first ? $first->getTimestamp() : null,
            "latest" => $latest ? $latest->getTimestamp() : null,
        ];
    }

    // Legacy image helper methods were deduplicated into LegacyImageHelper.

    private static function runApiQuery(array $params): ?array
    {
        $fauxRequest = new FauxRequest($params);
        $context = new RequestContext();
        $context->setRequest($fauxRequest);
        $api = new ApiMain($context, true);
        $api->execute();
        $data = $api->getResult()->getResultData(null, [
            "Strip" => "all",
            "BC" => [],
        ]);
        return is_array($data) ? $data : null;
    }
}
