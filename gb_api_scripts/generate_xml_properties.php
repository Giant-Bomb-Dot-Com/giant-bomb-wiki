<?php

require_once(__DIR__.'/libs/common.php');

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
                'title' => 'Property:Has aliases',
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
                'title' => 'Property:Has caption',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has characters',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for characters found in games. 
[[Has type::Page]]
[[Allows value::Category:Characters]]'
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
                'title' => 'Property:Has concepts',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for concepts found in games.
[[Has type::Page]]
[[Allows value::Category:Concepts]]'
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
                'title' => 'Property:Has department',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].

The allowed values for this property are:
* [[Allows value::Unclassified]]
* [[Allows value::Thanks]]
* [[Allows value::Audio]]
* [[Allows value::Business]]
* [[Allows value::Design]]
* [[Allows value::Production]]
* [[Allows value::Programming]]
* [[Allows value::Visual Arts]]
* [[Allows value::Voice Actor]]
* [[Allows value::Quality Assurance]]'
            ],
            [
                'title' => 'Property:Has description',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has developers',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for companies that developed games.
[[Has type::Page]]
[[Subproperty of::Has companies]]
[[Allows value::Category:Companies]]'
            ],
            [
                'title' => 'Property:Has developed games',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for the games a company has developed. Use ask query to retrieve releases.
[[Has type::Page]]
[[Subproperty of::Has games]]
[[Allows value::Category:Games]]
[[Allows value::Category:DLCs]]'
            ],
            [
                'title' => 'Property:Has display name',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has dlcs',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for dlcs of games.
[[Has type::Page]]
[[Allows value::Category:DlCs]]'
            ],
            [
                'title' => 'Property:Has email',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Email]].'
            ],
            [
                'title' => 'Property:Has enemies',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property connects a character to a character they are enemies with.
[[Has type::Page]].
[[Subproperty of::Has characters]]
[[Allows value::Category:Characters]]'
            ],
            [
                'title' => 'Property:Has founded date',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Date]].'
            ],
            [
                'title' => 'Property:Has franchises',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for franchise of games.
[[Has type::Page]]
[[Allows value::Category:Franchises]]'
            ],
            [
                'title' => 'Property:Has friends',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property connects a character to a character they are friends with.
[[Has type::Page]].
[[Subproperty of::Has characters]]
[[Allows value::Category:Characters]]'
            ],
            [
                'title' => 'Property:Has games',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for games.
[[Has type::Page]]
[[Allows value::Category:Games]]'
            ],
            [
                'title' => 'Property:Has gender',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has genres',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for game genres.
[[Has type::Page]]
[[Allows value::Category:Genres]]'
            ],
            [
                'title' => 'Property:Has guid',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has hometown',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
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
                'title' => 'Property:Has locations',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for locations found in games.
[[Has type::Page]]
[[Allows value::Category:Locations]]'
            ],
            [
                'title' => 'Property:Has manufacturer',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for the companies that made the accessories.
[[Has type::Page]]
[[Subproperty of::Has companies]]
[[Allows value::Category:Companies]]'
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
                'description' => 'This is a property of type [[Has type::Page]].'
            ],
            [
                'title' => 'Property:Has name',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has note',
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
                'title' => 'Property:Has objects',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for the objects found in game.
[[Has type::Page]]
[[Allows value::Category:Objects]]'
            ],
            [
                'title' => 'Property:Has object type',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has people',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for the people that worked on a game.
[[Has type::Page]]
[[Allows value::Category:People]]'
            ],
            [
                'title' => 'Property:Has phone',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has platforms',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for the game platforms.
[[Has type::Page]]
[[Allows value::Category:Platforms]]'
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
                'title' => 'Property:Has publishers',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for companies that published games.
[[Has type::Page]]
[[Subproperty of::Has companies]]
[[Allows value::Category:Companies]]'
            ],
            [
                'title' => 'Property:Has published games',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for the games a company has published. Use ask query to retrieve releases.
[[Has type::Page]]
[[Subproperty of::Has games]]
[[Allows value::Category:Games]]
[[Allows value::Category:DLCs]]'
            ],
            [
                'title' => 'Property:Has rating',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for ratings for game content.
[[Has type::Page]]
[[Allows value::Category:Ratings]]'
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
* [[Allows value::Full]]
* [[Allows value::Month]]
* [[Allows value::Quarter]]
* [[Allows value::Year]]'
            ],
            [
                'title' => 'Property:Has short name',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has single player features',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for single player features available in a game.
[[Has type::Page]]
[[Allows value::Category:Single Player Features]]'
            ],
            [
                'title' => 'Property:Has sound systems',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for the audio capability of games.
[[Has type::Page]]
[[Allows value::Category:Sound Systems]]'
            ],
            [
                'title' => 'Property:Has state',
                'namespace' => $this->namespaces['property'],
                'description' => 'This is a property of type [[Has type::Text]].'
            ],
            [
                'title' => 'Property:Has similar concepts',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for concepts similar to another concept.
[[Has type::Page]]
[[Subproperty of::Has concepts]]
[[Allows value::Category:Concepts]]'
            ],
            [
                'title' => 'Property:Has similar games',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for games similar to another game.
[[Has type::Page]]
[[Subproperty of::Has games]]
[[Allows value::Category:Games]]'
            ],
            [
                'title' => 'Property:Has similar locations',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for locations similar to another location.
[[Has type::Page]]
[[Subproperty of::Has locations]]
[[Allows value::Category:Locations]]'
            ],
            [
                'title' => 'Property:Has similar objects',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for objects similar to another object.
[[Has type::Page]]
[[Subproperty of::Has objects]]
[[Allows value::Category:Objects]]'
            ],
            [
                'title' => 'Property:Has similar people',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for people similar to another person.
[[Has type::Page]]
[[Subproperty of::Has people]]
[[Allows value::Category:People]]'
            ],
            [
                'title' => 'Property:Has themes',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for game themes
[[Has type::Page]]
[[Allows value::Category:Themes]]'
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
                'title' => 'Property:Has resolutions',
                'namespace' => $this->namespaces['property'],
                'description' => 'This property are for supported game resolutions.
[[Has type::Page]]
[[Allows value::Category:Resolutions]]'
            ],
            [
                'title' => 'Property:Has zip',
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
| Accessories={{CreateWithForm|Accessory}}
| Companies={{CreateWithForm|Company}}
| Concepts={{CreateWithForm|Concept}}
| Characters={{CreateWithForm|Character}}
| DLCs={{CreateWithForm|DLC}}
| Franchises={{CreateWithForm|Franchise}}
| Games={{CreateWithForm|Game}}
| Genres={{CreateWithForm|Genre}}
| Locations={{CreateWithForm|Location}}
| Multiplayer Features={{CreateWithForm|Multiplayer Feature}}
| Objects={{CreateWithForm|Object}}
| People={{CreateWithForm|Person}}
| Platforms={{CreateWithForm|Platform}}
| Ratings={{CreateWithForm|Rating}}
| Resolutions={{CreateWithForm|Resolution}}
| Single Player Features={{CreateWithForm|Single Player Feature}}
| Sound Systems={{CreateWithForm|Sound System}}
| Themes={{CreateWithForm|Theme}}
}}
MARKUP,
            ],
        ];

        $this->createXML('properties.xml', $data);
    }
}

$maintClass = GenerateXMLProperties::class;

require_once RUN_MAINTENANCE_IF_MAIN; 