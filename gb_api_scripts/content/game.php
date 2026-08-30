<?php

require_once __DIR__ . "/../libs/resource.php";
require_once __DIR__ . "/../libs/common.php";
require_once __DIR__ . "/../libs/build_page_data.php";
require_once __DIR__ . "/../libs/subpage_builder.php";

class Game extends Resource
{
    use CommonVariablesAndMethods;
    use BuildPageData;

    const TYPE_ID = 3030;
    const RESOURCE_SINGULAR = "game";
    const RESOURCE_MULTIPLE = "games";
    const PAGE_NAMESPACE = "Games/";
    const TABLE_NAME = "wiki_game";
    const TABLE_FIELDS = [
        "id",
        "name",
        "mw_page_name",
        "aliases",
        "deck",
        "mw_formatted_description",
        "release_date",
        "release_date_type",
        "background_image_id",
        "image_id",
    ];
    const RELATION_TABLE_MAP = [
        "characters" => [
            "table" => "wiki_assoc_game_character",
            "mainField" => "game_id",
            "relationTable" => "wiki_character",
            "relationField" => "character_id",
        ],
        "concepts" => [
            "table" => "wiki_assoc_game_concept",
            "mainField" => "game_id",
            "relationTable" => "wiki_concept",
            "relationField" => "concept_id",
        ],
        "developers" => [
            "table" => "wiki_assoc_game_developer",
            "mainField" => "game_id",
            "relationTable" => "wiki_company",
            "relationField" => "company_id",
        ],
        "franchises" => [
            "table" => "wiki_assoc_game_franchise",
            "mainField" => "game_id",
            "relationTable" => "wiki_franchise",
            "relationField" => "franchise_id",
        ],
        "genres" => [
            "table" => "wiki_game_to_genre",
            "mainField" => "game_id",
            "relationTable" => "wiki_game_genre",
            "relationField" => "genre_id",
        ],
        "locations" => [
            "table" => "wiki_assoc_game_location",
            "mainField" => "game_id",
            "relationTable" => "wiki_location",
            "relationField" => "location_id",
        ],
        "objects" => [
            "table" => "wiki_assoc_game_thing",
            "mainField" => "game_id",
            "relationTable" => "wiki_thing",
            "relationField" => "thing_id",
        ],
        "platforms" => [
            "table" => "wiki_game_to_platform",
            "mainField" => "game_id",
            "relationTable" => "wiki_platform",
            "relationField" => "platform_id",
        ],
        "publishers" => [
            "table" => "wiki_assoc_game_publisher",
            "mainField" => "game_id",
            "relationTable" => "wiki_company",
            "relationField" => "company_id",
        ],
        "games" => [
            "table" => "wiki_assoc_game_similar",
            "mainField" => "game_id",
            "relationTable" => "wiki_game",
            "relationField" => "similar_game_id",
        ],
        "themes" => [
            "table" => "wiki_game_to_theme",
            "mainField" => "game_id",
            "relationTable" => "wiki_game_theme",
            "relationField" => "theme_id",
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
        $imageId = $this->insertOrUpdate(
            "image",
            [
                "assoc_type_id" => self::TYPE_ID,
                "assoc_id" => $data["id"],
                "image" => $data["image"]["original_url"],
            ],
            ["assoc_type_id", "assoc_id", "image"],
        );

        // save the wiki type relationships in their respective relationship table
        //  these are only available when hitting the singular endpoint
        $keys = array_keys(self::RELATION_TABLE_MAP);
        foreach ($keys as $relation) {
            if (!empty($data[$relation])) {
                $this->addRelations(
                    self::RELATION_TABLE_MAP[$relation],
                    $data["id"],
                    $data[$relation],
                    $crawl,
                );
            }
        }

        $releaseDate = null;
        $releaseDateType = self::RELEASE_DATE_TYPE_USE_DATE;

        if (!empty($data["original_release_date"])) {
            $releaseDate = $data["original_release_date"];
        } elseif (!empty($data["expected_release_year"])) {
            if (!empty($data["expected_release_quarter"])) {
                $releaseDate = sprintf(
                    "%s-01-%s 00:00:00",
                    $data["expected_release_quarter"],
                    $data["expected_release_year"],
                );
                $releaseDateType = self::RELEASE_DATE_TYPE_QTR_YEAR;
            } elseif (!empty($data["expected_release_month"])) {
                $releaseDate = sprintf(
                    "%s-01-%s 00:00:00",
                    $data["expected_release_month"],
                    $data["expected_release_year"],
                );
                $releaseDateType = self::RELEASE_DATE_TYPE_MONTH_YEAR;
            } else {
                $releaseDate = sprintf(
                    "01-01-%s 00:00:00",
                    $data["expected_release_year"],
                );
                $releaseDateType = self::RELEASE_DATE_TYPE_ONLY_YEAR;
            }
        }

        return $this->insertOrUpdate(
            self::TABLE_NAME,
            [
                "id" => $data["id"],
                "image_id" => $imageId,
                "date_created" => $data["date_added"],
                "date_updated" => $data["date_last_updated"],
                "name" => is_null($data["name"]) ? "" : $data["name"],
                "deck" => $data["deck"],
                "description" => is_null($data["description"])
                    ? ""
                    : $data["description"],
                "aliases" => $data["aliases"],
                "release_date" => $releaseDate,
                "release_date_type" => $releaseDateType,
            ],
            ["id"],
        );
    }

    /**
     * Converts result row into page data array of ['title', 'namespace', 'description']
     *
     * @param stdClass $row
     * @return array
     */
    public function getPageDataArray(stdClass $row): array
    {
        $name = htmlspecialchars($row->name, ENT_XML1, "UTF-8");
        $guid = self::TYPE_ID . "-" . $row->id;
        if (empty($row->mw_formatted_description)) {
            $desc = !empty($row->deck)
                ? htmlspecialchars($row->deck, ENT_XML1, "UTF-8")
                : "";
        } else {
            $desc = htmlspecialchars(
                $row->mw_formatted_description,
                ENT_XML1,
                "UTF-8",
            );
        }
        $relations = $this->getRelationsFromDB($row->id);

        $description =
            $this->formatSchematicData([
                "name" => $name,
                "guid" => $guid,
                "aliases" => $row->aliases,
                "deck" => $row->deck,
                "release_date" => $row->release_date,
                "release_date_type" => $row->release_date_type,
                "relations" => $relations,
            ]) .
            $this->getImageDiv([
                "infobox_image_id" => $row->image_id,
                "background_image_id" => $row->background_image_id,
            ]) .
            $desc;

        return [
            "title" => $row->mw_page_name,
            "namespace" => $this->namespaces["page"],
            "description" => $description,
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
        $result = [];
        $credits = $this->getCreditsFromDB($row->id);

        if ($this->getDb()->hasResults($credits)) {
            $roleMap = [
                1 => "Unclassified",
                2 => "Voice Actor",
                3 => "Thanks",
                4 => "Production",
                5 => "Visual Arts",
                6 => "Programming",
                7 => "Design",
                8 => "Audio",
                9 => "Business",
                10 => "Quality Assurance",
            ];

            $description = <<<MARKUP
            {{Credits
            |ParentPage={$row->mw_page_name}
            }}

            MARKUP;
            foreach ($credits as $credit) {
                $department = is_null($credit->role_id)
                    ? $roleMap[1]
                    : $roleMap[$credit->role_id];
                $role = str_replace("&", " and ", $credit->description);
                $role = str_replace("<", "&lt;", $role);
                $role = str_replace(">", "&gt;", $role);

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
                "title" => $row->mw_page_name . "/Credits",
                "namespace" => $this->namespaces["page"],
                "description" => $description,
            ];
        }

        $releases = $this->getReleasesFromDB($row->id);

        if ($this->getDb()->hasResults($releases)) {
            $releaseObjects = [];
            foreach ($releases as $release) {
                if (!isset($releaseObjects[$release->id])) {
                    // was $row->image_id -> every release got the game cover
                    $imageUrl = "";
                    if (!empty($release->image_id)) {
                        $imageUrl = (string) $this->getDb()->getImageName(
                            $release->image_id,
                        );
                    }
                    if ($imageUrl === "" && !empty($row->image_id)) {
                        $imageUrl = (string) $this->getDb()->getImageName(
                            $row->image_id,
                        );
                    }
                    $imageUrl = str_replace(
                        [" ", "&"],
                        ["%20", "%26"],
                        $imageUrl,
                    );

                    // was $row->company_code (no such column) -> always empty
                    $companyCode = (string) $release->company_code;

                    $releaseObjects[$release->id] = array_merge(
                        [
                            "Guid" => "3050-" . $release->id,
                            "Name" => (string) $release->name,
                            "Image" => $imageUrl,
                            "Region" =>
                                SubpageBuilder::REGIONS[$release->region_id] ??
                                "",
                            "Platform" => (string) $release->platform,
                            "Rating" =>
                                SubpageBuilder::RATINGS[$release->rating_id] ??
                                "",
                            "Developers" => [],
                            "Publishers" => [],
                            "ProductCode" => (string) $release->product_code,
                            "ProductCodeType" => SubpageBuilder::guessProductCodeType(
                                $release->product_code,
                                $release->product_code_type,
                            ),
                            "CompanyCode" => $companyCode,
                            "CompanyCodeType" =>
                                $companyCode === ""
                                    ? ""
                                    : SubpageBuilder::COMPANY_CODE_TYPES[
                                            $release->company_code_type
                                        ] ?? "",
                            "WidescreenSupport" => SubpageBuilder::widescreenLabel(
                                $release->widescreen_support,
                            ),
                            "Resolutions" => [],
                            "SoundSystems" => [],
                            "SinglePlayerFeatures" => [],
                            "MultiplayerFeatures" => [],
                            "MinimumPlayers" => $release->minimum_players,
                            "MaximumPlayers" => $release->maximum_players,
                        ],
                        SubpageBuilder::dateParams(
                            $release->release_date,
                            $release->release_date_type,
                            $release->id,
                        ),
                    );
                }

                $obj = &$releaseObjects[$release->id];
                if (!empty($release->developer)) {
                    $obj["Developers"][$release->developer] = true;
                }
                if (!empty($release->publisher)) {
                    $obj["Publishers"][$release->publisher] = true;
                }
                if (!empty($release->resolution_id)) {
                    $res =
                        SubpageBuilder::RESOLUTIONS[$release->resolution_id] ??
                        null;
                    if ($res) {
                        $obj["Resolutions"][$res] = true;
                    }
                }
                if (!empty($release->soundsystem_id)) {
                    $ss =
                        SubpageBuilder::SOUND_SYSTEMS[
                            $release->soundsystem_id
                        ] ?? null;
                    if ($ss) {
                        $obj["SoundSystems"][$ss] = true;
                    }
                }
                if (
                    !empty($release->sp_feature_id) &&
                    $release->sp_feature_id >= 8 &&
                    $release->sp_feature_id <= 14
                ) {
                    $obj["SinglePlayerFeatures"][
                        SubpageBuilder::FEATURES[$release->sp_feature_id]
                    ] = true;
                }
                if (
                    !empty($release->mp_feature_id) &&
                    $release->mp_feature_id >= 15 &&
                    $release->mp_feature_id <= 25
                ) {
                    $obj["MultiplayerFeatures"][
                        SubpageBuilder::FEATURES[$release->mp_feature_id]
                    ] = true;
                }
                unset($obj);
            }

            // set-arrays -> lists for the renderer
            foreach ($releaseObjects as &$obj) {
                foreach (
                    [
                        "Developers",
                        "Publishers",
                        "Resolutions",
                        "SoundSystems",
                        "SinglePlayerFeatures",
                        "MultiplayerFeatures",
                    ]
                    as $k
                ) {
                    $obj[$k] = array_keys($obj[$k]);
                }
            }
            unset($obj);

            $result[] = [
                "title" => $row->mw_page_name . "/Releases",
                "namespace" => $this->namespaces["page"],
                "description" => SubpageBuilder::renderReleasesPage(
                    $row->mw_page_name,
                    $releaseObjects,
                    SubpageBuilder::ESCAPE_XML,
                ),
            ];
        }

        $dlcs = $this->getDLCFromDB($row->id);

        if ($this->getDb()->hasResults($dlcs)) {
            $dlcObjects = [];
            foreach ($dlcs as $dlc) {
                if (!isset($dlcObjects[$dlc->id])) {
                    $imageUrl = "";
                    if (!empty($dlc->image_id)) {
                        $imageUrl = (string) $this->getDb()->getImageName(
                            $dlc->image_id,
                        );
                    }
                    $imageUrl = str_replace(
                        [" ", "&"],
                        ["%20", "%26"],
                        $imageUrl,
                    );

                    $dlcObjects[$dlc->id] = array_merge(
                        [
                            "Guid" => "3020-" . $dlc->id,
                            "Name" => (string) $dlc->name,
                            "Image" => $imageUrl,
                            "Deck" => (string) $dlc->deck,
                            "LaunchPrice" => (string) $dlc->launch_price,
                            "Platform" => (string) $dlc->platform,
                            "Developers" => [],
                            "Publishers" => [],
                            "DlcTypes" => [],
                        ],
                        SubpageBuilder::dateParams(
                            $dlc->release_date,
                            $dlc->release_date_type,
                            $dlc->id,
                        ),
                    );
                }

                $obj = &$dlcObjects[$dlc->id];
                if (!empty($dlc->developer)) {
                    $obj["Developers"][$dlc->developer] = true;
                }
                if (!empty($dlc->publisher)) {
                    $obj["Publishers"][$dlc->publisher] = true;
                }
                if (!empty($dlc->dlc_type)) {
                    $obj["DlcTypes"][$dlc->dlc_type] = true;
                }
                unset($obj);
            }

            foreach ($dlcObjects as &$obj) {
                foreach (["Developers", "Publishers", "DlcTypes"] as $k) {
                    $obj[$k] = array_keys($obj[$k]);
                }
            }
            unset($obj);

            $result[] = [
                "title" => $row->mw_page_name . "/DLC",
                "namespace" => $this->namespaces["page"],
                "description" => SubpageBuilder::renderDlcPage(
                    $row->mw_page_name,
                    $dlcObjects,
                    SubpageBuilder::ESCAPE_XML,
                ),
            ];
        }

        return $result;
    }
}

?>
