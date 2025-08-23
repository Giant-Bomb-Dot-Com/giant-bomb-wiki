<?php

require_once(__DIR__.'/common.php');

class GenerateXMLPages extends Maintenance
{
    use CommonVariablesAndMethods;

    public function __construct() 
    {
        parent::__construct();
        $this->addDescription("Generates XML for categories");
    }

    public function execute()
    {
        $data = [
        	[
        		'title' => 'Ratings/ESRB_M',
        		'namespace' => $this->namespaces['page'],
        		'description' => <<<MARKUP
{{Rating
|Name=ESRB: M
|Image=ESRB M.png
|Caption=Logo for ESRB: M
|Explanation=Mature 17+
}}
Content is generally suitable for ages 17 and up. May contain intense violence, blood and gore, sexual content and/or strong language.
MARKUP,
        	],
        ];

        $this->createXML('categories.xml', $data);
    }
}

$maintClass = GenerateXMLPages::class;

require_once RUN_MAINTENANCE_IF_MAIN; 