<?php

require_once(__DIR__.'/resource.php');
require_once(__DIR__.'/common.php');
require_once(__DIR__.'/build_page_data.php');

class Character extends Resource
{
    use CommonVariablesAndMethods;
    use BuildPageData;

    const TYPE_ID = 3005;
    const RESOURCE_SINGULAR = "character";
    const RESOURCE_MULTIPLE = "characters";
    const TABLE_NAME = "wiki_character";
    const TABLE_FIELDS = ['id','name','mw_page_name','aliases','real_name','gender','birthday','deck','mw_formatted_description','death'];
    const RELATION_TABLE_MAP = [
        "concepts" => [
            "table" => "wiki_assoc_character_concept", 
            "mainField" => "character_id", 
            "relationField" => "concept_id", 
            "relationTable" => "wiki_concept",
            "relationName" => "concept_name",
            "relationPageName" => "concept_page_name"
        ],
        "enemies" =>  [
            "table" => "wiki_assoc_character_enemy", 
            "mainField" => "character_id", 
            "relationField" => "enemy_character_id", 
            "relationTable" => "wiki_character",
            "relationName" => "enemy_name",
            "relationPageName" => "enemy_page_name"
        ],
        "franchises" =>  [
            "table" => "wiki_assoc_character_franchise", 
            "mainField" => "character_id", 
            "relationField" => "franchise_id", 
            "relationTable" => "wiki_franchise",
            "relationName" => "franchise_name",
            "relationPageName" => "franchise_page_name"
        ],
        "friends" =>  [
            "table" => "wiki_assoc_character_friend", 
            "mainField" => "character_id", 
            "relationField" => "friend_character_id", 
            "relationTable" => "wiki_character",
            "relationName" => "friend_name",
            "relationPageName" => "friend_page_name"
        ],
        "games" =>  [
            "table" => "wiki_assoc_game_character", 
            "mainField" => "character_id", 
            "relationField" => "game_id", 
            "relationTable" => "wiki_game",
            "relationName" => "game_name",
            "relationPageName" => "game_page_name"
        ],
        "locations" =>  [
            "table" => "wiki_assoc_character_location", 
            "mainField" => "character_id", 
            "relationField" => "location_id", 
            "relationTable" => "wiki_location",
            "relationName" => "location_name",
            "relationPageName" => "location_page_name"
        ],
        "people" =>  [
            "table" => "wiki_assoc_character_person", 
            "mainField" => "character_id", 
            "relationField" => "person_id", 
            "relationTable" => "wiki_person",
            "relationName" => "person_name",
            "relationPageName" => "person_page_name"
        ],
        "objects" =>  [
            "table" => "wiki_assoc_character_thing", 
            "mainField" => "character_id", 
            "relationField" => "thing_id", 
            "relationTable" => "wiki_thing",
            "relationName" => "object_name",
            "relationPageName" => "object_page_name"
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

        return $this->insertOrUpdate(self::TABLE_NAME, [
            'id' => $data['id'],
            'image_id' => $imageId,
            'aliases' => $data['aliases'],
            'real_name' => $data['real_name'],
            'gender' => $data['gender'],
            'birthday' => $data['birthday'],
            'date_created' => $data['date_added'],
            'date_updated' => $data['date_last_updated'],
            'name' => (is_null($data['name'])) ? '' : $data['name'],
            'deck' => $data['deck'],
            'description' => (is_null($data['description'])) ? '' : $data['description'],
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

        $description = <<<MARKUP
$desc
{{Character
| Name=$name
| Guid=$guid

MARKUP;
        // only include if there is content to save db space
        if (!empty($row->aliases)) {
            $aliases = explode("\n", $row->aliases);
            $aliases = implode(',', $aliases);
            $description .= '| Aliases=' . htmlspecialchars($aliases, ENT_XML1, 'UTF-8') . "\n";
        }

        if (!empty($row->real_name)) {
            $description .= '| RealName=' . htmlspecialchars($row->real_name, ENT_XML1, 'UTF-8') . "\n";
        }

        if (!empty($row->gender)) {
            switch ($row->gender) {
                case 0: $gender = 'Female'; break;
                case 1: $gender = 'Male'; break;
                default: $gender = 'Non-Binary'; break;
            }
            $description .= '| Gender=' . htmlspecialchars($gender, ENT_XML1, 'UTF-8') . "\n";
        }

        if (!empty($row->birthday)) {
            $description .= '| Birthday=' . htmlspecialchars($row->birthday, ENT_XML1, 'UTF-8') . "\n";
        }

        if (!empty($row->death)) {
            $description .= '| Death=' . htmlspecialchars($row->death, ENT_XML1, 'UTF-8') . "\n";
        }

        if (!empty($row->deck)) {
            $description .= '| Deck=' . htmlspecialchars($row->deck, ENT_XML1, 'UTF-8') . "\n";
        }

        if (!empty($row->infobox_image)) {
            $imageFragment = parse_url($row->infobox_image, PHP_URL_PATH);
            $infoboxImage = basename($imageFragment);
            $description .= <<<MARKUP
| Image=$infoboxImage
| Caption=image of $name

MARKUP;
        }

        $description .= <<<MARKUP
$relations
}};
MARKUP;

        return [
            'title' => $row->mw_page_name,
            'namespace' => $this->namespaces['page'],
            'description' => $description
        ];
    }
}

?>