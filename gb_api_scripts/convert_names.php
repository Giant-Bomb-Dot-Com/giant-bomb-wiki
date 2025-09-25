<?php

require_once(__DIR__.'/libs/converter.php');
require_once(__DIR__.'/libs/db_connection.php');
require_once(__DIR__.'/libs/mw_db_wrapper.php');
require_once(__DIR__.'/libs/pdo_db_wrapper.php');

class ConvertToMWPageNames extends Maintenance
{
    use DBConnection;

    public function __construct() 
    {
        parent::__construct();
        $this->addDescription("Converts names into MediaWiki page names");
        $this->addOption('resource', 'One of accessory, character, company, concept, franchise, game, genre, location, person, platform, theme, thing', false, true, 'r');
        $this->addOption('id', 'Entity id. When visiting the GB Wiki, the url has a guid at the end. The id is the number after the dash.', false, true, 'i');
        $this->addOption('force', 'Forces conversion without checking for an empty mw_formatted_description field.', false, false, 'f');
        $this->addOption('external', 'Uses external db instead of local api db', false, false, 'e');
        $this->addOption('continue', 'Requires resource to be set. Will continue from that one onward.', false, false, 'c');
    }

    /**
     * - Retrieve all entries from the resource table that has a description and a null mw_formatted_description field
     * - Replace html tags with MediaWiki formatting
     * - Update the entry's mw_formatted_description field
     */
    public function execute()
    {
        $resources = ['accessory','character','company','concept','franchise','game','genre','location','person','platform','theme','thing'];

        if ($resourceOption = $this->getOption('resource', false)) {
            if ($this->getOption('continue', false)) {
                $index = array_search($resourceOption, $resources);
                if ($index !== false) {
                    $resources = array_slice($resources, $index);
                }
            } 
            else if (in_array($resourceOption, $resources)) {
                $resources = [$resourceOption];
            }
        }

        $db = ($this->getOption('external', false)) ? $this->getExtDb() : $this->getApiDb();
        $converter = new HtmlToMediaWikiConverter($db);

        foreach ($resources as $resource) {
            $filePath = sprintf('/var/www/html/maintenance/gb_api_scripts/content/%s.php', $resource);
            if (file_exists($filePath)) {
                include_once($filePath); 
            } else {
                echo "Error: External script not found at [{$filePath}]\n";
                exit(1);
            }

            $classname = ucfirst($resource);
            $content = new $classname($db);
            $rows = $content->getNamesToConvert($this->getOption('id', false), $this->getOption('force', false));
            $seenNames = [];
            foreach ($rows as $row) {

                if (!isset($seenNames[$row->name])) {
                    $name = $row->name;
                    $seenNames[$name] = 1;
                }
                else {
                    // append id to duplicate names
                    $suffix = $row->id;

                    // append release year to duplicate game names
                    if (property_exists($row, "release_date")) {
                        if (!is_null($row->release_date)) {
                            $year = substr($row->release_date, 0, 4);
                            $tempName = $row->name . '_' . $year;
                            if (!isset($seenNames[$tempName])) {
                                $seenNames[$tempName] = 1;
                                $suffix = $year;
                            }
                        }
                    }

                    $name = $row->name . '_' . $suffix;
                }

                $convertedPageName = $content::PAGE_NAMESPACE.$converter->convertName($name);
                $content->updateMediaWikiPageName($row->id, $convertedPageName);
                echo sprintf("Converted %s name for %s::%s => %s\n", $resource, $row->id, $row->name, $convertedPageName);
            }
        }

        echo "done\n";
    }
}

$maintClass = ConvertToMWPageNames::class;

require_once RUN_MAINTENANCE_IF_MAIN; 
?>