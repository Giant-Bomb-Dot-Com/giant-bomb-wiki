<?php

require_once(__DIR__.'/giantbomb_api.php');
require_once(__DIR__.'/common.php');

class TargetCrawlOfGBApi extends Maintenance
{
    use CommonVariablesAndMethods;

    const MAX_LIMIT = 190;

    public function __construct() 
    {
        parent::__construct();
        $this->addDescription("Crawls the GB Api for an entity and its relations");
        $this->addArg('resource', 'Wiki type that has relations to crawl: character, company, concept, franchise, game, person, release, thing (required)');
        $this->addArg('id', 'Entity id. When visiting the GB Wiki, the url has a guid at the end. The id is the number after the dash.');
        $this->addOption('apikey', 'Api key used to make requests to the GB api', false, true, 'a');
        $this->addOption('file', 'Skip main entity and process leftover relations from previous run stored in a file.', false, true, 'f');
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
            echo sprintf("ERROR: %s does not have relations. Use pull_data for filling in main details of the entity.", $resource);
            exit(1);
        }

        $exceededRateLimit = [];
        $processingApiUrl = '';

        try {
            $defaultApiKeyInEnv = (getenv('GB_API_KEY') === false) ? '' : getenv("GB_API_KEY");

            if ($this->getOption('file', false)) {
                $json = file_get_contents($this->getOption('file'));
                if ($json === false) {
                    echo printf("ERROR: file does not exist.");
                    exit(1);
                }
                $relations = json_decode($json);
                if (empty($relations)) {
                    echo printf("ERRORL: file is empty");
                    exit(1);
                }
            }
            else {
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
            }

            
            // loops through and fills in the relation's relationships - this as far deep as we go
            foreach ($relations as $apiUrl => $relationSet) {
                $processingApiUrl = $apiUrl;
                if ($this->map[$relationSet['related_type_id']]['count'] < self::MAX_LIMIT) {

                    if (is_null($this->map[$relationSet['related_type_id']]['content'])) {
                        $resource = $this->map[$relationSet['related_type_id']]['className'];
                        require_once(__DIR__.'/'.$resource.'.php');
                        $classname = ucfirst($resource);
                        $this->map[$relationSet['related_type_id']]['content'] = new $classname($this->getDB(DB_PRIMARY, [], $db), false);
                    }

                    if (!$this->map[$relationSet['related_type_id']]['content']->hasRelations()) {
                        continue;
                    }

                    $response = $api->request($apiUrl, [], false); // we'll track rate limit in this scope
                    sleep(rand(2,3));
                    $resultSet = [$response['results']];

                    $this->map[$relationSet['related_type_id']]['content']->save($resultSet);
                    $this->map[$relationSet['related_type_id']]['count']++;
                }
                else {
                    $exceededRateLimit[$apiUrl] = $relationSet;
                }
            }

            if (!empty($exceededRateLimit)) {
                $json = json_encode($exceededRateLimit);
                $filename = 'overflow_'.$content->getTypeId().'-'.$id.'.json';
                file_put_contents($filename, $json);
                echo sprintf('Saved overflow into %s. Run the script again with --filen=%s once your request limit resets to continue.', $filename, $filename);
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