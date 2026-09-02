<?php

namespace MediaWiki\Extension\GiantBombResolve\Rest;

use ApiMain;
use FauxRequest;
use MediaWiki\Config\Config;
use MediaWiki\Extension\AlgoliaSearch\LegacyImageHelper;
use MediaWiki\Extension\AlgoliaSearch\RecordMapper;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\SimpleRequestInterface;
use RequestContext;
use Title;
use User;

class ResolveHandler extends SimpleHandler
{
    private const LEGACY_GUID_PATTERN = '/^(\d{3,4})-(\d{1,12})$/';
    private const UUID_GUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
    private const TITLE_PREFIX_TYPE_IDS = [
        "Accessories" => 3000,
        "Characters" => 3005,
        "Companies" => 3010,
        "Concepts" => 3015,
        "DLC" => 3020,
        "Franchises" => 3025,
        "Games" => 3030,
        "Themes" => 3032,
        "Locations" => 3035,
        "People" => 3040,
        "Platforms" => 3045,
        "Releases" => 3050,
        "Objects" => 3055,
        "Genres" => 3060,
    ];

    /**
     * "(Type)" labels {{DISPLAYTITLE}} appends to entity pages. Fallback only
     * ("Has name" is primary); exact labels so real parens survive. Keep in
     * sync with the DISPLAYTITLEs in Template_*.wikitext.
     */
    private const DISPLAY_TITLE_TYPE_SUFFIXES = [
        "Accessory",
        "Character",
        "Company",
        "Concept",
        "DLC",
        "Franchise",
        "Game Rating",
        "Game",
        "Genre",
        "Location",
        "Object",
        "Person",
        "Platform",
        "Rating Board",
        "Region",
        "Release",
        "Theme",
    ];

    /** @var Config */
    private $config;

    /** @var array<string> */
    private $allowedFields = [];

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct()
    {
        $services = MediaWikiServices::getInstance();
        $this->config = $services->getMainConfig();
        $this->logger = LoggerFactory::getInstance("GiantBombResolve");
        $this->allowedFields = array_values(
            array_unique(
                array_map(static function ($field) {
                    return trim((string) $field);
                }, (array) $this->config->get("GiantBombResolveFields")),
            ),
        );
    }

    public function getParamSettings()
    {
        return [];
    }

    public function needsWriteAccess()
    {
        return false;
    }

    public function checkPermissions()
    {
        $this->assertRequestIsAllowed();
    }

    public function execute()
    {
        $request = $this->getRequest();
        $queryParams = method_exists($request, "getQueryParams")
            ? $request->getQueryParams()
            : [];
        $guids = $this->parseGuids($queryParams["guids"] ?? null);
        $this->enforceBatchLimit(count($guids));
        $fields = $this->parseFields($queryParams["fields"] ?? null);

        $records = [];
        $errors = 0;
        $missing = 0;
        foreach ($guids as $guid) {
            $records[] = $this->resolveGuid($guid, $fields);
        }
        foreach ($records as $record) {
            if ($record["status"] === "missing") {
                $missing++;
            } elseif (
                $record["status"] === "error" ||
                $record["status"] === "invalid"
            ) {
                $errors++;
            }
        }

        $this->logger->info("Resolved GUID batch", [
            "count" => count($records),
            "missing" => $missing,
            "errors" => $errors,
        ]);

        return $this->createResponse([
            "guids" => $records,
            "cache" => [
                "ttl" => 3600,
                "staleIfError" => 86400,
            ],
        ]);
    }

