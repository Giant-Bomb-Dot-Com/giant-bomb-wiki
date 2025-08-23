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
                'title' => 'Form:Rating',
                'namespace' => $this->namespace['form'],
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
! Description: 
| {{{field|Description|property=Has description}}}
|}
{{{end template}}}

'''Free text:'''

{{{standard input|free text|rows=10}}}
</includeonly>
MARKUP;
            ],
        ];

        $this->createXML('forms.xml', $data);
    }
}

$maintClass = GenerateXMLForms::class;

require_once RUN_MAINTENANCE_IF_MAIN; 