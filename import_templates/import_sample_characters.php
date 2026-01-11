<?php
/**
 * Generates and imports sample Character pages for local development.
 * 
 * Run from within the Docker container:
 *   php maintenance/run.php import_templates/import_sample_characters.php
 * 
 * This script:
 * 1. Generates sample character XML
 * 2. Imports it into the wiki
 * 3. Runs necessary maintenance
 */

require_once __DIR__ . '/../maintenance/Maintenance.php';

class ImportSampleCharacters extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->addDescription('Generate and import sample Character pages for local development');
        $this->addOption('generate-only', 'Only generate the XML, do not import', false, false);
    }

    public function execute() {
        global $IP;
        
        $generateScript = "$IP/maintenance/gb_api_scripts/generate_sample_characters.php";
        $xmlPath = "$IP/maintenance/gb_api_scripts/import_xml/sample_characters.xml";
        
        // Step 1: Generate the sample characters XML
        $this->output("Generating sample character pages...\n");
        
        $cmd = "php $IP/maintenance/run.php $generateScript";
        $this->output("Running: $cmd\n");
        passthru($cmd, $returnCode);
        
        if ($returnCode !== 0) {
            $this->fatalError("Failed to generate sample characters XML");
        }
        
        if ($this->hasOption('generate-only')) {
            $this->output("\nXML generated at: $xmlPath\n");
            $this->output("Import manually with:\n");
            $this->output("  php maintenance/run.php maintenance/importDump.php < $xmlPath\n");
            return;
        }
        
        // Step 2: Import the XML
        if (!file_exists($xmlPath)) {
            $this->fatalError("Generated XML not found at: $xmlPath");
        }
        
        $this->output("\nImporting character pages...\n");
        $cmd = "php $IP/maintenance/run.php $IP/maintenance/importDump.php < $xmlPath";
        $this->output("Running: $cmd\n");
        passthru($cmd, $returnCode);
        
        if ($returnCode !== 0) {
            $this->fatalError("Failed to import character pages");
        }
        
        // Step 3: Rebuild SMW data for imported pages
        $this->output("\nRefreshing SMW data for Characters...\n");
        $cmd = "php $IP/maintenance/run.php $IP/extensions/SemanticMediaWiki/maintenance/rebuildData.php --query='[[~Characters/*]]' --shallow-update";
        $this->output("Running: $cmd\n");
        passthru($cmd);
        
        $this->output("\n✓ Sample characters imported successfully!\n");
        $this->output("\nTest pages:\n");
        $this->output("  /wiki/Characters/Mario\n");
        $this->output("  /wiki/Characters/Link\n");
        $this->output("  /wiki/Characters/Master_Chief\n");
        $this->output("  /wiki/Characters/Kratos\n");
        $this->output("  /wiki/Characters/Solid_Snake\n");
        $this->output("  /wiki/Characters/Samus_Aran\n");
        $this->output("  /wiki/Characters/Cloud_Strife\n");
        $this->output("  /wiki/Characters/Geralt_of_Rivia\n");
    }
}

$maintClass = ImportSampleCharacters::class;
require_once RUN_MAINTENANCE_IF_MAIN;
