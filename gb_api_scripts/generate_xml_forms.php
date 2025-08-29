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
! Real Name:
| {{{field|RealName|property=Has real name}}}
|-
! Gender:
| {{{field|Aliases|property=Has aliases}}}
|-
! Birthday:
| {{{field|Birthday|property=Has birthday}}}
|}

'''Relationships'''

{| class="formtable"
! Concepts:
| {{{field|Concepts|property=Has concepts|list}}}
|-
! Enemies:
| {{{field|Enemies|property=Has enemies|list}}}
|-
! Friends:
| {{{field|Friends|property=Has friends|list}}}
|-
! Franchises:
| {{{field|Franchises|property=Has franchises|list}}}}
|-
! Games:
| {{{field|Games|property=Has games|list}}}
|-
! Locations:
| {{{field|Locations|property=Has locations|list}}}
|-
! People:
| {{{field|People|property=Has people|list}}}
|-
! Objects:
| {{{field|Objects|property=Has objects|list}}}
|}
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
|}
{{{end template}}}

'''Free text:'''

{{{standard input|free text|rows=10}}}
</includeonly>
MARKUP,
            ],
        ];

        $this->createXML('forms.xml', $data);
    }
}

$maintClass = GenerateXMLForms::class;

require_once RUN_MAINTENANCE_IF_MAIN; 