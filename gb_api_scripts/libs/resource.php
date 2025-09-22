<?php

use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\InsertQueryBuilder;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Rdbms\MysqliResultWrapper;

/**
 * Resource Class
 *
 * Defines common methods the wiki content classes should use and implement
 */
abstract class Resource 
{
    const RELEASE_DATE_TYPE_USE_DATE = 0;
    const RELEASE_DATE_TYPE_MONTH_YEAR = 1;
    const RELEASE_DATE_TYPE_QTR_YEAR = 2;
    const RELEASE_DATE_TYPE_ONLY_YEAR = 3;

    private $dbw;
    private bool $crawlRelations;

    /**
     * Constructor
     *
     * @param IDatabase $dbw Primay db for writes
     */
    public function __construct(DbInterface $dbw, bool $crawlRelations = false) 
    {
        $this->dbw = $dbw;
        $this->crawlRelations = $crawlRelations;
    }

    /**
     * Get the wiki type id - a 4 digit number representing the model
     */
    public function getTypeID()
    {
        return static::TYPE_ID;
    }

    /**
     * Get the singular endpoint
     */
    public function getResourceSingular()
    {
        return static::RESOURCE_SINGULAR;
    }

    /**
     * Get the plural endpoint
     */
    public function getResourceMultiple()
    {
        return static::RESOURCE_MULTIPLE;
    }

    /**
     * no comment
     */
    public function getDb()
    {
        return $this->dbw;
    }

    /**
     * Resets crawlRelations back to its default value of false
     */
    public function resetCrawlRelations()
    {
        $this->crawlRelations = false;
    }

    /**
     * Inserts a new entry or updates it if it exists
     * 
     * @param string $tableName The name of the table to be used in the query.
     * @param array  $data Associative array where key is the table column.
     * @param array  $uniquePrimaryKeys The primary key(s) of the table. Can be Id or composite keys.
     * @return int
     * @throws UnexpectedValueException
     */
    public function insertOrUpdate(string $tableName, array $data, array $uniquePrimaryKeys = []): int
    {
        $dataForUpdate = array_diff_key($data, array_flip($uniquePrimaryKeys));
        $diffCount = count($dataForUpdate);

        // all fields in the table are used as a composite key
        // so we return its id if it exists
        if ($diffCount <= 1) {
            $qb = $this->getDb()->newSelectQueryBuilder();
            $qb->select('*')
               ->from($tableName)
               ->where($data)
               ->caller(__METHOD__);

            $result = $qb->fetchRow();

            if ($result !== false) {
                $set = json_decode(json_encode($result), true);
                echo "Duplicate found in " . $tableName . " table with data: " . http_build_query($set, '', ' ') . "\r\n";

                return (isset($set['id'])) ? $set['id'] : 0;
            }
            else {
                $this->getDb()->insert(
                    $tableName,
                    [$data],
                    __METHOD__
                );
            }
        }
        else {

            // insert if new, update if exists
            $this->getDb()->upsert( 
                $tableName, 
                [$data],          
                [$uniquePrimaryKeys],  
                $dataForUpdate,              
                __METHOD__              
            );
        }

        $insertId = (int)$this->getDb()->insertId();

        if ($insertId === 0) {
            if (isset($data['id'])) {
                echo "Updated " . $tableName . " table with ID " . $data['id'] . "\r\n";
            }
            else {
                echo "Updated " . $tableName . " table with composite data: " . http_build_query($data, '', ' ') . "\r\n";
            }
        }
        else {
            if ($diffCount == 0) {
                echo "Added to " . $tableName . " table with composite data: " . http_build_query($data, '', ' ') . "\r\n";
            }
            else {
                echo "Added to " . $tableName . " table with ID " . $insertId . "\r\n";
            }
        }

        return $insertId;
    }

    /**
     * Get ids from the content
     * 
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function getIds(int $offset, int $limit)
    {
        $qb = $this->getDb()->newSelectQueryBuilder();
        $qb->field('id')
            ->from(static::TABLE_NAME)
            ->offset($offset)
            ->limit($limit)
            ->caller(__METHOD__);

        return $qb->fetchFieldValues();
    }

    /**
     * Get rows with descriptions that have not been converted yet
     * 
     * @param int|false $id
     * @param bool $force
     * @return array
     */
    public function getTextToConvert($id = false, $force = false)
    {
        return $this->dbw->getTextToConvert(static::TABLE_NAME, $id, $force);
    }

    /**
     * Get rows with names that have not been converted yet
     * 
     * @param int|false $id
     * @param bool $force
     * @return array
     */
    public function getNamesToConvert($id = false, $force = false)
    {
        return $this->dbw->getNamesToConvert(static::TABLE_NAME, $id, $force);
    }

    /**
     * Get the page name
     * 
     * @param int $id
     * @param string|null $table
     * 
     * @return string|false
     */
    public function getPageName(int $id, string $table = null)
    {
        $table = (is_null($table)) ? static::TABLE_NAME : $table;
        return $this->dbw->getPageName($table, $id);
    }

