<?php

require_once(__DIR__.'/common.php');

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
|}
{{{end template}}}

'''Free text:'''

{{{standard input|free text|rows=10}}}
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
'''Name:''' {{{field|Name|property=has name}}}

'''Guid:''' {{{field|Guid|property=has guid}}}

'''RealName:''' {{{field|RealName|property=has real name}}}

'''Aliases:''' {{{field|Aliases|property=has aliases}}}

'''Gender:''' {{{field|Gender|property=has gender}}}

'''Birthday:''' {{{field|Birthday|property=has birthday}}}

'''Image:''' {{{field|Image|property=has image}}}

'''Caption:''' {{{field|Caption|property=has caption}}}

'''Deck:''' {{{field|Deck|property=has deck}}}

'''Concepts:''' {{{field|Concepts|property=has concepts|_autocomplete|_suggested_category=Concepts}}}

'''Enemies:''' {{{field|Enemies|property=has enemies|_autocomplete|_suggested_category=Characters}}}

'''Franchises:''' {{{field|Franchises|property=has franchises|_autocomplete|_suggested_category=Franchises}}}

'''Friends:''' {{{field|Friends|property=has friends|_autocomplete|_suggested_category=Characters}}}

'''Games:''' {{{field|Games|property=has games|_autocomplete|_suggested_category=Games}}}

'''Locations:''' {{{field|Locations|property=has locations|_autocomplete|_suggested_category=Locations}}}

'''People:''' {{{field|People|property=has people|_autocomplete|_suggested_category=People}}}

'''Objects:''' {{{field|Objects|property=has objects|_autocomplete|_suggested_category=Objects}}}

{{{end template}}}

'''Free text:'''

{{{standard input|free text|rows=10}}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Form:Company',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
            [
                'title' => 'Form:Concept',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
            [
                'title' => 'Form:DLC',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
            [
                'title' => 'Form:Franchise',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
            [
                'title' => 'Form:Game',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
            [
                'title' => 'Form:Genre',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
            [
                'title' => 'Form:Location',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
            [
                'title' => 'Form:Object',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
            [
                'title' => 'Form:Person',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
            [
                'title' => 'Form:Platform',
                'namespace' => $this->namespaces['template'],
                'description' => ''
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
|}
{{{end template}}}

'''Free text:'''

{{{standard input|free text|rows=10}}}
</includeonly>
MARKUP,
            ],
            [
                'title' => 'Form:Release',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
            [
                'title' => 'Form:Theme',
                'namespace' => $this->namespaces['template'],
                'description' => ''
            ],
        ];

        $this->createXML('forms.xml', $data);
    }
}

$maintClass = GenerateXMLForms::class;

require_once RUN_MAINTENANCE_IF_MAIN; 