    private function resolveGuid(string $guid, array $fields): array
    {
        $isUuidGuid = $this->isUuidGuid($guid);
        $assocTypeId = 0;
        $assocId = 0;

        if (!$isUuidGuid) {
            $legacyParts = $this->splitLegacyGuid($guid);
            if (!$legacyParts) {
                return $this->makeInvalidRecord($guid, "invalid-guid");
            }
            $assocTypeId = $legacyParts["assocTypeId"];
            $assocId = $legacyParts["assocId"];
        }

        try {
            $data = $this->fetchGuidData($guid, $fields);
        } catch (\Throwable $e) {
            $this->logger->error("Failed resolving GUID", [
                "guid" => $guid,
                "exception" => $e,
            ]);
            return $this->makeErrorRecord($guid, "internal-error");
        }

        if ($data === null) {
            return $this->makeMissingRecord($guid, $assocTypeId, $assocId);
        }

        if ($isUuidGuid) {
            $inferredTypeId = $this->inferAssocTypeIdFromData($data);
            if ($inferredTypeId === null) {
                return $this->makeInvalidRecord($guid, "unmapped-guid-type");
            }
            $assocTypeId = $inferredTypeId;
        }

        return [
            "guid" => $guid,
            "assocTypeId" => $assocTypeId,
            "assocId" => $assocId,
            "status" => "ok",
            "data" => $data,
        ];
    }

    /**
     * "Star Fox (Game)" -> "Star Fox". Known type labels only, end only.
     */
    private function stripDisplayTitleTypeSuffix(string $title): string
    {
        foreach (self::DISPLAY_TITLE_TYPE_SUFFIXES as $type) {
            $suffix = " (" . $type . ")";
            if (
                strlen($title) > strlen($suffix) &&
                substr($title, -strlen($suffix)) === $suffix
            ) {
                return rtrim(substr($title, 0, -strlen($suffix)));
            }
        }
        return $title;
    }

    /**
     * Clean entity name from the "Has name" printout (the template's Name= param).
     */
    private function extractCleanName(array $first): ?string
    {
        $name = $first["printouts"]["Name"] ?? null;
        if (is_array($name)) {
            $name = reset($name);
        }
        if (is_string($name)) {
            $name = trim($name);
            if ($name !== "") {
                return $name;
            }
        }
        return null;
    }

    /**
     * Shared by title + displayTitle so they can't drift: "Has name" if set,
     * else DisplayTitle minus its "(Type)" suffix, else null.
     */
    private function resolveCleanTitle(
        ?string $cleanName,
        $rawDisplayTitle,
    ): ?string {
        if ($cleanName !== null) {
            return $cleanName;
        }
        if (is_string($rawDisplayTitle)) {
            $trimmed = trim($rawDisplayTitle);
            if ($trimmed !== "") {
                return $this->stripDisplayTitleTypeSuffix($trimmed);
            }
        }
        return null;
    }

