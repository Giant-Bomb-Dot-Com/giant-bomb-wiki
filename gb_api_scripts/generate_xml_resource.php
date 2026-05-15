<?php

require_once __DIR__ . "/libs/common.php";
require_once __DIR__ . "/libs/db_connection.php";
require_once __DIR__ . "/libs/mw_db_wrapper.php";
require_once __DIR__ . "/libs/pdo_db_wrapper.php";

class GenerateXMLResource extends Maintenance
{
    use CommonVariablesAndMethods;
    use DBConnection;

    const CHUNK_SIZE = 20000000;
    const LIMIT_SIZE = 20000;

    public function __construct()
    {
        parent::__construct();
        $this->addDescription("Converts db content into xml");
        $this->addOption(
            "resource",
            "One of accessory, character, company, concept, franchise, game, genre, location, person, platform, theme, thing",
            false,
            true,
            "r",
        );
        $this->addOption(
            "id",
            "Entity id. Requires resource to be set. When visiting the GB Wiki, the url has a guid at the end. The id is the number after the dash.",
            false,
            true,
            "i",
        );
        $this->addOption(
            "external",
            "Uses external db instead of local api db",
            false,
            false,
            "e",
        );
        $this->addOption(
            "continue",
            "Requires resource to be set and last id proccessed for that resource. Will proccess the rest of the resources afterwards.",
            false,
            true,
            "c",
        );
        $this->addOption(
            "overwritten",
            "Retrieve the resources that were overwritten because of a duplicate name",
        );
    }

    /**
     * - Retrieve all from a resource table
     * - Craft the xml block for each row
     * - Save the xml file
     *
     * In the initial page generation there were names that otherwise matched if the punctuation were removed and resulted
     *  in having the same mw_page_name. During import the latter mw_page_name overwrote the earlier mw_page_name.
     *
     * Steps to fix matching pages:
     *  - Check if the matching pages exist on the wiki (due to different capitalization)
     *      - Check their relationships
     *          - If correct, good
     *          - If they are swapped
     *              - move relationships so that the corresponding pages are pointing to the correct page
     *              - fix the relationships in the incorrect page
     *              - set overwritten flag to 1 in the legacy db for the affected relationships
     *  - Check if the page was overwritten (capitalization the same)
     *      - Check the relationships of the existing page
     *          - If correct, good
     *          - If they are for the overwritten page
     *              - change the page to the overwritten page
     *              - set overwritten flag to 1 in the legacy db for the affected relationships
     *  - Run this command with the overwritten flag to generate the xml for the affected pages
     */
    public function execute()
    {
        $resources = [
            "accessory",
            "character",
            "company",
            "concept",
            "franchise",
            "game",
            "genre",
            "location",
            "person",
            "platform",
            "theme",
            "thing",
        ];
        $continue = $this->getOption("continue", 0);

        if ($resourceOption = $this->getOption("resource", false)) {
            if ($continue > 0) {
                $index = array_search($resourceOption, $resources);
                if ($index !== false) {
                    $resources = array_slice($resources, $index);
                }
            } elseif (in_array($resourceOption, $resources)) {
                $resources = [$resourceOption];
            }
        }

        $db = $this->getOption("external", false)
            ? $this->getExtDb()
            : $this->getApiDb();

        foreach ($resources as $resource) {
            $filePath = sprintf("%s/content/%s.php", __DIR__, $resource);
            if (file_exists($filePath)) {
                include $filePath;
            } else {
                echo "Error: External script not found at {$filePath}";
                exit(1);
            }

            $classname = ucfirst($resource);
            $content = new $classname($db);

            if (
                $this->getOption("resource", false) &&
                ($id = $this->getOption("id", false))
            ) {
                $result = $content->getById($id);
                $totalItems = 1;
            } elseif ($this->getOption("overwritten", false)) {
                $result = $content->getByOverwrittenFlag();
                $totalItems = is_array($result)
                    ? count($result)
                    : $result->count();
            } else {
                $result = $content->getAll($continue);
                $continue = 0;
                $totalItems = is_array($result)
                    ? count($result)
                    : $result->count();
            }

            $data = [];
            $count = 0;
            $size = 0;
            foreach ($result as $row) {
                $pageData = $content->getPageDataArray($row);
                $count++;
                $size += strlen($pageData["description"]);
                $data[] = $pageData;

                if ($resource == "game") {
                    $subpageData = $content->getSubPageDataArray($row);
                    if (!empty($subpageData)) {
                        $data = array_merge($data, $subpageData);
                        foreach ($subpageData as $subpage) {
                            $size += strlen($subpage["description"]);
                        }
                    }
                }

                // limit size of file to either 20mb or 20000 pages
                if (
                    $size > self::CHUNK_SIZE ||
                    $count % self::LIMIT_SIZE == 0
                ) {
                    $filename = sprintf("%s_%07d.xml", $resource, $count);
                    $this->streamXML($filename, $data);
                    $data = [];
                    $size = 0;
                }

                $this->showProgressBar($count, $totalItems);
            }

            if ($size != 0) {
                $filename = sprintf(
                    "%s_%d_%07d.xml",
                    $resource,
                    $continue,
                    $count,
                );
                $this->streamXML($filename, $data);
            }
        }
    }

    public function showProgressBar(
        int $current,
        int $total,
        int $barWidth = 200,
    ): void {
        $percentage = $total > 0 ? round(($current / $total) * 100) : 0;
        $filledWidth = round($barWidth * ($percentage / 100));
        $emptyWidth = $barWidth - $filledWidth;

        $bar =
            "[" .
            str_repeat("=", $filledWidth) .
            str_repeat(" ", $emptyWidth) .
            "]";

        echo "\r" . $bar . " " . $percentage . "%";

        if ($current === $total) {
            echo PHP_EOL;
        }
    }
}

$maintClass = GenerateXMLResource::class;

require_once RUN_MAINTENANCE_IF_MAIN;
