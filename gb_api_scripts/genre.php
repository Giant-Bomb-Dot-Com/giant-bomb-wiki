<?php

require_once(__DIR__.'/resource.php');
require_once(__DIR__.'/common.php');
require_once(__DIR__.'/build_page_data.php');

class Genre extends Resource
{
    use BuildPageData;

    const TYPE_ID = 3060;
    const RESOURCE_SINGULAR = "genre";
    const RESOURCE_MULTIPLE = "genres";
    const TABLE_NAME = "wiki_game_genre";
    const TABLE_FIELDS = ['id','name','mw_page_name','aliases','deck','mw_formatted_description'];

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
        ], ['id']);
    }

    /**
     * Converts result row into page data array of ['title', 'namespace', 'description']
     * 
     * @param stdClass $row
     * @return array
     */
    public function getPageDataArray(stdClass $row): array
    {
        $name = htmlspecialchars($row->name, ENT_XML1, 'UTF-8');
        $guid = self::TYPE_ID.'-'.$row->id;
        $desc = (empty($row->mw_formatted_description)) ? '' : htmlspecialchars($row->mw_formatted_description, ENT_XML1, 'UTF-8');

        $description = <<<MARKUP
$desc
{{Genre
| Name=$name
| Guid=$guid

MARKUP;
        // only include if there is content to save db space
        if (!empty($row->aliases)) {
            $aliases = htmlspecialchars($row->aliases, ENT_XML1, 'UTF-8');
            $description .= <<<MARKUP
| Aliases=$aliases

MARKUP;
        }

        if (!empty($row->deck)) {
            $deck = htmlspecialchars($row->deck, ENT_XML1, 'UTF-8');
            $description .= <<<MARKUP
| Deck=$deck

MARKUP;
        }

        if (!empty($row->infobox_image)) {
            $imageFragment = parse_url($row->infobox_image, PHP_URL_PATH);
            $infoboxImage = basename($imageFragment);
            $description .= <<<MARKUP
| Image=$infoboxImage
| Caption=image of $name

MARKUP;
        }

        $description .= "}}\n";

        return [
            'title' => $row->mw_page_name,
            'namespace' => $this->namespaces['page'],
            'description' => $description
        ];
    }
}

?>