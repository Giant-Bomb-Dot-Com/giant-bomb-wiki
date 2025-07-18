<?php

use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\InsertQueryBuilder;
use Wikimedia\Rdbms\SelectQueryBuilder;;

/**
 * Resource Class
 *
 * Defines common methods the wiki content classes should use and implement
 */
abstract class Resource 
{
    private IDatabase $dbw;

    /**
     * Constructor
     *
     * @param IDatabase $dbw Primay db for writes
     */
    public function __construct(IDatabase $dbw) 
    {
        $this->dbw = $dbw;
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
            $qb->select($uniquePrimaryKeys)
               ->from($tableName)
               ->where($data)
               ->caller(__METHOD__);

            $result = $qb->fetchRow();

            if ($result !== false) {
                $set = json_decode(json_encode($result), true);

                echo "Duplicate found in " . $tableName . " table with data: " . implode(', ', $set) . "\r\n";
                return 0;
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
            echo "Updated " . $tableName . " table with ID " . $data['id'] . "\r\n";
        }
        else {
            if ($diffCount == 0) {
                echo "Added to " . $tableName . " table with composite data: " . implode(', ', $data) . "\r\n";
            }
            else {
                echo "Added to " . $tableName . " table with ID " . $insertId . "\r\n";
            }
        }

        return $insertId;
    }

    /**
     * Fills in the connector tables
     * 
     * @param array $map A mapping table defining the field names and table name
     * @param int $mainFieldId The id of the main field
     * @param array $relations An array that includes the id of the relation field
     */
    public function addRelations(array $map, int $mainFieldId, array $relations): void
    {
        foreach ($relations as $entry) {
            $this->insertOrUpdate($map["table"], 
                                 [$map["mainField"] => $mainFieldId, $map["relationFIeld"] => $entry["id"]], 
                                 [$map["mainField"], $map["relationFIeld"]]);
        }
    }

    /**
     * Loops through the results and saves each one
     * 
     * @param array $data The response from the api call.
     * @return void
     */
    public function save(array $data): void
    {
        try {
            $this->dbw->query("SET FOREIGN_KEY_CHECKS = 0;", __METHOD__); 

            // TODO: batch save?

            foreach ($data as $row) {
                $this->process($row);
            }

            echo "Total proccessed: " . count($data) . "\r\n";
        } catch (Exceoption $e) {
            wfLogWarning("Error during ".__METHOD__." with disabled FK checks: " . $e->getMessage());
            throw $e;
        } finally {
            $this->dbw->query("SET FOREIGN_KEY_CHECKS = 1;", __METHOD__); 
        }
    }

    /**
     * Implemented by child classes to match the api fields to the db fields
     * 
     * @param array $data
     * @return int
     */
    abstract public function process(array $data): int;
}

?>