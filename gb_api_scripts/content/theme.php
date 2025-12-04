<?php

require_once(__DIR__.'/../libs/resource.php');
require_once(__DIR__.'/../libs/common.php');
require_once(__DIR__.'/../libs/build_page_data.php');

class Theme extends Resource
{
    use CommonVariablesAndMethods;
    use BuildPageData;

    const TYPE_ID = 3032;
    const RESOURCE_SINGULAR = "theme";
    const RESOURCE_MULTIPLE = "themes";
    const PAGE_NAMESPACE = "Themes/";
    const TABLE_NAME = "wiki_game_theme";
    const TABLE_FIELDS = ['id','name','mw_page_name','aliases','deck','mw_formatted_description','image_id','background_image_id'];

    /**
     * Matching table fields to api response fields
     * 
     * id = id
     * name = name
     * 
     * @param array $data The api response array.
     * @return int 
     */
    public function process(array $data, array &$crawl): int
    {
        return $this->insertOrUpdate(self::TABLE_NAME, [
            'id' => $data['id'],
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
        
        $description = $this->formatSchematicData([
            'name' => $name,
            'guid' => $guid,
            'aliases' => $row->aliases,
            'deck' => $row->deck,
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