    /**
     * Get the wiki object by id
     * 
     * @param int $id
     */
    public function getById(int $id)
    {
        $prefix = function($element) { 
            return 'o.'.$element;
        };

        $fields = array_map($prefix, static::TABLE_FIELDS);

        return $this->dbw->getById(static::TABLE_NAME, $fields, $id);  
    }

    /**
     * Get all the wiki objects
     */
    public function getAll(int $offset = 0)
    {
        $prefix = function($element) { 
            return 'o.'.$element;
        };

        $fields = array_map($prefix, static::TABLE_FIELDS);

        return $this->dbw->getAll(static::TABLE_NAME, $fields, $offset);  
    }

    /**
     * Stores the media wiki description in the mw_formatted_description field
     * 
     * @param int $id
     * @param string $mwDescription
     */
    public function updateMediaWikiDescription(int $id, string $mwDescription)
    {
        return $this->dbw->updateMediaWikiDescription(static::TABLE_NAME, $id, $mwDescription);
    }

    /**
     * Stores the media wiki page name in the mw_page_name field
     * 
     * @param int $id
     * @param string $mwPageName
     */
    public function updateMediaWikiPageName(int $id, string $mwPageName)
    {
        return $this->dbw->updateMediaWikiPageName(static::TABLE_NAME, $id, $mwPageName);
    }

    /**
     * Loops through the relation table map to obtain a comma delimited list of relation page names
     * 
     * @param int $id
     * @return string
     */
    public function getRelationsFromDB(int $id): string
    {
        $result = $this->dbw->getRelatedPageNames(static::TABLE_NAME, static::RELATION_TABLE_MAP, $id);

        $relations = [];
        foreach (array_keys(static::RELATION_TABLE_MAP) as $key) {
            $relations[] = sprintf("| %s=%s", ucwords($key), $result->$key);
        }

        return implode("\n", $relations);
    }

    /**
     * Gets credits for a game
     * 
     * @param int $id
     */
    public function getCreditsFromDB(int $id)
    {
        return $this->dbw->getCreditsFromDB($id);
    }

    /**
     * Gets releases for a game
     *
     * @param int $id
     */
    public function getReleasesFromDB(int $id)
    {
        return $this->dbw->getReleasesFromDB($id);
    }

    /**
     * Gets dlcs for a game
     *
     * @param int $id
     */
    public function getDLCFromDB(int $id)
    {
        return $this->dbw->getDLCFromDB($id);
    }

    /**
     * Determines if a wiki type has relations by the defined constant RELATION_TABLE_MAP
     * 
     * @return bool
     */
    public function hasRelations(): bool
    {
        return defined('static::RELATION_TABLE_MAP');
    }

    /**
     * Fills in the connector tables
     * 
     * @param array $map A mapping table defining the field names and table name
     * @param int   $mainFieldId The id of the main field
     * @param array &$crawl A queue of the next entities to pull from the API
     * @param array $relations An array that includes the id of the relation field
     */
    public function addRelations(array $map, int $mainFieldId, array $relations, array &$crawl): void
    {
        foreach ($relations as $entry) {
            $this->insertOrUpdate($map["table"], 
                                 [$map["mainField"] => $mainFieldId, $map["relationField"] => $entry["id"]], 
                                 [$map["mainField"], $map["relationField"]]);

            if ($this->crawlRelations && isset($entry['api_detail_url'])) {
                preg_match('/(\w+)\/(\d{4})\-(\d+)/', $entry['api_detail_url'], $match);

                switch ($match[1]) {
                    case 'developer': $resource = 'company'; break;
                    case 'publisher': $resource = 'company'; break;
                    case 'enemy': $resource = 'character'; break;
                    case 'friend': $resource ='character'; break;
                    default: $resource = $match[1];
                }

                $crawl[sprintf('%s/%s-%s', $resource, $match[2], $match[3])] = [
                    'related_type_id' => (int)$match[2],
                    'related_id' => (int)$match[3]
                ];
            }
        }
    }

    /**
     * Loops through the results and saves each one
     * 
     * @param array $data The response from the api call.
     * @return array
     */
    public function save(array $data): array
    {
        try {
            $this->getDb()->query("SET FOREIGN_KEY_CHECKS = 0;", __METHOD__); 

            // TODO: batch save?

            $relations = [];
            foreach ($data as $row) {
                $this->process($row, $relations);
            }

            echo "Total proccessed: " . count($data) . "\r\n";
        } catch (Exceoption $e) {
            wfLogWarning("Error during ".__METHOD__." with disabled FK checks: " . $e->getMessage());
            throw $e;
        } finally {
            $this->getDb()->query("SET FOREIGN_KEY_CHECKS = 1;", __METHOD__); 
        }

        return $relations;
    }

    /**
     * Match the api fields to the db fields
     * 
     * @param array $data
     * @param array &$relations
     * @return int
     */
    abstract public function process(array $data, array &$relations): int;
}

?>