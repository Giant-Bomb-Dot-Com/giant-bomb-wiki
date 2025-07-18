<?php

require_once(__DIR__.'/resource.php');

class Company extends Resource
{
    const TYPE_ID = 3010;
    const RESOURCE_SINGULAR = "company";
    const RESOURCE_MULTIPLE = "companies";
    const TABLE_NAME = "wiki_company";
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
        if (!empty($data['games'])) {
            $this->addRelations(self::RELATION_TABLE_MAP['developed_games'], $data['id'], $data['developed_games']);
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
        if (!empty($data['games'])) {
            $this->addRelations(self::RELATION_TABLE_MAP['published_games'], $data['id'], $data['developed_games']);
        }

        return $this->insertOrUpdate(self::TABLE_NAME, [
            'id' => $data['id'],
            'image_id' => $imageId,
            'date_created' => $data['date_added'],
            'date_updated' => $data['date_last_updated'],
            'name' => $data['name'],
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
}

?>