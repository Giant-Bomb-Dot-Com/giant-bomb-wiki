<?php

require_once(__DIR__.'/common.php');

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
                'description' => 'This is the Accessories category.'
            ],
            [
                'title' => 'Category:Characters',
                'namespace' => $this->namespaces['category'],
                'description' => 'This is the Characters category.'
            ],
            [
                'title' => 'Category:Companies',
                'namespace' => $this->namespaces['category'],
                'description' => 'This is the Companies category.'
            ],
            [
                'title' => 'Category:Concepts',
                'namespace' => $this->namespaces['category'],
                'description' => 'This is the Concepts category.'
            ],
            [
                'title' => 'Category:DLCs',
                'namespace' => $this->namespaces['category'],
                'description' => 'This is the Dlcs category.'
            ],
            [
                'title' => 'Category:Franchises',
                'namespace' => $this->namespaces['category'],
                'description' => 'This is the Franchises category.'
            ],
            [
                'title' => 'Category:Games',
                'namespace' => $this->namespaces['category'],
                'description' => 'This is the Games category.'
            ],
            [
                'title' => 'Category:Genres',
                'namespace' => $this->namespaces['category'],
                'description' => 'This is the Genres category.'
            ],
            [
                'title' => 'Category:Locations',
                'namespace' => $this->namespaces['category'],
                'description' => 'This is the Locations category.'
            ],
            [
                'title' => 'Category:Objects',
                'namespace' => $this->namespaces['category'],
                'description' => 'This is the Objects category.'
            ],
            [
                'title' => 'Category:People',
                'namespace' => $this->namespaces['category'],
                'description' => 'This is the People category.'
            ],
            [
                'title' => 'Category:Platforms',
                'namespace' => $this->namespaces['category'],
                'description' => 'This is the Platforms category.'
            ],
            [
                'title' => 'Category:Ratings',
                'namespace' => $this->namespaces['category'],
                'description' => '{{#default_form:}}'
            ],
            [
                'title' => 'Category:Themes',
                'namespace' => $this->namespaces['category'],
                'description' => 'This is the Themes category.'
            ],
        ];

        $this->createXML('categories.xml', $data);
    }
}

$maintClass = GenerateXMLCategories::class;

require_once RUN_MAINTENANCE_IF_MAIN; 