<?php
require_once(__DIR__.'/db_interface.php');
require_once(__DIR__.'/common.php');

class PdoDbWrapper implements DbInterface
{
    use CommonVariablesAndMethods;
    private PDO $dbConnection;
    private string $version;

    public function __construct(PDO $dbConnection) 
    {
        $this->dbConnection = $dbConnection;
        $this->version = 'external';
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getById(string $table, array $fields, int $id)
    {
        $fieldString = implode(',',$fields);

        $sql = "SELECT {$fieldString}
                  FROM {$table} AS o
                 WHERE o.id = :id AND o.deleted = 0";

        return [$this->fetchObject($sql, ['id' => $id])];
    }

    public function getAll(string $table, array $fields, int $continue = 0)
    {
        $fieldString = implode(',',$fields);

        $sql = "SELECT {$fieldString}
                  FROM {$table} AS o
                 WHERE o.deleted = 0 AND id > :continue";

        return $this->fetchAllObjects($sql, ['continue' => $continue]);
    }

    public function getPageName(string $table, int $id)
    {
        $sql = "SELECT mw_page_name FROM {$table} WHERE id = :id";

        return $this->fetchField($sql, ['id' => $id]);
    }

    public function getRelatedPageNames(string $table, array $relationsMap, int $id)
    {
        $aliasId = 1;

        $subQueries = [];
        foreach ($relationsMap as $key => $relation) {
            $currentAlias = 'a'.$aliasId;
            $nextAlias = 'a'.++$aliasId;

            $subQueries[] = sprintf(
                "(SELECT GROUP_CONCAT(DISTINCT %s.mw_page_name SEPARATOR ',') 
                    FROM %s AS %s 
               LEFT JOIN %s AS %s ON %s.%s = %s.id 
                   WHERE %s.%s = o.id
                ORDER BY %s.mw_page_name ASC) AS %s",
                   $nextAlias,
                   $relation['table'], $currentAlias,
                   $relation['relationTable'], $nextAlias, $currentAlias, $relation['relationField'], $nextAlias,
                   $currentAlias, $relation['mainField'],
                   $nextAlias, $key
            );
        }

        $fields = implode(',', $subQueries);
        $sql = "SELECT {$fields} FROM {$table} AS o WHERE o.id = :id";

        return $this->fetchObject($sql, ['id' => $id]);
    }

    public function getImageName(int $id)
    {
        $sql = "SELECT name FROM image WHERE id = :id";

        return $this->fetchfield($sql, ['id' => $id]);
    }

    public function getImagesForGame(int $gameId)
    {
        $sql = "SELECT id, image, caption
                  FROM image
                 WHERE assoc_type_id = :assocTypeId AND assoc_id = :gameId
              ORDER BY id ASC";

        return $this->fetchAllObjects($sql, [
            'assocTypeId' => self::ASSOC_TYPE_GAME,
            'gameId' => $gameId
        ]);
    }

    public function getCreditsFromDB(int $id)
    {
        $sql = "SELECT o.person_id, o.description, o.role_id, p.mw_page_name
                  FROM wiki_assoc_game_person AS o
                  JOIN wiki_person AS p ON o.person_id = p.id
                 WHERE o.game_id = :id";

        return $this->fetchAllObjects($sql, ['id' => $id]);
    }
    
    public function getReleasesFromDB(int $id)
    {
        $sql = "SELECT o.id, o.region_id, o.product_code_type, o.company_code_type, o.rating_id, o.image_id, o.release_date, o.release_date_type, o.product_code, o.company_code, o.name, o.description, o.widescreen_support, o.minimum_players, o.maximum_players, a2.mw_page_name AS developer, a4.mw_page_name AS publisher, a5.mw_page_name AS platform, a6.feature_id as mp_feature_id, a7.resolution_id, a8.feature_id as sp_feature_id, a9.soundsystem_id
                  FROM wiki_game_release AS o
             LEFT JOIN wiki_game_release_to_developer AS a1 ON o.id = a1.release_id
             LEFT JOIN wiki_company AS a2 ON a1.company_id = a2.id
             LEFT JOIN wiki_game_release_to_publisher AS a3 ON o.id = a3.release_id
             LEFT JOIN wiki_company AS a4 ON a3.company_id = a4.id
             LEFT JOIN wiki_platform AS a5 ON o.platform_id = a5.id
             LEFT JOIN wiki_game_release_to_multiplayer_feature AS a6 ON o.id = a6.release_id
             LEFT JOIN wiki_game_release_to_resolution AS a7 ON o.id = a7.release_id
             LEFT JOIN wiki_game_release_to_singleplayer_feature AS a8 ON o.id = a8.release_id
             LEFT JOIN wiki_game_release_to_sound_system AS a9 ON o.id = a9.release_id
                 WHERE o.game_id = :id AND o.deleted = 0";

        return $this->fetchAllObjects($sql, ['id' => $id]);
    }

