<?php

require_once(__DIR__.'/resource.php');

class Character extends Resource
{
    const TYPE_ID = 3005;
    const RESOURCE_SINGULAR = "character";
    const RESOURCE_MULTIPLE = "characters";
    const TABLE_NAME = "wiki_character";
    const RELATION_TABLE_MAP = [
        "concepts" => ["table" => "wiki_assoc_character_concept", "mainField" => "character_id", "relationField" => "concept_id"],
        "enemies" =>  ["table" => "wiki_assoc_character_enemy", "mainField" => "character_id", "relationField" => "enemy_character_id"],
        "franchises" =>  ["table" => "wiki_assoc_character_franchise", "mainField" => "character_id", "relationField" => "franchise_id"],
        "friends" =>  ["table" => "wiki_assoc_character_friend", "mainField" => "character_id", "relationField" => "friend_character_id"],
        "games" =>  ["table" => "wiki_assoc_game_character", "mainField" => "character_id", "relationField" => "game_id"],
        "locations" =>  ["table" => "wiki_assoc_character_location", "mainField" => "character_id", "relationField" => "location_id"],
        "people" =>  ["table" => "wiki_assoc_character_person", "mainField" => "character_id", "relationField" => "person_id"],
        "objects" =>  ["table" => "wiki_assoc_character_thing", "mainField" => "character_id", "relationField" => "thing_id"],
    ];

    /**
     * Matching character table fields to api response fields
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
    public function process(array $data): int
    {
        var_dump($data);
        // save the image relation first to get its id
        $imageId = $this->insertOrUpdate("image", [
            'assoc_type_id' => self::TYPE_ID,
            'assoc_id' => $data['id'],
            'image' => $data['image']['original_url'],
        ], ['assoc_type_id', 'assoc_id', 'image']);

        // save the wiki type relationships in their respective relationship table
        if (!empty($data['concepts'])) {
            $this->addRelations(self::RELATION_TABLE_MAP['concepts'], $data['id'], $data['concepts']);
        }
        if (!empty($data['enemies'])) {
            $this->addRelations(self::RELATION_TABLE_MAP['enemies'], $data['id'], $data['enemies']);
        }
        if (!empty($data['franchises'])) {
            $this->addRelations(self::RELATION_TABLE_MAP['franchises'], $data['id'], $data['franchises']);
        }
        if (!empty($data['friends'])) {
            $this->addRelations(self::RELATION_TABLE_MAP['friends'], $data['id'], $data['friends']);
        }
        if (!empty($data['games'])) {
            $this->addRelations(self::RELATION_TABLE_MAP['games'], $data['id'], $data['games']);
        }
        if (!empty($data['locations'])) {
            $this->addRelations(self::RELATION_TABLE_MAP['locations'], $data['id'], $data['locations']);
        }
        if (!empty($data['objects'])) {
            $this->addRelations(self::RELATION_TABLE_MAP['objects'], $data['id'], $data['objects']);
        }
        if (!empty($data['people'])) {
            $this->addRelations(self::RELATION_TABLE_MAP['people'], $data['id'], $data['people']);
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
            'name' => $data['name'],
            'deck' => $data['deck'],
            'description' => (is_null($data['description'])) ? '' : $data['description'],
        ], ['id']);
    }
}

?>