    private function fetchGuidData(string $guid, array $fields): ?array
    {
        // always ask for "Has name" — the clean entity name, no "(Type)" suffix
        $query = "[[Has guid::" . $guid . "]]|?Has name=Name";
        if (
            in_array("image", $fields, true) ||
            in_array("printouts", $fields, true)
        ) {
            $query .= "|?Has image=Primary image";
        }
        // entity-detail printouts, per requested field so the common
        // name/image resolve stays as light as today
        if (in_array("deck", $fields, true)) {
            $query .= "|?Has deck=Deck";
        }
        if (in_array("original_release_date", $fields, true)) {
            $query .=
                "|?Has release date=ReleaseDate" .
                "|?Has release date type=ReleaseDateType";
        }
        if (in_array("platforms", $fields, true)) {
            $query .= "|?Has platforms=Platforms";
        }
        if (in_array("developers", $fields, true)) {
            $query .= "|?Has developers=Developers";
        }
        if (in_array("publishers", $fields, true)) {
            $query .= "|?Has publishers=Publishers";
        }
        if (in_array("genres", $fields, true)) {
            $query .= "|?Has genres=Genres";
        }
        $timer = microtime(true);
        $result = $this->runAskQuery($query);
        $elapsed = microtime(true) - $timer;
        $threshold = (float) $this->config->get("GiantBombResolveTimeout");
        if ($threshold > 0 && $elapsed > $threshold) {
            $this->logger->warning("Slow resolve query", [
                "guid" => $guid,
                "duration" => $elapsed,
            ]);
        }
        if (!$result) {
            return null;
        }
        $first = reset($result);
        $pageKey = key($result);
        $titleText = $pageKey ?? ($first["fulltext"] ?? null);

        $cleanName = $this->extractCleanName($first);

        $data = [];
        $baseOrigin = $this->getRewriteBaseOrigin();

        foreach ($fields as $field) {
            switch ($field) {
                case "displaytitle":
                    $rawDisplayTitle = $first["displaytitle"] ?? null;
                    $data["displayTitle"] =
                        $this->resolveCleanTitle(
                            $cleanName,
                            $rawDisplayTitle,
                        ) ?? $rawDisplayTitle;
                    break;
                case "fullurl":
                    $fullUrl = $first["fullurl"] ?? null;
                    if ($fullUrl && $baseOrigin) {
                        $data["fullUrl"] = $this->rewriteFullUrl(
                            $fullUrl,
                            $baseOrigin,
                            $titleText,
                        );
                    } else {
                        $data["fullUrl"] = $fullUrl;
                    }
                    break;
                case "fulltext":
                    $data["fullText"] = $first["fulltext"] ?? $titleText;
                    break;
                case "namespace":
                    $data["namespace"] = $first["namespace"] ?? null;
                    break;
                case "pageid":
                    $data["pageId"] = $first["pageid"] ?? null;
                    break;
                case "printouts":
                    $printouts = $first["printouts"] ?? [];
                    // "Name" is ours (clean title); keep printouts shape as before
                    unset($printouts["Name"]);
                    $data["printouts"] = $printouts;
                    break;
                case "image":
                    $image = $this->extractPrimaryImageData(
                        $first["printouts"] ?? [],
                    );
                    if ($image !== null) {
                        $data["image"] = $image;
                    }
                    break;
                case "deck":
                    $deck = $this->extractScalarPrintout(
                        $first["printouts"] ?? [],
                        "Deck",
                    );
                    if ($deck !== null) {
                        $data["deck"] = $deck;
                    }
                    break;
                case "original_release_date":
                    $releaseDate = $this->extractScalarPrintout(
                        $first["printouts"] ?? [],
                        "ReleaseDate",
                    );
                    if ($releaseDate !== null) {
                        $data["originalReleaseDate"] = $releaseDate;
                    }
                    $releaseDateType = $this->extractScalarPrintout(
                        $first["printouts"] ?? [],
                        "ReleaseDateType",
                    );
                    if ($releaseDateType !== null) {
                        $data["releaseDateType"] = $releaseDateType;
                    }
                    break;
                case "platforms":
                    $data["platforms"] = $this->extractListPrintout(
                        $first["printouts"] ?? [],
                        "Platforms",
                    );
                    break;
                case "developers":
                    $data["developers"] = $this->extractListPrintout(
                        $first["printouts"] ?? [],
                        "Developers",
                    );
                    break;
                case "publishers":
                    $data["publishers"] = $this->extractListPrintout(
                        $first["printouts"] ?? [],
                        "Publishers",
                    );
                    break;
                case "genres":
                    $data["genres"] = $this->extractListPrintout(
                        $first["printouts"] ?? [],
                        "Genres",
                    );
                    break;
            }
        }

        if ($titleText) {
            $title = Title::newFromText($titleText);
            if ($title) {
                // title: clean name -> stripped DisplayTitle -> slug-derived.
                // prefixedTitle: url-form slug (kept verbatim for url construction).
                $clean = $this->resolveCleanTitle(
                    $cleanName,
                    $first["displaytitle"] ?? null,
                );
                if ($clean !== null) {
                    $data["title"] = $clean;
                } else {
                    $raw = $title->getText();
                    $slashPos = strpos($raw, "/");
                    if ($slashPos !== false) {
                        $raw = substr($raw, $slashPos + 1);
                    }
                    $raw = preg_replace('/_\d+$/', "", $raw);
                    $data["title"] = str_replace("_", " ", $raw);
                }
                $data["prefixedTitle"] = $title->getPrefixedText();
                if (
                    in_array("image", $fields, true) &&
                    (!isset($data["image"]) || $data["image"] === null)
                ) {
                    $fallbackImage = LegacyImageHelper::findLegacyImageForTitle(
                        $title,
                    );
                    if ($fallbackImage !== null) {
                        $fullUrl =
                            $fallbackImage["full"] ?? $fallbackImage["thumb"];
                        $thumbUrl =
                            $fallbackImage["thumb"] ?? $fallbackImage["full"];
                        if ($fullUrl !== null || $thumbUrl !== null) {
                            $data["image"] = [
                                "title" =>
                                    $fallbackImage["caption"] ??
                                    $fallbackImage["file"],
                                "descriptionUrl" => $fullUrl,
                                "url" => $fullUrl,
                                "width" => null,
                                "height" => null,
                                "thumbUrl" => $thumbUrl,
                                "thumbWidth" => null,
                                "thumbHeight" => null,
                            ];
                        }
                    }
                }
            }
        }

        // last resort: assume a legacy cdn path, same guess the algolia push makes
        if (
            in_array("image", $fields, true) &&
            (!isset($data["image"]) || $data["image"] === null)
        ) {
            $rawImage = $this->extractFileTitleFromPrintout(
                $first["printouts"]["Primary image"][0] ?? null,
            );
            $url = $rawImage
                ? RecordMapper::resolveImageReference($rawImage, 640)
                : null;
            if ($url !== null) {
                $data["image"] = $this->makeDegradedImage($url);
            }
        }

        return $data;
    }

