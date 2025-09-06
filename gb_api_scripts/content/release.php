<?php

require_once(__DIR__.'/../libs/resource.php');

class Release extends Resource
{
    const TYPE_ID = 3050;
    const RESOURCE_SINGULAR = "release";
    const RESOURCE_MULTIPLE = "releases";
    const TABLE_NAME = "wiki_game_release";
    const RELATION_TABLE_MAP = [
        "developers" => ["table" => "wiki_game_release_to_developer", "mainField" => "release_id", "relationField" => "company_id"],
        "publishers" => ["table" => "wiki_game_release_to_publisher", "mainField" => "release_id", "relationField" => "company_id"],
        "resolutions" => ["table" => "wiki_game_release_to_resolution", "mainField" => "release_id", "relationField" => "resolution_id"],
        "sound_sytems" => ["table" => "wiki_game_release_to_sound_system", "mainField" => "release_id", "relationField" => "soundsystem_id"],
        "multiPlayerFeatures" => ["table" => "wiki_game_release_to_multiplayer_feature", "mainField" => "release_id", "relationField" => "feature_id"],
    ];
    const RELATION_SUB_TABLE_MAP = [
        "resolutions" => ["table" => "wiki_game_release_resolution"],
        "sound_sytems" => ["table" => "wiki_game_release_sound_system"],
        "multiPlayerFeatures" => ["table" => "wiki_game_release_feature"],
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
     * release_date = release_date OR combo of expected_release_day + expected_release_month + expected_release_quarter + expected_release_year
     * release_date_type = depends on what values are filled from expected_* 
     * game_id = game->id
     * rating_id = game_rating->id
     * maximum_players = maximum_players
     * minimum_players = minimum_players
     * platform_id = platform->id
     * product_code_type = product_code_type
     * product_code = product_code_value
     * region_id = region->id
     * widescreen_support = widescreen_support
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

                // fill in the other side's table in the relationship
                if (in_array($relation, array_keys(self::RELATION_SUB_TABLE_MAP))) {
                    foreach ($data[$relation] as &$entry) {
                        $primaryKeys = ['id'];
                        if ($relation == 'resolutions') {
                            $setData = [
                                'id' => $entry['id'],
                                'short_name' => $entry['name']
                            ];
                        }
                        else if ($relation == 'sound_systems') {
                            $setData = [
                                'id' => $entry['id'],
                                'name' => $entry['name']
                            ];
                        }
                        // this fieldset is missing an ID so we insert into its table first to 
                        //      get its last insert id and add it to the fieldset
                        else if ($relation == 'multiPlayerFeatures') {
                            $setData = [
                                'name' => $entry['multiPlayerFeature'],
                            ];
                            $primaryKeys = ['name'];
                        }

                        $lastInsertId = $this->insertOrUpdate(self::RELATION_SUB_TABLE_MAP[$relation]['table'], $setData, $primaryKeys);

                        if ($relation == 'multiPlayerFeatures') {
                            $entry['id'] = $lastInsertId;
                        }
                    }
                }

                $this->addRelations(self::RELATION_TABLE_MAP[$relation], $data['id'], $data[$relation], $crawl);
            }
        }

        $releaseDate = null;
        $releaseDateType = self::RELEASE_DATE_TYPE_NO_RELEASE;

        if (!empty($data['release_date'])) {
            $releaseDate = $data['release_date'];
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
            'release_date' => $releaseDate,
            'release_date_type' => $releaseDateType,
            'game_id' => (isset($data['game'])) ? $data['game']['id'] : null,
            'rating_id' => (isset($data['game_rating'])) ? $data['game_rating']['id'] : null,
            'minimum_players' => $data['minimum_players'],
            'maximum_players' => $data['maximum_players'],
            'platform_id' => (isset($data['platform'])) ? $data['platform']['id'] : null,
            'product_code_type' => (empty($data['product_code_type'])) ? null : $data['product_code_type'],
            'product_code' => $data['product_code_value'],
            'region_id' => (isset($data['region'])) ? $data['region']['id'] : null,
            'widescreen_support' => $data['widescreen_support'],
        ], ['id']);
    }
}

?>