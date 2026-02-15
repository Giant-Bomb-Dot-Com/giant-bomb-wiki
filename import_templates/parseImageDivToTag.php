<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\CommentStore\CommentStoreComment;
use Wikimedia\Rdbms\DBQueryError;
//use ContentHandler;

if (file_exists(__DIR__ . "/../Maintenance.php")) {
    require_once __DIR__ . "/../Maintenance.php";
} else {
    require_once __DIR__ . "/../maintenance/Maintenance.php";
}

class UpdateTemplateImages extends Maintenance
{
    private const LEGACY_UPLOAD_HOST = "https://www.giantbomb.com";
    private const PREFERRED_SIZES = [
        "scale_super",
        "screen_kubrick",
        "scale_large",
        "scale_medium",
    ];
    private const SAVE_FLAGS = 10;

    public function __construct()
    {
        parent::__construct();
        $this->addDescription(
            "Updates a specified template with Giant Bomb image URLs for a specific category.",
        );
        $this->addOption("category", "The category to process", true, true);
        $this->addOption(
            "template",
            "The template to update (e.g. Concept)",
            true,
            true,
        );
        $this->addOption("batch", "Batch size (default 500)", false, true);
        $this->addOption(
            "start-id",
            "Start from page_id (for resuming)",
            false,
            true,
        );
        $this->addOption(
            "dry-run",
            "Preview changes without saving",
            false,
            false,
        );
    }

    public function execute()
    {
        $category = $this->getOption("category");
        $template = $this->getOption("template");
        $batchSize = (int) $this->getOption("batch", 500);
        $lastId = (int) $this->getOption("start-id", 0);
        $dryRun = $this->hasOption("dry-run");

        $services = MediaWikiServices::getInstance();
        $wikiPageFactory = $services->getWikiPageFactory();
        $revisionLookup = $services->getRevisionLookup();
        $parserCache = $services->getParserCache();
        $user = $services->getUserFactory()->newFromName("Maintenance script");

        if (!$user) {
            $this->fatalError("Could not create maintenance user");
        }

        $dbr = $this->getDB(DB_REPLICA);

        $total = $dbr->selectRowCount(
            ["page", "categorylinks"],
            "*",
            ["cl_to" => $category, "page_namespace" => 0],
            __METHOD__,
            [],
            ["categorylinks" => ["JOIN", "page_id=cl_from"]],
        );
        $this->output("Total pages in Category:$category: $total\n");
        if ($dryRun) {
            $this->output("DRY RUN - no changes will be saved\n");
        }

        $processed = 0;
        $updated = 0;

        while (true) {
            $res = $dbr->select(
                ["page", "categorylinks"],
                ["page_id", "page_namespace", "page_title"],
                [
                    "cl_to" => $category,
                    "page_namespace" => 0,
                    "page_id > " . (int) $lastId,
                ],
                __METHOD__,
                ["LIMIT" => $batchSize, "ORDER BY" => "page_id ASC"],
                ["categorylinks" => ["JOIN", "page_id=cl_from"]],
            );

            if ($res->numRows() === 0) {
                break;
            }

            foreach ($res as $row) {
                $lastId = (int) $row->page_id;
                $title = Title::newFromRow($row);
                $wikiPage = $wikiPageFactory->newFromTitle($title);
                $rev = $revisionLookup->getRevisionByTitle($title);

                if (!$rev) {
                    $processed++;
                    continue;
                }
                $content = $rev->getContent(SlotRecord::MAIN);
                if (!$content instanceof TextContent) {
                    $processed++;
                    continue;
                }

                $text = $content->getText();
                $entries = $this->parseLegacyImageDataFromText($text);

                $imageEntry =
                    $entries["infobox"] ?? ($entries["background"] ?? null);
                $imageUrl = $imageEntry
                    ? $this->buildLegacyImageUrl($imageEntry)
                    : null;

                if ($imageUrl) {
                    $newText = $this->updateTemplate(
                        $text,
                        $imageUrl,
                        $template,
                    );
                    if ($newText !== $text) {
                        if ($dryRun) {
                            $this->output(
                                "Would update: " .
                                    $title->getPrefixedText() .
                                    "\n",
                            );
                        } else {
                            $this->saveWithRetry(
                                $wikiPage,
                                $user,
                                $newText,
                                $parserCache,
                            );
                        }
                        $updated++;
                    }
                }
                $processed++;
            }

            $this->output(
                "Progress: $processed (updated: $updated) - last page_id: $lastId\n",
            );

            unset($res);
            $services->getLinkCache()->clear();
            gc_collect_cycles();

            // After the foreach loop completes a batch:
            file_put_contents("last_id.txt", $lastId);

            exit(0);
        }

        if ($processed === 0 && $updated === 0) {
            file_put_contents("last_id.txt", "DONE");
        }

        $this->output("Done! Processed: $processed, Updated: $updated\n");
    }

