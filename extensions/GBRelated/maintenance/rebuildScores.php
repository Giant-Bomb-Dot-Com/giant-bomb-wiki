<?php

// backfill gb_page_score for the Popular games sort. the base pass
// (length + images) is one set-based query over Category:Games -- run it
// any time the weights change. review counts hit the public api per game,
// so that pass is opt-in and batchable:
//   php maintenance/run.php .../rebuildScores.php
//   php maintenance/run.php .../rebuildScores.php --reviews --limit=1000
//   php maintenance/run.php .../rebuildScores.php --reviews --start-after=12345

use MediaWiki\Extension\GBRelated\ScoreStore;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;

if (!class_exists("MediaWiki\\Maintenance\\Maintenance")) {
    require_once __DIR__ . "/../../../maintenance/Maintenance.php";
}

class RebuildScores extends Maintenance
{
    public function __construct()
    {
        parent::__construct();
        $this->addDescription(
            "Rebuild wiki-quality scores (gb_page_score) for game pages",
        );
        $this->addOption("reviews", "Also refresh review counts from the api");
        $this->addOption(
            "limit",
            "Max pages for the reviews pass",
            false,
            true,
        );
        $this->addOption(
            "start-after",
            "Reviews pass: resume after this page id",
            false,
            true,
        );
        $this->addOption(
            "throttle-ms",
            "Delay between api calls (default 200)",
            false,
            true,
        );
        $this->requireExtension("GBRelated");
    }

    public function execute()
    {
        $this->rebuildBase();
        if ($this->hasOption("reviews")) {
            $this->refreshReviews();
        }
    }

    // length + image components, one set-based upsert
    private function rebuildBase(): void
    {
        $dbw = $this->getPrimaryDB();
        $imgProp = $this->propId("Has_image");
        $bgProp = $this->propId("Has_background_image");

        $score = "gb_page_score";
        $page = "page";
        $cat = "categorylinks";
        $oids = "smw_object_ids";
        $blob = "smw_di_blob";

        $lenExpr = sprintf(
            "LEAST(p.page_len, %d) DIV %d",
            ScoreStore::LENGTH_CAP,
            ScoreStore::LENGTH_DIVISOR,
        );
        $imgExpr = sprintf(
            "(CASE WHEN img.s_id IS NOT NULL THEN %d ELSE 0 END) + " .
                "(CASE WHEN bg.s_id IS NOT NULL THEN %d ELSE 0 END)",
            ScoreStore::IMAGE_PTS,
            ScoreStore::BACKGROUND_PTS,
        );

        $sql = "INSERT INTO $score
                (gps_page, gps_length, gps_image, gps_reviews, gps_score)
            SELECT p.page_id, $lenExpr, $imgExpr, 0, ($lenExpr) + ($imgExpr)
            FROM $page p
            JOIN $cat cl ON cl.cl_from = p.page_id AND cl.cl_to = 'Games'
            LEFT JOIN $oids so ON so.smw_title = p.page_title
                AND so.smw_namespace = 0 AND so.smw_iw = ''
                AND so.smw_subobject = ''
            LEFT JOIN (SELECT DISTINCT s_id FROM $blob WHERE p_id = $imgProp)
                img ON img.s_id = so.smw_id
            LEFT JOIN (SELECT DISTINCT s_id FROM $blob WHERE p_id = $bgProp)
                bg ON bg.s_id = so.smw_id
            WHERE p.page_namespace = 0 AND p.page_is_redirect = 0
                AND p.page_title LIKE 'Games/%'
            ON DUPLICATE KEY UPDATE
                gps_length = VALUES(gps_length),
                gps_image = VALUES(gps_image),
                gps_score = VALUES(gps_length) + VALUES(gps_image)
                    + gb_page_score.gps_reviews";

        $start = microtime(true);
        $dbw->query($sql, __METHOD__);
        $this->output(
            sprintf(
                "Base pass done: %d scored pages in %.1fs\n",
                $dbw->affectedRows(),
                microtime(true) - $start,
            ),
        );
    }

    private function refreshReviews(): void
    {
        $key = getenv("GB_API_KEY");
        if (!$key) {
            $this->fatalError("GB_API_KEY not set; cannot fetch reviews");
        }
        $throttleUs =
            1000 * max(0, (int) $this->getOption("throttle-ms", 200));
        $http = MediaWikiServices::getInstance()->getHttpRequestFactory();
        $dbr = $this->getReplicaDB();

        $guidProp = $this->propId("Has_guid");
        $qb = $dbr
            ->newSelectQueryBuilder()
            ->select(["page_id", "guid" => "b.o_hash"])
            ->from("gb_page_score")
            ->join("page", "p", "p.page_id = gps_page")
            ->join("smw_object_ids", "so", [
                "so.smw_title = p.page_title",
                "so.smw_namespace" => 0,
                "so.smw_iw" => "",
                "so.smw_subobject" => "",
            ])
            ->join("smw_di_blob", "b", [
                "b.s_id = so.smw_id",
                "b.p_id" => $guidProp,
            ])
            ->orderBy("page_id")
            ->caller(__METHOD__);
        if ($this->hasOption("start-after")) {
            $qb->where(
                $dbr->expr(
                    "page_id",
                    ">",
                    (int) $this->getOption("start-after"),
                ),
            );
        }
        if ($this->hasOption("limit")) {
            $qb->limit((int) $this->getOption("limit"));
        }

        $done = 0;
        $lastId = 0;
        $start = microtime(true);
        foreach ($qb->fetchResultSet() as $row) {
            $count = $this->fetchReviewCount($http, $row->guid, $key);
            if ($count !== null) {
                ScoreStore::setReviews((int) $row->page_id, $count);
            }
            $lastId = (int) $row->page_id;
            $done++;
            if ($done % 100 === 0) {
                $this->output(
                    sprintf(
                        "-- %d pages, last id %d, %.1f min elapsed\n",
                        $done,
                        $lastId,
                        (microtime(true) - $start) / 60,
                    ),
                );
                $this->waitForReplication();
            }
            if ($throttleUs) {
                usleep($throttleUs);
            }
        }
        $this->output(
            sprintf(
                "Reviews pass done: %d pages in %.1f min (last id %d)\n",
                $done,
                (microtime(true) - $start) / 60,
                $lastId,
            ),
        );
    }

    // null on request failure so a flaky call never zeroes a stored count.
    // counts all ratings (summary user.count), matching the sidebar number
    private function fetchReviewCount($http, string $guid, string $key): ?int
    {
        $url =
            "https://giantbomb.com/api/public/review-summary" .
            "?game_guid=$guid&api_key=$key&format=json";
        $req = $http->create($url, ["timeout" => 5], __METHOD__);
        if (!$req->execute()->isOK()) {
            return null;
        }
        $data = json_decode($req->getContent(), true);
        if (!is_array($data) || !is_array($data["user"] ?? null)) {
            return null;
        }
        return (int) ($data["user"]["count"] ?? 0);
    }

    private function propId(string $property): int
    {
        return (int) $this->getReplicaDB()
            ->newSelectQueryBuilder()
            ->select("smw_id")
            ->from("smw_object_ids")
            ->where([
                "smw_namespace" => 102,
                "smw_title" => $property,
                "smw_iw" => "",
                "smw_subobject" => "",
            ])
            ->caller(__METHOD__)
            ->fetchField();
    }
}

$maintClass = RebuildScores::class;
require_once RUN_MAINTENANCE_IF_MAIN;
