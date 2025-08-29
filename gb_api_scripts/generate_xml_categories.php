<?php

require_once(__DIR__.'/libs/common.php');

class GenerateXMLCategories extends Maintenance
{
    use CommonVariablesAndMethods;

    public function __construct() 
    {
        parent::__construct();
        $this->addDescription("Generates XML for categories");
    }

    public function execute()
    {
        $data = [
            [
                'title' => 'Category:Accessories',
                'namespace' => $this->namespaces['category'],
                'description' => <<<MARKUP
This category lists the different accessories to play video games.

{{#default_form:Accessory}}
MARKUP,
            ],
            [
                'title' => 'Category:Characters',
                'namespace' => $this->namespaces['category'],
                'description' => <<<MARKUP
This category lists the different characters found in video games.

{{#default_form:Character}}
MARKUP,
            ],
            [
                'title' => 'Category:Companies',
                'namespace' => $this->namespaces['category'],
                'description' => <<<MARKUP
This category lists the companies that developed and/or published video games.

{{#default_form:Company}}
MARKUP,
            ],
            [
                'title' => 'Category:Concepts',
                'namespace' => $this->namespaces['category'],
                'description' => <<<MARKUP
This category lists the different concepts found in video games.

{{#default_form:Concept}}
MARKUP,
            ],
            [
                'title' => 'Category:DLCs',
                'namespace' => $this->namespaces['category'],
                'description' => <<<MARKUP
This category lists the different dlcs for video games.

{{#default_form:DLC}}
MARKUP,
            ],
            [
                'title' => 'Category:Franchises',
                'namespace' => $this->namespaces['category'],
                'description' => <<<MARKUP
This category lists the franchises the video games belong with.

{{#default_form:Franchise}}
MARKUP,
            ],
            [
                'title' => 'Category:Games',
                'namespace' => $this->namespaces['category'],
                'description' => <<<MARKUP
This category lists the video games.

{{#default_form:Game}}
MARKUP,
            ],
            [
                'title' => 'Category:Genres',
                'namespace' => $this->namespaces['category'],
                'description' => <<<MARKUP
This category lists the different genres for video games.

{{#default_form:Genre}}
MARKUP,
            ],
            [
                'title' => 'Category:Locations',
                'namespace' => $this->namespaces['category'],
                'description' => <<<MARKUP
This category lists the different locations found in video games.

{{#default_form:Location}}
MARKUP,
            ],
            [
                'title' => 'Category:Objects',
                'namespace' => $this->namespaces['category'],
                'description' => <<<MARKUP
This category lists the different objects found in video games.

{{#default_form:Object}}
MARKUP,
            ],
            [
                'title' => 'Category:People',
                'namespace' => $this->namespaces['category'],
                'description' => <<<MARKUP
This category lists the different people that worked on video games.

{{#default_form:Person}}
MARKUP,
            ],
            [
                'title' => 'Category:Platforms',
                'namespace' => $this->namespaces['category'],
                'description' => <<<MARKUP
This category lists the different platforms for video games.

{{#default_form:Platform}}
MARKUP,
            ],
            [
                'title' => 'Category:Ratings',
                'namespace' => $this->namespaces['category'],
                'description' => <<<MARKUP
This category lists the different regional rating options for video games.

{{#default_form:Rating}}
MARKUP,
            ],
            [
                'title' => 'Category:Themes',
                'namespace' => $this->namespaces['category'],
                'description' => <<<MARKUP
This category lists the different themes for video games.

{{#default_form:Theme}}
MARKUP,
            ],
        ];

        $this->createXML('categories.xml', $data);
    }
}

$maintClass = GenerateXMLCategories::class;

require_once RUN_MAINTENANCE_IF_MAIN; 