    private function buildLegacyImageUrl(array $entry): ?string
    {
        $file = trim($entry["file"] ?? "");
        $path = trim($entry["path"] ?? "", "/");
        $sizesStr = trim($entry["sizes"] ?? "");

        if ($file === "" || $path === "" || $sizesStr === "") {
            return null;
        }

        $sizes = array_values(
            array_filter(array_map("trim", explode(",", $sizesStr))),
        );
        if (!$sizes) {
            return null;
        }

        $useSize = $sizes[0];
        foreach (self::PREFERRED_SIZES as $candidate) {
            if (in_array($candidate, $sizes)) {
                $useSize = $candidate;
                break;
            }
        }

        return self::LEGACY_UPLOAD_HOST . "/a/uploads/$useSize/$path/$file";
    }

    private function saveWithRetry(
        $wikiPage,
        $user,
        $newText,
        $parserCache,
        $attempts = 3,
    ) {
        $title = $wikiPage->getTitle();

        for ($i = 0; $i < $attempts; $i++) {
            try {
                $updater = $wikiPage->newPageUpdater($user);
                $newContent = ContentHandler::makeContent(
                    $newText,
                    $title,
                    "wikitext",
                );
                $updater->setContent(SlotRecord::MAIN, $newContent);

                if ($wikiPage->getContentModel() !== "wikitext") {
                    $this->output(
                        "Fixing content model: " .
                            $title->getPrefixedText() .
                            "\n",
                    );
                }

                $comment = CommentStoreComment::newUnsavedComment(
                    "Batch update: Fixed Image URL",
                );
                $updater->saveRevision($comment, self::SAVE_FLAGS);

                $title->invalidateCache();
                $parserCache->deleteOptionsKey($wikiPage);

                $this->output("Updated: " . $title->getPrefixedText() . "\n");
                return;
            } catch (DBQueryError $e) {
                if ($i === $attempts - 1) {
                    throw $e;
                }
                $this->output("DB busy, retry " . ($i + 1) . "...\n");
                usleep(500000);
            }
        }
    }

    private function updateTemplate($text, $url, $templateName)
    {
        // Escape template name for regex
        $t = preg_quote($templateName, "/");
        if (preg_match("/({{$t}\b[^}]*)/is", $text, $matches)) {
            $templateBody = $matches[1];

            if (preg_match("/\|\s*Image\s*=[^|}]*/i", $templateBody)) {
                $newBody = preg_replace(
                    "/(\|\s*Image\s*=)[^|}]*/i",
                    "$1$url",
                    $templateBody,
                );
            } else {
                // Clean trim and ensure newline before final closing }}
                $newBody = rtrim($templateBody) . "\n| Image=" . $url . "\n";
            }
            return str_replace($templateBody, $newBody, $text);
        }
        return $text;
    }

    public static function parseLegacyImageDataFromText(string $text): ?array
    {
        if (
            !preg_match(
                '/<div[^>]*id=(["\'])imageData\\1[^>]*data-json=(["\'])(.*?)\\2/si',
                $text,
                $matches,
            )
        ) {
            return null;
        }
        $raw = html_entity_decode($matches[3], ENT_QUOTES | ENT_HTML5);
        $data = json_decode(trim($raw), true);
        return is_array($data) && json_last_error() === JSON_ERROR_NONE
            ? $data
            : null;
    }
}

$maintClass = "UpdateTemplateImages";
require_once RUN_MAINTENANCE_IF_MAIN;
