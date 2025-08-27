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
    private IDatabase $dbw;
    private bool $crawlRelations;

    /**
     * Constructor
     *
     * @param IDatabase $dbw Primay db for writes
     */
    public function __construct(IDatabase $dbw, bool $crawlRelations = false) 
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
            $qb = $this->dbw->newSelectQueryBuilder();
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
                $this->dbw->insert(
                    $tableName,
                    [$data],
                    __METHOD__
                );
            }
        }
        else {

            // insert if new, update if exists
            $this->dbw->upsert( 
                $tableName, 
                [$data],          
                [$uniquePrimaryKeys],  
                $dataForUpdate,              
                __METHOD__              
            );
        }

        $insertId = (int)$this->dbw->insertId();

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
        $qb = $this->dbw->newSelectQueryBuilder();
        $qb->field('id')
            ->from(static::TABLE_NAME)
            ->offset($offset)
            ->limit($limit)
            ->caller(__METHOD__);

        return $qb->fetchFieldValues();
    }

    /**
     * Get rows that have not been converted yet
     * 
     * @param int|false $id
     * @return array
     */
    public function getTextToConvert($id = false)
    {
        $clause = ($id) ? 'id = '.$id : 'description != "" AND mw_formatted_description IS NULL';

        $qb = $this->dbw->newSelectQueryBuilder();
        $qb->select(['id', 'name', 'description'])
             ->from(static::TABLE_NAME)
             ->where($clause)
             ->caller(__METHOD__);

        return $qb->fetchResultSet();
    }

    /**
     * Get name
     * 
     * @return string|false
     */
    public function getName(int $id)
    {
        $qb = $this->dbw->newSelectQueryBuilder();
        $qb->field('name')
           ->from(static::TABLE_NAME)
           ->where('id = '.$id);

        return $qb->fetchField();
    }

    /**
     * Get the page name
     * 
     * @return string|false
     */
    public function getPageName(int $id)
    {
        $qb = $this->dbw->newSelectQueryBuilder();
        $qb->field('mw_page_name')
           ->from(static::TABLE_NAME)
           ->where('id = '.$id);

        return $qb->fetchField();
    }

    /**
     * Get the wiki object by id
     * 
     * @param int $id
     * @return MysqliResultWrapper
     */
    public function getById(int $id): MysqliResultWrapper
    {
        $qb = $this->getAllFieldsQueryBuilder($this->dbw->newSelectQueryBuilder())->where(['o.id' => $id, 'o.deleted' => 0]);
        $result = $qb->fetchResultSet();

        return $result;
    }

    /**
     * Get all the wiki objects
     * 
     * @return MysqliResultWrapper
     */
    public function getAll(): MysqliResultWrapper
    {
        $qb = $this->getAllFieldsQueryBuilder($this->dbw->newSelectQueryBuilder())->where(['o.deleted' => 0]);
        $result = $qb->fetchResultSet();

        return $result;
    }

    /**
     * Creates the query builder to retrieve all fields and relationships
     */
    public function getAllFieldsQueryBuilder(SelectQueryBuilder $qb): SelectQueryBuilder
    {
        $prefix = function($element) { 
            return 'o.'.$element;
        };

        $fields = array_merge(
            array_map($prefix, static::TABLE_FIELDS),
            [
                'image1.image AS infobox_image',
                'image2.image AS background_image'
            ]
        );
        return $qb->select($fields)
                ->from(static::TABLE_NAME, 'o')
                ->leftJoin('image',
                           'image1',
                           'o.image_id = image1.id')
                ->leftJoin('image',
                           'image2',
                           'o.background_image_id = image2.id')
                ->caller( __METHOD__ );
    }

    /**
     * Stores the media wiki description in the mw_formatted_description field
     * 
     * @param int $id
     * @param string $mwDescription
     */
    public function updateMediaWikiDescription(int $id, string $mwDescription)
    {
        $ub = $this->dbw->newUpdateQueryBuilder();
        $ub->update(static::TABLE_NAME)
             ->set(['mw_formatted_description' => $mwDescription])
             ->where(['id' => $id])
             ->caller(__METHOD__);

        return $ub->execute();
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
            $this->dbw->query("SET FOREIGN_KEY_CHECKS = 0;", __METHOD__); 

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
            $this->dbw->query("SET FOREIGN_KEY_CHECKS = 1;", __METHOD__); 
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