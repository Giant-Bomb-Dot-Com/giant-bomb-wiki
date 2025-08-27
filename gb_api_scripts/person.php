<?php

require_once(__DIR__.'/resource.php');
require_once(__DIR__.'/common.php');
require_once(__DIR__.'/build_page_data.php');

class Person extends Resource
{
    use CommonVariablesAndMethods;
    use BuildPageData;

    const TYPE_ID = 3040;
    const RESOURCE_SINGULAR = "person";
    const RESOURCE_MULTIPLE = "people";
    const TABLE_NAME = "wiki_person";
    const TABLE_FIELDS = ['id','name','mw_page_name','aliases','deck','mw_formatted_description'];
    const RELATION_TABLE_MAP = [
        "characters" =>  ["table" => "wiki_assoc_character_person", "mainField" => "person_id", "relationField" => "character_id"],
        "concepts" => ["table" => "wiki_assoc_concept_person", "mainField" => "person_id", "relationField" => "concept_id"],
        "franchises" =>  ["table" => "wiki_assoc_franchise_person", "mainField" => "person_id", "relationField" => "franchise_id"],
        "games" =>  ["table" => "wiki_assoc_game_person", "mainField" => "person_id", "relationField" => "game_id"],
        "locations" =>  ["table" => "wiki_assoc_location_person", "mainField" => "person_id", "relationField" => "location_id"],
        "objects" =>  ["table" => "wiki_assoc_person_thing", "mainField" => "person_id", "relationField" => "thing_id"],
        "people" =>  ["table" => "wiki_assoc_person_similar", "mainField" => "person_id", "relationField" => "similar_person_id"],
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
     * birthday = birth_date
     * country = country
     * death = death_date
     * gender = gender
     * hometown = hometown
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
            'date_created' => $data['date_added'],
            'date_updated' => $data['date_last_updated'],
            'name' => (is_null($data['name'])) ? '' : $data['name'],
            'deck' => $data['deck'],
            'description' => (is_null($data['description'])) ? '' : $data['description'],
            'aliases' => $data['aliases'],
            'birthday' => $data['birth_date'],
            'country' => $data['country'],
            'death' => $data['death_date'],
            'gender' => $data['gender'],
            'hometown' => $data['hometown']
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
{{Person
| Name=$name
| Guid=$guid

MARKUP;
        // only include if there is content to save db space
        if (!empty($row->aliases)) {
            $aliases = htmlspecialchars($row->aliases, ENT_XML1, 'UTF-8');
            $description .= <<<MARKUP
| Aliases=$aliases

MARKUP;
        }

        if (!empty($row->deck)) {
            $deck = htmlspecialchars($row->deck, ENT_XML1, 'UTF-8');
            $description .= <<<MARKUP
| Deck=$deck

MARKUP;
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