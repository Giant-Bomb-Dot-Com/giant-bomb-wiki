<?php

require_once(__DIR__.'/../libs/resource.php');
require_once(__DIR__.'/../libs/common.php');
require_once(__DIR__.'/../libs/build_page_data.php');

class Character extends Resource
{
    use CommonVariablesAndMethods;
    use BuildPageData;

    const TYPE_ID = 3005;
    const RESOURCE_SINGULAR = "character";
    const RESOURCE_MULTIPLE = "characters";
    const PAGE_NAMESPACE = "Characters/";
    const TABLE_NAME = "wiki_character";
    const TABLE_FIELDS = ['id','name','mw_page_name','aliases','real_name','gender','birthday','deck','mw_formatted_description','death','image_id','background_image_id'];
    const RELATION_TABLE_MAP = [
        "concepts" => [
            "table" => "wiki_assoc_character_concept", 
            "mainField" => "character_id", 
            "relationField" => "concept_id", 
            "relationTable" => "wiki_concept",
        ],
        "enemies" =>  [
            "table" => "wiki_assoc_character_enemy", 
            "mainField" => "character_id", 
            "relationField" => "enemy_character_id", 
            "relationTable" => "wiki_character",
        ],
        "franchises" =>  [
            "table" => "wiki_assoc_character_franchise", 
            "mainField" => "character_id", 
            "relationField" => "franchise_id", 
            "relationTable" => "wiki_franchise",
        ],
        "friends" =>  [
            "table" => "wiki_assoc_character_friend", 
            "mainField" => "character_id", 
            "relationField" => "friend_character_id", 
            "relationTable" => "wiki_character",
        ],
        "games" =>  [
            "table" => "wiki_assoc_game_character", 
            "mainField" => "character_id", 
            "relationField" => "game_id", 
            "relationTable" => "wiki_game",
        ],
        "locations" =>  [
            "table" => "wiki_assoc_character_location", 
            "mainField" => "character_id", 
            "relationField" => "location_id", 
            "relationTable" => "wiki_location",
        ],
        "people" =>  [
            "table" => "wiki_assoc_character_person", 
            "mainField" => "character_id", 
            "relationField" => "person_id", 
            "relationTable" => "wiki_person",
        ],
        "objects" =>  [
            "table" => "wiki_assoc_character_thing", 
            "mainField" => "character_id", 
            "relationField" => "thing_id", 
            "relationTable" => "wiki_thing",
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
     * real_name = real_name
     * gender = gender
     * birthyday = birthday
     * death = ?
     * ? = last_name
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
            'aliases' => $data['aliases'],
            'real_name' => $data['real_name'],
            'gender' => $data['gender'],
            'birthday' => $data['birthday'],
            'date_created' => $data['date_added'],
            'date_updated' => $data['date_last_updated'],
            'name' => (is_null($data['name'])) ? '' : $data['name'],
            'deck' => $data['deck'],
            'description' => (is_null($data['description'])) ? '' : $data['description'],
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
        if (empty($row->mw_formatted_description)) { 
            $desc = (!empty($row->deck)) ? htmlspecialchars($row->deck, ENT_XML1, 'UTF-8') : '';
        }
        else {
            $desc = htmlspecialchars($row->mw_formatted_description, ENT_XML1, 'UTF-8');
        }
        $relations = $this->getRelationsFromDB($row->id);

        $description = $this->formatSchematicData([
            'name' => $name,
            'guid' => $guid,
            'aliases' => $row->aliases,
            'deck' => $row->deck,
            'real_name' => $row->real_name,
            'gender' => $row->gender,
            'birthday' => $row->birthday,
            'death' => $row->death,
            'relations' => $relations
        ])
        .$this->getImageData([
            'infobox_image_id' => $row->image_id,
            'background_image_id' => $row->background_image_id,
        ])
        .$desc;

        return [
            'title' => $row->mw_page_name,
            'namespace' => $this->namespaces['page'],
            'description' => $description
        ];
    }
}

?>