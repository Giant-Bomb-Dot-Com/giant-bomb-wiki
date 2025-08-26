<?php

use Wikimedia\Rdbms\SelectQueryBuilder;

trait BuildPageData 
{
    /**
     * Converts result data into page data of [['title', 'namespace', 'description'],...]
     * 
     * @param MysqliResultWrapper $data
     * @return array
     */
    abstract public function getPageDataArray(stdClass $data): array;

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

            $groupConcat = "GROUP_CONCAT(o.mw_page_name SEPARATOR ', ')";
        	// join the relation table with the connector table to get the page names
            $qb = $this->getDb()->newSelectQueryBuilder()
                       ->select(['mw_page_name' => $groupConcat])
                       ->from($relation['table'], 'j')
                       ->join($relation['relationTable'],'o','j.'.$relation['relationField'].' = o.id')
                       ->where('j.'.$relation['mainField'].' = '.$id)
                       ->groupBy('j.'.$relation['mainField'])
                       ->caller(__METHOD__);

            $result = $qb->fetchfield();

            if (!empty($result)) {
	            // craft the semantic table row for the relation
	            $relations .= '| '.ucwords($key).'='.$result;
	            if ($lastKey != $key) {
	            	$relations .= "\n";
	            }
	        }
        }

        return $relations;   	
    }
}