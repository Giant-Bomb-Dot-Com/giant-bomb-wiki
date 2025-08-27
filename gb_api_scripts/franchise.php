<?php

require_once(__DIR__.'/resource.php');
require_once(__DIR__.'/common.php');
require_once(__DIR__.'/build_page_data.php');

class Franchise extends Resource
{
    use CommonVariablesAndMethods;
    use BuildPageData;

    const TYPE_ID = 3025;
    const RESOURCE_SINGULAR = "franchise";
    const RESOURCE_MULTIPLE = "franchises";
    const TABLE_NAME = "wiki_franchise";
    const TABLE_FIELDS = ['id','name','mw_page_name','aliases','deck','mw_formatted_description'];
    const RELATION_TABLE_MAP = [
        "characters" =>  [
            "table" => "wiki_assoc_character_franchise", 
            "mainField" => "franchise_id", 
            "relationTable" => "wiki_character",
            "relationField" => "character_id"
        ],
        "concepts" => [
            "table" => "wiki_assoc_concept_franchise", 
            "mainField" => "franchise_id", 
            "relationTable" => "wiki_concept",
            "relationField" => "concept_id"
        ],
        "games" =>  [
            "table" => "wiki_assoc_game_franchise", 
            "mainField" => "franchise_id", 
            "relationTable" => "wiki_game_dlc",
            "relationField" => "game_id"
        ],
        "locations" =>  [
            "table" => "wiki_assoc_franchise_location", 
            "mainField" => "franchise_id", 
            "relationTable" => "wiki_location",
            "relationField" => "location_id"
        ],
        "objects" =>  [
            "table" => "wiki_assoc_franchise_thing", 
            "mainField" => "franchise_id", 
            "relationTable" => "wiki_thing",
            "relationField" => "thing_id"
        ],
        "people" =>  [
            "table" => "wiki_assoc_franchise_person", 
            "mainField" => "franchise_id", 
            "relationTable" => "wiki_person",
            "relationField" => "person_id"
        ],
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
     * aliases = aliases
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
            'aliases' => $data['aliases'],
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
        $relations = $this->getRelationsFromDB($row->id);

        $description = $desc."\n".$this->formatSchematicData([
            'name' => $name,
            'guid' => $guid,
            'aliases' => $row->aliases,
            'deck' => $row->deck,
            'infobox_image' => $row->infobox_image,
            'background_image' => $row->background_image,
            'relations' => $relations
        ]);

        return [
            'title' => $row->mw_page_name,
            'namespace' => $this->namespaces['page'],
            'description' => $description
        ];
    }
}

?>