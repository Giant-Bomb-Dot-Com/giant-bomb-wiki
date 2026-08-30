<?php
// generate_release_repairs.php — rebuild every /Releases and /DLC subpage body
// from the legacy db (the migration blanked all dates, used the game cover for
// every release image and dropped company codes). guid -> wiki title via the
// wiki's own Has_guid data; no legacy mw_page_name columns exist.
//
// Runs in the dev container (wiki db via "db", legacy via
// host.docker.internal:3307 + LEGACY_PW env).
//   php generate_release_repairs.php --out .local/release-repairs.jsonl \
//       [--dlc-out .local/dlc-repairs.jsonl] [--game-id=N] [--limit=N]
// NB: getopt optional values need "=" -- "--limit 25" silently means no limit
//
// Output JSONL rows: {"title": "...", "text": "..."} (full body replace).
// Skips + rejected dates land in <out>.log. Pages whose game has zero legacy
// releases get blanked to the bare {{Releases}} header so the broken migrated
// subobjects go away. Apply with apply_release_repairs_remote.php.

require_once __DIR__ . "/libs/subpage_builder.php";

ini_set("memory_limit", "-1");

$opts = getopt("", ["out:", "dlc-out::", "game-id::", "limit::"]);
$outFile = $opts["out"] ?? null;
if (!$outFile) {
    fwrite(STDERR, "--out required\n");
    exit(1);
}
$dlcOutFile = $opts["dlc-out"] ?? null;
$onlyGameId = (int) ($opts["game-id"] ?? 0);
$limit = (int) ($opts["limit"] ?? 0);

// junk legacy games excluded outright (e.g. 16641 = 17 releases named "Delete")
$DENYLIST = [16641];

// legacy duplicate company rows -> the row whose guid has a wiki page
// (911 Compile -> 12963 "Compile ?", etc; found via the generation log)
$COMPANY_ALIASES = [
    911 => 12963,   // Compile
    3659 => 19581,  // D3 Go!
    19676 => 19690, // Moonradish Inc.
    19367 => 2544,  // h.a.n.d., Inc.
];

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$wiki = mysqli_connect("db", getenv("MARIADB_USER"), getenv("MARIADB_PASSWORD"), getenv("MARIADB_DATABASE") ?: "wiki_db143");
$wiki->set_charset("utf8mb4");
$legacy = mysqli_connect("host.docker.internal", "root", getenv("LEGACY_PW"), "giantbomb", 3307);
$legacy->set_charset("utf8mb4");

$log = fopen($outFile . ".log", "w");
$logLine = function (string $msg) use ($log) {
    fwrite($log, $msg . "\n");
};

// guid -> ns0 title, one pass (see generate_figure_repairs.php for the o_hash
// rationale). we need games (3030), companies (3010) and platforms (3045).
fwrite(STDERR, "preloading Has_guid map...\n");
$guidMap = [];
$pid = $wiki->query("SELECT smw_id FROM smw_object_ids WHERE smw_title = 'Has_guid' AND smw_namespace = 102")->fetch_row()[0];
$gres = $wiki->query(
    "SELECT COALESCE(b.o_blob, b.o_hash), s.smw_title FROM smw_di_blob b
     JOIN smw_object_ids s ON b.s_id = s.smw_id
     WHERE b.p_id = " . (int) $pid . " AND s.smw_namespace = 0",
    MYSQLI_USE_RESULT
);
while ($g = $gres->fetch_row()) {
    $guidMap[$g[0]] = $g[1];
}
$gres->close();
fwrite(STDERR, count($guidMap) . " guids loaded\n");

$gameTitle = fn($id) => $guidMap["3030-$id"] ?? null;
$companyTitle = function ($id) use (&$guidMap, $COMPANY_ALIASES) {
    $id = $COMPANY_ALIASES[$id] ?? $id;
    return $guidMap["3010-$id"] ?? null;
};
$platformTitle = fn($id) => $guidMap["3045-$id"] ?? null;

