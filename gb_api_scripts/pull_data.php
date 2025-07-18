<?php

require_once(__DIR__.'/giantbomb_api.php');

class PullDataFromGBApi extends Maintenance
{

    public function __construct() 
    {
        parent::__construct();
        $this->addDescription("Pulls from an endpoint on the GB api");
        $this->addArg('resource', 'Wiki endpoint to make request against. ex: accessory (required)');
        $this->addOption('apikey', 'Api key used to make requests to the GB api');
        $this->addOption('id', 'To target pull by the wiki id (optional)', false, true, 'i');
        $this->addOption('offset', 'To start pulling from an offset value (optional)', false, true, 'o');
        $this->addOption('max', 'The max number of results to pull', false, true, 'm');
    }

    public function execute()
    {
        $resource = $this->getArg(0);

        // dynamically include the resource class based on the resource argument
        $filePath = sprintf('%s/%s.php', __DIR__, $resource);
        if (file_exists($filePath)) {
            include $filePath; 
        } else {
            echo "Error: External script not found at {$filePath}";
        }

        $classname = ucfirst($resource);
        $content = new $classname($this->getDB(DB_PRIMARY, [], 'gb_api_dump'));

        try {
            $api = new GiantBombAPI($this->getOption('apikey', ($_ENV["GB_API_KEY"]) ? $_ENV["GB_API_KEY"] : ''));

            // single item pull
            if ($id = $this->getOption('id', 0)) {
                $endpoint = sprintf('%s/%d-%d', $content->getResourceSingular(), $content->getTypeId(), $id);
                $response = $api->request($endpoint);
                $resultSet = [$response['results']];
            }
            // pull everything from the endpoint; will wait an hour if request limit is reached
            else {
                $endpoint = $content->getResourceMultiple();
                $max = ($this->getOption('max', -1) > 0) ? $this->getOption('max') : -1;
                $resultSet = $api->paginate($endpoint, ['offset' => (int)$this->getOption('offset', 0)], $max);
            }

            $data = $content->save($resultSet);
        }
        catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}

$maintClass = PullDataFromGBApi::class;

require_once RUN_MAINTENANCE_IF_MAIN; 
?>