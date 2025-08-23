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
                'title' => 'Template:Rating',
                'namespace' => $this->namespaces['template'],
                'description' => <<<MARKUP
<noinclude>
{{#template_params:Name (property=Has name)|Explanation (property=Stands for)|Image (property=Has image)|Description (property=Has description)}}
</noinclude><includeonly>
'''Name:''' [[Has name::{{{Name|}}}]]

'''Explanation:''' [[Stands for::{{{Explanation|}}}]]

'''Image:''' [[Has image::{{{Image|}}}]]

'''Description:''' [[Has description::{{{Description|}}}]]

''':''' {{#ask:[[Foaf:homepage::{{SUBJECTPAGENAME}}]]|format=list}}
</includeonly>
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