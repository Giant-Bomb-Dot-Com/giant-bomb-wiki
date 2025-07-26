<?php

require_once(__DIR__.'/resource.php');

class Game_rating extends Resource
{
    const TYPE_ID = 3065;
    const RESOURCE_SINGULAR = "game_rating";
    const RESOURCE_MULTIPLE = "game_ratings";
    const TABLE_NAME = "wiki_game_release_rating";

    /**
     * Matching table fields to api response fields
     * 
     * id = id
     * date_created = date_added
     * date_updated = date_last_updated
     * name = name
     * ratingBoard_id = rating_board->id
     * 
     * @param array $data The api response array.
     * @return int 
     */
    public function process(array $data, array &$crawl): int
    {
        return $this->insertOrUpdate(self::TABLE_NAME, [
            'id' => $data['id'],
            'name' => (is_null($data['name'])) ? '' : $data['name'],
            'ratingBoard_id' => $data['rating_board']['id'],
        ], ['id']);
    }
}

?>