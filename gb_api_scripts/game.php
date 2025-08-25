<?php

require_once(__DIR__.'/resource.php');
require_once(__DIR__.'/common.php');
require_once(__DIR__.'/build_page_data.php');

use Wikimedia\Rdbms\MysqliResultWrapper;

class Game extends Resource
{
    use BuildPageData;

    const TYPE_ID = 3030;
    const RESOURCE_SINGULAR = "game";
    const RESOURCE_MULTIPLE = "games";
    const TABLE_NAME = "wiki_game";
    const TABLE_FIELDS = ['id','name','mw_page_name','aliases','deck','mw_formatted_description'];
    const RELATION_TABLE_MAP = [
        "characters" => ["table" => "wiki_assoc_game_character", "mainField" => "game_id", "relationField" => "character_id"],
        "concepts" => ["table" => "wiki_assoc_game_concept", "mainField" => "game_id", "relationField" => "concept_id"],
        "developers" => ["table" => "wiki_assoc_game_developer", "mainField" => "game_id", "relationField" => "company_id"],
        "franchises" => ["table" => "wiki_assoc_game_franchise", "mainField" => "game_id", "relationField" => "franchise_id"],
        "genres" => ["table" => "wiki_game_to_genre", "mainField" => "game_id", "relationField" => "genre_id"],
        "locations" => ["table" => "wiki_assoc_game_location", "mainField" => "game_id", "relationField" => "location_id"],
        "objects" => ["table" => "wiki_assoc_game_thing", "mainField" => "game_id", "relationField" => "thing_id"],
        "people" => ["table" => "wiki_assoc_game_person", "mainField" => "game_id", "relationField" => "person_id"],
        "platforms" => ["table" => "wiki_game_to_platform", "mainField" => "game_id", "relationField" => "platform_id"],
        "publishers" => ["table" => "wiki_assoc_game_publisher", "mainField" => "game_id", "relationField" => "company_id"],
        "similar_games" => ["table" => "wiki_assoc_game_similar", "mainField" => "game_id", "relationField" => "similar_game_id"],
        "themes" => ["table" => "wiki_game_to_theme", "mainField" => "game_id", "relationField" => "theme_id"],
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
{{Game
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