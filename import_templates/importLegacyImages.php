<?php
/**
 * Standalone CLI script to migrate legacy Giant Bomb image galleries
 * to MediaWiki /Images subpages via the MediaWiki Action API.
 *
 * Reads from a local MySQL database (giantbomb-mysql) and writes to a
 * remote MediaWiki instance using bot credentials.
 *
 * The script iterates entities that have images in the legacy DB, resolves
 * each GUID to a wiki page title (via --guid-map CSV from SMW, or SMW Ask
 * API if no map), then writes /Images subpages via the Action API.
 *
 * Usage:
 *   php importLegacyImages.php [options]
 *
 * Options:
 *   --type=<id>         Filter by assoc_type_id (e.g. 3030 for Games). Default: all
 *   --guid=<guid>       Process a single GUID (e.g. 3030-16559). Implies --dry-run unless --force.
 *   --limit=<n>         Max entities to process
 *   --resume-after=<id> Resume after this GUID (e.g. 3030-16559)
 *   --batch-size=<n>    Entities per progress log (default 100)
 *   --dry-run           Output wikitext to stdout, don't call API
 *   --force             Allow --guid to write to the API (otherwise --guid implies dry-run)
 *   --sleep=<ms>        Milliseconds to sleep between API edit calls (default 200)
 *   --guid-map=<path>   CSV with columns guid,page_title (from SMW export). Skips SMW Ask per GUID.
 *   --max-body-slice=<n> Max raw bytes per body slice (default 280000; URL encoding adds overhead vs nginx limits).
 *   --env=<path>        Path to .env file (default: .env.migration)
 *
 * Environment:
 *   GUID_MAP=<path>     Default path for --guid-map if not passed on CLI
 */

$options = getopt("", [
    "type:",
    "guid:",
    "limit:",
    "resume-after:",
    "batch-size:",
    "dry-run",
    "force",
    "sleep:",
    "env:",
    "guid-map:",
    "max-body-slice:",
]);

$envFile = $options["env"] ?? __DIR__ . "/.env.migration";
if (file_exists($envFile)) {
    foreach (
        file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
        as $line
    ) {
        $line = trim($line);
        if ($line === "" || $line[0] === "#") {
            continue;
        }
        if (strpos($line, "=") !== false) {
            putenv($line);
        }
    }
}

$LEGACY_DB_HOST = getenv("LEGACY_DB_HOST") ?: "127.0.0.1";
$LEGACY_DB_PORT = getenv("LEGACY_DB_PORT") ?: "3306";
$LEGACY_DB_USER = getenv("LEGACY_DB_USER");
$LEGACY_DB_PASS = getenv("LEGACY_DB_PASSWORD");
$LEGACY_DB_NAME = getenv("LEGACY_DB_NAME");
$MW_API_URL = getenv("MW_API_URL");
$MW_BOT_USER = getenv("MW_BOT_USER");
$MW_BOT_PASS = getenv("MW_BOT_PASSWORD");

$filterType = $options["type"] ?? null;
$singleGuid = $options["guid"] ?? null;
$maxEntities = isset($options["limit"]) ? (int) $options["limit"] : null;
$resumeAfter = $options["resume-after"] ?? null;
$batchSize = isset($options["batch-size"]) ? (int) $options["batch-size"] : 100;
$dryRun =
    isset($options["dry-run"]) || ($singleGuid && !isset($options["force"]));
$sleepMs = isset($options["sleep"]) ? (int) $options["sleep"] : 200;
$guidMapPath = $options["guid-map"] ?? getenv("GUID_MAP") ?: null;
$guidMapPath =
    $guidMapPath !== false && $guidMapPath !== "" ? $guidMapPath : null;
$maxBodySlice = isset($options["max-body-slice"])
    ? max(50000, (int) $options["max-body-slice"])
    : 280000;

if (!$LEGACY_DB_USER || !$LEGACY_DB_NAME) {
    fwrite(STDERR, "ERROR: LEGACY_DB_USER and LEGACY_DB_NAME must be set.\n");
    fwrite(STDERR, "Create a .env.migration file or pass --env=<path>.\n");
    exit(1);
}

if (!$dryRun && (!$MW_API_URL || !$MW_BOT_USER || !$MW_BOT_PASS)) {
    fwrite(
        STDERR,
        "ERROR: MW_API_URL, MW_BOT_USER, and MW_BOT_PASSWORD must be set (or use --dry-run).\n",
    );
    exit(1);
}

