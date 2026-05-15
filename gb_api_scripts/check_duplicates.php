<?php

require_once __DIR__ . "/libs/converter.php";
require_once __DIR__ . "/libs/db_connection.php";
require_once __DIR__ . "/libs/mw_db_wrapper.php";
require_once __DIR__ . "/libs/pdo_db_wrapper.php";

class CheckDuplicates extends Maintenance
{
    use DBConnection;

    public function __construct()
    {
        parent::__construct();
        $this->addDescription("Prints out the queries to mark as overwritten");
        $this->addOption(
            "resource",
            "One of accessory, character, company, concept, franchise, game, genre, location, person, platform, theme, thing",
            false,
            true,
            "r",
        );
        $this->addOption(
            "ids",
            "Entity id. When visiting the GB Wiki, the url has a guid at the end. The id is the number after the dash.",
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
    }

    /**
     * - Retrieve all entries from the resource table that has a description and a null mw_formatted_description field
     * - Replace html tags with MediaWiki formatting
     * - Update the entry's mw_formatted_description field
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
            "location",
            "person",
            "platform",
            "thing",
        ];

        if ($resourceOption = $this->getOption("resource", false)) {
            if ($this->getOption("continue", false)) {
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
            $filePath = sprintf(
                "/var/www/html/maintenance/gb_api_scripts/content/%s.php",
                $resource,
            );
            if (file_exists($filePath)) {
                include_once $filePath;
            } else {
                echo "Error: External script not found at [{$filePath}]\n";
                exit(1);
            }

            $classname = ucfirst($resource);
            $content = new $classname($db);
            if ($this->getOption("ids", false)) {
                $ids = $this->getOption("ids");
                $ids = explode(",", $ids);

                foreach ($ids as $id) {
                    $result = $content->getRelatedIds($id);

                    foreach ($result as $resource => $ids) {
                        switch ($resource) {
                            case "concepts":
                                if (!empty($ids)) {
                                    echo "UPDATE wiki_concept SET overwritten = 1 WHERE id IN ($ids);";
                                }
                                break;
                            case "characters":
                                if (!empty($ids)) {
                                    echo "UPDATE wiki_character SET overwritten = 1 WHERE id IN ($ids);";
                                }
                                break;
                            case "companies":
                                if (!empty($ids)) {
                                    echo "UPDATE wiki_company SET overwritten = 1 WHERE id IN ($ids;)";
                                }
                                break;
                            case "franchises":
                                if (!empty($ids)) {
                                    echo "UPDATE wiki_franchise SET overwritten = 1 WHERE id IN ($ids);";
                                }
                                break;
                            case "games":
                                if (!empty($ids)) {
                                    echo "UPDATE wiki_game SET overwritten = 1 WHERE id IN ($ids);";
                                }
                                break;
                            case "locations":
                                if (!empty($ids)) {
                                    echo "UPDATE wiki_location SET overwritten = 1 WHERE id IN ($ids);";
                                }
                                break;
                            case "people":
                                if (!empty($ids)) {
                                    echo "UPDATE wiki_person SET overwritten = 1 WHERE id IN ($ids);";
                                }
                                break;
                            case "platforms":
                                if (!empty($ids)) {
                                    echo "UPDATE wiki_platform SET overwritten = 1 WHERE id IN ($ids);";
                                }
                                break;
                            case "things":
                                if (!empty($ids)) {
                                    echo "UPDATE wiki_thing SET overwritten = 1 WHERE id IN ($ids);";
                                }
                                break;
                        }
                    }
                    echo "\n";
                }
            }
        }

        echo "\n\ndone\n";
    }
}

$maintClass = CheckDuplicates::class;

require_once RUN_MAINTENANCE_IF_MAIN;
?>
