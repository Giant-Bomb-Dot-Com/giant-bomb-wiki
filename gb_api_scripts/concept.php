<?php

require_once(__DIR__.'/resource.php');

class Concept extends Resource
{
    const TYPE_ID = 3015;
    const RESOURCE_SINGULAR = "concept";
    const RESOURCE_MULTIPLE = "concepts";
    const TABLE_NAME = "wiki_concept";
    const RELATION_TABLE_MAP = [
        "characters" =>  ["table" => "wiki_assoc_character_concept", "mainField" => "concept_id", "relationField" => "character_id"],
        "concepts" => ["table" => "wiki_assoc_concept_similar", "mainField" => "concept_id", "relationField" => "similar_concept_id"],
        "franchises" =>  ["table" => "wiki_assoc_concept_franchise", "mainField" => "concept_id", "relationField" => "franchise_id"],
        "games" =>  ["table" => "wiki_assoc_game_concept", "mainField" => "concept_id", "relationField" => "game_id"],
        "locations" =>  ["table" => "wiki_assoc_concept_location", "mainField" => "concept_id", "relationField" => "location_id"],
        "objects" =>  ["table" => "wiki_assoc_concept_thing", "mainField" => "concept_id", "relationField" => "thing_id"],
        "people" =>  ["table" => "wiki_assoc_concept_person", "mainField" => "concept_id", "relationField" => "person_id"],
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
    public function process(array $data): int
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
                $this->addRelations(self::RELATION_TABLE_MAP[$relation], $data['id'], $data[$relation]);
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
}

?>