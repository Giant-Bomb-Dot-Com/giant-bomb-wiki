<?php

use MediaWiki\Extension\AlgoliaSearch\AlgoliaClientFactory;
use MediaWiki\Extension\AlgoliaSearch\IndexSettings;
use MediaWiki\Extension\AlgoliaSearch\RecordMapper;
use MediaWiki\MediaWikiServices;

$IP = getenv("MW_INSTALL_PATH");
if ($IP === false || $IP === "") {
    $IP = dirname(__DIR__, 3);
}
require_once "$IP/maintenance/Maintenance.php";

class AlgoliaReindex extends Maintenance
{
    private const DB_PAGE_SIZE = 500;

    public function __construct()
    {
        parent::__construct();
        $this->addDescription("Reindex MediaWiki/SMW content into Algolia.");
        $this->addOption(
            "apply-settings",
            "Apply default index settings before indexing",
            false,
            false,
        );
        $this->addOption(
            "types",
            "Comma-separated list of types to index",
            false,
            true,
        );
        $this->addOption(
            "since",
            "Only index pages updated since YYYY-MM-DD",
            false,
            true,
        );
        $this->addOption(
            "batch",
            "Batch size for saveObjects (default: 200)",
            false,
            true,
        );
        $this->addOption(
            "sleep",
            "Milliseconds to sleep between batches (default: 0)",
            false,
            true,
        );
        $this->addOption(
            "limit",
            "Max total records to index (for testing/incremental runs)",
            false,
            true,
        );
        $this->addOption(
            "resume-after",
            "Resume after this page_id (skip pages with id <= value)",
            false,
            true,
        );
    }

    public function execute()
    {
        $services = MediaWikiServices::getInstance();
        $config = $services->getMainConfig();

        $index = AlgoliaClientFactory::getIndexFromConfig($config);
        if (!$index) {
            $this->fatalError(
                "Algolia is disabled or misconfigured (no client/index)",
            );
        }

        if ($this->hasOption("apply-settings")) {
            IndexSettings::applyDefaultIndexSettings($index);
            $this->output("Applied default index settings.\n");
        }

        $typesOpt = $this->getOption("types", "");
        $types = array_filter(
            array_map("trim", preg_split("/[,\s]+/", $typesOpt) ?: []),
        );
        if (!$types) {
            $types = RecordMapper::getSupportedTypes();
        }

        $since = $this->getOption("since", "");
        $sinceTs = null;
        if ($since !== "") {
            $parts = explode("-", $since);
            if (count($parts) === 3) {
                $sinceTs = sprintf(
                    "%04d%02d%02d000000",
                    (int) $parts[0],
                    (int) $parts[1],
                    (int) $parts[2],
                );
            }
        }
        $batchSize = (int) $this->getOption("batch", "200");
        if ($batchSize <= 0) {
            $batchSize = 200;
        }
        $sleepMs = (int) $this->getOption("sleep", "0");
        $limit = (int) $this->getOption("limit", "0");
        $resumeAfter = (int) $this->getOption("resume-after", "0");

        $this->output("Index: " . $config->get("AlgoliaIndexName") . "\n");
        $this->output("Types: " . implode(",", $types) . "\n");
        if ($since !== "") {
            $this->output("Since: $since\n");
        }
        $this->output("Batch: $batchSize\n");
        if ($sleepMs > 0) {
            $this->output("Sleep: {$sleepMs}ms between batches\n");
        }
        if ($limit > 0) {
            $this->output("Limit: $limit records\n");
        }
        if ($resumeAfter > 0) {
            $this->output("Resuming after page_id $resumeAfter\n");
        }
        $this->output("Memory limit: " . ini_get("memory_limit") . "\n");

        $typePrefixMap = (array) $config->get("AlgoliaTypePrefixMap");
        $totalIndexed = 0;
        $totalSkipped = 0;
        $errors = 0;
        $hitLimit = false;

        foreach ($types as $type) {
            if ($hitLimit) {
                break;
            }
            $prefix = $typePrefixMap[$type] ?? null;
            if (!is_string($prefix) || $prefix === "") {
                $this->output("Skipping type '$type' (no prefix configured)\n");
                continue;
            }
            $this->output(
                "Enumerating type '$type' with prefix '$prefix/'...\n",
            );

            $recordsBatch = [];
            $countForType = 0;
            $skippedForType = 0;
            $errorForType = 0;
            $lastPageId = 0;

            foreach (
                $this->enumerateTitlesByPrefix($prefix, $resumeAfter)
                as [$pageId, $title]
            ) {
                $lastPageId = $pageId;
                try {
                    $record = RecordMapper::mapRecord($type, $title);
                    if ($record === null) {
                        $skippedForType++;
                        continue;
                    }
                    if (
                        $sinceTs &&
                        isset($record["_updatedAt"]) &&
                        is_string($record["_updatedAt"])
                    ) {
                        if ($record["_updatedAt"] < $sinceTs) {
                            $skippedForType++;
                            continue;
                        }
                    }
                    $recordsBatch[] = $record;
                    $countForType++;

                    if (count($recordsBatch) >= $batchSize) {
                        $this->flushBatch(
                            $index,
                            $recordsBatch,
                            $type,
                            $countForType,
                            $lastPageId,
                            $sleepMs,
                        );
                        $recordsBatch = [];
                    }

                    if ($limit > 0 && $totalIndexed + $countForType >= $limit) {
                        $hitLimit = true;
                        break;
                    }
                } catch (\Throwable $e) {
                    $errorForType++;
                    $this->output(
                        "Error page_id=$pageId '{$title->getPrefixedText()}': " .
                            $e->getMessage() .
                            "\n",
                    );
                }
            }
            if ($recordsBatch) {
                $this->flushBatch(
                    $index,
                    $recordsBatch,
                    $type,
                    $countForType,
                    $lastPageId,
                    0,
                );
                $recordsBatch = [];
            }
            $totalIndexed += $countForType;
            $totalSkipped += $skippedForType;
            $errors += $errorForType;
            $this->output(
                "Type '$type': indexed=$countForType, skipped=$skippedForType, errors=$errorForType\n",
            );
        }

        $this->output(
            "Done. Total indexed=$totalIndexed, skipped=$totalSkipped, errors=$errors\n",
        );
    }

