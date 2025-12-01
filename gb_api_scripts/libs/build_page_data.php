<?php

use Wikimedia\Rdbms\SelectQueryBuilder;

trait BuildPageData 
{
    /**
     * Converts result data into page data of [['title', 'namespace', 'description'],...]
     * 
     * @param stdClass $data
     * @return array
     */
    abstract public function getPageDataArray(stdClass $data): array;

    /**
     * Creates the semantic table based on fields in the incoming $data array
     *
     * @param array $data
     * @return string
     */
    public function formatSchematicData(array $data): string
    {
        // start with wiki type
        $wikiType = ucwords(static::RESOURCE_SINGULAR);
        $text = "{{{$wikiType}";

        // name and guid is guaranteed to exist
        $text .= "\n| Name={$data['name']}\n| Guid={$data['guid']}";

        // only include if there is content to save db space
        if (!empty($data['aliases'])) {
            $aliases = explode("\n", $data['aliases']);
            $aliases = array_map('trim', $aliases);
            $aliases = implode(',', $aliases);
            $aliases = trim(htmlspecialchars($aliases, ENT_XML1, 'UTF-8'));
            $text .= "\n| Aliases={$aliases}";
        }

        if (!empty($data['deck'])) {
            $deck = trim(htmlspecialchars($data['deck'], ENT_XML1, 'UTF-8'));
            $text .= "\n| Deck={$deck}";
        }

        if (!empty($data['real_name'])) {
            $realName = trim(htmlspecialchars($data['real_name'], ENT_XML1, 'UTF-8'));
            $text .= "\n| RealName={$realName}";
        }

        if (!empty($data['gender'])) {
            switch ($data['gender']) {
                case 0: $gender = 'Female'; break;
                case 1: $gender = 'Male'; break;
                default: $gender = 'Non-Binary'; break;
            }
            $text .= "\n| Gender={$gender}";
        }

        if (!empty($data['birthday'])) {
            $text .= "\n| Birthday={$data['birthday']}";
        }

        if (!empty($data['death'])) {
            $text .= "\n| Death={$data['death']}";
        }

        if (!empty($data['abbreviation'])) {
            $text .= "\n| Abbreviation={$data['abbreviation']}";
        }

        if (!empty($data['founded_date'])) {
            $text .= "\n| FoundedDate={$data['founded_date']}";
        }

        if (!empty($data['address'])) {
            $address = trim(htmlspecialchars($data['address'], ENT_XML1, 'UTF-8'));
            $text .= "\n| Address={$address}";
        }

        if (!empty($data['city'])) {
            $city = trim(htmlspecialchars($data['city'], ENT_XML1, 'UTF-8'));
            $text .= "\n| City={$city}";
        }

        if (!empty($data['country'])) {
            $country = trim(htmlspecialchars($data['country'], ENT_XML1, 'UTF-8'));
            $text .= "\n| Country={$country}";
        }

        if (!empty($data['state'])) {
            $state = trim(htmlspecialchars($data['state'], ENT_XML1, 'UTF-8'));
            $text .= "\n| State={$state}";
        }

        if (!empty($data['phone'])) {
            $text .= "\n| Phone={$data['phone']}";
        }

        if (!empty($data['website'])) {
            $website = trim(htmlspecialchars($data['website'], ENT_XML1, 'UTF-8'));
            $text .= "\n| Website={$website}";
        }

        if (!empty($data['release_date'])) {
            $text .= "\n| ReleaseDate={$data['release_date']}";
        }

        if (!empty($data['release_date_type'])) {
            // key in resource.php
            // value in generate_xml_properties.php
            switch($data['release_date_type']) {
                case '0': $releaseDateType = 'Full'; break;
                case '1': $releaseDateType = 'Month'; break;
                case '2': $releaseDateType = 'Quarter'; break;
                case '3': $releaseDateType = 'Year'; break;
                default: $releaseDateType = 'None'; break;
            }
            $text .= "\n| ReleaseDateType={$releaseDateType}";
        }

        if (!empty($data['install_base'])) {
            $text .= "\n| InstallBase={$data['install_base']}";
        }

        if (!empty($data['online_support'])) {
            $onlineSupport = ($data['online_support'] == 1) ? 'Yes' : 'No';
            $text .= "\n| OnlineSupport={$onlineSupport}";
        }

        if (!empty($data['original_price'])) {
            $text .= "\n| OriginalPrice={$data['original_price']}";
        }

        if (!empty($data['manufacturer_id'])) {
            $manufacturer = $this->getDb()->getPageName('wiki_company', $data['manufacturer_id']);
            $text .= "\n| Manufacturer={$manufacturer}";
        }

        if (!empty($data['last_name'])) {
            $text .= "\n| LastName={$data['last_name']}";
        }

        if (!empty($data['hometown'])) {
            $text .= "\n| Hometown={$data['hometown']}";
        }

        if (!empty($data['twitter'])) {
            $text .= "\n| Twitter={$data['twitter']}";
        }

        if (!empty($data['launch_price'])) {
            $text .= "\n| LaunchPrice={$data['launch_price']}";
        }

        if (!empty($data['game_id'])) {
            $game = $this->getDb()->getPageName('wiki_game', $data['game_id']);
            $text .= "\n| Games={$game}";
        }

        if (!empty($data['platform_id'])) {
            $platform = $this->getDb()->getPageName('wiki_platform', $data['platform_id']);
            $text .= "\n| Platforms={$platform}";
        }

        if (!empty($data['relations'])) {
            $text .= "\n".$data['relations'];
        }

        $text .= "\n}}\n";

        return $text;
    }

    /**
     * Creates a image div containing infobox and background image data
     *
     * @param array $data
     * @return string
     */
    public function getImageData(array $data): string
    {
        $imageArray = [
            'infobox' => [],
            'background' => []
        ];

        if (!empty($data['infobox_image_id'])) {
            $imageArray['infobox'] = $this->getImageData($data['infobox_image_id']);
        }

        if (!empty($data['background_image_id'])) {
            $imageArray['background'] = $this->getImageData($data['background_image_id']);
        }

        $imageData = "<div id='imageData' data-json='".json_encode($imageArray)."' />";

        return $imageData;
    }
}