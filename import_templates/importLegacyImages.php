<?php
/**
 * Standalone CLI script to migrate legacy Giant Bomb image galleries
 * to MediaWiki /Images subpages via the MediaWiki Action API.
 *
 * Reads from a local MySQL database (giantbomb-mysql) and writes to a
 * remote MediaWiki instance using bot credentials.
 *
 * The script iterates over wiki pages that have a GUID (via SMW Ask API),
 * then queries the legacy DB for images belonging to each entity. This
 * avoids scanning the entire legacy image table.
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
 *   --env=<path>        Path to .env file (default: .env.migration)
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
]);

$envFile = $options["env"] ?? __DIR__ . "/.env.migration";
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
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
$MW_API_URL     = getenv("MW_API_URL");
$MW_BOT_USER    = getenv("MW_BOT_USER");
$MW_BOT_PASS    = getenv("MW_BOT_PASSWORD");

$filterType   = $options["type"] ?? null;
$singleGuid   = $options["guid"] ?? null;
$maxEntities  = isset($options["limit"]) ? (int)$options["limit"] : null;
$resumeAfter  = $options["resume-after"] ?? null;
$batchSize    = isset($options["batch-size"]) ? (int)$options["batch-size"] : 100;
$dryRun       = isset($options["dry-run"]) || ($singleGuid && !isset($options["force"]));
$sleepMs      = isset($options["sleep"]) ? (int)$options["sleep"] : 200;

if (!$LEGACY_DB_USER || !$LEGACY_DB_NAME) {
    fwrite(STDERR, "ERROR: LEGACY_DB_USER and LEGACY_DB_NAME must be set.\n");
    fwrite(STDERR, "Create a .env.migration file or pass --env=<path>.\n");
    exit(1);
}

if (!$dryRun && (!$MW_API_URL || !$MW_BOT_USER || !$MW_BOT_PASS)) {
    fwrite(STDERR, "ERROR: MW_API_URL, MW_BOT_USER, and MW_BOT_PASSWORD must be set (or use --dry-run).\n");
    exit(1);
}

class MWApiClient {
    private string $apiUrl;
    private string $cookieFile;
    private ?string $csrfToken = null;

    public function __construct(string $apiUrl) {
        $this->apiUrl = $apiUrl;
        $this->cookieFile = tempnam(sys_get_temp_dir(), "mw_cookie_");
    }

    public function __destruct() {
        if (file_exists($this->cookieFile)) {
            unlink($this->cookieFile);
        }
    }

    private function post(array $params): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->apiUrl,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE     => $this->cookieFile,
            CURLOPT_COOKIEJAR      => $this->cookieFile,
            CURLOPT_USERAGENT      => "GBImageMigration/1.0",
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("cURL error: $error");
        }

        $data = json_decode($response, true);
        if ($data === null) {
            throw new RuntimeException("Invalid JSON from API (HTTP $httpCode): " . substr($response, 0, 500));
        }

        return $data;
    }

    private function get(array $params): array {
        $url = $this->apiUrl . "?" . http_build_query($params);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE     => $this->cookieFile,
            CURLOPT_COOKIEJAR      => $this->cookieFile,
            CURLOPT_USERAGENT      => "GBImageMigration/1.0",
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("cURL error: $error");
        }

        $data = json_decode($response, true);
        if ($data === null) {
            throw new RuntimeException("Invalid JSON from API: " . substr($response, 0, 500));
        }

        return $data;
    }

    public function login(string $user, string $password): void {
        $tokenData = $this->get([
            "action" => "query",
            "meta"   => "tokens",
            "type"   => "login",
            "format" => "json",
        ]);
        $loginToken = $tokenData["query"]["tokens"]["logintoken"]
            ?? throw new RuntimeException("Failed to get login token");

        $result = $this->post([
            "action"     => "login",
            "lgname"     => $user,
            "lgpassword" => $password,
            "lgtoken"    => $loginToken,
            "format"     => "json",
        ]);

        if (($result["login"]["result"] ?? "") !== "Success") {
            throw new RuntimeException("Login failed: " . json_encode($result["login"] ?? $result));
        }

        fwrite(STDERR, "Logged in as {$result['login']['lgusername']}\n");
    }

    private function getCsrfToken(): string {
        if ($this->csrfToken !== null) {
            return $this->csrfToken;
        }

        $data = $this->get([
            "action" => "query",
            "meta"   => "tokens",
            "type"   => "csrf",
            "format" => "json",
        ]);

        $this->csrfToken = $data["query"]["tokens"]["csrftoken"]
            ?? throw new RuntimeException("Failed to get CSRF token");

        return $this->csrfToken;
    }

    public function editPage(string $title, string $text, string $summary, int $retries = 3): bool {
        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $result = $this->post([
                    "action"  => "edit",
                    "title"   => $title,
                    "text"    => $text,
                    "summary" => $summary,
                    "bot"     => "1",
                    "token"   => $this->getCsrfToken(),
                    "format"  => "json",
                ]);

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
                    throw new RuntimeException("API error ($code): " . ($result["error"]["info"] ?? ""));
                }

                if (isset($result["edit"]["result"]) && $result["edit"]["result"] === "Success") {
                    return true;
                }

                throw new RuntimeException("Unexpected edit result: " . json_encode($result));
            } catch (RuntimeException $e) {
                if ($attempt === $retries) {
                    throw $e;
                }
                $wait = pow(2, $attempt);
                fwrite(STDERR, "  Retry {$attempt}/{$retries} after {$wait}s: {$e->getMessage()}\n");
                sleep($wait);
            }
        }
        return false;
    }

    public function resolveGuid(string $guid): ?string {
        $data = $this->get([
            "action" => "ask",
            "query"  => "[[Has guid::{$guid}]]|?Has guid|limit=1",
            "format" => "json",
        ]);

        $results = $data["query"]["results"] ?? [];
        foreach ($results as $pageTitle => $pageData) {
            return $pageTitle;
        }
        return null;
    }

    public function buildGuidMap(?string $typeFilter = null): array {
        $map = [];
        $offset = 0;
        $limit = 500;

        $condition = $typeFilter !== null
            ? "[[Has guid::~{$typeFilter}-*]]"
            : "[[Has guid::+]]";

        fwrite(STDERR, "Building GUID -> page title map via SMW Ask API...\n");

        while (true) {
            $data = $this->get([
                "action" => "ask",
                "query"  => "{$condition}|?Has guid|limit={$limit}|offset={$offset}",
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
                    $guidVal = is_array($guid) ? ($guid["fulltext"] ?? $guid["value"] ?? null) : $guid;
                    if ($guidVal) {
                        $map[$guidVal] = $pageTitle;
                    }
                }
            }

            $count = count($results);
            fwrite(STDERR, "  Fetched {$count} pages (offset {$offset}), map size: " . count($map) . "\n");

            $continueOffset = $data["query-continue-offset"] ?? null;
            if ($continueOffset === null || $count < $limit) {
                break;
            }
            $offset = (int)$continueOffset;
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
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function queryImagesForEntity(PDO $pdo, int $typeId, int $assocId): array {
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
            "path"     => $row["path"],
            "caption"  => $row["caption"],
        ];
    }
    return $albums;
}

function buildWikitext(array $albums): string {
    $parts = ["{{ImagesPage}}"];

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

    $parts[] = "";
    $parts[] = "{{ImagesPageEnd}}";
    return implode("\n", $parts);
}

fwrite(STDERR, "=== Giant Bomb Legacy Image Migration ===\n");
fwrite(STDERR, "Legacy DB: {$LEGACY_DB_HOST}:{$LEGACY_DB_PORT}/{$LEGACY_DB_NAME}\n");
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
if ($maxEntities !== null) {
    fwrite(STDERR, "Limit: {$maxEntities} entities\n");
}
if ($resumeAfter !== null) {
    fwrite(STDERR, "Resume after GUID: {$resumeAfter}\n");
}
fwrite(STDERR, "\n");

$pdo = connectLegacyDb($LEGACY_DB_HOST, $LEGACY_DB_PORT, $LEGACY_DB_USER, $LEGACY_DB_PASS, $LEGACY_DB_NAME);
fwrite(STDERR, "Connected to legacy database\n");

if ($singleGuid) {
    $parts = explode("-", $singleGuid, 2);
    if (count($parts) !== 2) {
        fwrite(STDERR, "ERROR: --guid must be in format TYPE_ID-ASSOC_ID (e.g. 3030-16559)\n");
        exit(1);
    }

    $albums = queryImagesForEntity($pdo, (int)$parts[0], (int)$parts[1]);
    if (empty($albums)) {
        fwrite(STDERR, "No images found for GUID {$singleGuid}\n");
        exit(0);
    }

    $totalImages = array_sum(array_map('count', $albums));
    $wikitext = buildWikitext($albums);

    if ($dryRun) {
        echo "--- {$singleGuid}/Images ({$totalImages} images, " . count($albums) . " albums) ---\n";
        echo $wikitext;
        echo "\n";
    } else {
        $api = new MWApiClient($MW_API_URL);
        $api->login($MW_BOT_USER, $MW_BOT_PASS);
        fwrite(STDERR, "Resolving GUID {$singleGuid}...\n");
        $pageTitle = $api->resolveGuid($singleGuid);
        if ($pageTitle === null) {
            fwrite(STDERR, "ERROR: No wiki page found for GUID {$singleGuid}\n");
            exit(1);
        }
        fwrite(STDERR, "Found: {$pageTitle}\n");
        $api->editPage("{$pageTitle}/Images", $wikitext, "Import legacy image gallery ({$totalImages} images)");
        fwrite(STDERR, "Created {$pageTitle}/Images ({$totalImages} images)\n");
    }
    exit(0);
}

$api = null;
$guidMap = [];

if (!$dryRun) {
    $api = new MWApiClient($MW_API_URL);
    $api->login($MW_BOT_USER, $MW_BOT_PASS);
}

$guidMap = $dryRun
    ? [] // dry-run without API needs --guid mode; bulk dry-run still needs the map
    : $api->buildGuidMap($filterType);

if (!$dryRun && empty($guidMap)) {
    fwrite(STDERR, "No wiki pages with GUIDs found. Nothing to migrate.\n");
    exit(0);
}

$entityCount = 0;
$imageCount = 0;
$skippedCount = 0;
$createdCount = 0;
$resumeSkipping = ($resumeAfter !== null);

$sortedGuids = array_keys($guidMap);
sort($sortedGuids);

foreach ($sortedGuids as $guid) {
    if ($resumeSkipping) {
        if ($guid === $resumeAfter) {
            $resumeSkipping = false;
        }
        continue;
    }

    if ($maxEntities !== null && $entityCount >= $maxEntities) {
        break;
    }

    $parts = explode("-", $guid, 2);
    if (count($parts) !== 2) {
        continue;
    }
    $typeId = (int)$parts[0];
    $assocId = (int)$parts[1];

    $albums = queryImagesForEntity($pdo, $typeId, $assocId);
    if (empty($albums)) {
        continue;
    }

    $entityCount++;
    $totalImages = array_sum(array_map('count', $albums));
    $imageCount += $totalImages;

    $pageTitle = $guidMap[$guid];
    $wikitext = buildWikitext($albums);
    $imagesPageTitle = "{$pageTitle}/Images";

    try {
        $api->editPage($imagesPageTitle, $wikitext, "Import legacy image gallery ({$totalImages} images)");
        $createdCount++;

        if ($entityCount % $batchSize === 0) {
            fwrite(STDERR, "Progress: {$entityCount} entities, {$createdCount} created, {$skippedCount} skipped, {$imageCount} images, last GUID: {$guid}\n");
        }
    } catch (RuntimeException $e) {
        fwrite(STDERR, "  ERROR creating {$imagesPageTitle}: {$e->getMessage()}\n");
        $skippedCount++;
    }

    if ($sleepMs > 0) {
        usleep($sleepMs * 1000);
    }
}

fwrite(STDERR, "\n=== Migration Complete ===\n");
fwrite(STDERR, "Entities processed: {$entityCount}\n");
fwrite(STDERR, "Pages created: {$createdCount}\n");
fwrite(STDERR, "Skipped (errors): {$skippedCount}\n");
fwrite(STDERR, "Total images: {$imageCount}\n");
