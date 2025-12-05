<?php
require_once(__DIR__.'/db_interface.php');
require_once(__DIR__.'/common.php');

use Wikimedia\Rdbms\IDatabase;

class MWDbWrapper implements DbInterface
{
    use CommonVariablesAndMethods;
    private IDatabase $dbConnection;
    private string $version;

    public function __construct( IDatabase $dbConnection ) 
    {
        $this->dbConnection = $dbConnection;
        $this->version = 'api';
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getById(string $table, array $fields, int $id)
    {
        $qb = $this->dbConnection->newSelectQueryBuilder();

        $qb->select($fields)
            ->from($table, 'o')
            ->where(['o.id' => $id, 'o.deleted' => 0])
            ->caller( __METHOD__ );

        return [$qb->fetchRow()];
    }

    public function getAll(string $table, array $fields, int $continue = 0)
    {
        $qb = $this->dbConnection->newSelectQueryBuilder();
        $qb->select($fields)
            ->from($table, 'o')
            ->where(['o.deleted' => 0, $this->dbConnection->expr('id', '>', $continue)])
            ->caller( __METHOD__ );

        return $qb->fetchResultSet();
    }

    public function getPageName(string $table, int $id)
    {
        $qb = $this->dbConnection->newSelectQueryBuilder();
        $qb->field('mw_page_name')
           ->from($table)
           ->where('id = '.$id);

        return $qb->fetchField();
    }

    public function getRelatedPageNames(string $table, array $relationsMap, int $id)
    {
        $qb = $this->dbConnection->newSelectQueryBuilder()
            ->from($table, 'o')
            ->where(['o.id' => $id])
            ->caller( __METHOD__ );

        $aliasId = 1;
        foreach ($relationsMap as $key => $relation) {
            $currentAlias = 'a'.$aliasId;
            $nextAlias = 'a'.++$aliasId;

            $groupConcat = "GROUP_CONCAT({$nextAlias}.mw_page_name SEPARATOR ',')";
            $joinOn = sprintf("%s.%s = %s.id", $currentAlias, $relation['relationField'], $nextAlias);
            $clause = sprintf("%s.%s = o.id", $currentAlias, $relation['mainField']);

            $subQuery = $this->dbConnection->newSelectQueryBuilder()
                ->select($groupConcat)
                ->from($relation['table'], $currentAlias)
                ->leftJoin($relation['relationTable'], $nextAlias, $joinOn)
                ->where($clause)
                ->orderBy($nextAlias.'.mw_page_name','ASC')
                ->caller(__METHOD__);

            $qb->field('('.$subQuery->getSQL().')', $key);
        }

        return $qb->fetchRow();        
    }

    public function getImageName(int $id)
    {
        $qb = $this->dbConnection->newSelectQueryBuilder();
        $qb->field('image')
            ->from('image')
            ->where(['id' => $id])
            ->caller( __METHOD__ );

        return $qb->fetchField();
    }

    public function getImageData(int $id): array
    {
        $result = $this->getById('image', 'name, caption, path, mimetype, image_sizes', $id);

        if (empty($result)) {
            return [];
        }

        return [
            'file' => $result->name,
            'path' => $result->path,
            'mime' => $result->mimetype,
            'sizes' => $result->image_sizes,
            'caption' => $result->caption
        ];
    }

    public function getCreditsFromDB(int $id)
    {
        $qb = $this->dbConnection->newSelectQueryBuilder()
                   ->select(['o.person_id','o.description','o.role_id','p.mw_page_name'])
                   ->from('wiki_assoc_game_person','o')
                   ->join('wiki_person', 'p', 'o.person_id = p.id')
                   ->where('o.game_id = '.$id)
                   ->caller(__METHOD__);

        return $qb->fetchResultSet();
    }

    public function getReleasesFromDB(int $id)
    {
        $qb = $this->dbConnection->newSelectQueryBuilder()
                   ->select(['o.id','o.region_id','o.product_code_type','o.company_code_type','o.rating_id','o.image_id','o.release_date','o.release_date_type','o.product_code','o.company_code','o.name','o.description','o.widescreen_support','o.minimum_players','o.maximum_players','a2.mw_page_name AS developer','a4.mw_page_name as publisher','a5.mw_page_name AS platform','a6.feature_id AS mp_feature_id','a7.resolution_id','a8.feature_id AS sp_feature_id','a9.soundsystem_id'])
                   ->from('wiki_game_release', 'o')
                   ->leftJoin('wiki_game_release_to_developer','a1','o.id = a1.release_id')
                   ->leftJoin('wiki_company','a2','a1.company_id = a2.id')
                   ->leftJoin('wiki_game_release_to_publisher','a3','o.id = a3.release_id')
                   ->leftJoin('wiki_company','a4','a3.company_id = a4.id')
                   ->leftJoin('wiki_platform','a5','o.platform_id = a5.id')
                   ->leftJoin('wiki_game_release_to_multiplayer_feature','a6','o.id = a6.release_id')
                   ->leftJoin('wiki_game_release_to_resolution','a7','o.id = a7.release_id')
                   ->leftJoin('wiki_game_release_to_singleplayer_feature','a8','o.id = a8.release_id')
                   ->leftJoin('wiki_game_release_to_sound_system','a9','o.id = a9.release_id')
                   ->where('o.game_id = '.$id.' AND o.deleted = 0')
                   ->caller(__METHOD__);

        return $qb->fetchResultSet();
    }

    public function getDLCFromDB(int $id)
    {
        $qb = $this->dbConnection->newSelectQueryBuilder()
                   ->select(['o.id','o.image_id','o.release_date','o.release_date_type','o.name','o.description','o.launch_price','o.deck','a2.mw_page_name AS developer','a4.mw_page_name as publisher','a5.mw_page_name AS platform','a7.name as dlc_type'])
                   ->from('wiki_game_dlc', 'o')
                   ->leftJoin('wiki_game_dlc_to_developer','a1','o.id = a1.dlc_id')
                   ->leftJoin('wiki_company','a2','a1.company_id = a2.id')
                   ->leftJoin('wiki_game_dlc_to_publisher','a3','o.id = a3.dlc_id')
                   ->leftJoin('wiki_company','a4','a3.company_id = a4.id')
                   ->leftJoin('wiki_platform','a5','o.platform_id = a5.id')
                   ->leftJoin('wiki_game_dlc_to_type', 'a6', 'o.id = a6.dlc_id')
                   ->leftJoin('wiki_game_dlc_type', 'a7', 'a6.type_id = a7.id')
                   ->where('o.game_id = '.$id.' AND o.deleted = 0')
                   ->caller(__METHOD__);

        return $qb->fetchResultSet();
    }

    public function getTextToConvert(string $table, $id = false, $force = false, $continue = 0)
    {
        if ($id) {
            $clause = 'id = '.$id;
        }
        else {
            if ($force) {
                $clause = '1=1';
            }
            else {
                $clause = 'mw_formatted_description IS NULL';
            }

            if ($continue > 0) {
                $clause .= ' AND id > '.$continue;
            }
        }

        $qb = $this->dbConnection->newSelectQueryBuilder();
        $qb->select(['id', 'name', 'description'])
             ->from($table)
             ->where($clause)
             ->caller(__METHOD__);

        return $qb->fetchResultSet();
    }

    public function getNamesToConvert(string $table, $id = false, $force = false)
    {
        if ($id) {
            $clause = 'id = '.$id;
        }
        else {
            if ($force) {
                $clause = '1=1';
            }
            else {
                $clause = 'mw_page_name IS NULL';
            }
        }

        $qb = $this->dbConnection->newSelectQueryBuilder();
        $qb->from($table)
             ->where($clause)
             ->caller(__METHOD__);

        if ($table == 'wiki_game') {
            $qb->select(['id', 'name', 'release_date']);
            $qb->orderBy('id', 'ASC');
        }
        else {
            $qb->select(['id', 'name']);
        }

        return $qb->fetchResultSet();
    }

    public function getPageEditors()
    {
        $qb = $this->dbConnection->newSelectQueryBuilder()
                   ->select(['o.submitter_id','o.image_id','o.release_date','o.release_date_type','o.name','o.description','o.launch_price','o.deck','a2.mw_page_name AS developer','a4.mw_page_name as publisher','a5.mw_page_name AS platform','a7.name as dlc_type'])
                   ->from('wiki_changeset', 'o')
                   ->where('o.status = 2 AND o.assoc_id IS NOT NULL')
                   ->orderBy('o.assoc_type_id', 'ASC')
                   ->orderBy('o.assoc_id', 'ASC')
                   ->caller(__METHOD__);

        return $qb->fetchResultSet();
    }

    public function updateMediaWikiDescription(string $table, int $id, string $mwDescription) 
    {
        $ub = $this->dbConnection->newUpdateQueryBuilder();

        if ($id == 0) {
            $ub->update($table)
                ->set(['mw_formatted_description' => $mwDescription])
                ->where('mw_formatted_description IS NULL')
                ->caller(__METHOD__);
        }
        else {
            $ub->update($table)
                 ->set(['mw_formatted_description' => $mwDescription])
                 ->where(['id' => $id])
                 ->caller(__METHOD__);
        }

        return $ub->execute();
    }

    public function updateMediaWikiPageName(string $table, int $id, string $mwPageName) 
    {
        $ub = $this->dbConnection->newUpdateQueryBuilder();
        $ub->update($table)
             ->set(['mw_page_name' => $mwPageName])
             ->where(['id' => $id])
             ->caller(__METHOD__);

        return $ub->execute();
    }

    public function hasResults($result): bool
    {
        return $result->count() > 0;
    }

    public function getDbw() 
    {
        return $this->dbConnection;
    }

}
?>