/**
 * CSV/JSON exports sometimes store "/" as "\/". MediaWiki titles must use real slashes.
 */
function normalizeWikiPageTitle(string $title): string
{
    $title = trim($title);
    return str_replace("\\/", "/", $title);
}

class MWApiClient
{
    private string $apiUrl;
    private string $cookieFile;
    private ?string $csrfToken = null;

    public function __construct(string $apiUrl)
    {
        $this->apiUrl = $apiUrl;
        $this->cookieFile = tempnam(sys_get_temp_dir(), "mw_cookie_");
    }

    public function __destruct()
    {
        if (file_exists($this->cookieFile)) {
            unlink($this->cookieFile);
        }
    }

    private function post(array $params): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_USERAGENT => "GBImageMigration/1.0",
            CURLOPT_TIMEOUT => 120,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("cURL error: $error");
        }

        if ($httpCode === 413) {
            throw new RuntimeException(
                "HTTP 413 Request Entity Too Large (reduce gallery size or use chunked upload)",
            );
        }
        if ($httpCode >= 400 && $httpCode !== 200) {
            throw new RuntimeException(
                "HTTP $httpCode from API: " . substr($response, 0, 300),
            );
        }

        $data = json_decode($response, true);
        if ($data === null) {
            throw new RuntimeException(
                "Invalid JSON from API (HTTP $httpCode): " .
                    substr($response, 0, 500),
            );
        }

        return $data;
    }

    public function get(array $params): array
    {
        $url = $this->apiUrl . "?" . http_build_query($params);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_USERAGENT => "GBImageMigration/1.0",
            CURLOPT_TIMEOUT => 60,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("cURL error: $error");
        }

        $data = json_decode($response, true);
        if ($data === null) {
            throw new RuntimeException(
                "Invalid JSON from API: " . substr($response, 0, 500),
            );
        }

        return $data;
    }

    public function login(string $user, string $password): void
    {
        $tokenData = $this->get([
            "action" => "query",
            "meta" => "tokens",
            "type" => "login",
            "format" => "json",
        ]);
        $loginToken =
            $tokenData["query"]["tokens"]["logintoken"] ??
            throw new RuntimeException("Failed to get login token");

        $result = $this->post([
            "action" => "login",
            "lgname" => $user,
            "lgpassword" => $password,
            "lgtoken" => $loginToken,
            "format" => "json",
        ]);

        if (($result["login"]["result"] ?? "") !== "Success") {
            throw new RuntimeException(
                "Login failed: " . json_encode($result["login"] ?? $result),
            );
        }

        fwrite(STDERR, "Logged in as {$result["login"]["lgusername"]}\n");
    }

    private function getCsrfToken(): string
    {
        if ($this->csrfToken !== null) {
            return $this->csrfToken;
        }

        $data = $this->get([
            "action" => "query",
            "meta" => "tokens",
            "type" => "csrf",
            "format" => "json",
        ]);

        $this->csrfToken =
            $data["query"]["tokens"]["csrftoken"] ??
            throw new RuntimeException("Failed to get CSRF token");

        return $this->csrfToken;
    }

    /**
     * @param array<string, string> $editParams title, summary, and either text or appendtext
     */
    private function editRequest(array $editParams, int $retries = 3): bool
    {
        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $result = $this->post(
                    array_merge(
                        [
                            "action" => "edit",
                            "bot" => "1",
                            "token" => $this->getCsrfToken(),
                            "format" => "json",
                        ],
                        $editParams,
                    ),
                );

                if (isset($result["error"])) {
                    $code = $result["error"]["code"] ?? "unknown";
                    if ($code === "badtoken") {
                        $this->csrfToken = null;
                        continue;
                    }
                    if ($code === "ratelimited" || $code === "maxlag") {
                        $wait = pow(2, $attempt);
                        fwrite(STDERR, "  Rate limited, waiting {$wait}s...\n");
                        sleep($wait);
                        continue;
                    }
                    throw new RuntimeException(
                        "API error ($code): " .
                            ($result["error"]["info"] ?? ""),
                    );
                }

                if (
                    isset($result["edit"]["result"]) &&
                    $result["edit"]["result"] === "Success"
                ) {
                    return true;
                }

                throw new RuntimeException(
                    "Unexpected edit result: " . json_encode($result),
                );
            } catch (RuntimeException $e) {
                if ($attempt === $retries) {
                    throw $e;
                }
                $wait = pow(2, $attempt);
                fwrite(
                    STDERR,
                    "  Retry {$attempt}/{$retries} after {$wait}s: {$e->getMessage()}\n",
                );
                sleep($wait);
            }
        }
        return false;
    }

    public function editPage(
        string $title,
        string $text,
        string $summary,
        int $retries = 3,
    ): bool {
        return $this->editRequest(
            [
                "title" => $title,
                "text" => $text,
                "summary" => $summary,
            ],
            $retries,
        );
    }

    /** Append wikitext to the end of an existing page (same session; used for oversized galleries). */
    public function appendToPage(
        string $title,
        string $appendText,
        string $summary,
        int $retries = 3,
    ): bool {
        return $this->editRequest(
            [
                "title" => $title,
                "appendtext" => $appendText,
                "summary" => $summary,
            ],
            $retries,
        );
    }

    public function resolveGuid(string $guid, int $retries = 3): ?string
    {
        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $data = $this->get([
                    "action" => "ask",
                    "query" => "[[Has guid::{$guid}]]|?Has guid|limit=1",
                    "format" => "json",
                ]);

                $results = $data["query"]["results"] ?? [];
                foreach ($results as $pageTitle => $pageData) {
                    return normalizeWikiPageTitle($pageTitle);
                }
                return null;
            } catch (RuntimeException $e) {
                if ($attempt === $retries) {
                    fwrite(
                        STDERR,
                        "  WARN: resolveGuid({$guid}) failed after {$retries} attempts: {$e->getMessage()}\n",
                    );
                    return null;
                }
                $wait = pow(2, $attempt);
                fwrite(
                    STDERR,
                    "  Retry resolveGuid({$guid}) {$attempt}/{$retries} after {$wait}s: {$e->getMessage()}\n",
                );
                sleep($wait);
            }
        }
        return null;
    }

    public function buildGuidMap(?string $typeFilter = null): array
    {
        $map = [];
        $offset = 0;
        $limit = 500;

        $condition =
            $typeFilter !== null
                ? "[[Has guid::~{$typeFilter}-*]]"
                : "[[Has guid::+]]";

        fwrite(STDERR, "Building GUID -> page title map via SMW Ask API...\n");

        while (true) {
            $data = $this->get([
                "action" => "ask",
                "query" => "{$condition}|?Has guid|limit={$limit}|offset={$offset}",
                "format" => "json",
            ]);

            $results = $data["query"]["results"] ?? [];
            if (empty($results)) {
                break;
            }

            foreach ($results as $pageTitle => $pageData) {
                $printouts = $pageData["printouts"] ?? [];
                $guids = $printouts["Has guid"] ?? [];
                foreach ($guids as $guid) {
                    $guidVal = is_array($guid)
                        ? $guid["fulltext"] ?? ($guid["value"] ?? null)
                        : $guid;
                    if ($guidVal) {
                        $map[$guidVal] = $pageTitle;
                    }
                }
            }

            $count = count($results);
            fwrite(
                STDERR,
                "  Fetched {$count} pages (offset {$offset}), map size: " .
                    count($map) .
                    "\n",
            );

            $continueOffset = $data["query-continue-offset"] ?? null;
            if ($continueOffset === null || $count < $limit) {
                break;
            }
            $offset = (int) $continueOffset;
        }

        fwrite(STDERR, "GUID map complete: " . count($map) . " entries\n");
        return $map;
    }
}

