<?php

require_once(__DIR__.'/resource.php');
require_once(__DIR__.'/common.php');
require_once(__DIR__.'/build_page_data.php');

use Wikimedia\Rdbms\MysqliResultWrapper;

class Theme extends Resource
{
    use BuildPageData;

    const TYPE_ID = 3032;
    const RESOURCE_SINGULAR = "theme";
    const RESOURCE_MULTIPLE = "themes";
    const TABLE_NAME = "wiki_game_theme";
    const TABLE_FIELDS = ['id','name','mw_page_name','aliases','deck','mw_formatted_description'];

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
{{Theme
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