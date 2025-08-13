<?php

require_once(__DIR__.'/resource.php');

class Platform extends Resource
{
    const TYPE_ID = 3045;
    const RESOURCE_SINGULAR = "platform";
    const RESOURCE_MULTIPLE = "platforms";
    const TABLE_NAME = "wiki_platform";

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
     * short_name = abbreviation
     * aliases = aliases
     * release_date = release_date
     * install_base = install_base
     * online_support = online_support
     * original_price = original_price
     * manufacturer_id = company->id
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
            'short_name' => $data['abbreviation'],
            'aliases' => $data['aliases'],
            'release_date' => $data['release_date'],
            'install_base' => $data['install_base'],
            'online_support' => $data['online_support'],
            'original_price' => $data['original_price'],
            'manufacturer_id' => (is_null($data['company'])) ? null : $data['company']['id'],
        ], ['id']);
    }
}

?>