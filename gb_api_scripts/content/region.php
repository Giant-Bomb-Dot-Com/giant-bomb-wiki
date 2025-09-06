<?php

require_once(__DIR__.'/../libs/resource.php');

class Region extends Resource
{
    const TYPE_ID = 3075;
    const RESOURCE_SINGULAR = "region";
    const RESOURCE_MULTIPLE = "regions";
    const TABLE_NAME = "wiki_game_release_region";

    /**
     * Matching table fields to api response fields
     * 
     * id = id
     * image_id = image->original_url
     * date_created = date_added
     * date_updated = date_last_updated
     * deck = deck
     * description = description
     * name = name
     * abbreviation = ''
     * 
     * @param array $data The api response array.
     * @return int 
     */
    public function process(array $data, array &$crawl): int
    {
        // save the image relation first to get its id
        $imageId = $this->insertOrUpdate("image", [
            'assoc_type_id' => self::TYPE_ID,
            'assoc_id' => $data['id'],
            'image' => $data['image']['original_url'],
        ], ['assoc_type_id', 'assoc_id', 'image']);

        return $this->insertOrUpdate(self::TABLE_NAME, [
            'id' => $data['id'],
            'image_id' => $imageId,
            'date_created' => $data['date_added'],
            'date_updated' => $data['date_last_updated'],
            'deck' => $data['deck'],
            'description' => (is_null($data['description'])) ? '' : $data['description'],
            'name' => (is_null($data['name'])) ? '' : $data['name'],
            'abbreviation' => '',
        ], ['id']);
    }
}

?>