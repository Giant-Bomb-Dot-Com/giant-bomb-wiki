<?php

require_once(__DIR__.'/libs/common.php');
require_once(__DIR__.'/libs/db_connection.php');
require_once(__DIR__.'/libs/mw_db_wrapper.php');
require_once(__DIR__.'/libs/pdo_db_wrapper.php');

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
        $this->addOption('resource', 'One of accessory, character, company, concept, franchise, game, genre, location, person, platform, theme, thing', false, true, 'r');
        $this->addOption('id', 'Entity id. Requires resource to be set. When visiting the GB Wiki, the url has a guid at the end. The id is the number after the dash.', false, true, 'i');
        $this->addOption('external', 'Uses external db instead of local api db', false, false, 'e');
        $this->addOption('start', 'Start at an id. Requires resource to be set.', false, true, 's');
    }

    /**
     * - Retrieve all from a resource table
     * - Craft the xml block for each row
     * - Save the xml file
     */
    public function execute()
    {
        $resources = ['accessory','character','company','concept','franchise','game','genre','location','person','platform','theme','thing'];

        if ($resourceOption = $this->getOption('resource', false)) {
            if (in_array($resourceOption, $resources)) {
                $resources = [$resourceOption];
            }
        }

        $db = ($this->getOption('external', false)) ? $this->getExtDb() : $this->getApiDb();
        $start = $this->getOption('start', 0);

        foreach ($resources as $resource) {

            $filePath = sprintf('%s/content/%s.php', __DIR__, $resource);
            if (file_exists($filePath)) {
                include $filePath; 
            } else {
                echo "Error: External script not found at {$filePath}";
                exit(1);
            }

            $classname = ucfirst($resource);
            $content = new $classname($db);

            if ($this->getOption('resource', false) && $id = $this->getOption('id', false)) {
                $result = $content->getById($id);
                $totalItems = 1;
            }
            else {
                $result = $content->getAll($start);
                $totalItems = is_array($result) ? count($result) : $result->count();
            }

            $data = [];
            $count = 0;
            $size = 0;
            foreach ($result as $row) {
                $pageData = $content->getPageDataArray($row);
                $count++;
                $size += strlen($pageData['description']);
                $data[] = $pageData;

                if ($resource == 'game') {
                    $subpageData = $content->getSubPageDataArray($row);
                    if (!empty($subpageData)) {
                        $data = array_merge($data, $subpageData);
                        foreach ($subpageData as $subpage) {
                            $size += strlen($subpage['description']);
                        }
                    }
                }

                // limit size of file to either 20mb or 20000 pages
                if ($size > self::CHUNK_SIZE || ($count % self::LIMIT_SIZE) == 0) {
                    $filename = sprintf('%s_%07d.xml', $resource, $count);
                    $this->streamXML($filename, $data);
                    $data = [];
                    $size = 0;
                }

                $this->showProgressBar($count, $totalItems);
            }

            if ($size != 0) {
                $filename = sprintf('%s_%d_%07d.xml', $resource, $start, $count);
                $this->streamXML($filename, $data);
            }
        }
    }

    public function showProgressBar(int $current, int $total, int $barWidth = 200): void
    {
        $percentage = ($total > 0) ? round(($current / $total) * 100) : 0;
        $filledWidth = round($barWidth * ($percentage / 100));
        $emptyWidth = $barWidth - $filledWidth;

        $bar = '[' . str_repeat('=', $filledWidth) . str_repeat(' ', $emptyWidth) . ']';

        echo "\r" . $bar . " " . $percentage . "%";
        
        if ($current === $total) {
            echo PHP_EOL;
        }
    }
}

$maintClass = GenerateXMLResource::class;

require_once RUN_MAINTENANCE_IF_MAIN; 
