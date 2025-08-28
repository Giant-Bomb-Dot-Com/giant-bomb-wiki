<?php

require_once(__DIR__.'/common.php');

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
{! class="wikitable"
|+ Field descriptions
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
{! class="wikitable"
|+ Field descriptions
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
-->{{#arraymap:{{{Concepts|}}}|,|@@|{{SetPropertyPrefix|Has concepts|Concepts|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Enemies|}}}|,|@@|{{SetPropertyPrefix|Has enemies|Enemies|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Franchises|}}}|,|@@|{{SetPropertyPrefix|Has franchises|Franchises|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Friends|}}}|,|@@|{{SetPropertyPrefix|Has friends|Friends|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Games|}}}|,|@@|{{SetPropertyPrefix|Has games|Games|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Locations|}}}|,|@@|{{SetPropertyPrefix|Has locations|Locations|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Objects|}}}|,|@@|{{SetPropertyPrefix|Has objects|Objects|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{People|}}}|,|@@|{{SetPropertyPrefix|Has people|People|{{#trim:@@}}}}| }}<!--
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
{! class="wikitable"
|+ Field descriptions
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
| Abbereviation || The abbreviation of the company's name.
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
-->{{#if:{{{Abbereviation|}}}|{{#set:Has abbreviation={{{Abbereviation|}}}}}}}<!--
-->{{#if:{{{FoundedDate|}}}|{{#set:Has founded date={{{FoundedDate|}}}}}}}<!--
-->{{#if:{{{Address|}}}|{{#set:Has address={{{Address|}}}}}}}<!--
-->{{#if:{{{City|}}}|{{#set:Has city={{{City|}}}}}}}<!--
-->{{#if:{{{Country|}}}|{{#set:Has country={{{Country|}}}}}}}<!--
-->{{#if:{{{State|}}}|{{#set:Has state={{{State|}}}}}}}<!--
-->{{#if:{{{Phone|}}}|{{#set:Has phone={{{Phone|}}}}}}}<!--
-->{{#if:{{{Website|}}}|{{#set:Has website={{{Website|}}}}}}}<!--
-->{{#arraymap:{{{Characters|}}}|,|@@|{{SetPropertyPrefix|Has characters|Characters|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Concepts|}}}|,|@@|{{SetPropertyPrefix|Has concepts|Concepts|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Locations|}}}|,|@@|{{SetPropertyPrefix|Has locations|Locations|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Objects|}}}|,|@@|{{SetPropertyPrefix|Has objects|Objects|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{People|}}}|,|@@|{{SetPropertyPrefix|Has people|People|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Developed|}}}|,|@@|{{SetPropertyPrefix|Has developed games|Games|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Published|}}}|,|@@|{{SetPropertyPrefix|Has published games|Games|{{#trim:@@}}}}| }}<!--
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
| Concepts (property=Has concepts)
| Franchises (property=Has franchises)
| Games (property=Has games)
| Locations (property=Has locations)
| Objects (property=Has objects)
| People (property=Has people)
}}
==Documentation==
This template is used to create concept pages, set its display title and infobox.
{! class="wikitable"
|+ Field descriptions
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
-->{{#arraymap:{{{Characters|}}}|,|@@|{{SetPropertyPrefix|Has characters|Characters|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Concepts|}}}|,|@@|{{SetPropertyPrefix|Has similar concepts|Concepts|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Franchises|}}}|,|@@|{{SetPropertyPrefix|Has franchises|Franchises|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Games|}}}|,|@@|{{SetPropertyPrefix|Has games|Games|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Locations|}}}|,|@@|{{SetPropertyPrefix|Has locations|Locations|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Objects|}}}|,|@@|{{SetPropertyPrefix|Has objects|Objects|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{People|}}}|,|@@|{{SetPropertyPrefix|Has people|People|{{#trim:@@}}}}| }}<!--
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
                'title' => 'Template:DLC',
                'namespace' => $this->namespaces['template'],
                'description' => ''
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
}}
==Documentation==
This template is used to create franchise pages, set its display title and infobox.
{! class="wikitable"
|+ Field descriptions
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
| Objects || The objects similar to the franchise.
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
-->{{#arraymap:{{{Characters|}}}|,|@@|{{SetPropertyPrefix|Has characters|Characters|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Concepts|}}}|,|@@|{{SetPropertyPrefix|Has concepts|Concepts|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Games|}}}|,|@@|{{SetPropertyPrefix|Has games|Games|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Locations|}}}|,|@@|{{SetPropertyPrefix|Has locations|Locations|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Objects|}}}|,|@@|{{SetPropertyPrefix|Has similar objects|Objects|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{People|}}}|,|@@|{{SetPropertyPrefix|Has people|People|{{#trim:@@}}}}| }}<!--
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
}}
==Documentation==
This template is used to create game pages, set its display title and infobox.
{! class="wikitable"
|+ Field descriptions
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
-->{{DISPLAYTITLE:{{{Name|}}}}}[[Category:Games|{{SUBPAGENAME}}]]
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
| Aliases (property=Has aliases)
| Image (property=Has image)
| Caption (property=Has caption)
| Deck (property=Has deck)
}}
==Documentation==
This template is used to create genre pages, set its display title and infobox.
{! class="wikitable"
|+ Field descriptions
|-
! Field Name !! Description
|-
| Name || The display name of the genre.
|-
| Guid || The identifier from Giant Bomb. 
|-
| Aliases || Alternative names.
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
-->{{DISPLAYTITLE:{{{Name|}}}}}[[Category:Genres|{{SUBPAGENAME}}]]
</includeonly>
MARKUP,
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
}}
==Documentation==
This template is used to create location pages, set its display title and infobox.
{! class="wikitable"
|+ Field descriptions
|-
! Field Name !! Description
|-
| Name || The display name of the location.
|-
| Guid || The identifier from Giant Bomb. 
|-
| Aliases || Alternative names.
|-
| Image || The image filename of the location. Image appears in the infobox.
|-
| Caption || The caption for the above image.
|-
| Deck || The short description of the location.
|-
| Characters || The characters related to the object.
|-
| Concepts || The concepts related to the object.
|-
| Locations || The locations related to the object.
|-
| Objects || The objects similar to the object.
|}
</noinclude><includeonly
>{{#set:Has name={{{Name|}}}}}<!--
-->{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}<!--
-->{{#if:{{{Aliases|}}}|{{#set:Has aliases={{{Aliases|}}}}}}}<!--
-->{{#if:{{{Image|}}}|{{#set:Has image={{{Image|}}}}}}}<!--
-->{{#if:{{{Caption|}}}|{{#set:Has caption={{{Caption|}}}}}}}<!--
-->{{#if:{{{Deck|}}}|{{#set:Has deck={{{Deck|}}}}}}}<!--
-->{{#arraymap:{{{Characters|}}}|,|@@|{{SetPropertyPrefix|Has characters|Characters|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Concepts|}}}|,|@@|{{SetPropertyPrefix|Has concepts|Concepts|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Locations|}}}|,|@@|{{SetPropertyPrefix|Has similar locations|Locations|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Objects|}}}|,|@@|{{SetPropertyPrefix|Has objects|Objects|{{#trim:@@}}}}| }}<!--
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
}}
==Documentation==
This template is used to create object pages, set its display title and infobox.
{! class="wikitable"
|+ Field descriptions
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
-->{{#arraymap:{{{Characters|}}}|,|@@|{{SetPropertyPrefix|Has characters|Characters|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Concepts|}}}|,|@@|{{SetPropertyPrefix|Has concepts|Concepts|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Franchises|}}}|,|@@|{{SetPropertyPrefix|Has franchises|Franchises|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Games|}}}|,|@@|{{SetPropertyPrefix|Has games|Games|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Locations|}}}|,|@@|{{SetPropertyPrefix|Has locations|Locations|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Objects|}}}|,|@@|{{SetPropertyPrefix|Has similar objects|Objects|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{People|}}}|,|@@|{{SetPropertyPrefix|Has people|People|{{#trim:@@}}}}| }}<!--
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
| People (property=Has people)
}}
==Documentation==
This template is used to create person pages, set its display title and infobox.
{! class="wikitable"
|+ Field descriptions
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
| People || The people simiilar to the person.
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
-->{{#arraymap:{{{Characters|}}}|,|@@|{{SetPropertyPrefix|Has characters|Characters|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Concepts|}}}|,|@@|{{SetPropertyPrefix|Has similar concepts|Concepts|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Franchises|}}}|,|@@|{{SetPropertyPrefix|Has franchises|Franchises|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Games|}}}|,|@@|{{SetPropertyPrefix|Has games|Games|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Locations|}}}|,|@@|{{SetPropertyPrefix|Has locations|Locations|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{Objects|}}}|,|@@|{{SetPropertyPrefix|Has objects|Objects|{{#trim:@@}}}}| }}<!--
-->{{#arraymap:{{{People|}}}|,|@@|{{SetPropertyPrefix|Has similar people|People|{{#trim:@@}}}}| }}<!--
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
{! class="wikitable"
|+ Field descriptions
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
-->{{#arraymap:{{{Manufacturer|}}}|,|@@|{{SetPropertyPrefix|Has manufacturer|Companies|{{#trim:@@}}}}| }}<!--
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
{! class="wikitable"
|+ Field descriptions
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
-->>{{Infobox
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
                'title' => 'Template:Release',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
            [
                'title' => 'Template:Theme',
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
This template is used to create theme pages, set its display title and infobox.
{! class="wikitable"
|+ Field descriptions
|-
! Field Name !! Description
|-
| Name || The display name of the theme.
|-
| Guid || The identifier from Giant Bomb. 
|-
| Aliases || Alternative names.
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
| deck={{{Deck|}}}
| aliases={{{Aliases|}}}
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
| data27 = {{{release date type|}}}

| label28 = Install Base
| data28 = {{{install base|}}}

| label29 = Online Support
| data29 = {{{online support|}}}

| label30 = [[Companies|Manufacturer]]
| data30 = {{{manufacturer|}}}

| label31 = [[Characters|Character(s)]
| data31 = {{{characters|}}}

| label32 = [[Concepts|Concept(s)]]
| data32 = {{{concepts|}}}

| label33 = [[Characters|Enemies]]
| data33 = {{{enemies|}}}

| label6 = [[Themes|Theme(s)]]
| data16  = {{{themes|}}}

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
{{#explode:{{{3}}}|/|0|Has_Prefix}}
{{#if:{{#var:Has_Prefix}}|{{#set:{{{1|}}}={{{3|}}}}}|{{#set:{{{1|}}}={{{2|}}}/{{{3|}}}}} }}
<noinclude>
This template automatically prefixes a value and sets a semantic property to allow editors to enter the wiki name without the category in the semantic table.

==Usage==
<nowiki>{{SetPrefixedProperty|Property Name|Namespace|Page Name}}</nowiki>

==Example==
<nowiki>{{SetPrefixedProperty|Has character|Characters|Mario}}</nowiki>
</noinclude>
MARKUP,
            ],
        ];

        $this->createXML('templates.xml', $data);
    }
}

$maintClass = GenerateXMLTemplates::class;

require_once RUN_MAINTENANCE_IF_MAIN; 