// bulk-load a two-column relation table into [left => [right, ...]]
function loadRelation(mysqli $db, string $sql): array
{
    $map = [];
    $res = $db->query($sql, MYSQLI_USE_RESULT);
    while ($r = $res->fetch_row()) {
        $map[(int) $r[0]][] = (int) $r[1];
    }
    $res->close();
    return $map;
}

fwrite(STDERR, "preloading legacy relations + images...\n");
$relDev = loadRelation($legacy, "SELECT release_id, company_id FROM wiki_game_release_to_developer");
$relPub = loadRelation($legacy, "SELECT release_id, company_id FROM wiki_game_release_to_publisher");
$relRes = loadRelation($legacy, "SELECT release_id, resolution_id FROM wiki_game_release_to_resolution");
$relSnd = loadRelation($legacy, "SELECT release_id, soundsystem_id FROM wiki_game_release_to_sound_system");
$relSpf = loadRelation($legacy, "SELECT release_id, feature_id FROM wiki_game_release_to_singleplayer_feature");
$relMpf = loadRelation($legacy, "SELECT release_id, feature_id FROM wiki_game_release_to_multiplayer_feature");

// image urls for releases, dlc and game-cover fallbacks, keyed by image id
$imageUrl = [];
$ires = $legacy->query(
    "SELECT DISTINCT i.id, i.path, i.name FROM image i
     WHERE i.id IN (SELECT image_id FROM wiki_game_release WHERE image_id IS NOT NULL)
        OR i.id IN (SELECT image_id FROM wiki_game_dlc WHERE image_id IS NOT NULL)
        OR i.id IN (SELECT image_id FROM wiki_game WHERE image_id IS NOT NULL)",
    MYSQLI_USE_RESULT
);
while ($r = $ires->fetch_row()) {
    $imageUrl[(int) $r[0]] = SubpageBuilder::imageUrlFromPathName($r[1], $r[2]);
}
$ires->close();
fwrite(STDERR, count($imageUrl) . " image urls loaded\n");

$gameCover = [];
$gres = $legacy->query("SELECT id, image_id FROM wiki_game WHERE image_id IS NOT NULL", MYSQLI_USE_RESULT);
while ($r = $gres->fetch_row()) {
    $gameCover[(int) $r[0]] = $imageUrl[(int) $r[1]] ?? "";
}
$gres->close();

$titlesToStrings = function (array $ids, callable $resolve, string $kind, int $releaseId) use ($logLine): array {
    $out = [];
    foreach (array_unique($ids) as $id) {
        $t = $resolve($id);
        if ($t === null) {
            $logLine("no wiki page for $kind $id (release/dlc $releaseId)");
            continue;
        }
        $out[$t] = true;
    }
    return array_keys($out);
};

$mapIds = function (array $ids, array $map): array {
    $out = [];
    foreach (array_unique($ids) as $id) {
        if (isset($map[$id])) {
            $out[$map[$id]] = true;
        }
    }
    return array_keys($out);
};

// ---- releases ----

fwrite(STDERR, "building release pages...\n");
$where = "deleted = 0";
if ($onlyGameId) {
    $where .= " AND game_id = " . $onlyGameId;
}
$releasesByGame = [];
$rres = $legacy->query(
    "SELECT id, game_id, region_id, product_code_type, company_code_type, rating_id,
            image_id, release_date, release_date_type, product_code, company_code,
            name, widescreen_support, minimum_players, maximum_players, platform_id
       FROM wiki_game_release WHERE $where ORDER BY game_id, id",
    MYSQLI_USE_RESULT
);
while ($r = $rres->fetch_assoc()) {
    $releasesByGame[(int) $r["game_id"]][] = $r;
}
$rres->close();

