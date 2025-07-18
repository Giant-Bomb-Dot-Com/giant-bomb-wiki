<?php

require_once(__DIR__.'/giantbomb_api.php');

class PullDataFromGBApi extends Maintenance
{

    public function __construct() 
    {
        parent::__construct();
        $this->addDescription("Pulls from an endpoint on the GB api");
        $this->addArg('apikey', 'Api key used to make requests to the GB api (required)');
        $this->addArg('resource', 'Wiki endpoint to make request against. ex: accessory (required)');
        $this->addOption('id', 'To target pull by the wiki id (optional)', false, true, 'i');
        $this->addOption('offset', 'To start pulling from an offset value (optional)', false, true, 'o');
        $this->addOption('max', 'The max number of results to pull', false, true, 'm');
    }

    public function execute()
    {
        $apikey = $this->getArg(0);
        $resource = $this->getArg(1);

        $filePath = sprintf('%s/%s.php', __DIR__, $resource);
        if (file_exists($filePath)) {
            include $filePath; 
        } else {
            echo "Error: External script not found at {$filePath}";
        }

        $classname = ucfirst($resource);
        $content = new $classname($this->getDB(DB_PRIMARY, [], 'gb_api_dump'));

        try {
            $api = new GiantBombAPI($apikey);

            if ($id = $this->getOption('id', 0)) {
                $endpoint = sprintf('%s/%d-%d', $content->getResourceSingular(), $content->getTypeId(), $id);
                $response = $api->request($endpoint);
                $response = [$response];
            }
            else {
                $endpoint = $content->getResourceMultiple();
                $max = ($this->getOption('max', -1) > 0) ? $this->getOption('max') : -1;
                $response = $api->paginate($endpoint, ['offset' => (int)$this->getOption('offset', 0)], $max);
            }

            $data = $content->save($response);
        }
        catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}

$maintClass = PullDataFromGBApi::class;

require_once RUN_MAINTENANCE_IF_MAIN; 
?>