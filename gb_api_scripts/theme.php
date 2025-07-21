<?php

require_once(__DIR__.'/resource.php');

class Theme extends Resource
{
    const TYPE_ID = 3032;
    const RESOURCE_SINGULAR = "theme";
    const RESOURCE_MULTIPLE = "themes";
    const TABLE_NAME = "wiki_game_theme";

    /**
     * Matching table fields to api response fields
     * 
     * id = id
     * name = name
     * 
     * @param array $data The api response array.
     * @return int 
     */
    public function process(array $data, array &$crawl): int
    {
        return $this->insertOrUpdate(self::TABLE_NAME, [
            'id' => $data['id'],
            'name' => (is_null($data['name'])) ? '' : $data['name'],
        ], ['id']);
    }
}

?>