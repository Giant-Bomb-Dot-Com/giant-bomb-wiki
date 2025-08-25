<?php

require_once(__DIR__.'/resource.php');
require_once(__DIR__.'/common.php');
require_once(__DIR__.'/build_page_data.php');

use Wikimedia\Rdbms\MysqliResultWrapper;

class Company extends Resource
{
    use BuildPageData;

    const TYPE_ID = 3010;
    const RESOURCE_SINGULAR = "company";
    const RESOURCE_MULTIPLE = "companies";
    const TABLE_NAME = "wiki_company";
    const TABLE_FIELDS = ['id','name','mw_page_name','aliases','deck','mw_formatted_description'];
    const RELATION_TABLE_MAP = [
        "characters" =>  ["table" => "wiki_assoc_character_company", "mainField" => "company_id", "relationField" => "character_id"],
        "concepts" => ["table" => "wiki_assoc_company_concept", "mainField" => "company_id", "relationField" => "concept_id"],
        "developed_games" =>  ["table" => "wiki_assoc_game_developer", "mainField" => "company_id", "relationField" => "game_id"],
        "locations" =>  ["table" => "wiki_assoc_company_location", "mainField" => "company_id", "relationField" => "location_id"],
        "objects" =>  ["table" => "wiki_assoc_company_thing", "mainField" => "company_id", "relationField" => "thing_id"],
        "people" =>  ["table" => "wiki_assoc_company_person", "mainField" => "company_id", "relationField" => "person_id"],
        "published_games" =>  ["table" => "wiki_assoc_game_publisher", "mainField" => "company_id", "relationField" => "game_id"],
    ];

    /**
     * Matching table fields to api response fields
     * 
     * id = id
     * image_id = image->original_url
     * date_created = date_added
     * date_updated = date_last_updated
     * name = name
     * deck = deck
     * description = description
     * abbreviation = abbreviation
     * aliases = aliases
     * founded_date = date_founded
     * address = location_address
     * city = location_city
     * country = location_country
     * state = location_state
     * phone = phone
     * website = website
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

        // save the wiki type relationships in their respective relationship table
        //  these are only available when hitting the singular endpoint
        $keys = array_keys(self::RELATION_TABLE_MAP);
        foreach ($keys as $relation) {
            if (!empty($data[$relation])) {
                $this->addRelations(self::RELATION_TABLE_MAP[$relation], $data['id'], $data[$relation], $crawl);
            }
        }

        return $this->insertOrUpdate(self::TABLE_NAME, [
            'id' => $data['id'],
            'image_id' => $imageId,
            'date_created' => $data['date_added'],
            'date_updated' => $data['date_last_updated'],
            'name' => (is_null($data['name'])) ? '' : $data['name'],
            'deck' => $data['deck'],
            'description' => (is_null($data['description'])) ? '' : $data['description'],
            'abbreviation' => $data['abbreviation'],
            'aliases' => $data['aliases'],
            'founded_date' => $data['date_founded'],
            'address' => $data['location_address'],
            'city' => $data['location_city'],
            'country' => $data['location_country'],
            'state' => $data['location_state'],
            'phone' => $data['phone'],
            'website' => $data['website'],
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
{{Company
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