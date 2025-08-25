<?php

use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Rdbms\MysqliResultWrapper;

trait BuildPageData 
{
    /**
     * Converts result data into page data of [['title', 'namespace', 'description'],...]
     * 
     * @param MysqliResultWrapper $data
     * @return array
     */
    abstract public function getPageDataArray(MysqliResultWrapper $data): array;

    /**
     * Loops through the relation table map to obtain a comma delimited list of relation page names
     * 
     * @param int $id
     * @return string
     */
    public function getRelationsFromDB(int $id): string
    {
		$relations = '';
		$lastKey = array_key_last(self::RELATION_TABLE_MAP);
        foreach (self::RELATION_TABLE_MAP as $key => $relation) {

        	// concatenate all the rows page names into a single field delimited by a comma
        	$groupConcat = $this->getDb()->buildGroupConcatField(
				', ',
				$relation['relationTable'],
				$relation['relationTable'].'.mw_page_name'
			);

        	// join the relation table with the connector table to get the page names
            $qb = $this->getDb()->newSelectQueryBuilder()
                       ->select(['mw_page_name' => $groupConcat])
                       ->from($relation['relationTable'], 'o')
                       ->leftJoin($relation['table'],
                                  'j',
                                  'j.'.$relation['relationField'].' = o.id')
                       ->where('j.'.$relation['mainField'].' = '.$id)
                       ->groupBy('j.'.$relation['mainField'])
                       ->caller(__METHOD__);

            $result = $qb->fetchfield();

            // craft the semantic table row for the relation
            $relations .= '| '.ucwords($key).'='.$qb->fetchField();
            if ($lastKey != $key) {
            	$relations .= "\n";
            }
        }

        return $relations;   	
    }
}