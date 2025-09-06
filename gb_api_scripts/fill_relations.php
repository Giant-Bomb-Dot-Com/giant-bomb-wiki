<?php

require_once(__DIR__.'/libs/giantbomb_api.php');

class FillRelationsFromGBApi extends Maintenance
{

    public function __construct() 
    {
        parent::__construct();
        $this->addDescription("Fills relations pulling from gb api");
        $this->addArg('resource', 'Wiki type that has relations to retrieve: character, company, concept, franchise, game, person, release, thing (required)');
        $this->addOption('apikey', 'Api key used to make requests to the GB api');
        $this->addOption('id', 'To target pull by the wiki id (optional)', false, true, 'i');
        $this->addOption('offset', 'To start pulling from an offset value (optional)', false, true, 'o');
        $this->addOption('max', 'The max number of results to pull. Check https://www.giantbomb.com/api for request limit', false, true, 'm');
    }

    /**
     * Relations only appear in the singular endpoints
     * 
     * We'll pull the wiki ids from the database in chunks of max (default 200) and make the api request for each one to get the relations
     */
    public function execute()
    {
        $resource = $this->getArg(0);

        // dynamically include the resource class based on the resource argument
        $filePath = sprintf('%s/content/%s.php', __DIR__, $resource);
        if (file_exists($filePath)) {
            include $filePath; 
        } else {
            echo "Error: External script not found at {$filePath}";
        }

        $classname = ucfirst($resource);
        $content = new $classname($this->getDB(DB_PRIMARY, [], 'gb_api_dump'));

        if (!$content->hasRelations()) {
            echo sprintf("ERROR: %s does not have relations.");
            exit(1);
        }

        $offset = (int)$this->getOption('offset', 0);
        if ($offset < 0) {
            $offset = 1;
        }

        $limit = (int)$this->getOption('max', 200);
        if ($limit < 1 || $limit > 200) {
            $limit = 200;
        }

        try {
            $defaultApiKeyInEnv = (getenv('GB_API_KEY') === false) ? '' : getenv("GB_API_KEY");

            $api = new GiantBombAPI($this->getOption('apikey', $defaultApiKeyInEnv), true);

            // single item pull
            if ($id = $this->getOption('id', 0)) {
                $endpoint = sprintf('%s/%d-%d', $content->getResourceSingular(), $content->getTypeId(), $id);
                $response = $api->request($endpoint);
                $resultSet = [$response['results']];
            }
            else {
                $ids = $content->getIds($offset, $limit);

                $resultSet = [];
                foreach ($ids as $i => $id) {
                    $endpoint = sprintf('%s/%d-%d', $content->getResourceSingular(), $content->getTypeId(), $id);
                    $response = $api->request($endpoint);
                    $resultSet[] = $response['results'];
                    echo ($i % 10) ? '.' : $i;
                }
            }

            $content->save($resultSet);
        }
        catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}

$maintClass = FillRelationsFromGBApi::class;

require_once RUN_MAINTENANCE_IF_MAIN; 
?>