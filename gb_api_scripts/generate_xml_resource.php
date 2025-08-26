<?php

require_once(__DIR__.'/common.php');

class GenerateXMLResource extends Maintenance
{
    use CommonVariablesAndMethods;

    const CHUNK_SIZE = 100000000;

    public function __construct() 
    {
        parent::__construct();
        $this->addDescription("Converts db content into xml");
        $this->addArg('resource', 'Wiki type');
        $this->addOption('id', 'Entity id. When visiting the GB Wiki, the url has a guid at the end. The id is the number after the dash.', false, true, 'i');
    }

    /**
     * - Retrieve all from a resource table
     * - Craft the xml block for each row
     * - Save the xml file
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
        $content = new $classname($this->getDB(DB_PRIMARY, [], $db));

        if ($id = $this->getOption('id', false)) {
            $result = $content->getById($id);
        }
        else {
            $result = $content->getAll();
        }

        $data = [];
        $count = 0;
        $size = 0;
        foreach ($result as $row) {
            $pageData = $content->getPageDataArray($row);
            $count++;
            $size += strlen($pageData['description']);
            $data[] = $pageData;

            // create 100mb files
            if ($size > self::CHUNK_SIZE) {
                $filename = sprintf('%s_%07d.xml', $resource, $count);
                $this->streamXML($filename, $data);
                $data = [];
                $size = 0;
            }

            if ($count % 1000 == 0) {
                echo "$count...\n";
            }
        }

        if ($size != 0) {
            $filename = sprintf('%s_%07d.xml', $resource, $count);
            $this->streamXML($filename, $data);
        }
    }
}

$maintClass = GenerateXMLResource::class;

require_once RUN_MAINTENANCE_IF_MAIN; 