    public function getDLCFromDB(int $id)
    {
        $sql = "SELECT o.id, o.image_id, o.release_date, o.release_date_type, o.name, o.description, o.launch_price, o.deck, a2.mw_page_name AS developer, a4.mw_page_name AS publisher, a5.mw_page_name AS platform, a7.name AS dlc_type
                  FROM wiki_game_dlc AS o
             LEFT JOIN wiki_game_release_to_developer AS a1 ON o.id = a1.release_id
             LEFT JOIN wiki_company AS a2 ON a1.company_id = a2.id
             LEFT JOIN wiki_game_release_to_publisher AS a3 ON o.id = a3.release_id
             LEFT JOIN wiki_company AS a4 ON a3.company_id = a4.id
             LEFT JOIN wiki_platform AS a5 ON o.platform_id = a5.id
             LEFT JOIN wiki_game_dlc_to_type AS a6 ON o.id = a6.dlc_id
             LEFT JOIN wiki_game_dlc_type AS a7 ON a6.type_id = a7.id
                 WHERE o.game_id = :id AND o.deleted = 0";

        return $this->fetchAllObjects($sql, ['id' => $id]);
    }

    public function getTextToConvert(string $table, $id = false, $force = false, $continue = 0)
    {
        $params = [];
        if ($id) {
            $clause = 'id = :id';
            $params['id'] = $id;
        }
        else {
            if ($force) {
                $clause = '1=1';
            }
            else {
                $clause = 'description <> "" AND mw_formatted_description IS NULL';
            }

            if ($continue > 0) {
                $clause .= ' AND id > '.$continue;
            }
        }

        $sql = "SELECT id, name, description FROM {$table} WHERE ".$clause;

        return $this->fetchAllObjects($sql, $params);
    }

    public function getNamesToConvert(string $table, $id = false, $force = false)
    {
        $params = [];
        if ($id) {
            $clause = 'id = :id';
            $params['id'] = $id;
        }
        else {
            if ($force) {
                $clause = '1=1';
            }
            else {
                $clause = 'mw_page_name IS NULL';
            }
        }

        $fields = 'id, name';
        $order = '';
        if ($table == 'wiki_game') {
            $fields .= ', release_date';
            $order = ' ORDER BY id ASC';
        }

        $sql = "SELECT {$fields} FROM {$table} WHERE ".$clause.$order;

        return $this->fetchAllObjects($sql, $params);
    }

    public function updateMediaWikiDescription(string $table, int $id, string $mwDescription) 
    {
        $this->descriptionTableCheck($table);

        if ($id == 0) {
            $sql = "UPDATE {$table} SET mw_formatted_description = :mwDescription WHERE mw_formatted_description IS NULL";
            $params = ['mwDescription' => $mwDescription];
        }
        else {
            $sql = "UPDATE {$table} SET mw_formatted_description = :mwDescription WHERE id = :id";
            $params = ['mwDescription' => $mwDescription, 'id' => $id];
        }

        return $this->execute($sql, $params);
    }

    public function updateMediaWikiPageName(string $table, int $id, string $mwPageName) 
    {
    	$this->pageNameTableCheck($table);

    	$sql = "UPDATE {$table} SET mw_page_name = :mwPageName WHERE id = :id";

        return $this->execute($sql, ['mwPageName' => $mwPageName, 'id' => $id]);
    }

    public function hasResults($result): bool
    {
        return count($result) > 0;
    }

    public function getDbw() 
    {
    	return $this;
    }

    private function descriptionTableCheck(string $table) 
    {
        if (!isset(self::ALLOWED_TABLES_FOR_DESCRIPTION[$table])) {
            throw new Exception("{$table} is not allowed");
        }
    }

    private function pageNameTableCheck(string $table) 
    {
		if (!isset(self::ALLOWED_TABLES_FOR_PAGENAME[$table])) {
    		throw new Exception("{$table} is not allowed");
    	}
    }

    private function fetchObject(string $sql, array $params = []): stdClass 
    {
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchObject();
    }

    private function fetchAllObjects(string $sql, array $params = []): array 
    {
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    private function fetchField(string $sql, array $params = [], int $column = 0)
    {
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn(0);
    }

    private function execute(string $sql, array $params = []): int 
    {
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }
}

?>