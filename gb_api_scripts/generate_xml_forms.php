<?php

require_once(__DIR__.'/libs/common.php');

class GenerateXMLForms extends Maintenance
{
    use CommonVariablesAndMethods;

    public function __construct() 
    {
        parent::__construct();
        $this->addDescription("Generates XML for forms");
    }

    public function execute()
    {
        $data = [
            [
                'title' => 'Form:Accessory',
                'namespace' => $this->namespaces['form'],
                'description' => <<<MARKUP
<noinclude>
This is the "Accessory" form.
To create a page with this form, enter the page name below;
if a page with that name already exists, you will be sent to a form to edit that page.

{{#forminput:form=Accessory|super_page=Accessories}}

</noinclude><includeonly>
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
{{{for template|Accessory}}}
{| class="formtable"
|-
! Name: 
| {{{field|Name|property=Has name}}}
|-
! Guid: 
| {{{field|Guid|property=Has guid}}}
|-
! Image: 
| {{{field|Image|property=Has image}}}
|-
! Caption: 
| {{{field|Image|property=Has caption}}}
|-
! Deck: 
| {{{field|Deck|property=Has deck}}}
|-
! Description:
| {{{standard input|free text|rows=10}}}
|}
{{{end template}}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Form:Character',
                'namespace' => $this->namespaces['form'],
                'description' => <<<MARKUP
<noinclude>
This is the "Character" form.
To create a page with this form, enter the page name below;
if a page with that name already exists, you will be sent to a form to edit that page.

{{#forminput:form=Character|super_page=Characters}}

</noinclude><includeonly>
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
{{{for template|Character}}}
{| class="formtable"
|-
! Name: 
| {{{field|Name|property=Has name}}}
|-
! Guid:
| {{{field|Guid|property=Has guid}}}
|-
! Aliases:
| {{{field|Aliases|property=Has aliases}}}
|-
! Image:
| {{{field|Image|property=Has image}}}
|-
! Caption:
| {{{field|Caption|property=Has caption}}}
|-
! Deck:
| {{{field|Deck|property=Has deck}}}
|-
! Real Name:
| {{{field|RealName|property=Has real name}}}
|-
! Gender:
| {{{field|Aliases|property=Has gender}}}
|-
! Birthday:
| {{{field|Birthday|property=Has birthday}}}
|-
! Concepts:
| {{{field|Concepts|property=Has concepts|input type=tokens|values from category=Concepts}}}
|-
! Enemies:
| {{{field|Enemies|property=Has enemies|input type=tokens|values from category=Characters}}}
|-
! Friends:
| {{{field|Friends|property=Has friends|input type=tokens|values from category=Characters}}}
|-
! Franchises:
| {{{field|Franchises|property=Has franchises|input type=tokens|values from category=Franchises}}}}
|-
! Games:
| {{{field|Games|property=Has games|input type=tokens|values from category=Games}}}
|-
! Locations:
| {{{field|Locations|property=Has locations|input type=tokens|values from category=Locations}}}
|-
! Objects:
| {{{field|Objects|property=Has objects|input type=tokens|values from category=Objects}}}
|-
! People:
| {{{field|People|property=Has people|input type=tokens|values from category=People}}}
|-
! Description:
| {{{standard input|free text|rows=10}}}
|}
{{{end template}}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Form:Company',
                'namespace' => $this->namespaces['form'],
                'description' => <<<MARKUP
<noinclude>
This is the "Company" form.
To create a page with this form, enter the page name below;
if a page with that name already exists, you will be sent to a form to edit that page.

{{#forminput:form=Company|super_page=Companies}}

</noinclude><includeonly>
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
{{{for template|Company}}}
{| class="formtable"
|-
! Name: 
| {{{field|Name|property=Has name}}}
|-
! Guid:
| {{{field|Guid|property=Has guid}}}
|-
! Aliases:
| {{{field|Aliases|property=Has aliases}}}
|-
! Image:
| {{{field|Image|property=Has image}}}
|-
! Caption:
| {{{field|Caption|property=Has caption}}}
|-
! Deck:
| {{{field|Deck|property=Has deck}}}
|-
! Abbreviation:
| {{{field|Abbreviation|property=Has abbreviation}}}
|-
! Founded Date:
| {{{field|FoundedDate|property=Has founded date}}}
|-
! Address:
| {{{field|Address|property=Has address}}}
|-
! City:
| {{{field|City|property=Has city}}}
|-
! Country:
| {{{field|Country|property=Has country}}}
|-
! State:
| {{{field|State|property=Has state}}}
|-
! Phone:
| {{{field|Phone|property=Has phone}}}
|-
! Website:
| {{{field|Website|property=Has website}}}
|-
! Characters:
| {{{field|Characters|property=Has characters|input type=tokens|values from category=Characters}}}
|-
! Concepts:
| {{{field|Concepts|property=Has concepts|input type=tokens|values from category=Concepts}}}
|-
! Locations:
| {{{field|Locations|property=Has locations|input type=tokens|values from category=Locations}}}
|-
! Objects:
| {{{field|Objects|property=Has objects|input type=tokens|values from category=Objects}}}
|-
! People:
| {{{field|People|property=Has people|input type=tokens|values from category=People}}}
|-
! Developed Games:
| {{{field|Developed|property=Has developed games|input type=tokens|values from category=Games}}}
|-
! Published Games:
| {{{field|Published|property=Has published games|input type=tokens|values from category=Games}}}
|-
! Description:
| {{{standard input|free text|rows=10}}}
|}
{{{end template}}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Form:Concept',
                'namespace' => $this->namespaces['form'],
                'description' => <<<MARKUP
<noinclude>
This is the "Concept" form.
To create a page with this form, enter the page name below;
if a page with that name already exists, you will be sent to a form to edit that page.

{{#forminput:form=Concept|super_page=Concepts}}

</noinclude><includeonly>
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
{{{for template|Concept}}}
{| class="formtable"
|-
! Name: 
| {{{field|Name|property=Has name}}}
|-
! Guid:
| {{{field|Guid|property=Has guid}}}
|-
! Aliases:
| {{{field|Aliases|property=Has aliases}}}
|-
! Image:
| {{{field|Image|property=Has image}}}
|-
! Caption:
| {{{field|Caption|property=Has caption}}}
|-
! Deck:
| {{{field|Deck|property=Has deck}}}
|-
! Characters:
| {{{field|Characters|property=Has characters|input type=tokens|values from category=Characters}}}
|-
! Concepts:
| {{{field|Concepts|property=Has similar concepts|input type=tokens|values from category=Concepts}}}
|-
! Locations:
| {{{field|Locations|property=Has locations|input type=tokens|values from category=Locations}}}
|-
! Franchises:
| {{{field|Franchises|property=Has franchises|input type=tokens|values from category=Franchises}}}
|-
! Games:
| {{{field|Games|property=Has games|input type=tokens|values from category=Games}}}
|-
! Objects:
| {{{field|Objects|property=Has objects|input type=tokens|values from category=Objects}}}
|-
! People:
| {{{field|People|property=Has people|input type=tokens|values from category=People}}}
|-
! Description:
| {{{standard input|free text|rows=10}}}
|}
{{{end template}}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Form:DLC',
                'namespace' => $this->namespaces['form'],
                'description' => <<<MARKUP
<noinclude>
This is the "DLC" form.
To create a page with this form, enter the page name below;
if a page with that name already exists, you will be sent to a form to edit that page.

{{#forminput:form=DLC|super_page=DLCs}}

</noinclude><includeonly>
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
{{{for template|DLC}}}
{| class="formtable"
! Name: 
| {{{field|Name|property=Has name}}}
|-
! Platform: 
| {{{field|Guid|property=Has platform}}}
|-
! Aliases: 
| {{{field|Aliases|property=Has aliases}}}
|-
! Image: 
| {{{field|Image|property=Has image}}}
|-
! Caption: 
| {{{field|Caption|property=Has caption}}}
|-
! Deck: 
| {{{field|Deck|property=Has deck}}}
|-
! ReleaseDate: 
| {{{field|ReleaseDate|property=Has release date}}}
|-
! Release Date Type: 
| {{{field|ReleaseDateType|property=Has release date type|input type=dropdown}}}
|-
! Launch Price: 
| {{{field|LaunchPrice|property=Has launch price}}}
|-
! Game: 
| {{{field|Game|property=Has games|input type=tokens|max values=1|values from category=Games}}}
|-
! Platform: 
| {{{field|Platform|property=Has platform|input type=tokens|values from category=Platforms}}}
|-
! Description:
| {{{standard input|free text|rows=10}}}
|}
{{{end template}}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Form:Franchise',
                'namespace' => $this->namespaces['form'],
                'description' => <<<MARKUP
<noinclude>
This is the "Franchise" form.
To create a page with this form, enter the page name below;
if a page with that name already exists, you will be sent to a form to edit that page.

{{#forminput:form=Franchise|super_page=Franchises}}

</noinclude><includeonly>
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
{{{for template|Franchise}}}
{| class="formtable"
|-
! Name: 
| {{{field|Name|property=Has name}}}
|-
! Guid:
| {{{field|Guid|property=Has guid}}}
|-
! Aliases:
| {{{field|Aliases|property=Has aliases}}}
|-
! Image:
| {{{field|Image|property=Has image}}}
|-
! Caption:
| {{{field|Caption|property=Has caption}}}
|-
! Deck:
| {{{field|Deck|property=Has deck}}}
|-
! Characters:
| {{{field|Characters|property=Has characters|input type=tokens|values from category=Characters}}}
|-
! Concepts:
| {{{field|Concepts|property=Has concepts|input type=tokens|values from category=Concepts}}}
|-
! Games:
| {{{field|Games|property=Has games|input type=tokens|values from category=Games}}}
|-
! Locations:
| {{{field|Locations|property=Has locations|input type=tokens|values from category=Locations}}}
|-
! Objects:
| {{{field|Objects|property=Has objects|input type=tokens|values from category=Objects}}}
|-
! People:
| {{{field|People|property=Has people|input type=tokens|values from category=People}}}
|-
! Description:
| {{{standard input|free text|rows=10}}}
|}
{{{end template}}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Form:Game',
                'namespace' => $this->namespaces['form'],
                'description' => <<<MARKUP
<noinclude>
This is the "Game" form.
To create a page with this form, enter the page name below;
if a page with that name already exists, you will be sent to a form to edit that page.

{{#forminput:form=Game|super_page=Games}}

</noinclude><includeonly>
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
{{{for template|Game}}}
{| class="formtable"
! Name: 
| {{{field|Name|property=Has name}}}
|-
! Guid: 
| {{{field|Guid|property=Has guid}}}
|-
! Aliases: 
| {{{field|Aliases|property=Has aliases}}}
|-
! Image: 
| {{{field|Image|property=Has image}}}
|-
! Caption: 
| {{{field|Caption|property=Has caption}}}
|-
! Deck: 
| {{{field|Deck|property=Has deck}}}
|-
! Characters:
| {{{field|Characters|property=Has characters|input type=tokens|values from category=Characters}}}
|-
! Concepts:
| {{{field|Concepts|property=Has concepts|input type=tokens|values from category=Concepts}}}
|-
! Developers:
| {{{field|Developers|property=Has developers|input type=tokens|values from category=Companies}}}
|-
! Franchises:
| {{{field|Franchises|property=Has franchises|input type=tokens|values from category=Companies}}}
|-
! Genres:
| {{{field|Genres|property=Has genres|input type=tokens|values from category=Genres}}}
|-
! Locations:
| {{{field|Locations|property=Has locations|input type=tokens|values from category=Locations}}}
|-
! Objects:
| {{{field|Objects|property=Has objects|input type=tokens|values from category=Objects}}}
|-
! Platforms:
| {{{field|Platforms|property=Has platforms|input type=tokens|values from category=Platforms}}}
|-
! Publishers:
| {{{field|Publishers|property=Has publishers|input type=tokens|values from category=Companies}}}
|-
! Games:
| {{{field|Games|property=Has similar games|input type=tokens|values from category=Games}}}
|-
! Themes:
| {{{field|Themes|property=Has themes|input type=tokens|values from category=Themes}}}
|-
! Description:
| {{{standard input|free text|rows=10}}}
|-
! Releases:
| {{{field|Releases|holds template|multiple}}}
|-
! Credits:
| {{{field|Credits|holds template|multiple}}}
|}
{{{end template}}}
{{{for template|Release|multiple|add button text=Add release|label=Releases|embed in field=Game[Releases]}}}
{| class="formtable"
! Release Name:
| {{{field|Name|property=Has name}}}
|-
! Image:
| {{{field|Image|property=Has image}}}
|-
! Region:
| {{{field|Region|property=Has region}}}
|-
! Rating:
| {{{field|Rating|property=Has rating|input type=dropdown|values from category=Ratings}}}
|-
! Platform:
| {{{field|Platform|property=Has platforms|input type=tokens|max values=1|values from category=Platforms}}}
|-
! Developers:
| {{{field|Developers|property=Has developers|input type=tokens|values from category=Companies}}}
|-
! Publishers:
| {{{field|Publishers|property=Has publishers|input type=tokens|values from category=Companies}}}
|-
! Release Date Type:
| {{{field|ReleaseDateType|property=Has release date type|input type=dropdown}}}
|-
! Release Date:
| {{{field|ReleaseDate|property=Has release date}}}
|-
! Product Code Type:
| {{{field|ProductCodeType|property=Has product code type|input type=dropdown}}}
|-
! Product Code:
| {{{field|ProductCode|property=Has product code}}}
|-
! Company Code Type:
| {{{field|CompanyCodeType|property=Has company code type|input type=dropdown}}}
|-
! Company Code:
| {{{field|CompanyCode|property=Has company code}}}
|-
! Widescreen Support:
| {{{field|WidescreenSupport|property=Has widescreen support|input type=dropdown}}}
|-
! Resolutions:
| {{{field|Resolutions|property=Has resolutions|input type=listbox|values from category=Resolutions}}}
|-
! Sound Systems
| {{{field|SoundSystems|property=Has sound systems|input type=listbox|values from category=Sound Systems}}}
|-
! Single Player Features:
| {{{field|SinglePlayerFeatures|property=Has single player features|input type=listbox|values from category=Single Player Features}}}
|-
! Multiplayer Features:
| {{{field|MultiplayerFeatures|property=Has multiplayer features|input type=listbox|values from category=Multiplayer Features}}}
|-
! Minimum Players:
| {{{field|MinimumPlayers|property=Has minimum players|default=1}}}
|-
! Maximum Players:
| {{{field|MaximumPlayers|property=Has maximum players}}}
|}
{{{end template}}}
{{{for template|Credit|multiple|add button text=Add credit|label=Credits|embed in field=Game[Credits]}}}
{| class="formtable"
! Person:
| {{{field|Person|property=Has people|input type=tokens|max values=1|values from category=People}}}
|-
! Company:
| {{{field|Company|property=Has companies|input type=tokens|max values=1|values from category=Companies}}}
|-
! Department:
| {{{field|Department|property=Has department|input type=dropdown|default=Unclassified}}}
|-
! Role:
| {{{field|Role|property=Has role}}}
|-
! Note:
| {{{field|Note|property=Has note}}}
|}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Form:Genre',
                'namespace' => $this->namespaces['form'],
                'description' => <<<MARKUP
<noinclude>
This is the "Genre" form.
To create a page with this form, enter the page name below;
if a page with that name already exists, you will be sent to a form to edit that page.

{{#forminput:form=Genre|super_page=Genres}}

</noinclude><includeonly>
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
{{{for template|Genre}}}
{| class="formtable"
|-
! Name: 
| {{{field|Name|property=Has name}}}
|-
! Guid: 
| {{{field|Guid|property=Has guid}}}
|-
! Aliases: 
| {{{field|Aliases|property=Has aliases}}}
|-
! Image: 
| {{{field|Image|property=Has image}}}
|-
! Caption: 
| {{{field|Caption|property=Has caption}}}
|-
! Deck: 
| {{{field|Deck|property=Has deck}}}
|-
! Description:
| {{{standard input|free text|rows=10}}}
|}
{{{end template}}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Form:Location',
                'namespace' => $this->namespaces['form'],
                'description' => <<<MARKUP
<noinclude>
This is the "Location" form.
To create a page with this form, enter the page name below;
if a page with that name already exists, you will be sent to a form to edit that page.

{{#forminput:form=Location|super_page=Locations}}

</noinclude><includeonly>
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
{{{for template|Location}}}
{| class="formtable"
! Name: 
| {{{field|Name|property=Has name}}}
|-
! Guid: 
| {{{field|Guid|property=Has guid}}}
|-
! Aliases: 
| {{{field|Aliases|property=Has aliases}}}
|-
! Image: 
| {{{field|Image|property=Has image}}}
|-
! Caption: 
| {{{field|Caption|property=Has caption}}}
|-
! Deck: 
| {{{field|Deck|property=Has deck}}}
|-
! Characters: 
| {{{field|Characters|property=Has characters|input type=tokens|values from category=Characters}}}
|-
! Concepts: 
| {{{field|Concepts|property=Has concepts|input type=tokens|values from category=Concepts}}}
|-
! Locations: 
| {{{field|Locations|property=Has similar locations|input type=tokens|values from category=Locations}}}
|-
! Objects: 
| {{{field|Objects|property=Has objects|input type=tokens|values from category=Objects}}}
|-
! Description:
| {{{standard input|free text|rows=10}}}
|}
{{{end template}}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Form:Multiplayer Feature',
                'namespace' => $this->namespaces['form'],
                'description' => <<<MARKUP
<noinclude>
This is the "Multiplayer Feature" form.
To create a page with this form, enter the page name below;
if a page with that name already exists, you will be sent to a form to edit that page.

{{#forminput:form=Multiplayer Feature|super_page=Multiplayer Features}}

</noinclude><includeonly>
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
{{{for template|Multiplayer Feature}}}
{| class="formtable"
|-
! Name: 
| {{{field|Name|mandatory|property=Has name}}}
|-
! Guid: 
| {{{field|Guid|property=Has guid}}}
|-
! Description:
| {{{standard input|free text|rows=10}}}
|}
{{{end template}}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Form:Object',
                'namespace' => $this->namespaces['form'],
                'description' => <<<MARKUP
<noinclude>
This is the "Object" form.
To create a page with this form, enter the page name below;
if a page with that name already exists, you will be sent to a form to edit that page.

{{#forminput:form=Object|super_page=Objects}}

</noinclude><includeonly>
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
{{{for template|Object}}}
{| class="formtable"
! Name: 
| {{{field|Name|property=Has name}}}
|-
! People: 
| {{{field|Guid|property=Has people}}}
|-
! Aliases: 
| {{{field|Aliases|property=Has aliases}}}
|-
! Image: 
| {{{field|Image|property=Has image}}}
|-
! Caption: 
| {{{field|Caption|property=Has caption}}}
|-
! Deck: 
| {{{field|Deck|property=Has deck}}}
|-
! Characters: 
| {{{field|Characters|property=Has characters|input type=tokens|values from category=Characters}}}
|-
! Concepts: 
| {{{field|Concepts|property=Has concepts|input type=tokens|values from category=Concepts}}}
|-
! Franchises: 
| {{{field|Franchises|property=Has franchises|input type=tokens|values from category=Franchises}}}
|-
! Games: 
| {{{field|Games|property=Has games|input type=tokens|values from category=Games}}}
|-
! Locations: 
| {{{field|Locations|property=Has locations|input type=tokens|values from category=Locations}}}
|-
! Objects: 
| {{{field|Objects|property=Has similar objects|input type=tokens|values from category=Objects}}}
|-
! People: 
| {{{field|People|property=Has people|input type=tokens|values from category=People}}}
|-
! Description:
| {{{standard input|free text|rows=10}}}
|}
{{{end template}}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Form:Person',
                'namespace' => $this->namespaces['form'],
                'description' => <<<MARKUP
<noinclude>
This is the "Person" form.
To create a page with this form, enter the page name below;
if a page with that name already exists, you will be sent to a form to edit that page.

{{#forminput:form=Person|super_page=People}}

</noinclude><includeonly>
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
{{{for template|Person}}}
{| class="formtable"
! Name: 
| {{{field|Name|property=Has name}}}
|-
! People: 
| {{{field|Guid|property=Has people}}}
|-
! Aliases: 
| {{{field|Aliases|property=Has aliases}}}
|-
! Image: 
| {{{field|Image|property=Has image}}}
|-
! Caption: 
| {{{field|Caption|property=Has caption}}}
|-
! Deck: 
| {{{field|Deck|property=Has deck}}}
|-
! LastName: 
| {{{field|LastName|property=Has last name}}}
|-
! Gender: 
| {{{field|Gender|property=Has gender}}}
|-
! Hometown: 
| {{{field|Hometown|property=Has hometown}}}
|-
! Birthday: 
| {{{field|Birthday|property=Has birthday}}}
|-
! Death: 
| {{{field|Death|property=Has death}}}
|-
! Website: 
| {{{field|Website|property=Has website}}}
|-
! Characters: 
| {{{field|Characters|property=Has characters|input type=tokens|values from category=Characters}}}
|-
! Concepts: 
| {{{field|Concepts|property=Has concepts|input type=tokens|values from category=Concepts}}}
|-
! Franchises: 
| {{{field|Franchises|property=Has franchises|input type=tokens|values from category=Franchises}}}
|-
! Games: 
| {{{field|Games|property=Has games|input type=tokens|values from category=Games}}}
|-
! Locations: 
| {{{field|Locations|property=Has locations|input type=tokens|values from category=Locations}}}
|-
! Objects: 
| {{{field|Objects|property=Has objects|input type=tokens|values from category=Objects}}}
|-
! People: 
| {{{field|People|property=Has similar people|input type=tokens|values from category=People}}}
|-
! Description:
| {{{standard input|free text|rows=10}}}
|}
{{{end template}}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Form:Platform',
                'namespace' => $this->namespaces['form'],
                'description' => <<<MARKUP
<noinclude>
This is the "Platform" form.
To create a page with this form, enter the page name below;
if a page with that name already exists, you will be sent to a form to edit that page.

{{#forminput:form=Platform|super_page=Platforms}}

</noinclude><includeonly>
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
{{{for template|Platform}}}
{| class="formtable"
! Name: 
| {{{field|Name|property=Has name}}}
|-
! Manufacturer: 
| {{{field|Guid|property=Has manufacturer}}}
|-
! Aliases: 
| {{{field|Aliases|property=Has aliases}}}
|-
! Image: 
| {{{field|Image|property=Has image}}}
|-
! Caption: 
| {{{field|Caption|property=Has caption}}}
|-
! Deck: 
| {{{field|Deck|property=Has deck}}}
|-
! ShortName: 
| {{{field|ShortName|property=Has short name}}}
|-
! ReleaseDate: 
| {{{field|ReleaseDate|property=Has release date}}}
|-
! Release Date Type: 
| {{{field|ReleaseDateType|property=Has release date type|input type=dropdown}}}
|-
! Install Base: 
| {{{field|InstallBase|property=Has install base}}}
|-
! Online Support: 
| {{{field|OnlineSupport|property=Has online support}}}
|-
! Original Price: 
| {{{field|OriginalPrice|property=Has original price}}}
|-
! Manufacturer: 
| {{{field|Manufacturer|property=Has manufacturer|input type=tokens|values from category=Companies}}}
|-
! Description:
| {{{standard input|free text|rows=10}}}
|}
{{{end template}}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Form:Rating',
                'namespace' => $this->namespaces['form'],
                'description' => <<<MARKUP
<noinclude>
This is the "Rating" form.
To create a page with this form, enter the page name below;
if a page with that name already exists, you will be sent to a form to edit that page.

{{#forminput:form=Rating|super_page=Ratings}}

</noinclude><includeonly>
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
{{{for template|Rating}}}
{| class="formtable"
|-
! Name: 
| {{{field|Name|mandatory|property=Has name}}}
|-
! Explanation: 
| {{{field|Explanation|property=Stands for}}}
|-
! Image: 
| {{{field|Image|property=Has image}}}
|-
! Caption: 
| {{{field|Caption|property=Has caption}}}
|-
! Deck: 
| {{{field|Deck|property=Has deck}}}
|-
! Description:
| {{{standard input|free text|rows=10}}}
|}
{{{end template}}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Form:Resolution',
                'namespace' => $this->namespaces['form'],
                'description' => <<<MARKUP
<noinclude>
This is the "Resolution" form.
To create a page with this form, enter the page name below;
if a page with that name already exists, you will be sent to a form to edit that page.

{{#forminput:form=Resolution|super_page=Resolutions}}

</noinclude><includeonly>
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
{{{for template|Resolution}}}
{| class="formtable"
|-
! Name: 
| {{{field|Name|mandatory|property=Has name}}}
|-
! Guid: 
| {{{field|Guid|property=Has guid}}}
|-
! Description:
| {{{standard input|free text|rows=10}}}
|}
{{{end template}}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Form:Single Player Feature',
                'namespace' => $this->namespaces['form'],
                'description' => <<<MARKUP
<noinclude>
This is the "Single Player Feature" form.
To create a page with this form, enter the page name below;
if a page with that name already exists, you will be sent to a form to edit that page.

{{#forminput:form=Single Player Feature|super_page=Single Player Features}}

</noinclude><includeonly>
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
{{{for template|Single Player Feature}}}
{| class="formtable"
|-
! Name: 
| {{{field|Name|mandatory|property=Has name}}}
|-
! Guid: 
| {{{field|Guid|property=Has guid}}}
|-
! Description:
| {{{standard input|free text|rows=10}}}
|}
{{{end template}}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Form:Sound System',
                'namespace' => $this->namespaces['form'],
                'description' => <<<MARKUP
<noinclude>
This is the "Sound System" form.
To create a page with this form, enter the page name below;
if a page with that name already exists, you will be sent to a form to edit that page.

{{#forminput:form=Sound System|super_page=Sound Systems}}

</noinclude><includeonly>
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
{{{for template|Sound System}}}
{| class="formtable"
|-
! Name: 
| {{{field|Name|mandatory|property=Has name}}}
|-
! Guid: 
| {{{field|Guid|property=Has guid}}}
|-
! Description:
| {{{standard input|free text|rows=10}}}
|}
{{{end template}}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Form:Theme',
                'namespace' => $this->namespaces['form'],
                'description' => <<<MARKUP
<noinclude>
This is the "Theme" form.
To create a page with this form, enter the page name below;
if a page with that name already exists, you will be sent to a form to edit that page.

{{#forminput:form=Theme|super_page=Themes}}

</noinclude><includeonly>
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
{{{for template|Theme}}}
{| class="formtable"
|-
! Name: 
| {{{field|Name|property=Has name}}}
|-
! Guid: 
| {{{field|Guid|property=Has guid}}}
|-
! Aliases: 
| {{{field|Aliases|property=Has aliases}}}
|-
! Image: 
| {{{field|Image|property=Has image}}}
|-
! Caption: 
| {{{field|Caption|property=Has caption}}}
|-
! Deck: 
| {{{field|Deck|property=Has deck}}}
|-
! Description:
| {{{standard input|free text|rows=10}}}
|}
{{{end template}}}
</includeonly>
MARKUP,
            ],
        ];

        $this->createXML('forms.xml', $data);
    }
}

$maintClass = GenerateXMLForms::class;

require_once RUN_MAINTENANCE_IF_MAIN; 