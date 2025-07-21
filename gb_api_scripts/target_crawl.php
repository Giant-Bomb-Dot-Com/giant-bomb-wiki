<?php

require_once(__DIR__.'/giantbomb_api.php');


class TargetCrawlOfGBApi extends Maintenance
{
    const MAX_LIMIT = 190;

    private $map = [
        3000 => ['className' => 'accessory', 'content' => null, 'count' => 0],
        3005 => ['className' => 'character', 'content' => null, 'count' => 0],
        3010 => ['className' => 'company', 'content' => null, 'count' => 0],
        3015 => ['className' => 'concept', 'content' => null, 'count' => 0],
        3020 => ['className' => 'dlc', 'content' => null, 'count' => 0],
        3025 => ['className' => 'franchise', 'content' => null, 'count' => 0],
        3030 => ['className' => 'game', 'content' => null, 'count' => 0],
        3065 => ['className' => 'game_rating', 'content' => null, 'count' => 0],
        3060 => ['className' => 'genre', 'content' => null, 'count' => 0],
        3035 => ['className' => 'location', 'content' => null, 'count' => 0],
        3040 => ['className' => 'person', 'content' => null, 'count' => 0],
        3045 => ['className' => 'platform', 'content' => null, 'count' => 0],
        3070 => ['className' => 'rating_board', 'content' => null, 'count' => 0],
        3075 => ['className' => 'region', 'content' => null, 'count' => 0],
        3050 => ['className' => 'release', 'content' => null, 'count' => 0],
        3032 => ['className' => 'theme', 'content' => null, 'count' => 0],
        3055 => ['className' => 'thing', 'content' => null, 'count' => 0],
    ];

    public function __construct() 
    {
        parent::__construct();
        $this->addDescription("Crawls the GB Api for an entity and its relations");
        $this->addArg('resource', 'Wiki type that has relations to crawl: character, company, concept, franchise, game, person, release, thing (required)');
        $this->addArg('id', 'Entity id. When visiting the GB Wiki, the url has a guid at the end. The id is the number after the dash.');
        $this->addOption('apikey', 'Api key used to make requests to the GB api');
    }

    /**
     * Relations only appear in the singular endpoints
     * 
     * We'll pull the wiki ids from the database in chunks of max (default 200) and make the api request for each one to get the relations
     */
    public function execute()
    {
        $resource = $this->getArg(0);
        $id = $this->getArg(1);

        // dynamically include the resource class based on the resource argument
        $filePath = sprintf('%s/%s.php', __DIR__, $resource);
        if (file_exists($filePath)) {
            include $filePath; 
        } else {
            echo "Error: External script not found at {$filePath}";
        }

        $classname = ucfirst($resource);
        $db = getenv('MARIADB_API_DUMP_DATABASE');
        $content = new $classname($this->getDB(DB_PRIMARY, [], $db), true);

        if (!$content->hasRelations()) {
            echo sprintf("ERROR: %s does not have relations.");
            exit(1);
        }

        $exceededRateLimit = [];
        $processingApiUrl = '';

        try {
            $defaultApiKeyInEnv = (getenv('GB_API_KEY') === false) ? '' : getenv("GB_API_KEY");

            $api = new GiantBombAPI($this->getOption('apikey', $defaultApiKeyInEnv), true);

            // get the main entity
            $endpoint = sprintf('%s/%d-%d', $content->getResourceSingular(), $content->getTypeId(), $id);
            $response = $api->request($endpoint);
            $resultSet = [$response['results']];

            // returns the main entity relations
            $relations = $content->save($resultSet);
            $this->map[$content->getTypeId()]['count']++;
            $content->resetCrawlRelations();
            $this->map[$content->getTypeId()]['content'] = $content;

            
            // loops through and fills in the relation's relationships - this as far deep as we go
            foreach ($relations as $apiUrl => $relationSet) {
                $processingApiUrl = $apiUrl;
                if ($this->map[$relationSet['related_type_id']]['count'] < self::MAX_LIMIT) {
                    $response = $api->request($apiUrl, [], false); // we'll track rate limit in this scope
                    sleep(rand(1,3));
                    $resultSet = [$response['results']];

                    if (is_null($this->map[$relationSet['related_type_id']]['content'])) {
                        $resource = $this->map[$relationSet['related_type_id']]['className'];
                        require_once(__DIR__.'/'.$resource.'.php');
                        $classname = ucfirst($resource);
                        $this->map[$relationSet['related_type_id']]['content'] = new $classname($this->getDB(DB_PRIMARY, [], $db), false);
                    }

                    $this->map[$relationSet['related_type_id']]['content']->save($resultSet);
                    $this->map[$relationSet['related_type_id']]['count']++;
                }
                else {
                    $exceededRateLimit[] = $apiUrl;
                }
            }

            if (!empty($exceededRateLimit)) {
                var_dump($exceededRateLimit);
            }
        }
        catch (Exception $e) {
            echo "API URL ERROR: " . $processingApiUrl;
            echo $e->getMessage();
        }
    }
}

$maintClass = TargetCrawlOfGBApi::class;

require_once RUN_MAINTENANCE_IF_MAIN; 
?>