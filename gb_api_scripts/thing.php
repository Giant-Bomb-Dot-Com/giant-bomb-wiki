<?php

require_once(__DIR__.'/resource.php');

class Thing extends Resource
{
    const TYPE_ID = 3055;
    const RESOURCE_SINGULAR = "object";
    const RESOURCE_MULTIPLE = "objects";
    const TABLE_NAME = "wiki_thing";
    const RELATION_TABLE_MAP = [
        "characters" =>  ["table" => "wiki_assoc_character_thing", "mainField" => "thing_id", "relationField" => "character_id"],
        "concepts" => ["table" => "wiki_assoc_concept_thing", "mainField" => "thing_id", "relationField" => "concept_id"],
        "franchises" =>  ["table" => "wiki_assoc_franchise_thing", "mainField" => "thing_id", "relationField" => "game_id"],
        "games" =>  ["table" => "wiki_assoc_game_thing", "mainField" => "thing_id", "relationField" => "game_id"],
        "locations" =>  ["table" => "wiki_assoc_location_thing", "mainField" => "thing_id", "relationField" => "location_id"],
        "people" =>  ["table" => "wiki_assoc_person_thing", "mainField" => "thing_id", "relationField" => "person_id"],
        "similar" =>  ["table" => "wiki_assoc_thing_similar", "mainField" => "thing_id", "relationField" => "similar_thing_id"],
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
        if (!empty($data['characters'])) {
            $this->addRelations(self::RELATION_TABLE_MAP['characters'], $data['id'], $data['characters']);
        }
        if (!empty($data['concepts'])) {
            $this->addRelations(self::RELATION_TABLE_MAP['concepts'], $data['id'], $data['concepts']);
        }
        if (!empty($data['franchises'])) {
            $this->addRelations(self::RELATION_TABLE_MAP['franchises'], $data['id'], $data['franchises']);
        }
        if (!empty($data['games'])) {
            $this->addRelations(self::RELATION_TABLE_MAP['games'], $data['id'], $data['games']);
        }
        if (!empty($data['locations'])) {
            $this->addRelations(self::RELATION_TABLE_MAP['locations'], $data['id'], $data['locations']);
        }
        if (!empty($data['people'])) {
            $this->addRelations(self::RELATION_TABLE_MAP['people'], $data['id'], $data['people']);
        }
        if (!empty($data['similar'])) {
            $this->addRelations(self::RELATION_TABLE_MAP['similar'], $data['id'], $data['similar']);
        }

        return $this->insertOrUpdate(self::TABLE_NAME, [
            'id' => $data['id'],
            'image_id' => $imageId,
            'date_created' => $data['date_added'],
            'date_updated' => $data['date_last_updated'],
            'name' => $data['name'],
            'deck' => $data['deck'],
            'description' => (is_null($data['description'])) ? '' : $data['description'],
            'aliases' => $data['aliases'],
        ], ['id']);
    }
}

?>