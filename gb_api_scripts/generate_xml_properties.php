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

    public function execute()
    {
        $data = [
            [
                'title' => 'Property:Has abbreviation',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has address',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has alias',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has background image',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'
            ],
            [
                'title' => 'Property:Has birthday',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Date]].'
            ],
            [
                'title' => 'Property:Has city',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has company code',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has company code type',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].

The allowed values for this property are:
* [[Allows value::Nintendo Product ID]]
* [[Allows value::Sony Company Code]]'
            ],
            [
                'title' => 'Property:Has country',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has deck',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has death',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Date]].'
            ],
            [
                'title' => 'Property:Has description',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has email',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Email]].'
            ],
            [
                'title' => 'Property:Has founded date',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Date]].'
            ],
            [
                'title' => 'Property:Has gender',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has guid',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has concepts',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'
            ],
            [
                'title' => 'Property:Has credits',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'
            ],
            [
                'title' => 'Property:Has developers',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'
            ],
            [
                'title' => 'Property:Has dlcs',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'
            ],
            [
                'title' => 'Property:Has enemies',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'
            ],
            [
                'title' => 'Property:Has friends',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'
            ],
            [
                'title' => 'Property:Has games',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'
            ],
            [
                'title' => 'Property:Has locations',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'
            ],
            [
                'title' => 'Property:Has objects',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'
            ],
            [
                'title' => 'Property:Has publishers',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'
            ],
            [
                'title' => 'Property:Has platforms',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'
            ],
            [
                'title' => 'Property:Has similar concepts',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'
            ],
            [
                'title' => 'Property:Has similar games',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'
            ],
            [
                'title' => 'Property:Has similar objects',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'
            ],
            [
                'title' => 'Property:Has image',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'
            ],
            [
                'title' => 'Property:Has install base',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has last name',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has launch price',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Number]].'
            ],
            [
                'title' => 'Property:Has manufacturer ID',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has maximum players',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Number]].'
            ],
            [
                'title' => 'Property:Has minimum players',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Number]].'
            ],
            [
                'title' => 'Property:Has multiplayer features',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].

The allowed values for this property are:
* [[Allows value::Local co-op]]
* [[Allows value::LAN co-op]]
* [[Allows value::Online co-op]]
* [[Allows value::Local competitive]]
* [[Allows value::LAN competitive]]
* [[Allows value::Online competitive]]
* [[Allows value::Local splitscreen]]
* [[Allows value::Online splitscreen]]
* [[Allows value::Pass and play]]
* [[Allows value::Voice chat]]'
            ],
            [
                'title' => 'Property:Has name',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has display name',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has online support',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has original price',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Number]].'
            ],
            [
                'title' => 'Property:Has product code',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has product code type',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].

The allowed values for this property are:
* [[Allows value::EAN/13]]
* [[Allows value::UPC/A]]
* [[Allows value::ISBN-10]]'
            ],
            [
                'title' => 'Property:Has rating',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'
            ],
            [
                'title' => 'Property:Has rating board',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Page]].'
            ],
            [
                'title' => 'Property:Has real name',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has region',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].

The allowed values for this property are:
* [[Allows value::Australia]]
* [[Allows value::Japan]]
* [[Allows value::United Kingdom]]
* [[Allows value::United States]]'
            ],
            [
                'title' => 'Property:Has release date',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Date]].'
            ],
            [
                'title' => 'Property:Has release date type',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].

The allowed values for this property are:
* [[Allows value::Full Date]]
* [[Allows value::Month/Year]]
* [[Allows value::Only Year]]
* [[Allows value::By Quarter]]'
            ],
            [
                'title' => 'Property:Has short name',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has single player features',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].

The allowed values for this property are:
* [[Allows value::Camera support]]
* [[Allows value::Voice control]]
* [[Allows value::Motion control]]
* [[Allows value::Driving wheel (native)]]
* [[Allows value::Flightstick (native)]]
* [[Allows value::PC gamepad (native)]]
* [[Allows value::Head tracking (native)]]'
            ],
            [
                'title' => 'Property:Has sound system',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].

The allowed values for this property are:
* [[Allows value::5.1]]
* [[Allows value::7.1]]
* [[Allows value::Dolby Pro Logic II]]
* [[Allows value::DTS]]
* [[Allows value::Mono]]
* [[Allows value::Stereo]]'
            ],
            [
                'title' => 'Property:Has state',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has twitter',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has website',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::URL]].'
            ],
            [
                'title' => 'Property:Has widescreen support',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].

The allowed values for this property are:
* [[Allows value::Yes]]
* [[Allows value::No]]'
            ],
            [
                'title' => 'Property:Has resolution',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Number]].

The allowed values for this property are:
* [[Allows value::1080p]]
* [[Allows value::1080i]]
* [[Allows value::720p]]
* [[Allows value::480p]]
* [[Allows value::PC CGA 320x200]]
* [[Allows value::PC EGA 640x350]]
* [[Allows value::PC VGA 640x480]]
* [[Allows value::PC WVGA 768x480]]
* [[Allows value::PC SVGA 800x600]]
* [[Allows value::PC 1024x768]]
* [[Allows value::PC 1440x900]]
* [[Allows value::PC 1600x1200]]
* [[Allows value::PC 2560x1440]]
* [[Allows value::PC 2560x1600]]'
            ],
            [
                'title' => 'Property:Has zipcode',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Stands for',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'MediaWiki:Noarticletext',
                'namespace' => $this->namespaces['core'],
                'description' => <<<MARKUP
There is currently no text in this page.
You can [[Special:Search/{{PAGENAME}}|search for this page title]] in other pages,
<span class="plainlinks">[{{fullurl:{{#Special:Log}}|page={{FULLPAGENAMEE}}}} search the related logs],
or [{{fullurl:{{FULLPAGENAME}}|action=edit}} create this page]</span>.
{{#switch: {{#explode:{{PAGENAME}}|/|0}}
| Accessories = {{CreateWithForm|Accessory}}
| Companies = {{CreateWithForm|Company}}
| Concepts = {{CreateWithForm|Concept}}
| Characters = {{CreateWithForm|Character}}
| DLCs = {{CreateWithForm|DLC}}
| Franchises = {{CreateWithForm|Franchise}}
| Games = {{CreateWithForm|Game}}
| Genres = {{CreateWithForm|Genre}}
| Locations = {{CreateWithForm|Location}}
| Objects = {{CreateWithForm|Object}}
| People = {{CreateWithForm|Person}}
| Platforms = {{CreateWithForm|Platform}}
| Ratings = {{CreateWithForm|Rating}}
| Themes = {{CreateWithForm|Theme}}
}}
MARKUP,
            ],
        ];

        $this->createXML('properties.xml', $data);
    }
}

$maintClass = GenerateXMLProperties::class;

require_once RUN_MAINTENANCE_IF_MAIN; 