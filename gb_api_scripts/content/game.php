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
    const PAGE_NAMESPACE = "Games/";
    const TABLE_NAME = "wiki_game";
    const TABLE_FIELDS = ['id','name','mw_page_name','aliases','deck','mw_formatted_description','release_date','release_date_type','background_image_id', 'image_id'];
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
        "games" => [
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
        $releaseDateType = self::RELEASE_DATE_TYPE_USE_DATE;

        if (!empty($data['original_release_date'])) {
            $releaseDate = $data['original_release_date'];
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
                $releaseDateType = self::RELEASE_DATE_TYPE_ONLY_YEAR;
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
            'release_date' => $row->release_date,
            'release_date_type' => $row->release_date_type,
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

    /**
     * Converts release, dlcs and credits
     * 
     * @param stdClass $row
     * @return array
     */
    public function getSubPageDataArray(stdClass $row): array
    {
        $releaseDateTypeMap = [
            0 => 'Full',
            1 => 'Month',
            2 => 'Quarter',
            3 => 'Year',
            5 => 'None',
        ];

        $result = [];
        $credits = $this->getCreditsFromDB($row->id);

        if ($this->getDb()->hasResults($credits)) {
            $roleMap = [
                1 => 'Unclassified',
                2 => 'Voice Actor',
                3 => 'Thanks',
                4 => 'Production',
                5 => 'Visual Arts',
                6 => 'Programming',
                7 => 'Design',
                8 => 'Audio',
                9 => 'Business',
                10 => 'Quality Assurance'
            ];

            $description = <<<MARKUP
{{Credits
|ParentPage={$row->mw_page_name}
}}

MARKUP;
            foreach ($credits as $credit) {
                $department = (is_null($credit->role_id)) ? $roleMap[1] : $roleMap[$credit->role_id];
                $role = str_replace('&', ' and ', $credit->description);
                $role = str_replace('<', '&lt;', $role);
                $role = str_replace('>', '&gt;', $role);

                $description .= <<<MARKUP
{{CreditSubobject
|Game={$row->mw_page_name}
|Release=
|Dlc=
|Person={$credit->mw_page_name}
|Company=
|Department={$department}
|Role={$role}
}}

MARKUP;
            }

            $result[] = [
                'title' => $row->mw_page_name.'/Credits',
                'namespace' => $this->namespaces['page'],
                'description' => $description
            ];
        }

        $releases = $this->getReleasesFromDB($row->id);

        if ($this->getDb()->hasResults($releases)) {
            $regionMap = [
                1 => 'United States',
                2 => 'United Kingdom',
                6 => 'Japan',
                11 => 'Australia',
            ];

            $ratingsMap = [
                1 => 'Ratings/ESRB_T',
                2 => 'Ratings/PEGI_16',
                5 => 'Ratings/BBFC_15', 
                6 => 'Ratings/ESRB_E',  
                7 => 'Ratings/PEGI_3', 
                9 => 'Ratings/ESRB_K_A',
                12 => 'Ratings/OFLC_MA15',   
                13 => 'Ratings/OFLC_M15',   
                14 => 'Ratings/OFLC_G',  
                15 => 'Ratings/OFLC_G8',
                16 => 'Ratings/ESRB_M',  
                17 => 'Ratings/BBFC_18', 
                18 => 'Ratings/PEGI_7', 
                19 => 'Ratings/CERO_All_Ages',
                20 => 'Ratings/BBFC_PG', 
                21 => 'Ratings/BBFC_12', 
                23 => 'Ratings/ESRB_AO', 
                24 => 'Ratings/CERO_18',
                25 => 'Ratings/CERO_A',  
                26 => 'Ratings/ESRB_EC', 
                27 => 'Ratings/CERO_C',  
                28 => 'Ratings/CERO_15',
                29 => 'Ratings/ESRB_E10',   
                30 => 'Ratings/BBFC_U',  
                31 => 'Ratings/OFLC_M',  
                32 => 'Ratings/CERO_D',  
                33 => 'Ratings/CERO_B',  
                34 => 'Ratings/CERO_Z',  
                36 => 'Ratings/PEGI_12',
                37 => 'Ratings/PEGI_18',
                38 => 'Ratings/OFLC_PG', 
                39 => 'Ratings/OFLC_R18',
            ];

            $resolutionsMap = [
                5 => 'Resolutions/1080p',
                6 => 'Resolutions/1080i',
                7 => 'Resolutions/720p',
                8 => 'Resolutions/480p',
                9 => 'Resolutions/PC_CGA_320x200',
                10 => 'Resolutions/PC_EGA_640x350',
                11 => 'Resolutions/PC_VGA_640x480',
                12 => 'Resolutions/PC_WVGA_768x480',
                13 => 'Resolutions/PC_SVGA_800x600',
                14 => 'Resolutions/PC_1024x768',
                15 => 'Resolutions/PC_1440x900',
                16 => 'Resolutions/PC_1600x1200',
                17 => 'Resolutions/PC_2560x1440',
                18 => 'Resolutions/PC_2560x1600',
                19 => 'Resolutions/Other_PC_Resolution',
                20 => 'Resolutions/Other_Console_Resolution',
            ];

            $soundSystemsMap = [
                4 => 'Sound_Systems/Mono',
                5 => 'Sound_Systems/Stereo',
                6 => 'Sound_Systems/5.1',
                7 => 'Sound_Systems/7.1',
                8 => 'Sound_Systems/Dolby_Pro_Logic_II',
                9 => 'Sound_Systems/DTS',
            ];

            $productCodeTypeMap = [
                1 => 'EAN/13',
                2 => 'UPC/A',
                3 => 'ISBN-10',
            ];

            $companyCodeTypeMap = [
                1 => 'Nintendo Product ID',
                2 => 'Sony Company Code',
            ];

            $featuresMap = [
                8 => 'Single_Player_Features/Camera_support',
                9 => 'Single_Player_Features/Voice_control',
                10 => 'Single_Player_Features/Motion_control',
                11 => 'Single_Player_Features/Driving_wheel_native',
                12 => 'Single_Player_Features/Flightstick_native',
                13 => 'Single_Player_Features/PC_gamepad_native',
                14 => 'Single_Player_Features/Head_tracking_native',
                15 => 'Multiplayer_Features/Local_co_op',
                16 => 'Multiplayer_Features/LAN_co_op',
                17 => 'Multiplayer_Features/Online_co_op',
                18 => 'Multiplayer_Features/Local_competitive',
                19 => 'Multiplayer_Features/LAN_competitive',
                20 => 'Multiplayer_Features/Online_competitive',
                21 => 'Multiplayer_Features/Local_splitscreen',
                22 => 'Multiplayer_Features/Online_splitscreen',
                23 => 'Multiplayer_Features/Pass_and_play',
                24 => 'Multiplayer_Features/Voice_chat',
                25 => 'Multiplayer_Features/Asynchronous_multiplayer',
            ];

            $description = <<<MARKUP
{{Releases
|ParentPage={$row->mw_page_name}
}}

MARKUP;

            // hydrate release objects
            $releaseObjects = [];
            foreach ($releases as $release) {

                if (!isset($releaseObjects[$release->id])) {
                    $widescreenSupport = '';
                    if ($release->widescreen_support == 0) {
                        $widescreenSupport = 'No';
                    }
                    else if ($release->widescreen_support == 1) {
                        $widescreenSupport = 'Yes';
                    }

                    $productCodeType = '';
                    $productCode = $release->product_code;
                    if (!empty($release->product_code)) {
                        if (is_null($release->product_code_type)) {
                            if (preg_match('/^(\d[ -]?){12}\d$/', trim($productCode))) { // EAN/13: 13 digits
                                $productCodeType = $productCodeTypeMap[1];
                            }
                            else if (preg_match('/^(\d[ -]?){11}\d$/', trim($productCode))) { // UPC/A: 12 digits
                                $productCodeType = $productCodeTypeMap[2];
                            }
                            else if (preg_match('/^(?:\d-?){9}[\dX]$/', trim($productCode))) { // ISBN-10: 10 digits
                                $productCodeType = $productCodeTypeMap[3];
                            }
                        }
                        else {
                            $productCodeType = $productCodeTypeMap[$release->product_code_type];
                        }
                    }

                    $releaseDateType = $release->release_date_type;
                    $releaseDate = $release->release_date;
                    if (empty($releaseDate) || $releaseDate = '0000-00-00') {
                        $releaseDate = '';
                        $releaseDateType = $releaseDateTypeMap[5];
                    }
                    else {
                        if (is_null($releaseDateType)) {
                            $releaseDateType = $releaseDateTypeMap[0];
                        }
                        else {
                            $releaseDateType = $releaseDateTypeMap[$releaseDateType];
                        }
                    }

                    $imageName = $row->image_id;
                    if (!empty($imageName)) {
                        $imageName = $this->getDb()->getImageName($row->image_id);
                        if (!empty($imageName)) {
                            $imageName = str_replace('%20', ' ', $imageName);
                            $imageName = str_replace('&', '%26', $imageName);
                        }
                    }

                    if (!empty($row->company_code)) {
                        $companyCode = str_replace('&', '&amp;', $row->company_code);
                    }
                    else {
                        $companyCode = '';
                    }
    
                    $releaseObjects[$release->id] = [
                        'Game' => $row->mw_page_name,
                        'Name' => htmlspecialchars($release->name, ENT_XML1, 'UTF-8'),
                        'Image' => $imageName,
                        'Region' => empty($release->region_id) ? '' : $regionMap[$release->region_id],
                        'Platform' => $release->platform,
                        'Rating' => empty($release->rating_id) ? '' : $ratingsMap[$release->rating_id],
                        'Developers' => empty($release->developer) ? [] : [$release->developer => 0],
                        'Publishers' => empty($release->publisher) ? [] : [$release->publisher => 0],
                        'ReleaseDate' => $releaseDate,
                        'ReleaseDateType' => $releaseDateType,
                        'ProductCode' => $productCode,
                        'ProductCodeType' => $productCodeType,
                        'CompanyCode' => $companyCode,
                        'CompanyCodeType' => empty($release->company_code_type) ? '' : $companyCodeTypeMap[$release->company_code_type],
                        'WidescreenSupport' => $widescreenSupport,
                        'Resolutions' => empty($release->resolution_id) ? [] : [$resolutionsMap[$release->resolution_id] => 0],
                        'SoundSystems' => empty($release->soundsystem_id) ? [] : [$soundSystemsMap[$release->soundsystem_id] => 0],
                        'SinglePlayerFeatures' => (empty($release->sp_feature_id) || ($release->sp_feature_id < 8 || $release->sp_feature_id > 14)) ? [] : [$featuresMap[$release->sp_feature_id] => 0],
                        'MultiplayerFeatures' => (empty($release->mp_feature_id) || ($release->mp_feature_id < 15 || $release->mp_feature_id > 25)) ? [] : [$featuresMap[$release->mp_feature_id] => 0],
                        'MinimumPlayers' => $release->minimum_players,
                        'MaximumPlayers' => $release->maximum_players,
                    ];
                }
                else {
                    if (!empty($release->developer)) {
                        $releaseObjects[$release->id]['Developers'][$release->developer] = 0;
                    }

                    if (!empty($release->publisher)) {
                        $releaseObjects[$release->id]['Publishers'][$release->publisher] = 0;
                    }

                    if (!empty($release->resolution_id)) {
                        $releaseObjects[$release->id]['Resolutions'][$resolutionsMap[$release->resolution_id]] = 0;
                    }

                    if (!empty($release->soundsystem_id)) {
                        $releaseObjects[$release->id]['SoundSystems'][$soundSystemsMap[$release->soundsystem_id]] = 0;
                    }

                    if (!empty($release->sp_feature_id) && ($release->sp_feature_id > 7 && $release->sp_feature_id < 15)) {
                        $releaseObjects[$release->id]['SinglePlayerFeatures'][$featuresMap[$release->sp_feature_id]] = 0;
                    }

                    if (!empty($release->mp_feature_id) && ($release->mp_feature_id > 14 && $release->mp_feature_id < 26)) {
                        $releaseObjects[$release->id]['MultiplayerFeatures'][$featuresMap[$release->mp_feature_id]] = 0;
                    }                    
                }
            }

            // convert db release objects into mediawiki release objects
            foreach ($releaseObjects as $releaseId => $obj) {
                $developers = empty($obj['Developers']) ? '' : implode(',', array_keys($obj['Developers']));
                $publishers = empty($obj['Publishers']) ? '' : implode(',', array_keys($obj['Publishers']));
                $resolutions = empty($obj['Resolutions']) ? '' : implode(',', array_keys($obj['Resolutions']));
                $soundSystems = empty($obj['SoundSystems']) ? '' : implode(',', array_keys($obj['SoundSystems']));
                $spFeatures = empty($obj['SinglePlayerFeatures']) ? '' : implode(',', array_keys($obj['SinglePlayerFeatures']));
                $mpFeatures = empty($obj['MultiplayerFeatures']) ? '' : implode(',', array_keys($obj['MultiplayerFeatures']));

                $description .= <<<MARKUP
{{ReleaseSubobject
|Game={$obj['Game']}
|Guid=3050-{$releaseId}
|Name={$obj['Name']}
|Image={$obj['Image']}
|Region={$obj['Region']}
|Platform={$obj['Platform']}
|Rating={$obj['Rating']}
|Developers={$developers}
|Publishers={$publishers}
|ReleaseDate={$obj['ReleaseDate']}
|ReleaseDateType={$obj['ReleaseDateType']}
|ProductCode={$obj['ProductCode']}
|ProductCodeType={$obj['ProductCodeType']}
|CompanyCode={$obj['CompanyCode']}
|CompanyCodeType={$obj['CompanyCodeType']}
|WidescreenSupport={$obj['WidescreenSupport']}
|Resolutions={$resolutions}
|SoundSystems={$soundSystems}
|SinglePlayerFeatures={$spFeatures}
|MultiplayerFeatures={$mpFeatures}
|MinimumPlayers={$obj['MinimumPlayers']}
|MaximumPlayers={$obj['MaximumPlayers']}
}}

MARKUP;
            }

            $result[] = [
                'title' => $row->mw_page_name.'/Releases',
                'namespace' => $this->namespaces['page'],
                'description' => $description
            ];
        }


        $description = <<<MARKUP
        {{DLC
        |ParentPage={$row->mw_page_name}
        }}

MARKUP;
        $dlcs = $this->getDLCFromDB($row->id);

        if ($this->getDb()->hasResults($dlcs)) {
            // hydrate dlc objects
            $dlcObjects = [];
            foreach ($dlcs as $dlc) {

                if (!isset($dlcObjects[$dlc->id])) {

                    $releaseDateType = $dlc->release_date_type;
                    $releaseDate = $dlc->release_date;
                    if (empty($releaseDate) || $releaseDate = '0000-00-00') {
                        $releaseDate = '';
                        $releaseDateType = $releaseDateTypeMap[5];
                    }
                    else {
                        if (is_null($releaseDateType)) {
                            $releaseDateType = $releaseDateTypeMap[0];
                        }
                        else {
                            $releaseDateType = $releaseDateTypeMap[$releaseDateType];
                        }
                    }

                    $imageName = $dlc->image_id;
                    if (!empty($imageName)) {
                        $imageName = $this->getDb()->getImageName($dlc->image_id);
                        if (!empty($imageName)) {
                            $imageName = str_replace('%20', ' ', $imageName);
                            $imageName = str_replace('&', '%26', $imageName);
                        }
                    }

                    $dlcObjects[$dlc->id] = [
                        'Game' => $row->mw_page_name,
                        'Name' => htmlspecialchars($dlc->name, ENT_XML1, 'UTF-8'),
                        'Deck' => empty($dlc->deck) ? '' : htmlspecialchars($dlc->deck, ENT_XML1, 'UTF-8'),
                        'LaunchPrice' => $dlc->launch_price,
                        'Image' => $imageName,
                        'Platform' => $dlc->platform,
                        'Developers' => empty($dlc->developer) ? [] : [$dlc->developer => 0],
                        'Publishers' => empty($dlc->publisher) ? [] : [$dlc->publisher => 0],
                        'DlcTypes' => empty($dlc->dlc_type) ? [] : [$dlc->dlc_type => 0],
                        'ReleaseDate' => $releaseDate,
                        'ReleaseDateType' => $releaseDateType,
                    ];
                }
                else {
                    if (!empty($dlc->developer)) {
                        $dlcObjects[$dlc->id]['Developers'][$dlc->developer] = 0;
                    }

                    if (!empty($dlc->publisher)) {
                        $dlcObjects[$dlc->id]['Publishers'][$dlc->publisher] = 0;
                    }

                    if (!empty($dlc->dlc_type)) {
                        $dlcObjects[$dlc->id]['DlcTypes'][$dlc->dlc_type] = 0;
                    }
                }
            }

            // convert db dlc objects into mediawiki dlc objects
            foreach ($dlcObjects as $dlcId => $obj) {
                $developers = empty($obj['Developers']) ? '' : implode(',', array_keys($obj['Developers']));
                $publishers = empty($obj['Publishers']) ? '' : implode(',', array_keys($obj['Publishers']));
                $dlcTypes = empty($obj['DlcTypes']) ? '' : implode(',', array_keys($obj['DlcTypes']));

                $description .= <<<MARKUP
{{DlcSubobject
|Game={$obj['Game']}
|Guid=3020-{$dlcId}
|Name={$obj['Name']}
|Image={$obj['Image']}
|Deck={$obj['Deck']}
|LaunchPrice={$obj['LaunchPrice']}
|Platform={$obj['Platform']}
|Developers={$developers}
|Publishers={$publishers}
|ReleaseDate={$obj['ReleaseDate']}
|ReleaseDateType={$obj['ReleaseDateType']}
|DlcTypes={$dlcTypes}
}}

MARKUP;
            }

            $result[] = [
                'title' => $row->mw_page_name.'/DLC',
                'namespace' => $this->namespaces['page'],
                'description' => $description
            ];
        }

        return $result;
    }
}

?>