<?php

require_once(__DIR__.'/../libs/resource.php');
require_once(__DIR__.'/../libs/common.php');
require_once(__DIR__.'/../libs/build_page_data.php');

class Location extends Resource
{
    use CommonVariablesAndMethods;
    use BuildPageData;

    const TYPE_ID = 3035;
    const RESOURCE_SINGULAR = "location";
    const RESOURCE_MULTIPLE = "locations";
    const PAGE_NAMESPACE = "Locations/";
    const TABLE_NAME = "wiki_location";
    const TABLE_FIELDS = ['id','name','mw_page_name','aliases','deck','mw_formatted_description','image_id','background_image_id'];
    const RELATION_TABLE_MAP = [
        "characters" =>  [
            "table" => "wiki_assoc_character_location", 
            "mainField" => "location_id", 
            "relationTable" => "wiki_character",
            "relationField" => "character_id", 
        ],
        "concepts" =>  [
            "table" => "wiki_assoc_concept_location", 
            "mainField" => "location_id", 
            "relationTable" => "wiki_concept",
            "relationField" => "concept_id"
        ],
        "objects" =>  [
            "table" => "wiki_assoc_location_thing", 
            "mainField" => "location_id", 
            "relationTable" => "wiki_thing",
            "relationField" => "thing_id"
        ],
        "locations" =>  [
            "table" => "wiki_assoc_location_similar", 
            "mainField" => "location_id", 
            "relationTable" => "wiki_location",
            "relationField" => "similar_location_id"
        ],
    ];
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
            'relations' => $relations
        ])
        .$this->getImageDiv([
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