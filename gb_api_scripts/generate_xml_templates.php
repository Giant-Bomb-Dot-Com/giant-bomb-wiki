<?php

require_once(__DIR__.'/libs/common.php');

class GenerateXMLTemplates extends Maintenance
{
    use CommonVariablesAndMethods;

    public function __construct() 
    {
        parent::__construct();
        $this->addDescription("Generates XML for templates");
    }

    public function execute()
    {
        $data = [
            [
                'title' => 'Template:Accessory',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<noinclude>{{#template_params:
  Name (property=Has name)
| Guid (property=Has guid)
| Aliases (property=Has aliases)
| Image (property=Has image)
| Caption (property=Has caption)
| Deck (property=Has deck)
}}
==Documentation==
This template is used to create accessory pages, set its display title and infobox.
{| class="wikitable"
|-
! Field Name !! Description
|-
| Name || The display name of the accessory.
|-
| Guid || The identifier from Giant Bomb. 
|-
| Aliases || Alternative names.
|-
| Image || The image filename of the accessory. Image appears in the infobox.
|-
| Caption || The caption for the above image.
|-
| Deck || The short description of the accessory.
|}
</noinclude><includeonly
>{{#set:Has name={{{Name|}}}}}<!--
-->{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}<!--
-->{{#if:{{{Aliases|}}}|{{#set:Has aliases={{{Aliases|}}}}}}}<!--
-->{{#if:{{{Image|}}}|{{#set:Has image={{{Image|}}}}}}}<!--
-->{{#if:{{{Caption|}}}|{{#set:Has caption={{{Caption|}}}}}}}<!--
-->{{#if:{{{Deck|}}}|{{#set:Has deck={{{Deck|}}}}}}}<!--
-->{{Infobox
| title={{{Name|}}}
| italic title=no
| image={{{Image|}}}
| image size=40
| caption={{{Caption|}}}
| aliases={{{Aliases|}}}
| deck={{{Deck|}}}
}}<!--
-->{{DISPLAYTITLE:{{{Name|}}}}}[[Category:Accessories|{{SUBPAGENAME}}]]
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:Character',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<noinclude>{{#template_params:
  Name (property=Has name)
| Guid (property=Has guid)
| Aliases (property=Has aliases)
| Image (property=Has image)
| Caption (property=Has caption)
| Deck (property=Has deck)
| RealName (property=Has real name)
| Gender (property=Has gender)
| Birthday (property=Has birthday)
| Concepts (property=Has concepts)
| Enemies (property=Has enemies)
| Franchises (property=Has franchises)
| Friends (property=Has friends)
| Games (property=Has games)
| Locations (property=Has locations)
| People (property=Has people)
| Objects (property=Has objects)
}}
==Documentation==
This template is used to create character pages, set its display title and infobox.
{| class="wikitable"
|-
! Field Name !! Description
|-
| Name || The display name of the character.
|-
| Guid || The identifier from Giant Bomb. 
|-
| Aliases || Alternative names.
|-
| Image || The image filename of the character. Image appears in the infobox.
|-
| Caption || The caption for the above image.
|-
| Deck || The short description for the character.
|-
| RealName || The character's real name.
|-
| Gender || The character's gender.
|-
| Birthday || The character's birth date.
|-
| Concepts || The concepts related to the character.
|-
| Enemies || The characters considered a enemy to the character.
|-
| Franchises || The franchises the character has appeared in.
|-
| Friends || The characters considered a friend to the character.
|-
| Games || The games the character has appeared in.
|-
| Locations || The locations related to the character.
|-
| Objects || The objects related to the character.
|-
| People || The people related to the character.
|}
</noinclude><includeonly
>{{#set:Has name={{{Name|}}}}}<!--
-->{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}<!--
-->{{#if:{{{Aliases|}}}|{{#set:Has aliases={{{Aliases|}}}}}}}<!--
-->{{#if:{{{Image|}}}|{{#set:Has image={{{Image|}}}}}}}<!--
-->{{#if:{{{Caption|}}}|{{#set:Has caption={{{Caption|}}}}}}}<!--
-->{{#if:{{{Deck|}}}|{{#set:Has deck={{{Deck|}}}}}}}<!--
-->{{#if:{{{RealName|}}}|{{#set:Has real name={{{RealName|}}}}}}}<!--
-->{{#if:{{{Gender|}}}|{{#set:Has gender={{{Gender|}}}}}}}<!--
-->{{#if:{{{Birthday|}}}|{{#set:Has birthday={{{Birthday|}}}}}}}<!--
-->{{#arraymap:{{{Concepts|}}}|,|@@|{{SetPropertyPrefix|Has concepts|Concepts|@@}}| }}<!--
-->{{#arraymap:{{{Enemies|}}}|,|@@|{{SetPropertyPrefix|Has enemies|Enemies|@@}}| }}<!--
-->{{#arraymap:{{{Franchises|}}}|,|@@|{{SetPropertyPrefix|Has franchises|Franchises|@@}}| }}<!--
-->{{#arraymap:{{{Friends|}}}|,|@@|{{SetPropertyPrefix|Has friends|Friends|@@}}| }}<!--
-->{{#arraymap:{{{Games|}}}|,|@@|{{SetPropertyPrefix|Has games|Games|@@}}| }}<!--
-->{{#arraymap:{{{Locations|}}}|,|@@|{{SetPropertyPrefix|Has locations|Locations|@@}}| }}<!--
-->{{#arraymap:{{{Objects|}}}|,|@@|{{SetPropertyPrefix|Has objects|Objects|@@}}| }}<!--
-->{{#arraymap:{{{People|}}}|,|@@|{{SetPropertyPrefix|Has people|People|@@}}| }}<!--
-->{{Infobox
| title={{{Name|}}}
| italic title=no
| image={{{Image|}}}
| image size=40
| caption={{{Caption|}}}
| aliases={{{Aliases|}}}
| deck={{{Deck|}}}
| real name={{{RealName|}}}
| gender={{{Gender|}}}
| birthday={{{Birthday|}}}
}}<!--
-->{{DISPLAYTITLE:{{{Name|}}}}}[[Category:Characters|{{SUBPAGENAME}}]]
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:Company',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<noinclude>{{#template_params:
  Name (property=Has name)
| Guid (property=Has guid)
| Aliases (property=Has aliases)
| Image (property=Has image)
| Caption (property=Has caption)
| Deck (property=Has deck)
| Abbreviation (property=Has abbreviation)
| FoundedDate (property=Has founded date)
| Address (property=Has address)
| City (property=Has city)
| Country (property=Has country)
| State (property=Has state)
| Phone (property=Has phone)
| Website (property=Has website)
| Characters (property=Has characters)
| Concepts (property=Has concepts)
| Locations (property=Has locations)
| Objects (property=Has objects)
| People (property=Has people)
| Developed (property=Has developed games)
| Published (property=Has published games)
}}
==Documentation==
This template is used to create company pages, set its display title and infobox.
{| class="wikitable"
|-
! Field Name !! Description
|-
| Name || The display name of the company.
|-
| Guid || The identifier from Giant Bomb. 
|-
| Aliases || Alternative names.
|-
| Image || The image filename of the company. Image appears in the infobox.
|-
| Caption || The caption for the above image.
|-
| Deck || The short description for the company.
|-
| Abbreviation || The abbreviation of the company's name.
|-
| FoundedDate || The company's founded date.
|-
| Address || The address the company resides.
|-
| City || The city the company resides.
|-
| Country || The country the company resides.
|-
| State || The state the company ressides.
|-
| Phone || The company's customer service phone number.
|-
| Website || The company's website.
|-
| Characters || The characters related to the company.
|-
| Concepts || The concepts related to the company.
|-
| Locations || The locations related to the company.
|-
| Objects || The objects related to the company.
|-
| People || The people related to the company.
|-
| Developed || The games the company developed.
|-
| Published || The games the company published.
|}
</noinclude><includeonly
>{{#set:Has name={{{Name|}}}}}<!--
-->{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}<!--
-->{{#if:{{{Aliases|}}}|{{#set:Has aliases={{{Aliases|}}}}}}}<!--
-->{{#if:{{{Image|}}}|{{#set:Has image={{{Image|}}}}}}}<!--
-->{{#if:{{{Caption|}}}|{{#set:Has caption={{{Caption|}}}}}}}<!--
-->{{#if:{{{Deck|}}}|{{#set:Has deck={{{Deck|}}}}}}}<!--
-->{{#if:{{{Abbereviation|}}}|{{#set:Has abbreviation={{{Abbreviation|}}}}}}}<!--
-->{{#if:{{{FoundedDate|}}}|{{#set:Has founded date={{{FoundedDate|}}}}}}}<!--
-->{{#if:{{{Address|}}}|{{#set:Has address={{{Address|}}}}}}}<!--
-->{{#if:{{{City|}}}|{{#set:Has city={{{City|}}}}}}}<!--
-->{{#if:{{{Country|}}}|{{#set:Has country={{{Country|}}}}}}}<!--
-->{{#if:{{{State|}}}|{{#set:Has state={{{State|}}}}}}}<!--
-->{{#if:{{{Phone|}}}|{{#set:Has phone={{{Phone|}}}}}}}<!--
-->{{#if:{{{Website|}}}|{{#set:Has website={{{Website|}}}}}}}<!--
-->{{#arraymap:{{{Characters|}}}|,|@@|{{SetPropertyPrefix|Has characters|Characters|@@}}| }}<!--
-->{{#arraymap:{{{Concepts|}}}|,|@@|{{SetPropertyPrefix|Has concepts|Concepts|@@}}| }}<!--
-->{{#arraymap:{{{Locations|}}}|,|@@|{{SetPropertyPrefix|Has locations|Locations|@@}}| }}<!--
-->{{#arraymap:{{{Objects|}}}|,|@@|{{SetPropertyPrefix|Has objects|Objects|@@}}| }}<!--
-->{{#arraymap:{{{People|}}}|,|@@|{{SetPropertyPrefix|Has people|People|@@}}| }}<!--
-->{{#arraymap:{{{Developed|}}}|,|@@|{{SetPropertyPrefix|Has developed games|Games|@@}}| }}<!--
-->{{#arraymap:{{{Published|}}}|,|@@|{{SetPropertyPrefix|Has published games|Games|@@}}| }}<!--
-->{{Infobox
| title={{{Name|}}}
| italic title=no
| image={{{Image|}}}
| image size=40
| caption={{{Caption|}}}
| aliases={{{Aliases|}}}
| deck={{{Deck|}}}
| abbreviation={{{Abbreviation|}}}
| founded date={{{FoundedDate|}}}
| address={{{Address|}}}
| city={{{City|}}}
| country={{{Country|}}}
| state={{{State|}}}
| phone={{{Phone|}}}
| website={{{{Website|}}}}
}}<!--
-->{{DISPLAYTITLE:{{{Name|}}}}}[[Category:Companies|{{SUBPAGENAME}}]]
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:Concept',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<noinclude>{{#template_params:
  Name (property=Has name)
| Guid (property=Has guid)
| Aliases (property=Has aliases)
| Image (property=Has image)
| Caption (property=Has caption)
| Deck (property=Has deck)
| Characters (property=Has characters)
| Concepts (property=Has similar concepts)
| Franchises (property=Has franchises)
| Games (property=Has games)
| Locations (property=Has locations)
| Objects (property=Has objects)
| People (property=Has people)
}}
==Documentation==
This template is used to create concept pages, set its display title and infobox.
{| class="wikitable"
|-
! Field Name !! Description
|-
| Name || The display name of the concept.
|-
| Guid || The identifier from Giant Bomb. 
|-
| Aliases || Alternative names.
|-
| Image || The image filename of the concept. Image appears in the infobox.
|-
| Caption || The caption for the above image.
|-
| Deck || The short description for the concept.
|-
| Characters || The characters related to the concept.
|-
| Concepts || The concepts similar to the concept.
|-
| Franchises || The franchises that have the concept.
|-
| Games || The games that have the concept.
|-
| Locations || The locations related to the concept.
|-
| Objects || The objects related to the concept.
|-
| People || The people related to the concept.
|}
</noinclude><includeonly
>{{#set:Has name={{{Name|}}}}}<!--
-->{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}<!--
-->{{#if:{{{Aliases|}}}|{{#set:Has aliases={{{Aliases|}}}}}}}<!--
-->{{#if:{{{Image|}}}|{{#set:Has image={{{Image|}}}}}}}<!--
-->{{#if:{{{Caption|}}}|{{#set:Has caption={{{Caption|}}}}}}}<!--
-->{{#if:{{{Deck|}}}|{{#set:Has deck={{{Deck|}}}}}}}<!--
-->{{#arraymap:{{{Characters|}}}|,|@@|{{SetPropertyPrefix|Has characters|Characters|@@}}| }}<!--
-->{{#arraymap:{{{Concepts|}}}|,|@@|{{SetPropertyPrefix|Has similar concepts|Concepts|@@}}| }}<!--
-->{{#arraymap:{{{Franchises|}}}|,|@@|{{SetPropertyPrefix|Has franchises|Franchises|@@}}| }}<!--
-->{{#arraymap:{{{Games|}}}|,|@@|{{SetPropertyPrefix|Has games|Games|@@}}| }}<!--
-->{{#arraymap:{{{Locations|}}}|,|@@|{{SetPropertyPrefix|Has locations|Locations|@@}}| }}<!--
-->{{#arraymap:{{{Objects|}}}|,|@@|{{SetPropertyPrefix|Has objects|Objects|@@}}| }}<!--
-->{{#arraymap:{{{People|}}}|,|@@|{{SetPropertyPrefix|Has people|People|@@}}| }}<!--
-->{{Infobox
| title={{{Name|}}}
| italic title=no
| image={{{Image|}}}
| image size=40
| caption={{{Caption|}}}
| aliases={{{Aliases|}}}
| deck={{{Deck|}}}
}}<!--
-->{{DISPLAYTITLE:{{{Name|}}}}}[[Category:Concepts|{{SUBPAGENAME}}]]
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:CreditSubobject',
                'namespace' => $this->namespaces['template'],
                'description' => <<< MARKUP
{{#subobject: {{#invoke:Identifiers|getCreditIdentifier|Game={{{Game|}}}|Release={{{Release|}}}|Dlc={{{Dlc|}}}|Person={{{Person|}}}|Department={{{Department|}}}|Role={{{Role|}}}}} |
|Has object type=Credit
|Has games={{{Game|}}}
|Has release={{{Release|}}}
|Has dlc={{{Dlc|}}}
|Has people={{{Person|}}}
|Has companies={{{Company|}}}
|Has department={{{Department|}}}
|Has role={{{Role|}}}
}}
MARKUP,
            ],
            [
                'title' => 'Template:Credits',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<noinclude>{{#template_params:
 ParentPage (property=Has superpage)
}}
==Documentation==
This template is used to create the credits subpage for games.
{| class="wikitable"
|-
! Field Name !! Description
|-
| ParentPage || The parent page the credits are for.
|-
| CreditSubobject || The people that worked on the game stored as subobjects.
|-
| ↳ Game || The game these credits are for.
|-
| ↳ Release || The release these credits are for.
|-
| ↳ Dlc || The dlc these credits are for.
|-
| ↳ Person || Person that worked on the game.
|-
| ↳ Company || The company they worked for.
|-
| ↳ Department || The department they worked for.
|-
| ↳ Role || Their specific role.
|}
</noinclude><includeonly>{{#set:Has superpage={{{ParentPage|}}}}}<!--
-->{{#ifeq:{{#titleparts:{{FULLPAGENAME}}||-1}}|Credits|[[Category:Credits]]}}<!--
-->{{#ask: [[-Has subobject::{{FULLPAGENAME}}]] [[Has object type::Credit]]
|?Has games=Game
|?Has release=Release
|?Has dlc=Dlc
|?Has people=Person
|?Has companies=Company
|?Has department=Department
|?Has role=Role
|format=broadtable
|link=none
}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:DlcSubobject',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
{{#subobject: {{#invoke:Identifiers|getDlcIdentifier|Name={{{Name|}}}|Platform={{{Platform|}}}}} |
|Has object type=Dlc
|Has composite name={{{Name|}}} ({{{Platform|}}})
|Has games={{{Game|}}}
|Has name={{{Name|}}}
|Has guid={{{Guid|}}}
|Has aliases={{{Aliases|}}}
|Has image={{{Image|}}}
|Has caption={{{Caption|}}}
|Has deck={{{Deck|}}}
|Has release date={{{ReleaseDate|}}}
|Has release date type={{{ReleaseDateType|}}}
|Has launch price={{{LaunchPrice|}}}
|Has developers={{{Developers|}}}
|Has publishers={{{Publishers|}}}
|Has platforms={{{Platform|}}}
|Has dlc types={{{DlcTypes|}}}
}}
MARKUP,
            ],
            [
                'title' => 'Template:DLC',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<<noinclude>{{#template_params:
 ParentPage (property=Has superpage)
}}
==Documentation==
This template is used to create DLC pages, set its display title and infobox.
{| class="wikitable"
|-
! Field Name !! Description
|-
| ParentPage || The parent page to this subpage.
|-
| DlcSubobject || The subobject for a dlc.
|-
| ↳ Game || Game of release.
|-
| ↳ Name || The display name of the DLC.
|-
| ↳ Guid || The identifier from Giant Bomb. 
|-
| ↳ Aliases || Alternative names.
|-
| ↳ Image || The image filename of the DLC. Image appears in the infobox.
|-
| ↳ Caption || The caption for the above image.
|-
| ↳ Deck || The short description for the DLC.
|-
| ↳ ReleaseDate || The DLC's release date.
|-
| ↳ ReleaseDateType || The format of the release date.
|-
| ↳ LaunchPrice || The price of the DLC at launch in USD.
|-
| ↳ Platform || The platform related to the DLC.
|-
| ↳ DlcTypes || Type of DLC.
|}
</noinclude><includeonly>{{#set:Has superpage={{{ParentPage|}}}}}<!--
-->{{#ifeq:{{#titleparts:{{FULLPAGENAME}}||-1}}|DLC|[[Category:DLC]]}}<!--
-->{{#ask: [[-Has subobject::{{FULLPAGENAME}}]] [[Has object type::Dlc]]
|format=broadtable
|link=none
|?Has image=Image
|?Has name=Name
|?Has deck=Deck
|?Has platforms=Platform
|?Has developers=Developers
|?Has publishers=Publishers
|?Has release date=ReleaseDate
|?Has release date type=ReleaseDateType
|?Has launch price=LaunchPrice
|?Has dlc types=DlcTypes
}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:Franchise',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<noinclude>{{#template_params:
  Name (property=Has name)
| Guid (property=Has guid)
| Aliases (property=Has aliases)
| Image (property=Has image)
| Caption (property=Has caption)
| Deck (property=Has deck)
| Characters (property=Has characters)
| Concepts (property=Has concepts)
| Games (property=Has games)
| Locations (property=Has locations)
| Objects (property=Has objects)
| People (property=Has people)
}}
==Documentation==
This template is used to create franchise pages, set its display title and infobox.
{| class="wikitable"
|-
! Field Name !! Description
|-
| Name || The display name of the franchise.
|-
| Guid || The identifier from Giant Bomb. 
|-
| Aliases || Alternative names.
|-
| Image || The image filename of the franchise. Image appears in the infobox.
|-
| Caption || The caption for the above image.
|-
| Deck || The short description of the franchise.
|-
| Characters || The characters related to the franchise.
|-
| Concepts || The concepts related to the franchise.
|-
| Games || The games that are part of the franchise.
|-
| Locations || The locations related to the franchise.
|-
| Objects || The objects related to the franchise.
|-
| People || The people that worked on the franchise.
|}
</noinclude><includeonly
>{{#set:Has name={{{Name|}}}}}<!--
-->{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}<!--
-->{{#if:{{{Aliases|}}}|{{#set:Has aliases={{{Aliases|}}}}}}}<!--
-->{{#if:{{{Image|}}}|{{#set:Has image={{{Image|}}}}}}}<!--
-->{{#if:{{{Caption|}}}|{{#set:Has caption={{{Caption|}}}}}}}<!--
-->{{#if:{{{Deck|}}}|{{#set:Has deck={{{Deck|}}}}}}}<!--
-->{{#arraymap:{{{Characters|}}}|,|@@|{{SetPropertyPrefix|Has characters|Characters|@@}}| }}<!--
-->{{#arraymap:{{{Concepts|}}}|,|@@|{{SetPropertyPrefix|Has concepts|Concepts|@@}}| }}<!--
-->{{#arraymap:{{{Games|}}}|,|@@|{{SetPropertyPrefix|Has games|Games|@@}}| }}<!--
-->{{#arraymap:{{{Locations|}}}|,|@@|{{SetPropertyPrefix|Has locations|Locations|@@}}| }}<!--
-->{{#arraymap:{{{Objects|}}}|,|@@|{{SetPropertyPrefix|Has objects|Objects|@@}}| }}<!--
-->{{#arraymap:{{{People|}}}|,|@@|{{SetPropertyPrefix|Has people|People|@@}}| }}<!--
-->{{Infobox
| title={{{Name|}}}
| italic title=no
| image={{{Image|}}}
| image size=40
| caption={{{Caption|}}}
| aliases={{{Aliases|}}}
| deck={{{Deck|}}}
}}<!--
-->{{DISPLAYTITLE:{{{Name|}}}}}[[Category:Franchises|{{SUBPAGENAME}}]]
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:Game',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<noinclude>{{#template_params:
  Name (property=Has name)
| Guid (property=Has guid)
| Aliases (property=Has aliases)
| Image (property=Has image)
| Caption (property=Has caption)
| Deck (property=Has deck)
| ReleaseDate (property=Has release date)
| ReleaseDateType (property=Has release date type)
| Characters (property=Has characters)
| Concepts (property=Has concepts)
| Developers (property=Has developers)
| Franchise (property=Has franchise)
| Games (property=Has similar games)
| Genres (property=Has genres)
| Locations (property=Has locations)
| Objects (property=Has objects)
| Platforms (property=Has platforms)
| Publishers (property=Has publishers)
| Themes (property=Has themes)
}}
==Documentation==
This template is used to create game pages, set its display title and infobox.
{| class="wikitable"
|-
! Field Name !! Description
|-
| Name || The display name of the game.
|-
| Guid || The identifier from Giant Bomb. 
|-
| Aliases || Alternative names.
|-
| Image || The image filename of the game. Image appears in the infobox.
|-
| Caption || The caption for the above image.
|-
| Deck || The short description of the game.
|-
| ReleaseDate || The game's release date.
|-
| ReleaseDateType || The format of the release date.
|-
| Characters || The characters found in the game.
|-
| Concepts || The concepts found in the game.
|-
| Developers || The developers of the game.
|-
| Franchise || The franchise the game belongs with.
|-
| Games || The games similar to the game.
|-
| Genres || The genres associated to the game.
|-
| Locations || The locations found in the game.
|-
| Objects || The objects found in the game.
|-
| Platforms || The platforms available for the game.
|-
| Publishers || The publshers of the game.
|-
| Themes || The themes found in the game.
|}
</noinclude><includeonly
>{{#set:Has name={{{Name|}}}}}<!--
-->{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}<!--
-->{{#if:{{{Aliases|}}}|{{#set:Has aliases={{{Aliases|}}}}}}}<!--
-->{{#if:{{{Image|}}}|{{#set:Has image={{{Image|}}}}}}}<!--
-->{{#if:{{{Caption|}}}|{{#set:Has caption={{{Caption|}}}}}}}<!--
-->{{#if:{{{Deck|}}}|{{#set:Has deck={{{Deck|}}}}}}}<!--
-->{{#if:{{{ReleaseDate|}}}|{{#set:Has release date={{{ReleaseDate|}}}}}}}<!--
-->{{#if:{{{ReleaseDateType|}}}|{{#set:Has release date type={{{ReleaseDateType|}}}}}}}<!--
-->{{#arraymap:{{{Characters|}}}|,|@@|{{SetPropertyPrefix|Has characters|Characters|@@}}| }}<!--
-->{{#arraymap:{{{Concepts|}}}|,|@@|{{SetPropertyPrefix|Has concepts|Concepts|@@}}| }}<!--
-->{{#arraymap:{{{Developers|}}}|,|@@|{{SetPropertyPrefix|Has developers|Developers|@@}}| }}<!--
-->{{#arraymap:{{{Franchise|}}}|,|@@|{{SetPropertyPrefix|Has franchise|Franchise|@@}}| }}<!--
-->{{#arraymap:{{{Games|}}}|,|@@|{{SetPropertyPrefix|Has games|Games|@@}}| }}<!--
-->{{#arraymap:{{{Genres|}}}|,|@@|{{SetPropertyPrefix|Has genres|Genres|@@}}| }}<!--
-->{{#arraymap:{{{Locations|}}}|,|@@|{{SetPropertyPrefix|Has locations|Locations|@@}}| }}<!--
-->{{#arraymap:{{{Objects|}}}|,|@@|{{SetPropertyPrefix|Has objects|Objects|@@}}| }}<!--
-->{{#arraymap:{{{Platforms|}}}|,|@@|{{SetPropertyPrefix|Has platforms|Platforms|@@}}| }}<!--
-->{{#arraymap:{{{Publishers|}}}|,|@@|{{SetPropertyPrefix|Has publishers|Publishers|@@}}| }}<!--
-->{{#arraymap:{{{Themes|}}}|,|@@|{{SetPropertyPrefix|Has themes|Themes|@@}}| }}<!--
-->{{Infobox
| title={{{Name|}}}
| italic title=no
| image={{{Image|}}}
| image size=40
| caption={{{Caption|}}}
| aliases={{{Aliases|}}}
| deck={{{Deck|}}}
| release date={{{ReleaseDate|}}}
| release date type={{{ReleaseDateType|}}}
| developers={{{Developers|}}}
| publishers={{{Publishers|}}}
| platforms={{{Platforms|}}}
| franchise={{{Franchise|}}}
| genres={{{Genres|}}}
| themes={{{Themes|}}}
}}<!--
-->{{DISPLAYTITLE:{{{Name|}}}}}[[Category:Games|{{SUBPAGENAME}}]]
== {{#ifexist:{{PAGENAME}}/Releases|[[{{PAGENAME}}/Releases|Releases]]|Releases}} ==
{{#formlink:
|form=Releases
|link text={{#ifexist:{{PAGENAME}}/Releases|Add/Edit Releases|Create Releases Page}}
|target={{PAGENAME}}/Releases
|field-Game={{PAGENAME}}
}}
{{#ifexist:{{PAGENAME}}/Releases
|{{:{{PAGENAME}}/Releases}}
}}
== {{#ifexist:{{PAGENAME}}/DLC|[[{{PAGENAME}}/DLC|DLC]]|DLC}} ==
{{#formlink:
|form=DLC
|link text={{#ifexist:{{PAGENAME}}/DLC|Add/Edit DLC|Create DLC Page}}
|target={{PAGENAME}}/DLC
|field-Game={{PAGENAME}}
}}
{{#ifexist:{{PAGENAME}}/DLC
|{{:{{PAGENAME}}/DLC}}
}}
== {{#ifexist:{{PAGENAME}}/Credits|[[{{PAGENAME}}/Credits|Credits]]|Credits}} ==
{{#formlink:
|form=Credits
|link text={{#ifexist:{{PAGENAME}}/Credits|Add/Edit Credits|Create Credits Page}}
|target={{PAGENAME}}/Credits
|field-Game={{PAGENAME}}
}}
{{#ifexist:{{PAGENAME}}/Credits
|{{:{{PAGENAME}}/Credits}}
}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:Genre',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<noinclude>{{#template_params:
  Name (property=Has name)
| Guid (property=Has guid)
| Image (property=Has image)
| Caption (property=Has caption)
| Deck (property=Has deck)
}}
==Documentation==
This template is used to create genre pages, set its display title and infobox.
{| class="wikitable"
|-
! Field Name !! Description
|-
| Name || The display name of the genre.
|-
| Guid || The identifier from Giant Bomb. 
|-
| Image || The image filename of the genre. Image appears in the infobox.
|-
| Caption || The caption for the above image.
|-
| Deck || The short description of the genre.
|}
</noinclude><includeonly
>{{#set:Has name={{{Name|}}}}}<!--
-->{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}<!--
-->{{#if:{{{Image|}}}|{{#set:Has image={{{Image|}}}}}}}<!--
-->{{#if:{{{Caption|}}}|{{#set:Has caption={{{Caption|}}}}}}}<!--
-->{{#if:{{{Deck|}}}|{{#set:Has deck={{{Deck|}}}}}}}<!--
-->{{Infobox
| title={{{Name|}}}
| italic title=no
| image={{{Image|}}}
| image size=40
| caption={{{Caption|}}}
| deck={{{Deck|}}}
}}<!--
-->{{DISPLAYTITLE:{{{Name|}}}}}[[Category:Genres|{{SUBPAGENAME}}]]
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:ListSubobjectName',
                'namespace' => $this->namespaces['template'],
                'description' => '{{#show:{{{1}}}|?Has name}}',
            ],
            [
                'title' => 'Template:Location',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<noinclude>{{#template_params:
  Name (property=Has name)
| Guid (property=Has guid)
| Aliases (property=Has aliases)
| Image (property=Has image)
| Caption (property=Has caption)
| Deck (property=Has deck)
| Characters (property=Has characters)
| Concepts (property=Has concepts)
| Locations (property=Has similar locations)
| Objects (property=Has objects)
}}
==Documentation==
This template is used to create location pages, set its display title and infobox.
{| class="wikitable"
|-
! Field Name !! Description
|-
| Name || The display name of the location.
|-
| Guid || The identifier from Giant Bomb. 
|-
| Image || The image filename of the location. Image appears in the infobox.
|-
| Caption || The caption for the above image.
|-
| Deck || The short description of the location.
|-
| Characters || The characters related to the location.
|-
| Concepts || The concepts related to the location.
|-
| Locations || The locations similar to the location.
|-
| Objects || The objects related to the location.
|}
</noinclude><includeonly
>{{#set:Has name={{{Name|}}}}}<!--
-->{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}<!--
-->{{#if:{{{Aliases|}}}|{{#set:Has aliases={{{Aliases|}}}}}}}<!--
-->{{#if:{{{Image|}}}|{{#set:Has image={{{Image|}}}}}}}<!--
-->{{#if:{{{Caption|}}}|{{#set:Has caption={{{Caption|}}}}}}}<!--
-->{{#if:{{{Deck|}}}|{{#set:Has deck={{{Deck|}}}}}}}<!--
-->{{#arraymap:{{{Characters|}}}|,|@@|{{SetPropertyPrefix|Has characters|Characters|@@}}| }}<!--
-->{{#arraymap:{{{Concepts|}}}|,|@@|{{SetPropertyPrefix|Has concepts|Concepts|@@}}| }}<!--
-->{{#arraymap:{{{Locations|}}}|,|@@|{{SetPropertyPrefix|Has similar locations|Locations|@@}}| }}<!--
-->{{#arraymap:{{{Objects|}}}|,|@@|{{SetPropertyPrefix|Has objects|Objects|@@}}| }}<!--
-->{{Infobox
| title={{{Name|}}}
| italic title=no
| image={{{Image|}}}
| image size=40
| caption={{{Caption|}}}
| aliases={{{Aliases|}}}
| deck={{{Deck|}}}
}}<!--
-->{{DISPLAYTITLE:{{{Name|}}}}}[[Category:Locations|{{SUBPAGENAME}}]]
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:Multiplayer Feature',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<noinclude>{{#template_params:
  Name (property=Has name)
| Guid (property=Has guid)
}}
==Documentation==
This template is used to create multiplayer features pages, sets its display title and infobox.
{| class="wikitable"
|-
! Field Name !! Description
|-
| Name || The display name of the sound system.
|-
| Guid || The identifier from Giant Bomb. 
|}
</noinclude><includeonly
>{{#set:Has name={{{Name|}}}}}<!--
-->{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}<!--
-->{{Infobox
| title={{{Name|}}}
| italic title=no
}}<!--
-->{{DISPLAYTITLE:{{{Name|}}}}}[[Category:Multiplayer Features|{{SUBPAGENAME}}]]
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:Object',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<noinclude>{{#template_params:
  Name (property=Has name)
| Guid (property=Has guid)
| Aliases (property=Has aliases)
| Image (property=Has image)
| Caption (property=Has caption)
| Deck (property=Has deck)
| Characters (property=Has characters)
| Concepts (property=Has concepts)
| Franchises (property=Has franchises)
| Games (property=Has games)
| Locations (property=Has locations)
| Objects (property=Has similar objects)
| People (property=Has people)
}}
==Documentation==
This template is used to create object pages, set its display title and infobox.
{| class="wikitable"
|-
! Field Name !! Description
|-
| Name || The display name of the object.
|-
| Guid || The identifier from Giant Bomb. 
|-
| Aliases || Alternative names.
|-
| Image || The image filename of the object. Image appears in the infobox.
|-
| Caption || The caption for the above image.
|-
| Deck || The short description of the object.
|-
| Characters || The characters related to the object.
|-
| Concepts || The concepts related to the object.
|-
| Franchises || The franchises containing the object.
|-
| Games || The games containing the object.
|-
| Locations || The locations related to the object.
|-
| Objects || The objects similar to the object.
|-
| People || The people related to the object.
|}
</noinclude><includeonly
>{{#set:Has name={{{Name|}}}}}<!--
-->{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}<!--
-->{{#if:{{{Aliases|}}}|{{#set:Has aliases={{{Aliases|}}}}}}}<!--
-->{{#if:{{{Image|}}}|{{#set:Has image={{{Image|}}}}}}}<!--
-->{{#if:{{{Caption|}}}|{{#set:Has caption={{{Caption|}}}}}}}<!--
-->{{#if:{{{Deck|}}}|{{#set:Has deck={{{Deck|}}}}}}}<!--
-->{{#arraymap:{{{Characters|}}}|,|@@|{{SetPropertyPrefix|Has characters|Characters|@@}}| }}<!--
-->{{#arraymap:{{{Concepts|}}}|,|@@|{{SetPropertyPrefix|Has concepts|Concepts|@@}}| }}<!--
-->{{#arraymap:{{{Franchises|}}}|,|@@|{{SetPropertyPrefix|Has franchises|Franchises|@@}}| }}<!--
-->{{#arraymap:{{{Games|}}}|,|@@|{{SetPropertyPrefix|Has games|Games|@@}}| }}<!--
-->{{#arraymap:{{{Locations|}}}|,|@@|{{SetPropertyPrefix|Has locations|Locations|@@}}| }}<!--
-->{{#arraymap:{{{Objects|}}}|,|@@|{{SetPropertyPrefix|Has similar objects|Objects|@@}}| }}<!--
-->{{#arraymap:{{{People|}}}|,|@@|{{SetPropertyPrefix|Has people|People|@@}}| }}<!--
-->{{Infobox
| title={{{Name|}}}
| italic title=no
| image={{{Image|}}}
| image size=40
| caption={{{Caption|}}}
| aliases={{{Aliases|}}}
| deck={{{Deck|}}}
}}<!--
-->{{DISPLAYTITLE:{{{Name|}}}}}[[Category:Objects|{{SUBPAGENAME}}]]
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:Person',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<noinclude>{{#template_params:
  Name (property=Has name)
| Guid (property=Has guid)
| Aliases (property=Has aliases)
| Image (property=Has image)
| Caption (property=Has caption)
| Deck (property=Has deck)
| LastName (property=Has last name)
| Gender (property=Has gender)
| Hometown (property=Has hometown)
| Birthday (property=Has birthday)
| Death (property=Has death)
| Website (property=Has website)
| Twitter (property=Has twitter)
| Characters (property=Has characters)
| Franchises (property=Has franchises)
| Games (property=Has games)
| Locations (property=Has locations)
| Objects (property=has objects)
| People (property=Has similar people)
}}
==Documentation==
This template is used to create person pages, set its display title and infobox.
{| class="wikitable"
|-
! Field Name !! Description
|-
| Name || The display name of the person.
|-
| Guid || The identifier from Giant Bomb. 
|-
| Aliases || Alternative names.
|-
| Image || The image filename of the person. Image appears in the infobox.
|-
| Caption || The caption for the above image.
|-
| Deck || The short description of the person.
|-
| LastName || The person's last name.
|-
| Gender || The person's gender.
|-
| Hometown || The person's hometown.
|-
| Birthday || The person's date of birth.
|-
| Death || The person's date of death.
|-
| Website || The personal website of the person.
|-
| Characters || The characters related to the person.
|-
| Concepts || The concepts related to the person.
|-
| Franchises || The franchises the person worked on.
|-
| Games || The games the person worked on.
|-
| Locations || The locations related to the person.
|-
| Objects || The objects related to the person.
|-
| People || The people similar to the person.
|}
</noinclude><includeonly
>{{#set:Has name={{{Name|}}}}}<!--
-->{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}<!--
-->{{#if:{{{Aliases|}}}|{{#set:Has aliases={{{Aliases|}}}}}}}<!--
-->{{#if:{{{Image|}}}|{{#set:Has image={{{Image|}}}}}}}<!--
-->{{#if:{{{Caption|}}}|{{#set:Has caption={{{Caption|}}}}}}}<!--
-->{{#if:{{{Deck|}}}|{{#set:Has deck={{{Deck|}}}}}}}<!--
-->{{#if:{{{LastName|}}}|{{#set:Has last name={{{LastName|}}}}}}}<!--
-->{{#if:{{{Gender|}}}|{{#set:Has gender={{{Gender|}}}}}}}<!--
-->{{#if:{{{Hometown|}}}|{{#set:Has hometown={{{Hometown|}}}}}}}<!--
-->{{#if:{{{Birthday|}}}|{{#set:Has birthday={{{Birthday|}}}}}}}<!--
-->{{#if:{{{Death|}}}|{{#set:Has death={{{Death|}}}}}}}<!--
-->{{#if:{{{Website|}}}|{{#set:Has website={{{Website|}}}}}}}<!--
-->{{#arraymap:{{{Characters|}}}|,|@@|{{SetPropertyPrefix|Has characters|Characters|@@}}| }}<!--
-->{{#arraymap:{{{Concepts|}}}|,|@@|{{SetPropertyPrefix|Has concepts|Concepts|@@}}| }}<!--
-->{{#arraymap:{{{Franchises|}}}|,|@@|{{SetPropertyPrefix|Has franchises|Franchises|@@}}| }}<!--
-->{{#arraymap:{{{Games|}}}|,|@@|{{SetPropertyPrefix|Has games|Games|@@}}| }}<!--
-->{{#arraymap:{{{Locations|}}}|,|@@|{{SetPropertyPrefix|Has locations|Locations|@@}}| }}<!--
-->{{#arraymap:{{{Objects|}}}|,|@@|{{SetPropertyPrefix|Has objects|Objects|@@}}| }}<!--
-->{{#arraymap:{{{People|}}}|,|@@|{{SetPropertyPrefix|Has similar people|People|@@}}| }}<!--
-->{{Infobox
| title={{{Name|}}}
| italic title=no
| image={{{Image|}}}
| image size=40
| caption={{{Caption|}}}
| aliases={{{Aliases|}}}
| deck={{{Deck|}}}
| last name={{{LastName|}}}
| gender={{{Gender|}}}
| hometown={{{Hometown|}}}
| birthday={{{Birthday|}}}
| death={{{Death|}}}
| website={{{Website|}}}
}}<!--
-->{{DISPLAYTITLE:{{{Name|}}}}}[[Category:People|{{SUBPAGENAME}}]]
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:Platform',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<noinclude>{{#template_params:
  Name (property=Has name)
| Guid (property=Has guid)
| Aliases (property=Has aliases)
| Image (property=Has image)
| Caption (property=Has caption)
| Deck (property=Has deck)
| ShortName (property=Has short name)
| ReleaseDate (property=Has release date)
| ReleaseDateType (property=Has release date type)
| InstallBase (property=Has install base)
| OnlineSupport (property=Has online support)
| Manufacturer (property=Has manufacturer)
}}
==Documentation==
This template is used to create platform pages, set its display title and infobox.
{| class="wikitable"
|-
! Field Name !! Description
|-
| Name || The display name of the platform.
|-
| Guid || The identifier from Giant Bomb. 
|-
| Aliases || Alternative names.
|-
| Image || The image filename of the platform. Image appears in the infobox.
|-
| Caption || The caption for the above image.
|-
| Deck || The short description of the platform.
|-
| ShortName || The short name for the platform.
|-
| ReleaseDate || The release date of the platform.
|-
| ReleaseDateType || A flag to determine the condition of the release date.
|-
| InstallBase || The amount of people using the platform.
|-
| OnlineSupport || Whether the platform has online support. Yes/No
|-
| OriginalPrice || The launch price of the platform in US dollars.
|-
| Manufacturer || The manufacturer of the platform.
|}
</noinclude><includeonly
>{{#set:Has name={{{Name|}}}}}<!--
-->{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}<!--
-->{{#if:{{{Aliases|}}}|{{#set:Has aliases={{{Aliases|}}}}}}}<!--
-->{{#if:{{{Image|}}}|{{#set:Has image={{{Image|}}}}}}}<!--
-->{{#if:{{{Caption|}}}|{{#set:Has caption={{{Caption|}}}}}}}<!--
-->{{#if:{{{Deck|}}}|{{#set:Has deck={{{Deck|}}}}}}}<!--
-->{{#if:{{{ShortName|}}}|{{#set:Has short name={{{ShortName|}}}}}}}<!--
-->{{#if:{{{ReleaseDate|}}}|{{#set:Has release date={{{ReleaseDate|}}}}}}}<!--
-->{{#if:{{{ReleaseDateType|}}}|{{#set:Has release date type={{{ReleaseDateType|}}}}}}}<!--
-->{{#if:{{{InstallBase|}}}|{{#set:Has install base={{{InstallBase|}}}}}}}<!--
-->{{#if:{{{OnlineSupport|}}}|{{#set:Has online support={{{OnlineSupport|}}}}}}}<!--
-->{{#if:{{{OriginalPrice|}}}|{{#set:Has original price={{{OriginalPrice|}}}}}}}<!--
-->{{#arraymap:{{{Manufacturer|}}}|,|@@|{{SetPropertyPrefix|Has manufacturer|Companies|@@}}| }}<!--
-->{{Infobox
| title={{{Name|}}}
| italic title=no
| image={{{Image|}}}
| image size=40
| caption={{{Caption|}}}
| aliases={{{Aliases|}}}
| deck={{{Deck|}}}
}}<!--
-->{{DISPLAYTITLE:{{{Name|}}}}}[[Category:Platforms|{{SUBPAGENAME}}]]
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:Rating',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<noinclude>{{#template_params:
  Name (property=Has name)
| Guid (property=Has guid)
| Explanation (property=Stands for)
| Image (property=Has image)
| Caption (property=Has caption)
}}
==Documentation==
This template is used to create rating pages, sets its display title and infobox.
{| class="wikitable"
|-
! Field Name !! Description
|-
| Name || The display name of the rating.
|-
| Guid || The identifier from Giant Bomb. 
|-
| Explanation || The short phrase explaining of the rating.
|-
| Image || The image filename of the rating. Image appears in the infobox.
|-
| Caption || The caption for the above image.
|}
</noinclude><includeonly
>{{#set:Has name={{{Name|}}}}}<!--
-->{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}<!--
-->{{#if:{{{Explanation|}}}|{{#set:Stands for={{{Explanation|}}}}}}}<!--
-->{{#if:{{{Image|}}}|{{#set:Has image={{{Image|}}}}}}}<!--
-->{{#if:{{{Caption|}}}|{{#set:Has caption={{{Caption|}}}}}}}<!--
-->{{Infobox
| title={{{Name|}}}
| italic title=no
| image={{{Image|}}}
| image size=40
| caption={{{Caption|}}}
| stands for={{{Explanation|}}}
}}<!--
-->{{DISPLAYTITLE:{{{Name|}}}}}[[Category:Ratings|{{SUBPAGENAME}}]]
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:ReleaseSubobject',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
{{#subobject: {{#invoke:Identifiers|getReleaseIdentifier|Name={{{Name|}}}|Region={{{Region|}}}|Platform={{{Platform|}}}}} |
|Has object type=Release
|Has composite name={{{Name|}}} ({{{Platform|}}}, {{{Region|}}})
|Has games={{{Game|}}}
|Has name={{{Name|}}}
|Has guid={{{Guid|}}}
|Has image={{{Image|}}}
|Has region={{{Region|}}}
|Has platforms={{#arraymap:{{{Platform|}}}|,|@@|@@|;}}|+sep=;
|Has rating={{{Rating|}}}
|Has developers={{{Developers|}}}
|Has publishers={{{Publishers|}}}
|Has release date={{{ReleaseDate|}}}
|Has release date type={{{ReleaseDateType|}}}
|Has product code={{{ProductCode|}}}
|Has product code type={{{ProductCodeType|}}}
|Has company code={{{CompanyCode|}}}
|Has company code type={{{CompanyCodeType|}}}
|Has widescreen support={{{WidescreenSupport|}}}
|Has resolutions={{{Resolutions|}}}
|Has sound systems={{{SoundSystems|}}}
|Has single player features={{{SinglePlayerFeatures|}}}
|Has multiplayer features={{{MultiplayerFeatures|}}}
|Has minimum players={{{MinimumPlayers|}}}
|Has maximum players={{{MaximumPlayers|}}}
}}
MARKUP,
            ],
            [
                'title' => 'Template:Releases',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<noinclude>{{#template_params:
 ParentPage (property=Has superpage)
}}
==Documentation==
This template is used to create release subobjects.
{| class="wikitable"
|-
! Field Name !! Description
|-
| ParentPage || The parent page.
|-
| ReleaseSubobject || The game releases stored as subobjects.
|-
| ↳ Game || Game of release.
|-
| ↳ Name || Name of release.
|-
| ↳ Guid || Guid of release.
|-
| ↳ Image || Image of release.
|-
| ↳ Region || Region of release.
|-
| ↳ Platform || Platform the release is on.
|-
| ↳ Rating || Rating of release.
|-
| ↳ Developer || Developers of release.
|-
| ↳ Publisher || Image of release.
|-
| ↳ ReleaseDate || Release date of release.
|-
| ↳ ReleaseDateType || Format for release date.
|-
| ↳ ProductCode || Numeric identifier representing the release product.
|-
| ↳ ProductCodeType || Type of product code (e.g. UPC).
|-
| ↳ CompanyCode || Identifier of release on a console platform
|-
| ↳ CompanyCodeType || The company the code is for.
|-
| ↳ WidescreenSupport || If the release supports widescreens. (The Rorie Test)
|-
| ↳ Resolutions || Resolutions the release supports.
|-
| ↳ SoundSystems || Sound systems the release supports.
|-
| ↳ SinglePlayerFeatures || Single player features the release supports.
|-
| ↳ MultiplayerFeatures || Multi-player features release supports.
|-
| ↳ MinimumPlayers || Minimum amount of players the release supports.
|-
| ↳ MaxmiumPlayers || Maximum amount of players the release supports.
|}
</noinclude><includeonly>{{#set:Has superpage={{{ParentPage|}}}}}<!--
-->{{#ifeq:{{#titleparts:{{FULLPAGENAME}}||-1}}|Releases|[[Category:Releases]]}}<!--
-->{{#ask: [[-Has subobject::{{FULLPAGENAME}}]] [[Has object type::Release]]
|format=broadtable
|link=none
|mainlabel=-
|?Has image=Image
|?Has name=Name
|?Has region=Region
|?Has platforms=Platform
|?Has rating=Rating
|?Has developers=Developers
|?Has publishers=Publishers
|?Has release date=ReleaseDate
|?Has release date type=ReleaseDateType
|?Has product code=ProductCode
|?Has product code type=ProductCodeType
|?Has company code=CompanyCode
|?Has company code type=CompanyCodeType
|?Has widescreen support=WidescreenSupport
|?Has resolutions=Resolutions
|?Has sound systems=SoundSystems
|?Has single player features=SinglePlayerFeatures
|?Has multiplayer features=MultiplayerFeatures
|?Has minimum players=MinimumPlayers
|?Has maximum players=MaximumPlayers
}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:Resolution',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<noinclude>{{#template_params:
  Name (property=Has name)
| Guid (property=Has guid)
}}
==Documentation==
This template is used to create resolution pages, sets its display title and infobox.
{| class="wikitable"
|-
! Field Name !! Description
|-
| Name || The display name of the resolution.
|-
| Guid || The identifier from Giant Bomb. 
|}
</noinclude><includeonly
>{{#set:Has name={{{Name|}}}}}<!--
-->{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}<!--
-->{{Infobox
| title={{{Name|}}}
| italic title=no
}}<!--
-->{{DISPLAYTITLE:{{{Name|}}}}}[[Category:Resolutions|{{SUBPAGENAME}}]]
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:Single Player Feature',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<noinclude>{{#template_params:
  Name (property=Has name)
| Guid (property=Has guid)
}}
==Documentation==
This template is used to create single player features pages, sets its display title and infobox.
{| class="wikitable"
|-
! Field Name !! Description
|-
| Name || The display name of the single player feature.
|-
| Guid || The identifier from Giant Bomb. 
|}
</noinclude><includeonly
>{{#set:Has name={{{Name|}}}}}<!--
-->{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}<!--
-->{{Infobox
| title={{{Name|}}}
| italic title=no
}}<!--
-->{{DISPLAYTITLE:{{{Name|}}}}}[[Category:Single Player Features|{{SUBPAGENAME}}]]
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:Sound System',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<noinclude>{{#template_params:
  Name (property=Has name)
| Guid (property=Has guid)
}}
==Documentation==
This template is used to create sound system pages, sets its display title and infobox.
{| class="wikitable"
|-
! Field Name !! Description
|-
| Name || The display name of the sound system.
|-
| Guid || The identifier from Giant Bomb. 
|}
</noinclude><includeonly
>{{#set:Has name={{{Name|}}}}}<!--
-->{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}<!--
-->{{Infobox
| title={{{Name|}}}
| italic title=no
}}<!--
-->{{DISPLAYTITLE:{{{Name|}}}}}[[Category:Sound Systems|{{SUBPAGENAME}}]]
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:Theme',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<noinclude>{{#template_params:
  Name (property=Has name)
| Guid (property=Has guid)
| Image (property=Has image)
| Caption (property=Has caption)
| Deck (property=Has deck)
}}
==Documentation==
This template is used to create theme pages, set its display title and infobox.
{| class="wikitable"
|-
! Field Name !! Description
|-
| Name || The display name of the theme.
|-
| Guid || The identifier from Giant Bomb. 
|-
| Image || The image filename of the theme. Image appears in the infobox.
|-
| Caption || The caption for the above image.
|-
| Deck || The short description for the theme.
|}
</noinclude><includeonly
>{{#set:Has name={{{Name|}}}}}<!--
-->{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}<!--
-->{{#if:{{{Image|}}}|{{#set:Has image={{{Image|}}}}}}}<!--
-->{{#if:{{{Caption|}}}|{{#set:Has caption={{{Caption|}}}}}}}<!--
-->{{#if:{{{Deck|}}}|{{#set:Has deck={{{Deck|}}}}}}}<!--
-->{{Infobox
| title={{{Name|}}}
| italic title=no
| image={{{Image|}}}
| image size=40
| caption={{{Caption|}}}
| deck={{{Deck|}}}
}}<!--
-->{{DISPLAYTITLE:{{{Name|}}}}}[[Category:Themes|{{SUBPAGENAME}}]]
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:Infobox',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
{{main other|{{short description|2=noreplace|{{{deck|}}}}}}}{{#invoke:infobox|infoboxTemplate
<!-- Start and Styling -->
| child          = {{{child|}}}
| subbox         = {{{subbox|}}}
| bodyclass      = ib-content hproduct {{#ifeq:{{{collapsible|}}}|yes|collapsible {{#if:{{{state|}}}|{{{state}}}|autocollapse}}}}
| templatestyles = Infobox/styles.css
| aboveclass     = fn
| italic title   = {{{italic title|<noinclude>no</noinclude>}}}

<!-- Title -->
| above          = <includeonly>{{{title|{{PAGENAMEBASE}}}}}</includeonly>

<!-- Image -->
| image          = {{#invoke:InfoboxImage|InfoboxImage|image={{{image|}}}|size={{{image size|{{{image_size|{{{imagesize|}}}}}}}}}|sizedefault=frameless|upright={{{image_upright|1}}}|alt={{{alt|}}}|border={{{border|}}}|suppressplaceholder=yes}}

| caption        = {{{caption|}}}

<!-- Start of content -->
| label2  = [[Companies|Developer(s)]]
| data2   = {{{developers|}}}

| label3  = [[Companies|Publisher(s)]]
| data3   = {{{publishers|}}}

| label4 = [[Franchises|Franchise(s)]]
| data4  = {{{franchises|}}}

| label5 = [[Platforms|Platform(s)]]
| data15  = {{{platforms|}}}

| label6 = [[Genres|Genre(s)]]
| data16  = {{{genres|}}}

| label7 = Stands for
| data17 = {{{stands for|}}}

| label8 = Aliases
| data18 = {{{aliases|}}}

| label9 = Gender
| data9 = {{{gender|}}}

| label10 = Birthday
| data10 = {{{birthday|}}}

| label11 = Real Name
| data11 = {{{real name|}}}

| label12 = Death
| data12 = {{{death|}}}

| label13 = Guid
| data13 = {{{guid|}}}

| label14 = [[Games|Game(s)]]
| data14 = {{{games|}}}

| label15 = Abbreviation
| data15 = {{{abbreviation|}}}

| label16 = Founded Date
| data16 = {{{founded date|}}}

| label17 = Address
| data117 = {{{address|}}}

| label18 = City
| data18 = {{{city|}}}

| label19 = Country
| data19 = {{{country|}}}

| label20 = State
| data20 = {{{state|}}}

| label21 = Phone
| data21 = {{{phone|}}}

| label22 = Website
| data22 = {{{website|}}}

| label23 = Last Name
| data23 = {{{last name|}}}

| label24 = Hometown
| data24 = {{{hometown|}}}

| label25 = Twitter
| data25 = {{{twitter|}}}

| label26 = Short Name
| data26 = {{{short name|}}}

| label27 = Release Date
| data27 = {{{release date|}}}

| label28 = Install Base
| data28 = {{{install base|}}}

| label29 = Online Support
| data29 = {{{online support|}}}

| label30 = [[Companies|Manufacturer]]
| data30 = {{{manufacturer|}}}

| label31 = [[Characters|Character(s)]]
| data31 = {{{characters|}}}

| label32 = [[Concepts|Concept(s)]]
| data32 = {{{concepts|}}}

| label33 = [[Characters|Enemies]]
| data33 = {{{enemies|}}}

| label34 = [[Themes|Theme(s)]]
| data34 = {{{themes|}}}

| label35 = [[Characters|Friends]]
| data35 = {{{friends|}}}

| label36 = [[Locations|Location(s)]]
| data36 = {{{locations|}}}

| label37 = [[People|People]]
| data37 = {{{people|}}}

| label38 = [[Objects|Object(s)]]
| data38 = {{{object|}}}

| label39 = [[Games|Developed Game(s)]]
| data39 = {{{developed games|}}}

| label40 = [[Games|Published Game(s)]]
| data40 = {{{published games|}}}

| label41 = Release Date Type
| data41 = {{{release date type|}}}

<!-- For embedded content -->
| data50  = {{{embedded|}}}

}}{{main other|{{#ifeq:{{lc:{{{italic title|}}}}}|no||{{italic title|force={{#ifeq:{{lc:{{{italic title|}}}}}|force|true}}}}}}
}}{{#invoke:Check for unknown parameters|check|unknown={{main other|[[Category:Pages using infobox with unknown parameters|_VALUE_{{PAGENAME}}]]}}|ignoreblank=1|preview=Page using [[Template:Infobox]] with unknown parameter "_VALUE_"| abbreviation | address | alt | birthday | border | caption | characters | child | city | collapsible | commons | concepts | country | death | develoepd games | developers | embedded | enemies | founded date | franchises | friends | games | gender | genre | guid | hometown | image | image_size | image_upright | install base | italic title | last name | locations | manufacturer | noicon | objects | online support | onlysourced | manufacturer | people | phone | platform | platforms | published games | publishers | qid | refs | real name | released | release date | release date type | short name | stands for | state | subbox | suppressfields | themes | title | twitter | website }}<noinclude>
{{documentation}}
</noinclude>
MARKUP,
            ],
            [
                'title' => 'Template:Infobox/styles.css',
                'namespace' => $this->namespaces['template'],
                'model' => 'sanitized-css',
                'format' => 'text/css',
                'description' => <<<MARKUP
/* {{pp-template|small=yes}} */
.ib-content .infobox-label {
    white-space: nowrap;
    /* to ensure gap between any long/nonwrapped label and subsequent data on same line */
    padding-right: 0.65em; 
}

.ib-content .infobox-above {
    font-style: italic; font-size: 125%;
}
MARKUP,
            ],
            [
                'title' => 'Template:CreateWithForm',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<includeonly>
{{#formlink:
  form={{{1}}}
| link text=Create with form
| target={{PAGENAME}}
}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:SetPropertyPrefix',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
{{#if: {{#explode:{{{3}}}|/|1}}|{{#set:{{{1|}}}={{{3|}}}}}|{{#set:{{{1|}}}={{{2|}}}/{{{3|}}}}} }}
<noinclude>
This template automatically prefixes a namespace to allow editors to enter the wiki name without the namespace.

==Usage==
<nowiki>{{SetPropertyPrefix|Property Name|Namespace|Page Name}}</nowiki>

==Example==
<nowiki>{{SetPropertyPrefix|Has character|Characters|Mario}}</nowiki>
</noinclude>
MARKUP,
            ],
        ];

        $this->createXML('templates.xml', $data);
    }
}

$maintClass = GenerateXMLTemplates::class;

require_once RUN_MAINTENANCE_IF_MAIN; 
