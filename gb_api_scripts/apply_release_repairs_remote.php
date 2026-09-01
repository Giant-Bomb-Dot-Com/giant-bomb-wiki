<?php
// apply_release_repairs_remote.php — full-body replace of /Releases and /DLC
// subpages from a generate_release_repairs.php jsonl. Gate: the page's latest
// revision must be by the migration bot or the repair bot itself (every one of
// these pages is bot-only today; the check guards edits made between now and
// the run). Missing pages are skipped, never created — a miss means the local
// snapshot is stale or a moderator deleted the page.
//
//   BOT_PASSWORD=x MW_SK=y php apply_release_repairs_remote.php \
//     --api https://giantbomb.com/wiki/api.php --user Giantbomb@repair \
//     --file .local/release-repairs.jsonl \
//     --summary "Restore release data from legacy DB (release-repair v1)" \
//     [--dry-run] [--throttle 300] [--start-at TITLE]
//
// --start-at resumes a run mid-file (skips rows until the given title).
// Pace to the job queue: watch the job table and pause/raise --throttle if it
// grows past ~50k (prod drains one runJobs --maxtime=240 per 5 min).

$opts = getopt("", ["api:", "user:", "file:", "summary:", "dry-run", "throttle:", "start-at:"]);
$api = $opts["api"] ?? "https://giantbomb.com/wiki/api.php";
$botUser = $opts["user"] ?? "Giantbomb@repair";
$file = $opts["file"] ?? null;
$summary = $opts["summary"] ?? "Restore release data from legacy DB (release-repair v1)";
$dryRun = isset($opts["dry-run"]);
$throttleMs = (int) ($opts["throttle"] ?? 300);
$startAt = $opts["start-at"] ?? null;
$botPass = getenv("BOT_PASSWORD");
$sk = getenv("MW_SK");
if (!$file) {
    fwrite(STDERR, "--file required\n");
    exit(1);
}
if (!$botPass && !$dryRun) {
    fwrite(STDERR, "BOT_PASSWORD env required (except --dry-run)\n");
    exit(1);
}

// bot actors whose latest revision we may overwrite
$ALLOWED_ACTORS = ["Giantbomb", "Maintenance script"];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => "",
    CURLOPT_USERAGENT => "gb-wiki-repair/1.0 (release repair)",
    CURLOPT_TIMEOUT => 90, CURLOPT_HTTPHEADER => ["X-Repair-Token: " . $sk],
]);
function apiCall($ch, string $api, array $params, bool $post = false): array
{
    $params["format"] = "json";
    for ($try = 0; $try < 4; $try++) {
        if ($post) {
            curl_setopt($ch, CURLOPT_URL, $api);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } else {
            curl_setopt($ch, CURLOPT_URL, $api . "?" . http_build_query($params));
            curl_setopt($ch, CURLOPT_POST, false);
        }
        $raw = curl_exec($ch);
        if ($raw === false) { sleep(2 << $try); continue; }
        $data = json_decode($raw, true);
        // dev wikis append a huge debuginfo blob that can break decoding;
        // the edit itself still went through
        if ($data === null && strpos($raw, '"result":"Success"') !== false) {
            return ["edit" => ["result" => "Success"]];
        }
        if ($data === null) { sleep(1); continue; }
        if (isset($data["error"]["code"]) && $data["error"]["code"] === "maxlag") { sleep(5); continue; }
        return $data;
    }
    return [];
}

if (!$dryRun) {
    $tok = apiCall($ch, $api, ["action" => "query", "meta" => "tokens", "type" => "login"]);
    $lt = $tok["query"]["tokens"]["logintoken"] ?? null;
    $login = apiCall($ch, $api, ["action" => "login", "lgname" => $botUser, "lgpassword" => $botPass, "lgtoken" => $lt], true);
    if (($login["login"]["result"] ?? "") !== "Success") {
        fwrite(STDERR, "login failed: " . json_encode($login) . "\n");
        exit(1);
    }
    fwrite(STDERR, "logged in as {$botUser}\n");
}
$csrf = null;
$freshCsrf = function () use ($ch, $api, &$csrf) {
    $t = apiCall($ch, $api, ["action" => "query", "meta" => "tokens"]);
    $csrf = $t["query"]["tokens"]["csrftoken"] ?? null;
    return $csrf;
};

$n = ["edited" => 0, "unchanged" => 0, "skipped" => 0, "errors" => 0];
$fh = fopen($file, "r");
$seeking = $startAt !== null;
while (($line = fgets($fh)) !== false) {
    $rec = json_decode(trim($line), true);
    if (!$rec || empty($rec["title"]) || !isset($rec["text"])) { continue; }
    if ($seeking) {
        if ($rec["title"] !== $startAt) { continue; }
        $seeking = false;
    }

    $q = apiCall($ch, $api, [
        "action" => "query", "titles" => $rec["title"], "prop" => "revisions",
        "rvslots" => "main", "rvprop" => "content|timestamp|user", "curtimestamp" => 1,
    ]);
    $pages = $q["query"]["pages"] ?? [];
    $page = $pages ? reset($pages) : null;
    $rev = $page["revisions"][0] ?? null;
    $current = $rev["slots"]["main"]["*"] ?? null;
    if ($current === null) { $n["skipped"]++; echo "SKIP   {$rec["title"]} (missing)\n"; continue; }

    // never touch a page a human has edited since the snapshot
    if (!in_array($rev["user"] ?? "", $ALLOWED_ACTORS) && ($rev["user"] ?? "") !== explode("@", $botUser)[0]) {
        $n["skipped"]++; echo "SKIP   {$rec["title"]} (latest editor: " . ($rev["user"] ?? "?") . ")\n"; continue;
    }

    $newText = rtrim($rec["text"]) . "\n";
    if (rtrim($newText) === rtrim($current)) { $n["unchanged"]++; continue; }

    if ($dryRun) {
        $n["edited"]++;
        echo sprintf("WOULD EDIT %s (%d -> %d chars)\n", $rec["title"], strlen($current), strlen($newText));
        continue;
    }
    if ($csrf === null) { $freshCsrf(); }
    $doEdit = fn() => apiCall($ch, $api, [
        "action" => "edit", "title" => $rec["title"], "text" => $newText,
        "summary" => $summary, "bot" => 1,
        "basetimestamp" => $rev["timestamp"], "starttimestamp" => $q["curtimestamp"] ?? $rev["timestamp"],
        "maxlag" => 5, "assert" => "user", "token" => $csrf,
    ], true);
    $edit = $doEdit();
    if (($edit["error"]["code"] ?? null) === "badtoken") { $freshCsrf(); $edit = $doEdit(); }

    if (($edit["edit"]["result"] ?? "") === "Success") {
        $n["edited"]++; echo "EDITED {$rec["title"]}\n";
    } else {
        $n["errors"]++; echo "ERROR  {$rec["title"]}: " . json_encode($edit["error"] ?? $edit) . "\n";
    }
    if ($throttleMs > 0) { usleep($throttleMs * 1000); }
}
fclose($fh);
echo ($dryRun ? "[dry-run] " : "") . json_encode($n) . "\n";