$out = fopen($outFile, "w");
$emit = function (string $title, string $text) use ($out) {
    fwrite($out, json_encode(["title" => $title, "text" => $text], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
};

$written = 0;
ksort($releasesByGame);
foreach ($releasesByGame as $gameId => $rows) {
    if (in_array($gameId, $DENYLIST)) {
        $logLine("denylisted game $gameId skipped (" . count($rows) . " releases)");
        continue;
    }
    $title = $gameTitle($gameId);
    if ($title === null) {
        $logLine("no wiki page for game $gameId (" . count($rows) . " releases)");
        continue;
    }

    $objects = [];
    foreach ($rows as $r) {
        $rid = (int) $r["id"];
        $img = $imageUrl[(int) $r["image_id"]] ?? "";
        if ($img === "") {
            $img = $gameCover[$gameId] ?? "";
        }
        $companyCode = trim((string) $r["company_code"]);
        $platform = $platformTitle((int) $r["platform_id"]);
        if ($platform === null && !empty($r["platform_id"])) {
            $logLine("no wiki page for platform {$r["platform_id"]} (release $rid)");
        }

        $objects[] = array_merge(
            [
                "Guid" => "3050-$rid",
                "Name" => (string) $r["name"],
                "Image" => $img,
                "Region" => SubpageBuilder::REGIONS[$r["region_id"]] ?? "",
                "Platform" => $platform ?? "",
                "Rating" => SubpageBuilder::RATINGS[$r["rating_id"]] ?? "",
                "Developers" => $titlesToStrings($relDev[$rid] ?? [], $companyTitle, "company", $rid),
                "Publishers" => $titlesToStrings($relPub[$rid] ?? [], $companyTitle, "company", $rid),
                "ProductCode" => (string) $r["product_code"],
                "ProductCodeType" => SubpageBuilder::guessProductCodeType($r["product_code"], $r["product_code_type"]),
                "CompanyCode" => $companyCode,
                "CompanyCodeType" =>
                    $companyCode === ""
                        ? ""
                        : SubpageBuilder::COMPANY_CODE_TYPES[$r["company_code_type"]] ?? "",
                "WidescreenSupport" => SubpageBuilder::widescreenLabel($r["widescreen_support"]),
                "Resolutions" => $mapIds($relRes[$rid] ?? [], SubpageBuilder::RESOLUTIONS),
                "SoundSystems" => $mapIds($relSnd[$rid] ?? [], SubpageBuilder::SOUND_SYSTEMS),
                "SinglePlayerFeatures" => $mapIds($relSpf[$rid] ?? [], SubpageBuilder::FEATURES),
                "MultiplayerFeatures" => $mapIds($relMpf[$rid] ?? [], SubpageBuilder::FEATURES),
                "MinimumPlayers" => (string) $r["minimum_players"],
                "MaximumPlayers" => (string) $r["maximum_players"],
            ],
            SubpageBuilder::dateParams($r["release_date"], $r["release_date_type"], $rid),
        );
    }

    $emit("$title/Releases", SubpageBuilder::renderReleasesPage($title, $objects, SubpageBuilder::ESCAPE_API));
    $written++;
    if ($limit && $written >= $limit) {
        break;
    }
}

// blank /Releases pages whose game has zero legacy rows (broken migrated
// subobjects, nothing to restore) -- full runs only
if (!$onlyGameId && !$limit) {
    $titleToGuid = [];
    foreach ($guidMap as $guid => $t) {
        if (strpos($guid, "3030-") === 0) {
            $titleToGuid[$t] = (int) substr($guid, 5);
        }
    }
    $pres = $wiki->query(
        "SELECT page_title FROM page WHERE page_namespace = 0 AND page_title LIKE '%/Releases'",
        MYSQLI_USE_RESULT
    );
    $stale = [];
    while ($p = $pres->fetch_row()) {
        $parent = substr($p[0], 0, -strlen("/Releases"));
        $gid = $titleToGuid[$parent] ?? null;
        if ($gid !== null && !isset($releasesByGame[$gid])) {
            $stale[] = [$p[0], $parent];
        }
    }
    $pres->close();
    foreach ($stale as [$pageTitle, $parent]) {
        $emit($pageTitle, "{{Releases\n|ParentPage=$parent\n}}\n");
        $logLine("blanked stale page $pageTitle (game has no legacy releases)");
    }
    fwrite(STDERR, count($stale) . " stale /Releases pages blanked\n");
}

fclose($out);
fwrite(STDERR, "$written release pages written to $outFile\n");

// ---- dlc ----

if ($dlcOutFile) {
    fwrite(STDERR, "building dlc pages...\n");
    $dlcDev = loadRelation($legacy, "SELECT dlc_id, company_id FROM wiki_game_dlc_to_developer");
    $dlcPub = loadRelation($legacy, "SELECT dlc_id, company_id FROM wiki_game_dlc_to_publisher");
    $dlcType = loadRelation($legacy, "SELECT dlc_id, type_id FROM wiki_game_dlc_to_type");

    $dlcTypeNames = [];
    $tres = $legacy->query("SELECT id, name FROM wiki_game_dlc_type");
    while ($t = $tres->fetch_row()) {
        $dlcTypeNames[(int) $t[0]] = $t[1];
    }

    $dlcsByGame = [];
    $dres = $legacy->query(
        "SELECT id, game_id, image_id, release_date, release_date_type, name,
                launch_price, deck, platform_id
           FROM wiki_game_dlc WHERE $where ORDER BY game_id, id",
        MYSQLI_USE_RESULT
    );
    while ($d = $dres->fetch_assoc()) {
        $dlcsByGame[(int) $d["game_id"]][] = $d;
    }
    $dres->close();

    $dout = fopen($dlcOutFile, "w");
    $emitDlc = function (string $title, string $text) use ($dout) {
        fwrite($dout, json_encode(["title" => $title, "text" => $text], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    };

    $dlcWritten = 0;
    ksort($dlcsByGame);
    foreach ($dlcsByGame as $gameId => $rows) {
        $title = $gameTitle($gameId);
        if ($title === null) {
            $logLine("no wiki page for game $gameId (" . count($rows) . " dlc)");
            continue;
        }

        $objects = [];
        foreach ($rows as $d) {
            $did = (int) $d["id"];
            $platform = $platformTitle((int) $d["platform_id"]);
            $objects[] = array_merge(
                [
                    "Guid" => "3020-$did",
                    "Name" => (string) $d["name"],
                    "Image" => $imageUrl[(int) $d["image_id"]] ?? "",
                    "Deck" => (string) $d["deck"],
                    "LaunchPrice" => (string) $d["launch_price"],
                    "Platform" => $platform ?? "",
                    "Developers" => $titlesToStrings($dlcDev[$did] ?? [], $companyTitle, "company", $did),
                    "Publishers" => $titlesToStrings($dlcPub[$did] ?? [], $companyTitle, "company", $did),
                    "DlcTypes" => $mapIds($dlcType[$did] ?? [], $dlcTypeNames),
                ],
                SubpageBuilder::dateParams($d["release_date"], $d["release_date_type"], $did),
            );
        }

        $emitDlc("$title/DLC", SubpageBuilder::renderDlcPage($title, $objects, SubpageBuilder::ESCAPE_API));
        $dlcWritten++;
    }

    // stale /DLC pages, same deal as /Releases
    if (!$onlyGameId && !$limit) {
        $pres = $wiki->query(
            "SELECT page_title FROM page WHERE page_namespace = 0 AND page_title LIKE '%/DLC'",
            MYSQLI_USE_RESULT
        );
        $staleDlc = [];
        while ($p = $pres->fetch_row()) {
            $parent = substr($p[0], 0, -strlen("/DLC"));
            $gid = $titleToGuid[$parent] ?? null;
            if ($gid !== null && !isset($dlcsByGame[$gid])) {
                $staleDlc[] = [$p[0], $parent];
            }
        }
        $pres->close();
        foreach ($staleDlc as [$pageTitle, $parent]) {
            $emitDlc($pageTitle, "{{DLC\n|ParentPage=$parent\n}}\n");
            $logLine("blanked stale page $pageTitle (game has no legacy dlc)");
        }
        fwrite(STDERR, count($staleDlc) . " stale /DLC pages blanked\n");
    }

    fclose($dout);
    fwrite(STDERR, "$dlcWritten dlc pages written to $dlcOutFile\n");
}

foreach (SubpageBuilder::$rejectedDates as $rej) {
    $logLine("rejected date {$rej["date"]} on row {$rej["id"]} -> emitted as None");
}
fclose($log);
fwrite(STDERR, "done\n");

?>