    private function rewriteFullUrl(
        string $wikiUrl,
        string $baseOrigin,
        ?string $titleText,
    ): string {
        $parsed = parse_url($wikiUrl);
        if ($titleText) {
            $title = Title::newFromText($titleText);
            if ($title) {
                return rtrim($baseOrigin, "/") . "/" . $title->getPrefixedURL();
            }
        }
        if ($parsed && isset($parsed["path"])) {
            return rtrim($baseOrigin, "/") . "/" . ltrim($parsed["path"], "/");
        }
        return $wikiUrl;
    }

    private function getRewriteBaseOrigin(): ?string
    {
        $baseOrigin = $this->config->get("GiantBombResolveBaseOrigin");
        if (is_string($baseOrigin)) {
            $trimmed = trim($baseOrigin);
            if ($trimmed !== "") {
                return rtrim($trimmed, "/");
            }
        }

        $canonical = $this->config->get("CanonicalServer");
        if (is_string($canonical) && $canonical !== "") {
            return rtrim($canonical, "/") . "/wiki";
        }

        return null;
    }

    protected function runAskQuery(string $query): array
    {
        $params = [
            "action" => "ask",
            "query" => $query,
            "format" => "json",
        ];

        $fauxRequest = new FauxRequest($params);
        $context = new RequestContext();
        $context->setRequest($fauxRequest);
        $systemUser = User::newSystemUser("GiantBombResolve", [
            "steal" => true,
        ]);
        if ($systemUser) {
            $context->setUser($systemUser);
        }

        $api = new ApiMain($context, true);
        $api->execute();
        $data = $api->getResult()->getResultData(null, [
            "Strip" => "all",
            "BC" => [],
        ]);

        return $data["query"]["results"] ?? [];
    }

    /**
     * @param string|string[]|null $guids
     */
    private function parseGuids($guids): array
    {
        $values = $this->normalizeQueryValues($guids);
        if (!$values) {
            throw new HttpException("resolve-missing-guids", 400);
        }
        $out = [];
        foreach ($values as $part) {
            if ($part === "") {
                continue;
            }
            if (!$this->isValidGuid($part)) {
                throw new HttpException("resolve-invalid-guid", 400, [
                    "guid" => $part,
                ]);
            }
            $out[] = $part;
        }
        if (!$out) {
            throw new HttpException("resolve-missing-guids", 400);
        }
        return array_values(array_unique($out));
    }

    /**
     * First value of a printout as a plain string (null when absent).
     *
     * @param array<string,mixed> $printouts
     */
    private function extractScalarPrintout(
        array $printouts,
        string $key,
    ): ?string {
        $values = $this->extractListPrintout($printouts, $key);
        return $values[0] ?? null;
    }

