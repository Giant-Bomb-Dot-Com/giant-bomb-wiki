<?php

require_once(__DIR__.'/common.php');

class GenerateXMLProperties extends Maintenance
{
    use CommonVariablesAndMethods;

    public function __construct() 
    {
        parent::__construct();
        $this->addDescription("Generates XML for properties");
    }

    /**
     * - Retrieve all from a resource table
     */
    public function execute()
    {

        $data = [
            [
                'title' => 'Abbreviation',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'Address',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'Aliases',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'BackgroundImage',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'                
            ],
            [
                'title' => 'Birthday',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Date]].'                
            ],
            [
                'title' => 'City',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'CompanyCode',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'CompanyCodeType',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'Country',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'Deck',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'Death',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Date]].'                
            ],
            [
                'title' => 'Description',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'Email',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Email]].'                
            ],
            [
                'title' => 'FoundedDate',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Date]].'                
            ],
            [
                'title' => 'Gender',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'Guid',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Has concepts',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'                
            ],
            [
                'title' => 'Has credits',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'                
            ],
            [
                'title' => 'Has developers',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'                
            ],
            [
                'title' => 'Has dlcs',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'                
            ],
            [
                'title' => 'Has enemies',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'                
            ],
            [
                'title' => 'Has friends',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'                
            ],
            [
                'title' => 'Has games',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'                
            ],
            [
                'title' => 'Has locations',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'                
            ],
            [
                'title' => 'Has objects',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'                
            ],
            [
                'title' => 'Has publishers',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'                
            ],
            [
                'title' => 'Has platforms',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'                
            ],
            [
                'title' => 'Has similar concepts',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'                
            ],
            [
                'title' => 'Has similar games',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'                
            ],
            [
                'title' => 'Has similar objects',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'                
            ],
            [
                'title' => 'Image',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'
            ],
            [
                'title' => 'InstallBase',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'LastName',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'LaunchPrice',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Number]].'                
            ],
            [
                'title' => 'ManufacturerID',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'MaximumPlayers',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Number]].'                
            ],
            [
                'title' => 'MinimumPlayers',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Number]].'                
            ],
            [
                'title' => 'Name',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'OnlineSupport',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'OriginalPrice',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Number]].'                
            ],
            [
                'title' => 'ProductCode',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'ProductCodeType',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'Rating',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'                
            ],
            [
                'title' => 'RatingBoard',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'                
            ],
            [
                'title' => 'RealName',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'Region',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'ReleaseDate',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Date]].'                
            ],
            [
                'title' => 'ReleaseDateType',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'ShortName',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'State',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'Twitter',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'Website',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::URL]].'                
            ],
            [
                'title' => 'WidescreenSupport',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
            [
                'title' => 'XResolution',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Number]].'                
            ],
            [
                'title' => 'YResolution',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Number]].'                
            ],
            [
                'title' => 'Zip',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'                
            ],
        ];

        $this->createXML('properties.xml', $data);
    }
}

$maintClass = GenerateXMLProperties::class;

require_once RUN_MAINTENANCE_IF_MAIN; 