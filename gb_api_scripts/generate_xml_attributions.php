<?php

require_once(__DIR__.'/libs/common.php');
require_once(__DIR__.'/libs/db_connection.php');
require_once(__DIR__.'/libs/mw_db_wrapper.php');
require_once(__DIR__.'/libs/pdo_db_wrapper.php');

class GenerateXMLAttributions extends Maintenance
{
    use CommonVariablesAndMethods;
    use DBConnection;

    const CHUNK_SIZE = 20000000;
    const LIMIT_SIZE = 20000;

    public function __construct() 
    {
        parent::__construct();
        $this->addDescription("Attribute edits to the pages to users");
        $this->addOption('external', 'Uses external db instead of local api db', false, false, 'e');
    }

    /**
     * - Retrieve all from a resource table
     * - Craft the xml block for each row
     * - Save the xml file
     */
    public function execute()
    {
        $tables = [
            3000 => 'wiki_accessory',
            3005 => 'wiki_character',
            3010 => 'wiki_company',
            3015 => 'wiki_concept',
            3025 => 'wiki_franchise',
            3030 => 'wiki_game',
            3035 => 'wiki_location',
            3040 => 'wiki_person',
            3045 => 'wiki_platform',
            3055 => 'wiki_thing'
        ];

        $db = ($this->getOption('external', false)) ? $this->getExtDb() : $this->getApiDb();

        $result = $db->getPageEditors();
        $totalItems = count($result);

        $count = 0;
        $cachedPageName = [];
        $data = [];
        foreach ($result as $row) {
            $count++;

            if (!isset($tables[$row->assoc_type_id])) {
                continue;
            }

            if ($row->date_moderated == null) {
                continue;
            }

            // get the pagename
            if (isset($cachedPageName[$row->assoc_type_id.'-'.$row->assoc_id])) {
                $pageName = $cachedPageName[$row->assoc_type_id.'-'.$row->assoc_id];
            }
            else {
                $pageName = $db->getPageName($tables[$row->assoc_type_id], $row->assoc_id);
                $cachedPageName = [];
                $cachedPageName[$row->assoc_type_id.'-'.$row->assoc_id] = $pageName;
            }

            $dateTime = new DateTime($row->date_moderated, new DateTimeZone('America/Los_Angeles'));
            $dateTime->setTimezone(new DateTimeZone('UTC'));
            $timestamp = $dateTime->format(DateTime::ISO8601); 

            $data[] = [
                'title' => $pageName,
                'timestamp' => $timestamp,
                'namespace' => 0,
                'username' => $row->submitter_id,
                'comment' => $row->submitter_comment,
            ];

            // limit size of file to either 20mb or 20000 pages
            // if ($count % self::LIMIT_SIZE == 0) {
            //     $filename = sprintf('attribution_%07d.xml', $count);
            //     $this->streamXML($filename, $data);
            //     $data = [];
            // }

            $this->showProgressBar($count, $totalItems);
        }

        if (!empty($data)) {
            $filename = sprintf('attribution_%07d.xml', $count);
            $this->streamXML($filename, $data);
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

$maintClass = GenerateXMLAttributions::class;

require_once RUN_MAINTENANCE_IF_MAIN; 