function connectLegacyDb(
    string $host,
    string $port,
    string $user,
    string $pass,
    string $name,
): PDO {
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function queryImagesForEntity(PDO $pdo, int $typeId, int $assocId): array
{
    $stmt = $pdo->prepare("
        SELECT it.name AS tag_name, i.name AS filename, i.path, i.caption
        FROM image_tag it
        JOIN image_imagetag iit ON iit.imagetag_id = it.id
        JOIN image i ON i.id = iit.image_id
        WHERE it.assoc_type_id = :type_id
          AND it.assoc_id = :assoc_id
          AND it.deleted = 0
          AND i.deleted = 0
        ORDER BY it.name, i.id
    ");
    $stmt->execute([":type_id" => $typeId, ":assoc_id" => $assocId]);

    $albums = [];
    while ($row = $stmt->fetch()) {
        $albumName = $row["tag_name"] ?: "Images";
        if (!isset($albums[$albumName])) {
            $albums[$albumName] = [];
        }
        $albums[$albumName][] = [
            "filename" => $row["filename"],
            "path" => $row["path"],
            "caption" => $row["caption"],
        ];
    }
    return $albums;
}

/**
 * Load guid -> MediaWiki page title from CSV (e.g. DBeaver export of SMW query).
 * Expects two columns: guid, page_title. Optional header row with "guid" in first column.
 *
 * @return array<string, string>
 */
function loadGuidMap(string $path): array
{
    if (!is_readable($path)) {
        throw new InvalidArgumentException(
            "GUID map file not readable: {$path}",
        );
    }

    $h = fopen($path, "rb");
    if ($h === false) {
        throw new InvalidArgumentException("Could not open GUID map: {$path}");
    }

    $map = [];
    $lineNum = 0;
    while (($row = fgetcsv($h)) !== false) {
        $lineNum++;
        if (isset($row[0])) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', "", $row[0]);
        }
        if ($lineNum === 1 && isset($row[0])) {
            $c0 = strtolower(trim($row[0]));
            if ($c0 === "guid" || $c0 === "guid_map") {
                continue;
            }
        }
        if (count($row) < 2) {
            continue;
        }
        $guid = trim((string) $row[0]);
        $title = trim((string) $row[1]);
        if ($guid === "" || $title === "") {
            continue;
        }
        $title = normalizeWikiPageTitle($title);
        if (isset($map[$guid]) && $map[$guid] !== $title) {
            fwrite(
                STDERR,
                "WARN: duplicate GUID in map, using last row: {$guid}\n",
            );
        }
        $map[$guid] = $title;
    }
    fclose($h);

    return $map;
}

function buildWikitextBody(array $albums): string
{
    $parts = [];

    foreach ($albums as $albumName => $images) {
        $sectionTitle = trim($albumName) ?: "Images";
        $parts[] = "";
        $parts[] = "== {$sectionTitle} ==";
        $parts[] = "<gb-gallery>";

        foreach ($images as $img) {
            $path = rtrim($img["path"], "/") . "/" . $img["filename"];
            $caption = trim($img["caption"] ?? "");
            if ($caption !== "") {
                $parts[] = "{$path} | {$caption}";
            } else {
                $parts[] = $path;
            }
        }

        $parts[] = "</gb-gallery>";
    }

    return implode("\n", $parts);
}

function buildWikitext(array $albums): string
{
    $body = buildWikitextBody($albums);
    return "{{ImagesPage}}\n\n" . $body . "\n\n{{ImagesPageEnd}}";
}

/**
 * Split long wikitext at newlines so each chunk is at most $maxBytes (UTF-8 bytes).
 *
 * @return list<string>
 */
function splitWikitextBodyAtNewlines(string $body, int $maxBytes): array
{
    if ($body === "") {
        return [""];
    }
    if (strlen($body) <= $maxBytes) {
        return [$body];
    }

    $out = [];
    $start = 0;
    $len = strlen($body);
    while ($start < $len) {
        $remain = $len - $start;
        if ($remain <= $maxBytes) {
            $out[] = substr($body, $start);
            break;
        }
        $portion = substr($body, $start, $maxBytes);
        $nl = strrpos($portion, "\n");
        if ($nl === false) {
            $cutLen = $maxBytes;
        } else {
            $cutLen = $nl + 1;
        }
        $chunk = substr($body, $start, $cutLen);
        $out[] = $chunk;
        $start += strlen($chunk);
    }

    return $out;
}

/** Stay under nginx/client_max_body_size after http_build_query() expansion. */
function maxSinglePostBytes(): int
{
    return 600000;
}

/**
 * Create / overwrite an /Images page, splitting across multiple edits if the gallery is huge (HTTP 413).
 */
function editImagesPageChunked(
    MWApiClient $api,
    string $title,
    array $albums,
    string $summary,
    int $maxBodySliceBytes,
    int $sleepMs,
): void {
    $header = "{{ImagesPage}}\n\n";
    $footer = "\n\n{{ImagesPageEnd}}";
    $body = buildWikitextBody($albums);
    $full = $header . $body . $footer;

    if (strlen($full) <= maxSinglePostBytes()) {
        $api->editPage($title, $full, $summary);
        if ($sleepMs > 0) {
            usleep($sleepMs * 1000);
        }
        return;
    }

    $slices = splitWikitextBodyAtNewlines($body, $maxBodySliceBytes);
    $n = count($slices);
    fwrite(
        STDERR,
        "  Large gallery: {$n} API edit(s) for {$title} (avoids HTTP 413)\n",
    );

    $api->editPage($title, $header . $slices[0], "{$summary} (1/{$n})");
    if ($sleepMs > 0) {
        usleep($sleepMs * 1000);
    }

    for ($i = 1; $i < $n; $i++) {
        $piece =
            $i === $n - 1
                ? "\n\n" . $slices[$i] . $footer
                : "\n\n" . $slices[$i];
        $api->appendToPage(
            $title,
            $piece,
            "{$summary} (" . ($i + 1) . "/{$n})",
        );
        if ($sleepMs > 0) {
            usleep($sleepMs * 1000);
        }
    }
}

fwrite(STDERR, "=== Giant Bomb Legacy Image Migration ===\n");
fwrite(
    STDERR,
    "Legacy DB: {$LEGACY_DB_HOST}:{$LEGACY_DB_PORT}/{$LEGACY_DB_NAME}\n",
);
if ($dryRun) {
    fwrite(STDERR, "Mode: DRY RUN (no API calls)\n");
} else {
    fwrite(STDERR, "Target: {$MW_API_URL}\n");
}
if ($singleGuid) {
    fwrite(STDERR, "Single GUID: {$singleGuid}\n");
} else {
    fwrite(STDERR, "Type filter: " . ($filterType ?? "all") . "\n");
}
fwrite(STDERR, "Sleep: {$sleepMs}ms between edits\n");
fwrite(
    STDERR,
    "Max body slice: {$maxBodySlice} bytes (chunked uploads if page larger)\n",
);
if ($maxEntities !== null) {
    fwrite(STDERR, "Limit: {$maxEntities} entities\n");
}
if ($resumeAfter !== null) {
    fwrite(STDERR, "Resume after GUID: {$resumeAfter}\n");
}
fwrite(STDERR, "\n");

$pdo = connectLegacyDb(
    $LEGACY_DB_HOST,
    $LEGACY_DB_PORT,
    $LEGACY_DB_USER,
    $LEGACY_DB_PASS,
    $LEGACY_DB_NAME,
);
fwrite(STDERR, "Connected to legacy database\n");

$guidMap = null;
if ($guidMapPath !== null) {
    try {
        $guidMap = loadGuidMap($guidMapPath);
        fwrite(
            STDERR,
            "Loaded " .
                count($guidMap) .
                " GUID mappings from {$guidMapPath}\n",
        );
    } catch (InvalidArgumentException $e) {
        fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
        exit(1);
    }
}

if ($singleGuid) {
    $parts = explode("-", $singleGuid, 2);
    if (count($parts) !== 2) {
        fwrite(
            STDERR,
            "ERROR: --guid must be in format TYPE_ID-ASSOC_ID (e.g. 3030-16559)\n",
        );
        exit(1);
    }

    $albums = queryImagesForEntity($pdo, (int) $parts[0], (int) $parts[1]);
    if (empty($albums)) {
        fwrite(STDERR, "No images found for GUID {$singleGuid}\n");
        exit(0);
    }

    $totalImages = array_sum(array_map("count", $albums));
    $wikitext = buildWikitext($albums);

    if ($dryRun) {
        echo "--- {$singleGuid}/Images ({$totalImages} images, " .
            count($albums) .
            " albums) ---\n";
        echo $wikitext;
        echo "\n";
    } else {
        $api = new MWApiClient($MW_API_URL);
        $api->login($MW_BOT_USER, $MW_BOT_PASS);
        $pageTitle = $guidMap[$singleGuid] ?? null;
        if ($pageTitle === null) {
            fwrite(STDERR, "Resolving GUID {$singleGuid} via SMW API...\n");
            $pageTitle = $api->resolveGuid($singleGuid);
        }
        if ($pageTitle === null) {
            fwrite(
                STDERR,
                "ERROR: No wiki page found for GUID {$singleGuid}\n",
            );
            exit(1);
        }
        fwrite(STDERR, "Found: {$pageTitle}\n");
        editImagesPageChunked(
            $api,
            "{$pageTitle}/Images",
            $albums,
            "Import legacy image gallery ({$totalImages} images)",
            $maxBodySlice,
            $sleepMs,
        );
        fwrite(STDERR, "Created {$pageTitle}/Images ({$totalImages} images)\n");
    }
    exit(0);
}

if ($dryRun) {
    fwrite(
        STDERR,
        "ERROR: Bulk mode requires API credentials. Use --guid for dry-run.\n",
    );
    exit(1);
}

$api = new MWApiClient($MW_API_URL);
$api->login($MW_BOT_USER, $MW_BOT_PASS);

if ($guidMap === null) {
    fwrite(
        STDERR,
        "NOTE: No --guid-map; resolving each GUID via SMW Ask (slow). Export SMW to CSV and pass --guid-map for bulk runs.\n",
    );
}

$typeCondition = $filterType !== null ? " AND assoc_type_id = :type_id" : "";
$resumeCondition = "";
if ($resumeAfter !== null) {
    $raParts = explode("-", $resumeAfter, 2);
    if (count($raParts) === 2) {
        $resumeCondition =
            " AND (assoc_type_id > :ra_type OR (assoc_type_id = :ra_type2 AND assoc_id > :ra_id))";
    }
}

$sql = "
    SELECT DISTINCT assoc_type_id, assoc_id
    FROM image_tag
    WHERE deleted = 0{$typeCondition}{$resumeCondition}
    ORDER BY assoc_type_id, assoc_id
";
$stmt = $pdo->prepare($sql);
if ($filterType !== null) {
    $stmt->bindValue(":type_id", (int) $filterType, PDO::PARAM_INT);
}
if ($resumeAfter !== null && count($raParts) === 2) {
    $stmt->bindValue(":ra_type", (int) $raParts[0], PDO::PARAM_INT);
    $stmt->bindValue(":ra_type2", (int) $raParts[0], PDO::PARAM_INT);
    $stmt->bindValue(":ra_id", (int) $raParts[1], PDO::PARAM_INT);
}
$stmt->execute();

$allGuids = $stmt->fetchAll();
$totalGuids = count($allGuids);
fwrite(STDERR, "Found {$totalGuids} entities with images in legacy DB\n");

$entityCount = 0;
$imageCount = 0;
$skippedCount = 0;
$createdCount = 0;
$noPageCount = 0;

foreach ($allGuids as $row) {
    if ($maxEntities !== null && $entityCount >= $maxEntities) {
        break;
    }

    $guid = $row["assoc_type_id"] . "-" . $row["assoc_id"];
    if ($guidMap !== null) {
        $pageTitle = $guidMap[$guid] ?? null;
    } else {
        $pageTitle = $api->resolveGuid($guid);
    }

    if ($pageTitle === null) {
        $noPageCount++;
        continue;
    }

    $albums = queryImagesForEntity(
        $pdo,
        (int) $row["assoc_type_id"],
        (int) $row["assoc_id"],
    );
    if (empty($albums)) {
        continue;
    }

    $entityCount++;
    $totalImages = array_sum(array_map("count", $albums));
    $imageCount += $totalImages;

    $imagesPageTitle = "{$pageTitle}/Images";

    try {
        editImagesPageChunked(
            $api,
            $imagesPageTitle,
            $albums,
            "Import legacy image gallery ({$totalImages} images)",
            $maxBodySlice,
            $sleepMs,
        );
        $createdCount++;

        if ($entityCount % $batchSize === 0) {
            fwrite(
                STDERR,
                "Progress: {$entityCount}/{$totalGuids} entities, {$createdCount} created, {$skippedCount} errors, {$noPageCount} no wiki page, {$imageCount} images, last GUID: {$guid}\n",
            );
        }
    } catch (RuntimeException $e) {
        fwrite(
            STDERR,
            "  ERROR creating {$imagesPageTitle}: {$e->getMessage()}\n",
        );
        $skippedCount++;
    }
}

fwrite(STDERR, "\n=== Migration Complete ===\n");
fwrite(STDERR, "Entities with images in legacy DB: {$totalGuids}\n");
fwrite(STDERR, "Entities processed: {$entityCount}\n");
fwrite(STDERR, "Pages created: {$createdCount}\n");
fwrite(STDERR, "No wiki page found: {$noPageCount}\n");
fwrite(STDERR, "Errors: {$skippedCount}\n");
fwrite(STDERR, "Total images: {$imageCount}\n");
