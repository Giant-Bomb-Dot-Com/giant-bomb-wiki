<?php

require_once(__DIR__.'/converter.php');

class ConvertToMWDescriptions extends Maintenance
{

    public function __construct() 
    {
        parent::__construct();
        $this->addDescription("Converts descriptions into MediaWiki format");
        $this->addArg('resource', 'Wiki type');
        $this->addOption('id', 'Entity id. When visiting the GB Wiki, the url has a guid at the end. The id is the number after the dash.', false, true, 'i');
        $this->addOption('apikey', 'Api key used to make requests to the GB api', false, true, 'a');
    }

    /**
     * - Retrieve all entries from the resource table that has a description and an empty description_new field
     * - Replace html tags with MediaWiki formatting
     * - Update the entry's description_new field
     */
    public function execute()
    {
        $resource = $this->getArg(0);

        $filePath = sprintf('%s/%s.php', __DIR__, $resource);
        if (file_exists($filePath)) {
            include $filePath; 
        } else {
            echo "Error: External script not found at {$filePath}";
            exit(1);
        }

        $classname = ucfirst($resource);
        $db = getenv('MARIADB_API_DUMP_DATABASE');
        $converter = new HtmlToMediaWikiConverter($this->getDB(DB_PRIMARY, [], $db));

        $content = new $classname($this->getDB(DB_PRIMARY, [], $db));
        $rows = $content->getTextToConvert($this->getOption('id', false));
        foreach ($rows as $row) {
            $convertedDescription = $converter->convert($row->description, $content::TYPE_ID, $row->id);
            $content->updateMediaWikiDescription($row->id, $convertedDescription);
            echo sprintf('Converted description for %s::%s', $row->id, $row->name);
        }
    }
}

$maintClass = ConvertToMWDescriptions::class;

require_once RUN_MAINTENANCE_IF_MAIN; 
?>