<?php

require_once(__DIR__.'/libs/common.php');

class GenerateXMLResource extends Maintenance
{
    use CommonVariablesAndMethods;

    const CHUNK_SIZE = 20000000;
    const LIMIT_SIZE = 20000;

    public function __construct() 
    {
        parent::__construct();
        $this->addDescription("Converts releases and credits content into xml");
        $this->addOption('resource', 'release|credits', false, true, 'r');
    }

    /**
     * - Retrieve all from a resource table
     * - Craft the xml block for each row
     * - Save the xml file
     */
    public function execute()
    {
        $resources = ['release','credits'];

        if ($resourceOption = $this->getOption('resource', false)) {
            if (in_array($resourceOption, $resources)) {
                $resources = [$resourceOption];
            }
        }

        $db = $this->getDB(DB_PRIMARY, [], getenv('MARIADB_API_DUMP_DATABASE'));

        foreach ($resources as $resource) {
            if ($resource == 'credits') {
                $resource = 'person';
            }

            $filePath = sprintf('%s/content/%s.php', __DIR__, $resource);
            if (file_exists($filePath)) {
                include $filePath; 
            } else {
                echo "Error: External script not found at {$filePath}";
                exit(1);
            }

            $classname = ucfirst($resource);
            $content = new $classname($db);
            $result = $content->getAll();

            $data = [];
            $count = 0;
            $size = 0;
            foreach ($result as $row) {
                $pageData = $content->getSubPageDataArray($row);
                $count++;
                $size += strlen($pageData['description']);
                $data[] = $pageData;

                // limit size of file to either 100mb or 50000 pages
                if ($size > self::CHUNK_SIZE || $count == self::LIMIT_SIZE) {
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
}

$maintClass = GenerateXMLResource::class;

require_once RUN_MAINTENANCE_IF_MAIN; 
