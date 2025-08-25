<?php

require_once(__DIR__.'/resource.php');
require_once(__DIR__.'/common.php');
require_once(__DIR__.'/build_page_data.php');

use Wikimedia\Rdbms\MysqliResultWrapper;

class Dlc extends Resource
{
    use BuildPageData;

    const TYPE_ID = 3020;
    const RESOURCE_SINGULAR = "dlc";
    const RESOURCE_MULTIPLE = "dlcs";
    const TABLE_NAME = "wiki_game_dlc";
    const TABLE_FIELDS = ['id','name','mw_page_name','aliases','deck','mw_formatted_description'];

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
            'date_created' => $data['date_added'],
            'date_updated' => $data['date_last_updated'],
            'name' => (is_null($data['name'])) ? '' : $data['name'],
            'deck' => $data['deck'],
            'description' => (is_null($data['description'])) ? '' : $data['description'],
        ], ['id']);
    }

    /**
     * Prepends semantic data to description
     * 
     * @param MysqliResultWrapper $data
     * @return void
     */
    public function getPageDataArray(MysqliResultWrapper $data): array
    {
        $content = [];
        foreach ($data as $row) {
            $guid = self::TYPE_ID.'-'.$row->id;
            $desc = htmlspecialchars($row->mw_formatted_description);
            $imageFragment = parse_url($row->infobox_image, PHP_URL_PATH);
            $infoboxImage = basename($imageFragment);

            $description = <<<MARKUP
{{DLC
| Name=$row->name
| Guid=$guid
| Image=$infoboxImage
| Caption=image of $row->name
| Deck=$row->deck
}}
$desc
MARKUP;
            $content[] = [
                'title' => $row->mw_page_name,
                'namespace' => $this->namespace['page'],
                'description' => $description
            ];
        }

        return $content;
    }
}

?>