    /**
     * All values of a printout as plain strings.
     *
     * @param array<string,mixed> $printouts
     * @return string[]
     */
    private function extractListPrintout(array $printouts, string $key): array
    {
        if (empty($printouts[$key]) || !is_array($printouts[$key])) {
            return [];
        }
        $out = [];
        foreach ($printouts[$key] as $value) {
            $text = $this->printoutValueToString($value);
            if ($text !== null && $text !== "") {
                $out[] = $text;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * Normalize an SMW printout value (plain string, page reference, or
     * date) to a plain string.
     *
     * @param mixed $value
     */
    private function printoutValueToString($value): ?string
    {
        if (is_string($value)) {
            return trim($value);
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            // page values: use the subpage leaf ("Platforms/Atari 2600" -> "Atari 2600")
            if (isset($value["fulltext"]) && is_string($value["fulltext"])) {
                $text = trim($value["fulltext"]);
                $slashPos = strrpos($text, "/");
                return $slashPos === false
                    ? $text
                    : trim(substr($text, $slashPos + 1));
            }
            if (isset($value["raw"]) && is_string($value["raw"])) {
                return $this->smwRawDateToIso($value["raw"]);
            }
            if (isset($value["timestamp"]) && is_numeric($value["timestamp"])) {
                return gmdate("Y-m-d", (int) $value["timestamp"]);
            }
        }
        return null;
    }

    /**
     * SMW raw date ("1/1972/11/29", calendar model then Y[/M[/D]]) to an
     * iso-ish string with matching precision ("1972-11-29", "1972-11",
     * "1972") -- a year-only date must not fabricate a month/day.
     */
    private function smwRawDateToIso(string $raw): ?string
    {
        $trimmed = trim($raw);
        if ($trimmed === "") {
            return null;
        }
        $parts = explode("/", $trimmed);
        if (count($parts) < 2 || !is_numeric($parts[1])) {
            return $trimmed;
        }
        array_shift($parts); // calendar model
        $year = (int) array_shift($parts);
        $iso = sprintf("%04d", $year);
        if ($parts && is_numeric($parts[0])) {
            $iso .= sprintf("-%02d", (int) array_shift($parts));
            if ($parts && is_numeric($parts[0])) {
                $iso .= sprintf("-%02d", (int) array_shift($parts));
            }
        }
        return $iso;
    }

    /**
     * @param string|string[]|null $fields
     */
    private function parseFields($fields): array
    {
        $allowed = $this->allowedFields ?: [
            "displaytitle",
            "fullurl",
            "fulltext",
            "pageid",
            "namespace",
        ];
        $values = $this->normalizeQueryValues($fields);
        if (!$values) {
            return $allowed;
        }
        $requested = array_filter(array_map("trim", $values));
        $filtered = [];
        foreach ($requested as $field) {
            if ($field !== "" && in_array($field, $allowed, true)) {
                $filtered[] = $field;
            }
        }
        return $filtered ?: $allowed;
    }

    /**
     * Normalize query params that may arrive as strings, csv strings, or arrays.
     *
     * @param string|string[]|null $value
     * @return array<int,string>
     */
    private function normalizeQueryValues($value): array
    {
        if ($value === null) {
            return [];
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                $parts = array_merge(
                    $parts,
                    $this->normalizeQueryValues($item),
                );
            }
            return $parts;
        }
        // $value is a string; split on commas and whitespace.
        $segments = preg_split("/[,\s]+/", trim((string) $value)) ?: [];
        return array_values(
            array_filter($segments, static fn($segment) => $segment !== ""),
        );
    }

    private function enforceBatchLimit(int $count): void
    {
        $limit = (int) $this->config->get("GiantBombResolveBatchLimit");
        if ($count > $limit) {
            throw new HttpException("resolve-batch-limit", 400, [
                "limit" => $limit,
            ]);
        }
    }

    private function assertRequestIsAllowed(): void
    {
        $request = $this->getRequest();
        $allowPublic = (bool) $this->config->get("GiantBombResolveAllowPublic");
        if (!$allowPublic && !$this->hasValidInternalToken($request)) {
            throw new HttpException("resolve-auth-required", 401);
        }
    }

    private function hasValidInternalToken(
        SimpleRequestInterface $request,
    ): bool {
        if (!$request->hasHeader("X-GB-Internal-Key")) {
            return false;
        }
        $provided = trim($request->getHeaderLine("X-GB-Internal-Key"));
        if ($provided === "") {
            return false;
        }
        $expectedRaw = $this->config->get("GiantBombResolveInternalToken");
        $expected = trim(
            is_string($expectedRaw) ? $expectedRaw : (string) $expectedRaw,
        );
        if ($expected === "") {
            $this->logger->warning(
                "GiantBombResolveInternalToken is not configured; blocking request",
            );
            return false;
        }
        return hash_equals($expected, $provided);
    }

    private function splitLegacyGuid(string $guid): ?array
    {
        if (!preg_match(self::LEGACY_GUID_PATTERN, $guid, $matches)) {
            return null;
        }
        return [
            "assocTypeId" => (int) $matches[1],
            "assocId" => (int) $matches[2],
        ];
    }

    private function isValidGuid(string $guid): bool
    {
        return $this->isLegacyGuid($guid) || $this->isUuidGuid($guid);
    }

    private function isLegacyGuid(string $guid): bool
    {
        return (bool) preg_match(self::LEGACY_GUID_PATTERN, $guid);
    }

    private function isUuidGuid(string $guid): bool
    {
        return (bool) preg_match(self::UUID_GUID_PATTERN, $guid);
    }

    private function inferAssocTypeIdFromData(?array $data): ?int
    {
        if (!$data) {
            return null;
        }
        $title = null;
        if (
            isset($data["prefixedTitle"]) &&
            is_string($data["prefixedTitle"])
        ) {
            $title = $data["prefixedTitle"];
        } elseif (isset($data["fullText"]) && is_string($data["fullText"])) {
            $title = $data["fullText"];
        }
        if (!$title) {
            return null;
        }
        $prefix = strstr($title, "/", true);
        if ($prefix === false || $prefix === "") {
            return null;
        }
        return self::TITLE_PREFIX_TYPE_IDS[$prefix] ?? null;
    }

    private function makeMissingRecord(
        string $guid,
        int $assocTypeId,
        int $assocId,
    ): array {
        return [
            "guid" => $guid,
            "assocTypeId" => $assocTypeId,
            "assocId" => $assocId,
            "status" => "missing",
        ];
    }

    private function makeInvalidRecord(string $guid, string $reason): array
    {
        return [
            "guid" => $guid,
            "status" => "invalid",
            "reason" => $reason,
        ];
    }

    private function makeErrorRecord(string $guid, string $reason): array
    {
        return [
            "guid" => $guid,
            "status" => "error",
            "reason" => $reason,
        ];
    }

    /**
     * @param array<string,mixed> $printouts
     */
    private function extractPrimaryImageData(array $printouts): ?array
    {
        if (!$printouts) {
            return null;
        }
        $candidates = ["Primary image", "Has image", "Image"];
        foreach ($candidates as $key) {
            if (empty($printouts[$key])) {
                continue;
            }
            $entry = $printouts[$key][0] ?? null;
            if ($entry === null) {
                continue;
            }
            $rawValue = $this->extractFileTitleFromPrintout($entry);
            if (!$rawValue) {
                continue;
            }
            // smw stores spaces as '+' -> undo for the local file lookup
            $titleText = str_replace("+", " ", $rawValue);
            if (stripos($titleText, "File:") !== 0) {
                $titleText = "File:" . $titleText;
            }
            $title = Title::newFromText($titleText);
            $services = MediaWikiServices::getInstance();
            $file = $title
                ? $services->getRepoGroup()->findFile($title)
                : null;
            if (!$file) {
                // plain urls pass through; anything else falls through so
                // the imageData div gets first shot
                if (stripos(ltrim($rawValue), "http") === 0) {
                    $url = RecordMapper::resolveImageReference($rawValue, 640);
                    if ($url !== null) {
                        return $this->makeDegradedImage($url);
                    }
                }
                continue;
            }
            $thumbOutput = $file->transform(["width" => 640]);
            $thumbUrl = null;
            $thumbWidth = null;
            $thumbHeight = null;
            if ($thumbOutput && !$thumbOutput->isError()) {
                $thumbUrl = $thumbOutput->getUrl();
                if ($thumbUrl !== null) {
                    $thumbUrl = \wfExpandUrl($thumbUrl, \PROTO_CANONICAL);
                }
                $thumbWidth = $thumbOutput->getWidth();
                $thumbHeight = $thumbOutput->getHeight();
            }
            $url = $file->getFullUrl();
            $descriptionUrl = null;
            if (
                is_array($entry) &&
                isset($entry["fullurl"]) &&
                is_string($entry["fullurl"])
            ) {
                $descriptionUrl = $entry["fullurl"];
            } else {
                $descriptionUrl = $file->getTitle()->getFullURL();
            }

            return [
                "title" => $title->getPrefixedText(),
                "descriptionUrl" => $descriptionUrl,
                "url" => $url,
                "width" => $file->getWidth(),
                "height" => $file->getHeight(),
                "thumbUrl" => $thumbUrl ?? $url,
                "thumbWidth" => $thumbWidth ?? $file->getWidth(),
                "thumbHeight" => $thumbHeight ?? $file->getHeight(),
            ];
        }
        return null;
    }

    // url-only record, same null-dims shape as the imageData-div fallback
    private function makeDegradedImage(string $url): array
    {
        return [
            "title" => null,
            "descriptionUrl" => null,
            "url" => $url,
            "width" => null,
            "height" => null,
            "thumbUrl" => $url,
            "thumbWidth" => null,
            "thumbHeight" => null,
        ];
    }

    /**
     * @param mixed $entry
     */
    private function extractFileTitleFromPrintout($entry): ?string
    {
        if (is_array($entry)) {
            foreach (["fulltext", "raw", "title"] as $key) {
                if (
                    isset($entry[$key]) &&
                    is_string($entry[$key]) &&
                    $entry[$key] !== ""
                ) {
                    return $entry[$key];
                }
            }
            if (isset($entry["fullurl"]) && is_string($entry["fullurl"])) {
                $path = parse_url($entry["fullurl"], PHP_URL_PATH);
                if (is_string($path) && $path !== "") {
                    $decoded = rawurldecode($path);
                    $parts = explode("/", trim($decoded, "/"));
                    $last = end($parts);
                    if ($last !== false && $last !== "") {
                        return $last;
                    }
                }
            }
        } elseif (is_string($entry) && $entry !== "") {
            return $entry;
        }
        return null;
    }

    private function createResponse(array $payload): Response
    {
        $response = $this->getResponseFactory()->createJson($payload);

        $cacheControl = (string) $this->config->get(
            "GiantBombResolveCacheControl",
        );
        if ($cacheControl === "") {
            $cacheControl =
                "public, max-age=900, stale-while-revalidate=300, stale-if-error=86400";
        }
        $response->setHeader("Cache-Control", $cacheControl);
        $response->setHeader("X-GB-Resolve-Version", "1");
        $response->setHeader("Vary", "Accept-Encoding");

        $count = count($payload["guids"] ?? []);
        $response->setHeader("X-GB-Resolve-Count", (string) $count);

        $prefix = (string) $this->config->get(
            "GiantBombResolveSurrogatePrefix",
        );
        if ($prefix !== "" && $count > 0) {
            $keys = [];
            foreach ($payload["guids"] as $record) {
                if (isset($record["guid"])) {
                    $keys[] = $prefix . $record["guid"];
                }
            }
            if ($keys) {
                $response->setHeader("Surrogate-Key", implode(" ", $keys));
            }
        }

        return $response;
    }
}
