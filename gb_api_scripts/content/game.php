<?php

require_once(__DIR__.'/../libs/resource.php');
require_once(__DIR__.'/../libs/common.php');
require_once(__DIR__.'/../libs/build_page_data.php');

class Game extends Resource
{
    use CommonVariablesAndMethods;
    use BuildPageData;

    const TYPE_ID = 3030;
    const RESOURCE_SINGULAR = "game";
    const RESOURCE_MULTIPLE = "games";
    const TABLE_NAME = "wiki_game";
    const TABLE_FIELDS = ['id','name','mw_page_name','aliases','deck','mw_formatted_description'];
    const RELATION_TABLE_MAP = [
        "characters" => [
            "table" => "wiki_assoc_game_character",
            "mainField" => "game_id", 
            "relationTable" => "wiki_character",
            "relationField" => "character_id"
        ],
        "concepts" => [
            "table" => "wiki_assoc_game_concept", 
            "mainField" => "game_id", 
            "relationTable" => "wiki_concept",
            "relationField" => "concept_id"
        ],
        "developers" => [
            "table" => "wiki_assoc_game_developer", 
            "mainField" => "game_id",  
            "relationTable" => "wiki_company",
            "relationField" => "company_id"
        ],
        "franchises" => [
            "table" => "wiki_assoc_game_franchise", 
            "mainField" => "game_id",  
            "relationTable" => "wiki_franchise",
            "relationField" => "franchise_id"
        ],
        "genres" => [
            "table" => "wiki_game_to_genre", 
            "mainField" => "game_id", 
            "relationTable" => "wiki_game_genre",
            "relationField" => "genre_id"
        ],
        "locations" => [
            "table" => "wiki_assoc_game_location", 
            "mainField" => "game_id", 
            "relationTable" => "wiki_location",
            "relationField" => "location_id"
        ],
        "objects" => [
            "table" => "wiki_assoc_game_thing", 
            "mainField" => "game_id", 
            "relationTable" => "wiki_thing",
            "relationField" => "thing_id"
        ],
        "people" => [
            "table" => "wiki_assoc_game_person", 
            "mainField" => "game_id",  
            "relationTable" => "wiki_person",
            "relationField" => "person_id"
        ],
        "platforms" => [
            "table" => "wiki_game_to_platform", 
            "mainField" => "game_id", 
            "relationTable" => "wiki_platform",
            "relationField" => "platform_id"
        ],
        "publishers" => [
            "table" => "wiki_assoc_game_publisher", 
            "mainField" => "game_id",  
            "relationTable" => "wiki_company",
            "relationField" => "company_id"
        ],
        "similar_games" => [
            "table" => "wiki_assoc_game_similar", 
            "mainField" => "game_id",  
            "relationTable" => "wiki_game",
            "relationField" => "similar_game_id"
        ],
        "themes" => [
            "table" => "wiki_game_to_theme", 
            "mainField" => "game_id",  
            "relationTable" => "wiki_game_theme",
            "relationField" => "theme_id"
        ],
    ];

    // Explains the stored release date
    const RELEASE_DATE_TYPE_NO_RELEASE = 0;
    const RELEASE_DATE_TYPE_FULL_DATE = 1;
    const RELEASE_DATE_TYPE_MONTH_YEAR = 2;
    const RELEASE_DATE_TYPE_QTR_YEAR = 3;
    const RELEASE_DATE_TYPE_ONLY_YEAR = 4;

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
     * release_date = original_release_date OR combo of expected_release_day + expected_release_month + expected_release_quarter + expected_release_year
     * release_date_type = depends on what values are filled from expected_* 
     * aliases = aliases
     * 
     * Fields returned in the game api rolled up from other tables and has no relationship table itself
     * 
     * original_game_rating
     * first_appeareance_characters
     * first_appeareance_concepts
     * first_appeareance_locations
     * first_appeareance_objets
     * first_appearaance_people
     * releases
     * 
     * @param array $data The api response array.
     * @param array &$crawl The relations returned by the API.
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

        $releaseDate = null;
        $releaseDateType = self::RELEASE_DATE_TYPE_NO_RELEASE;

        if (!empty($data['original_release_date'])) {
            $releaseDate = $data['original_release_date'];
            $releaseDateType = self::RELEASE_DATE_TYPE_FULL_DATE;
        }
        else if (!empty($data['expected_release_year'])) {
            if (!empty($data['expected_release_quarter'])) {
                $releaseDate = sprintf('%s-01-%s 00:00:00', $data['expected_release_quarter'], $data['expected_release_year']);
                $releaseDateType = self::RELEASE_DATE_TYPE_QTR_YEAR;
            }
            else if (!empty($data['expected_release_month'])) {
                $releaseDate = sprintf('%s-01-%s 00:00:00', $data['expected_release_month'], $data['expected_release_year']);
                $releaseDateType = self::RELEASE_DATE_TYPE_MONTH_YEAR;
            }
            else {
                $releaseDate = sprintf('01-01-%s 00:00:00', $data['expected_release_year']);
                $releaseDataType = self::RELEASE_DATE_TYPE_ONLY_YEAR;
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
            'release_date' => $releaseDate,
            'release_date_type' => $releaseDateType,
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

        // TODO: how to handle releases, dlcs, people
        $description = $this->formatSchematicData([
            'name' => $name,
            'guid' => $guid,
            'aliases' => $row->aliases,
            'deck' => $row->deck,
            'infobox_image' => $row->infobox_image,
            'background_image' => $row->background_image,
            'relations' => $relations
        ]).$desc;

        return [
            'title' => $row->mw_page_name,
            'namespace' => $this->namespaces['page'],
            'description' => $description
        ];
    }
}

?>