<?php

require_once(__DIR__.'/../libs/resource.php');
require_once(__DIR__.'/../libs/common.php');

class Dlc extends Resource
{
    use CommonVariablesAndMethods;

    const TYPE_ID = 3020;
    const RESOURCE_SINGULAR = "dlc";
    const RESOURCE_MULTIPLE = "dlcs";
    const PAGE_NAMESPACE = "DLCs/";
    const TABLE_NAME = "wiki_game_dlc";
    const TABLE_FIELDS = ['id','name','mw_page_name','aliases','deck','mw_formatted_description','game_id','platform_id','release_date','release_date_type','launch_price','image_id','background_image_id'];

    /**
     * Matching table fields to api response fields
     * 
     * id = id
     * image_id = image->original_url
     * game_id = game->id
     * platform_id = platform->id
     * date_created = date_added
     * date_updated = date_last_updated
     * name = name
     * deck = deck
     * description = description
     * release_date = release_date
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
            'game_id' => (is_null($data['game'])) ? null : $data['game']['id'],
            'platform_id' => (is_null($data['platform'])) ? null : $data['platform']['id'],
            'release_date' => $data['release_date'],
            'release_date_type' => $data['release_date_type'],
            'date_created' => $data['date_added'],
            'date_updated' => $data['date_last_updated'],
            'name' => (is_null($data['name'])) ? '' : $data['name'],
            'deck' => $data['deck'],
            'description' => (is_null($data['description'])) ? '' : $data['description'],
        ], ['id']);
    }
}

?>