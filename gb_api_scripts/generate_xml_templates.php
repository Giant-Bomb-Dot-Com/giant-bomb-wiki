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

'''Name''': The display name of the accessory.

'''Guid''': The accessory identifier from Giant Bomb.

'''Aliases''': Alternative names for the accessory.

'''Image''': The image filename of the accessory. Image appears in the infobox.

'''Caption''': The caption for the above image.

'''Deck''': The short description of the accessory.</noinclude><includeonly>{{#set:Has name={{{Name|}}}}}{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}{{#if:{{{Aliases|}}}|{{#set:Has aliases={{{Aliases|}}}}}}}{{#if:{{{Image|}}}|{{#set:Has image={{{Image|}}}}}}}{{#if:{{{Caption|}}}|{{#set:Has caption={{{Caption|}}}}}}}{{#if:{{{Deck|}}}|{{#set:Has deck={{{Deck|}}}}}}}
{{Infobox
| title={{{Name|}}}
| italic title=no
| image={{{Image|}}}
| image size=40
| caption={{{Caption|}}}
}}
{{DISPLAYTITLE:{{{Name|}}}}}
[[Category:Accessories|{{SUBPAGENAME}}]]
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
| RealName (property=Has real name)
| Aliases (property=Has aliases)
| Gender (property=Has gender)
| Birthday (property=Has birthday)
| Image (property=Has image)
| Caption (property=Has caption)
| Deck (property=Has deck)
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

'''Name''': The display name of the character.

'''RealName''': The real name of the character.

'''Guid''': The character identifier from Giant Bomb.

'''Aliases''': Alternative names for the character.

'''Gender''': The character's gender.

'''Birthday''': The character's birth date.

'''Image''': The image filename of the character. Image appears in the infobox.

'''Caption''': The caption for the above image.

'''Deck''': The short description of the character.

'''Concepts''': The concept pages related to the character.

'''Enemies''': The character pages considered an enemy to the character.

'''Franchises''': The franchise pages related to the character.

'''Friends''': The character pages considered a friend to the character.

'''Games''': The game pages related to the character.

'''Locations''': The location pages related to the character.

'''People''': The person pages related to the character.

'''Objects''': The object pages related to the character.</noinclude><includeonly>{{#set:Has name={{{Name|}}}}}{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}{{#if:{{{RealName|}}}|{{#set:Has real name={{{RealName|}}}}}}}{{#if:{{{Aliases|}}}|{{#set:Has aliases={{{Aliases|}}}}}}}{{#if:{{{Gender|}}}|{{#set:Has gender={{{Gender|}}}}}}}{{#if:{{{Birthday|}}}|{{#set:Has birthday={{{Birthday|}}}}}}}{{#if:{{{Image|}}}|{{#set:Has image={{{Image|}}}}}}}{{#if:{{{Caption|}}}|{{#set:Has caption={{{Caption|}}}}}}}{{#if:{{{Deck|}}}|{{#set:Has deck={{{Deck|}}}}}}}{{#arraymap:{{{Concepts|}}}|,|@@|[[Has concepts::@@| ]]|}}{{#arraymap:{{{Enemies|}}}|,|@@|[[Has enemies::@@| ]]|}}{{#arraymap:{{{Franchises|}}}|,|@@|[[Has franchises::@@| ]]|}}{{#arraymap:{{{Friends|}}}|,|@@|[[Has friends::@@| ]]|}}{{#arraymap:{{{Games|}}}|,|@@|[[Has games::@@| ]]|}}{{#arraymap:{{{Locations|}}}|,|@@|[[Has locations::@@| ]]|}}{{#arraymap:{{{People|}}}|,|@@|[[Has people::@@| ]]|}}{{#arraymap:{{{Objects|}}}|,|@@|[[Has objects::@@| ]]|}}
{{Infobox
|title={{{Name|}}}
|italic title=no
|image={{{Image|}}}
|image size=40
|caption={{{Caption|}}}
|aliases={{{Aliases|}}}
|gender={{{Gender|}}}
|birthday={{{Birthday|}}}
|real name={{{RealName|}}}
}}
{{DISPLAYTITLE:{{{Name|}}}}}
[[Category:Characters|{{SUBPAGENAME}}]]
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Template:Company',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
            [
                'title' => 'Template:Concept',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
            [
                'title' => 'Template:DLC',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
            [
                'title' => 'Template:Franchise',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
            [
                'title' => 'Template:Game',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
            [
                'title' => 'Template:Genre',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
            [
                'title' => 'Template:Location',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
            [
                'title' => 'Template:Object',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
            [
                'title' => 'Template:Person',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
            [
                'title' => 'Template:Platform',
                'namespace' => $this->namespaces['template'],
                'description' => ''
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

'''Name''': The display name of the rating.

'''Guid''': The rating identifier from Giant Bomb.

'''Explanation''': The long form representation of the rating.

'''Image''': The image filename of the rating. Image appears in the infobox.

'''Caption''': The caption for the above image.</noinclude><includeonly>{{#set:
| Has name={{{Name|}}}
| Has guid={{{Guid|}}}
| Stands for={{{Explanation|}}}
| Has image={{{Image|}}}
| Has caption={{{Caption|}}} }}
{{Infobox
| title={{{Name|}}}
| italic title=no
| image={{{Image|}}}
| image size=40
| caption={{{Caption|}}}
| stands for={{{Explanation|}}}
}}
{{DISPLAYTITLE:{{{Name|}}}}}
[[Category:Ratings|{{SUBPAGENAME}}]]
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

'''Name''': The display name of the theme.

'''Guid''': The theme identifier from Giant Bomb.

'''Aliases''': Alternative names for the theme.

'''Image''': The image filename of the theme. Image appears in the infobox.

'''Caption''': The caption for the above image.

'''Deck''': The short description of the theme.</noinclude><includeonly>{{#set:Has name={{{Name|}}}}}{{#if:{{{Guid|}}}|{{#set:Has guid={{{Guid|}}}}}}}{{#if:{{{Aliases|}}}|{{#set:Has aliases={{{Aliases|}}}}}}}{{#if:{{{Image|}}}|{{#set:Has image={{{Image|}}}}}}}{{#if:{{{Caption|}}}|{{#set:Has caption={{{Caption|}}}}}}}{{#if:{{{Deck|}}}|{{#set:Has deck={{{Deck|}}}}}}}
{{Infobox
| title={{{Name|}}}
| italic title=no
| image={{{Image|}}}
| image size=40
| caption={{{Caption|}}}
}}
{{DISPLAYTITLE:{{{Name|}}}}}
[[Category:Themes|{{SUBPAGENAME}}]]
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
| label2  = [[Video game developer|Developer(s)]]
| data2   = {{{developer|}}}

| label3  = [[Video game publisher|Publisher(s)]]
| data3   = {{{publisher|}}}

| label4  = [[Video game creative director|Director(s)]]
| data4   = {{{director|}}}

| label5  = [[Video game producer|Producer(s)]]
| data5   = {{{producer|}}}

| label6  = [[Video game designer|Designer(s)]]
| data6   = {{{designer|}}}

| label7  = [[Video game programmer|Programmer(s)]]
| data7   = {{{programmer|}}}

| label8  = [[Video game artist|Artist(s)]]
| data8   = {{{artist|}}}

| label9  = [[Video game writer|Writer(s)]]
| data9   = {{{writer|}}}

| label10 = [[Video game composer|Composer(s)]]
| data10  = {{{composer|}}}

| label11 = Series
| data11  = {{{series|}}}

| label12 = [[Game engine|Engine]]
| data12  = {{{engine|}}}

| label13 = [[Computing platform|Platform(s)]]
| data13  = {{{platform|{{{platforms|}}}}}}

| label14 = Release
| data14  = {{{released|{{{release|}}}}}}

| label15 = [[Video game genre|Genre(s)]]
| data15  = {{{genre|}}}

| label16 = Mode(s)
| data16  = {{{modes|}}}

| label17 = [[Arcade system board|Arcade system]]
| data17  = {{{arcade system|}}}

| label18 = Stands for
| data18 = {{{stands for|}}}

| label19 = Aliases
| data19 = {{{aliases|}}}

| label20 = Gender
| data20 = {{{gender|}}}

| label21 = Birthday
| data21 = {{{birthday|}}}

| label22 = Real Name
| data22 = {{{real name|}}}

| label23 = Death
| data23 = {{{death|}}}

<!-- For embedded content -->
| data30  = {{{embedded|}}}

}}{{main other|{{#ifeq:{{lc:{{{italic title|}}}}}|no||{{italic title|force={{#ifeq:{{lc:{{{italic title|}}}}}|force|true}}}}}}
}}{{#invoke:Check for unknown parameters|check|unknown={{main other|[[Category:Pages using infobox with unknown parameters|_VALUE_{{PAGENAME}}]]}}|ignoreblank=1|preview=Page using [[Template:Infobox]] with unknown parameter "_VALUE_"| alt | arcade system | artist | caption | border | child | collapsible | commons | composer | designer | developer | director | embedded | engine | genre | image | image_size | image_upright | italic title | modes | noicon | onlysourced | platform | platforms | producer | programmer | publisher | qid | refs | release | released | series | state | subbox | suppressfields | title | writer | stands for | gender | birthday | real name | death }}<noinclude>
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
                'description' => '<includeonly>
{{#formlink:
  form={{{1}}}
| link text=Create with form
| target={{PAGENAME}}
}}
</includeonly>'
            ],
        ];

        $this->createXML('templates.xml', $data);
    }
}

$maintClass = GenerateXMLTemplates::class;

require_once RUN_MAINTENANCE_IF_MAIN; 