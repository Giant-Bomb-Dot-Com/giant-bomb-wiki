<?php

require_once(__DIR__.'/libs/converter.php');

class ConvertToMWPageNames extends Maintenance
{
    public function __construct() 
    {
        parent::__construct();
        $this->addDescription("Converts names into MediaWiki page names");
        $this->addOption('resource', 'One of accessory, character, company, concept, dlc, franchise, game, genre, location, person, platform, theme, thing', false, true, 'r');
        $this->addOption('id', 'Entity id. When visiting the GB Wiki, the url has a guid at the end. The id is the number after the dash.', false, true, 'i');
        $this->addOption('force', 'Forces conversion without checking for an empty mw_formatted_description field.', false, false, 'f');
    }

    /**
     * - Retrieve all entries from the resource table that has a description and a null mw_formatted_description field
     * - Replace html tags with MediaWiki formatting
     * - Update the entry's mw_formatted_description field
     */
    public function execute()
    {
        $resources = ['accessory','character','company','concept','dlc','franchise','game','genre','location','person','platform','theme','thing'];
        $seenNames = array_fill_keys($resources, []);

        if ($resourceOption = $this->getOption('resource', false)) {
            if (in_array($resourceOption, $resources)) {
                $resources = [$resourceOption];
            }
        }

        $db = $this->getDB(DB_PRIMARY, [], getenv('MARIADB_API_DUMP_DATABASE'));
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
            foreach ($rows as $row) {

                // append id to duplicate names
                if (!isset($seenNames[$resource][$row->name])) {
                    $name = $row->name;
                    $seenNames[$resource][$name] = 1;
                }
                else {
                    $name = $row->name.'_'.$row->id;
                }

                $convertedPageName = $content::PAGE_NAMESPACE.$converter->convertName($name);
                $content->updateMediaWikiPageName($row->id, $convertedPageName);
                echo sprintf("Converted %s name for %s::%s => %s\n", $resource, $row->id, $row->name, $convertedPageName);
            }

            unset($seenNames[$resource]);
        }

        echo "done\n";
    }
}

$maintClass = ConvertToMWPageNames::class;

require_once RUN_MAINTENANCE_IF_MAIN; 
?>