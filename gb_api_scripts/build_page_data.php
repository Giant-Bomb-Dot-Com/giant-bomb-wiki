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
        $text = "{{{$wikiType}";

        // name and guid is guaranteed to exist
        $text .= "\n| Name={$data['name']}\n| Guid={$data['guid']}";

        // only include if there is content to save db space
        if (!empty($data['aliases'])) {
            $aliases = explode("\n", $data['aliases']);
            $aliases = implode(',', $aliases);
            $text .= "\n| Aliases={$aliases}";
        }

        if (!empty($data['deck'])) {
            $deck = htmlspecialchars($data['deck'], ENT_XML1, 'UTF-8');
            $text .= "\n| Deck={$deck}";
        }

        if (!empty($data['infobox_image'])) {
            $imageFragment = parse_url($data['infobox_image'], PHP_URL_PATH);
            $infoboxImage = basename($imageFragment);
            $text .= "\n| Image={$infoboxImage}";
            $text .= "\n| Caption=image of {$data['name']}";
        }

        if (!empty($data['background_image'])) {
            $imageFragment = parse_url($data['background_image'], PHP_URL_PATH);
            $backgroundImage = basename($imageFragment);
            $text .= "\n| BackgroundImage={$backgroundImage}";
            $text .= "\n| Caption=background image used in Giant Bomb's game page for {$data['name']}";
        }

        if (!empty($data['real_name'])) {
            $text .= "\n| RealName={$realName}";
        }

        if (!empty($data['gender'])) {
            switch ($data['gender']) {
                case 0: $gender = 'Female'; break;
                case 1: $gender = 'Male'; break;
                default: $gender = 'Non-Binary'; break;
            }
            $text .= "\n| Gender={$gender}";
        }

        if (!empty($data['birthday'])) {
            $text .= "\n| Birthday={$birthday}";
        }

        if (!empty($data['death'])) {
            $text .= "\n| Death={$death}";
        }

        if (!empty($data['abbreviation'])) {
            $text .= "\n| Abbreviation={$abbreviation}";
        }

        if (!empty($data['founded_date'])) {
            $text .= "\n| FoundedDate={$foundedDate}";
        }

        if (!empty($data['address'])) {
            $text .= "\n| Address={$address}";
        }

        if (!empty($data['city'])) {
            $text .= "\n| City={$city}";
        }

        if (!empty($data['country'])) {
            $text .= "\n| Country={$country}";
        }

        if (!empty($data['state'])) {
            $text .= "\n| State={$state}";
        }

        if (!empty($data['phone'])) {
            $text .= "\n| Phone={$phone}";
        }

        if (!empty($data['website'])) {
            $text .= "\n| Website={$website}";
        }

        if (!empty($data['relations'])) {
            $text .= "\n".$data['relations'];
        }

        $text .= "\n}}\n";

        return $text;
    }
}