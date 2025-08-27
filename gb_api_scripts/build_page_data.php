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

            $groupConcat = "GROUP_CONCAT(o.mw_page_name SEPARATOR ',')";
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

    /**
     * Creates the semantic table based on fields in the incoming $data array
     *
     * @param array $data
     * @return string
     */
    public function formatTemplateData(array $data): string
    {
        // start with wiki type
        $wikiType = ucwords(static::RESOURCE_SINGULAR);
        $text = "{{{$wikiType}\n";

        // name and guid is guaranteed to exist
        $text .= "| Name={$data['name']}\n| Guid={$data['guid']}\n";

        // only include if there is content to save db space
        if (!empty($data['aliases'])) {
            $aliases = explode("\n", $data['aliases']);
            $aliases = implode(',', $aliases);
            $aliases = htmlspecialchars($aliases, ENT_XML1, 'UTF-8');
            $text .= "| Aliases={$aliases}\n";
        }

        if (!empty($data['deck'])) {
            $deck = htmlspecialchars($data['deck'], ENT_XML1, 'UTF-8');
            $text .= "| Deck={$deck}\n";
        }

        if (!empty($data['infobox_image'])) {
            $imageFragment = parse_url($data['infobox_image'], PHP_URL_PATH);
            $infoboxImage = basename($imageFragment);
            $text .= "| Image={$infoboxImage}\n";
            $text .= "| Caption=image of {$data['name']}\n";
        }

        if (!empty($data['real_name'])) {
            $realName = htmlspecialchars($data['real_name'], ENT_XML1, 'UTF-8');
            $text .= "| RealName={$realName}\n";
        }

        if (!empty($data['gender'])) {
            switch ($data['gender']) {
                case 0: $gender = 'Female'; break;
                case 1: $gender = 'Male'; break;
                default: $gender = 'Non-Binary'; break;
            }
            $text .= "| Gender={$gender}\n";
        }

        if (!empty($data['birthday'])) {
            $birthday = htmlspecialchars($data['birthday'], ENT_XML1, 'UTF-8');
            $text .= "| Birthday={$birthday}\n";
        }

        if (!empty($data['death'])) {
            $death = htmlspecialchars($data['death'], ENT_XML1, 'UTF-8');
            $text .= "| Death={$death}\n";
        }

        if (!empty($data['relations'])) {
            $text .= $data['relations'];
        }
        
        $text .= "}}\n";

        return $text;
    }
}