    private function flushBatch(
        $index,
        array &$records,
        string $type,
        int $countForType,
        int $lastPageId,
        int $sleepMs,
    ): void {
        $index->saveObjects($records);
        $mem = round(memory_get_usage(true) / 1048576, 1);
        $this->output(
            "  [$type] upserted=$countForType last_page_id=$lastPageId mem={$mem}MB\n",
        );

        $this->clearMediaWikiCaches();

        if ($sleepMs > 0) {
            usleep($sleepMs * 1000);
        }
    }

    private function clearMediaWikiCaches(): void
    {
        $services = MediaWikiServices::getInstance();

        $linkCache = $services->getLinkCache();
        $linkCache->clear();

        gc_collect_cycles();
    }

    private function enumerateTitlesByPrefix(
        string $prefix,
        int $resumeAfter = 0,
    ): \Generator {
        $services = MediaWikiServices::getInstance();
        $dbr = $services->getDBLoadBalancer()->getConnection(DB_REPLICA);
        $dbPrefix = str_replace(" ", "_", $prefix) . "/";
        $like = $dbr->buildLike($dbPrefix, $dbr->anyString());

        $lastId = $resumeAfter;

        while (true) {
            $res = $dbr
                ->newSelectQueryBuilder()
                ->select(["page_id", "page_namespace", "page_title"])
                ->from("page")
                ->where([
                    "page_namespace" => NS_MAIN,
                    "page_is_redirect" => 0,
                    "page_id > " . (int) $lastId,
                ])
                ->andWhere(["page_title $like"])
                ->orderBy("page_id", "ASC")
                ->limit(self::DB_PAGE_SIZE)
                ->caller(__METHOD__)
                ->fetchResultSet();

            $count = 0;
            foreach ($res as $row) {
                $count++;
                $pageId = (int) $row->page_id;
                $lastId = $pageId;
                $dbTitle = (string) $row->page_title;
                $remainder = substr($dbTitle, strlen($dbPrefix));
                if ($remainder === false || strpos($remainder, "/") !== false) {
                    continue;
                }
                $title = Title::makeTitle((int) $row->page_namespace, $dbTitle);
                if ($title) {
                    yield [$pageId, $title];
                }
            }

            if ($count < self::DB_PAGE_SIZE) {
                break;
            }
        }
    }
}

$maintClass = AlgoliaReindex::class;
require_once RUN_MAINTENANCE_IF_MAIN;
