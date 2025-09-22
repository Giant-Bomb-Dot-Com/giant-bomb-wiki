<?php

require_once(__DIR__.'/libs/converter.php');
require_once(__DIR__.'/libs/db_connection.php');
require_once(__DIR__.'/libs/mw_db_wrapper.php');
require_once(__DIR__.'/libs/pdo_db_wrapper.php');

class ConvertToMWDescriptions extends Maintenance
{
    use DBConnection;

    public function __construct() 
    {
        parent::__construct();
        $this->addDescription("Converts descriptions into MediaWiki format");
        $this->addOption('resource', 'One of accessory, character, company, concept, dlc, franchise, game, genre, location, person, platform, release, theme, thing', false, true, 'r');
        $this->addOption('id', 'Entity id. When visiting the GB Wiki, the url has a guid at the end. The id is the number after the dash. Requires resource set.', false, true, 'i');
        $this->addOption('force', 'Forces conversion without checking for an empty mw_formatted_description field.', false, false, 'f');
        $this->addOption('debug', 'Prints out debug statements, does not update', false, false, 'd');
        $this->addOption('external', 'Uses external db instead of local api db', false, false, 'e');
    }

    /**
     * - Retrieve all entries from the resource table that has a description and a null mw_formatted_description field
     * - Replace html tags with MediaWiki formatting
     * - Update the entry's mw_formatted_description field
     */
    public function execute()
    {
        $resources = ['accessory','character','company','concept','dlc','franchise','game','genre','location','person','platform','release','theme','thing'];

        if ($resourceOption = $this->getOption('resource', false)) {
            if (in_array($resourceOption, $resources)) {
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

            $emptyDescriptionTally = 0;
            $classname = ucfirst($resource);
            $content = new $classname($db);
            $rows = $content->getTextToConvert($this->getOption('id', false), $this->getOption('force', false));
            foreach ($rows as $row) {
                if (!empty($row->description)) {
                    $convertedDescription = $converter->convert($row->description, $content::TYPE_ID, $row->id);
                    $content->updateMediaWikiDescription($row->id, $convertedDescription);
                    echo sprintf("Converted %s description for %s::%s\n", $resource, $row->id, $row->name);
                }
                else {
                    $emptyDescriptionTally++;
                }
            }

            $content->updateMediaWikiDescription(0, '');
            echo sprintf("Updated %d of %s to an empty string\n", $emptyDescriptionTally, $resource);
        }

        echo "done\n";
    }
}

$maintClass = ConvertToMWDescriptions::class;

require_once RUN_MAINTENANCE_IF_MAIN; 
?>