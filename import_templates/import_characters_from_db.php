<?php
/**
 * Pull real Character pages from the remote API database.
 * 
 * Usage:
 *   php maintenance/run.php import_templates/import_characters_from_db.php
 *   php maintenance/run.php import_templates/import_characters_from_db.php --limit=10
 *   php maintenance/run.php import_templates/import_characters_from_db.php --ids=1,2,3,4,5
 * 
 * Requires env vars:
 *   EXTERNAL_DB_HOST, EXTERNAL_DB_USER, EXTERNAL_DB_PASSWORD, EXTERNAL_DB_NAME
 */

require_once __DIR__ . '/../maintenance/Maintenance.php';

class ImportCharactersFromDb extends Maintenance {
    private $db;
    
    public function __construct() {
        parent::__construct();
        $this->addDescription('Pull Character pages from remote API database');
        $this->addOption('limit', 'Number of characters to pull (default: 5)', false, true);
        $this->addOption('ids', 'Comma-separated character IDs to pull', false, true);
        $this->addOption('generate-only', 'Only generate XML, do not import', false, false);
    }

    public function execute() {
        global $IP;
        
        // Connect to external database
        $this->db = $this->connectToExternalDb();
        if (!$this->db) {
            $this->fatalError("Could not connect to external database. Check EXTERNAL_DB_* env vars.");
        }
        
        // Get characters
        if ($this->hasOption('ids')) {
            $ids = array_map('intval', explode(',', $this->getOption('ids')));
            $characters = $this->getCharactersByIds($ids);
        } else {
            $limit = (int)$this->getOption('limit', 5);
            $characters = $this->getPopularCharacters($limit);
        }
        
        if (empty($characters)) {
            $this->fatalError("No characters found");
        }
        
        $this->output("Found " . count($characters) . " characters\n");
        
        // Generate XML
        $xmlPath = "$IP/maintenance/gb_api_scripts/import_xml/characters_from_db.xml";
        $this->generateXml($characters, $xmlPath);
        
        if ($this->hasOption('generate-only')) {
            $this->output("\nXML generated at: $xmlPath\n");
            return;
        }
        
        // Import
        $this->output("\nImporting characters...\n");
        $cmd = "php $IP/maintenance/run.php $IP/maintenance/importDump.php < $xmlPath";
        passthru($cmd, $returnCode);
        
        if ($returnCode !== 0) {
            $this->fatalError("Import failed");
        }
        
        // Refresh SMW
        $this->output("\nRefreshing SMW data...\n");
        $cmd = "php $IP/maintenance/run.php $IP/extensions/SemanticMediaWiki/maintenance/rebuildData.php --query='[[~Characters/*]]' --shallow-update 2>&1 | head -20";
        passthru($cmd);
        
        $this->output("\n✓ Characters imported!\n");
    }
    
    private function connectToExternalDb() {
        $host = getenv('EXTERNAL_DB_HOST');
        $user = getenv('EXTERNAL_DB_USER');
        $pass = getenv('EXTERNAL_DB_PASSWORD');
        $name = getenv('EXTERNAL_DB_NAME');
        
        if (!$host || !$user || !$name) {
            $this->output("Missing EXTERNAL_DB_* environment variables\n");
            $this->output("Required: EXTERNAL_DB_HOST, EXTERNAL_DB_USER, EXTERNAL_DB_PASSWORD, EXTERNAL_DB_NAME\n");
            return null;
        }
        
        try {
            $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
            $db = new PDO($dsn, $user, $pass);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->exec("SET SESSION group_concat_max_len = 1000000;");
            $this->output("Connected to external database: $name@$host\n");
            return $db;
        } catch (PDOException $e) {
            $this->output("Database error: " . $e->getMessage() . "\n");
            return null;
        }
    }
    
    /**
     * Get characters with the most game associations (popular characters)
     */
    private function getPopularCharacters(int $limit): array {
        $sql = "
            SELECT c.*, 
                   COUNT(gc.game_id) as game_count,
                   i.image as infobox_image,
                   i.path as infobox_path
            FROM wiki_character c
            LEFT JOIN wiki_assoc_game_character gc ON gc.character_id = c.id
            LEFT JOIN wiki_image i ON i.id = c.image_id
            WHERE c.mw_page_name IS NOT NULL 
              AND c.mw_page_name != ''
              AND c.deck IS NOT NULL
              AND c.deck != ''
            GROUP BY c.id
            ORDER BY game_count DESC
            LIMIT $limit
        ";
        
        $stmt = $this->db->query($sql);
        $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get relations for each character
        foreach ($characters as &$char) {
            $char['relations'] = $this->getCharacterRelations($char['id']);
        }
        
        return $characters;
    }
    
    private function getCharactersByIds(array $ids): array {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "
            SELECT c.*,
                   i.image as infobox_image,
                   i.path as infobox_path
            FROM wiki_character c
            LEFT JOIN wiki_image i ON i.id = c.image_id
            WHERE c.id IN ($placeholders)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids);
        $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($characters as &$char) {
            $char['relations'] = $this->getCharacterRelations($char['id']);
        }
        
        return $characters;
    }
    
    private function getCharacterRelations(int $characterId): array {
        $relations = [];
        
        // Games
        $sql = "
            SELECT CONCAT('Games/', g.mw_page_name) as page
            FROM wiki_assoc_game_character gc
            JOIN wiki_game g ON g.id = gc.game_id
            WHERE gc.character_id = ? AND g.mw_page_name IS NOT NULL
            LIMIT 20
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$characterId]);
        $relations['games'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'page');
        
        // Friends
        $sql = "
            SELECT CONCAT('Characters/', f.mw_page_name) as page
            FROM wiki_assoc_character_friend cf
            JOIN wiki_character f ON f.id = cf.friend_character_id
            WHERE cf.character_id = ? AND f.mw_page_name IS NOT NULL
            LIMIT 15
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$characterId]);
        $relations['friends'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'page');
        
        // Enemies
        $sql = "
            SELECT CONCAT('Characters/', e.mw_page_name) as page
            FROM wiki_assoc_character_enemy ce
            JOIN wiki_character e ON e.id = ce.enemy_character_id
            WHERE ce.character_id = ? AND e.mw_page_name IS NOT NULL
            LIMIT 15
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$characterId]);
        $relations['enemies'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'page');
        
        // Franchises
        $sql = "
            SELECT CONCAT('Franchises/', f.mw_page_name) as page
            FROM wiki_assoc_character_franchise cf
            JOIN wiki_franchise f ON f.id = cf.franchise_id
            WHERE cf.character_id = ? AND f.mw_page_name IS NOT NULL
            LIMIT 10
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$characterId]);
        $relations['franchises'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'page');
        
        // Concepts
        $sql = "
            SELECT CONCAT('Concepts/', co.mw_page_name) as page
            FROM wiki_assoc_character_concept cc
            JOIN wiki_concept co ON co.id = cc.concept_id
            WHERE cc.character_id = ? AND co.mw_page_name IS NOT NULL
            LIMIT 15
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$characterId]);
        $relations['concepts'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'page');
        
        // Locations
        $sql = "
            SELECT CONCAT('Locations/', l.mw_page_name) as page
            FROM wiki_assoc_character_location cl
            JOIN wiki_location l ON l.id = cl.location_id
            WHERE cl.character_id = ? AND l.mw_page_name IS NOT NULL
            LIMIT 15
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$characterId]);
        $relations['locations'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'page');
        
        // Objects
        $sql = "
            SELECT CONCAT('Objects/', t.mw_page_name) as page
            FROM wiki_assoc_character_thing ct
            JOIN wiki_thing t ON t.id = ct.thing_id
            WHERE ct.character_id = ? AND t.mw_page_name IS NOT NULL
            LIMIT 15
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$characterId]);
        $relations['objects'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'page');
        
        // People (voice actors etc)
        $sql = "
            SELECT CONCAT('People/', p.mw_page_name) as page
            FROM wiki_assoc_character_person cp
            JOIN wiki_person p ON p.id = cp.person_id
            WHERE cp.character_id = ? AND p.mw_page_name IS NOT NULL
            LIMIT 10
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$characterId]);
        $relations['people'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'page');
        
        return $relations;
    }
    
    private function generateXml(array $characters, string $path): void {
        $xml = new XMLWriter();
        $xml->openURI($path);
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNS(null, 'mediawiki', 'http://www.mediawiki.org/xml/export-0.11/');
        $xml->writeAttribute('version', '0.11');
        $xml->writeAttribute('xml:lang', 'en');
        
        foreach ($characters as $char) {
            $this->output("  - {$char['name']} (ID: {$char['id']})\n");
            
            $xml->startElement('page');
            $xml->writeElement('title', 'Characters/' . $char['mw_page_name']);
            $xml->writeElement('ns', '0');
            
            $xml->startElement('revision');
            $xml->startElement('contributor');
            $xml->writeElement('username', 'Giantbomb');
            $xml->writeElement('id', '1');
            $xml->endElement(); // contributor
            
            $xml->writeElement('model', 'wikitext');
            $xml->writeElement('format', 'text/x-wiki');
            
            $xml->startElement('text');
            $xml->writeAttribute('xml:space', 'preserve');
            $xml->writeRaw($this->formatCharacterWikitext($char));
            $xml->endElement(); // text
            
            $xml->endElement(); // revision
            $xml->endElement(); // page
        }
        
        $xml->endElement(); // mediawiki
        $xml->endDocument();
        $xml->flush();
        
        $this->output("\nGenerated: $path\n");
    }
    
    private function formatCharacterWikitext(array $char): string {
        $text = "{{Character\n";
        $text .= "| Name=" . htmlspecialchars($char['name'], ENT_XML1) . "\n";
        $text .= "| Guid=3005-{$char['id']}\n";
        
        if (!empty($char['aliases'])) {
            $aliases = str_replace("\n", ",", trim($char['aliases']));
            $text .= "| Aliases=" . htmlspecialchars($aliases, ENT_XML1) . "\n";
        }
        if (!empty($char['deck'])) {
            $text .= "| Deck=" . htmlspecialchars($char['deck'], ENT_XML1) . "\n";
        }
        if (!empty($char['real_name'])) {
            $text .= "| RealName=" . htmlspecialchars($char['real_name'], ENT_XML1) . "\n";
        }
        if (isset($char['gender']) && $char['gender'] !== null) {
            $gender = match((int)$char['gender']) {
                0 => 'Female',
                1 => 'Male',
                default => 'Other'
            };
            $text .= "| Gender=$gender\n";
        }
        if (!empty($char['birthday'])) {
            $text .= "| Birthday={$char['birthday']}\n";
        }
        if (!empty($char['death'])) {
            $text .= "| Death={$char['death']}\n";
        }
        
        // Relations
        $rel = $char['relations'];
        if (!empty($rel['franchises'])) {
            $text .= "| Franchises=" . implode(',', $rel['franchises']) . "\n";
        }
        if (!empty($rel['games'])) {
            $text .= "| Games=" . implode(',', $rel['games']) . "\n";
        }
        if (!empty($rel['friends'])) {
            $text .= "| Friends=" . implode(',', $rel['friends']) . "\n";
        }
        if (!empty($rel['enemies'])) {
            $text .= "| Enemies=" . implode(',', $rel['enemies']) . "\n";
        }
        if (!empty($rel['concepts'])) {
            $text .= "| Concepts=" . implode(',', $rel['concepts']) . "\n";
        }
        if (!empty($rel['locations'])) {
            $text .= "| Locations=" . implode(',', $rel['locations']) . "\n";
        }
        if (!empty($rel['objects'])) {
            $text .= "| Objects=" . implode(',', $rel['objects']) . "\n";
        }
        if (!empty($rel['people'])) {
            $text .= "| People=" . implode(',', $rel['people']) . "\n";
        }
        
        $text .= "}}\n";
        
        // Image data
        if (!empty($char['infobox_image'])) {
            $imageData = [
                'infobox' => [
                    'file' => basename($char['infobox_image']),
                    'path' => $char['infobox_path'] ?? '',
                ],
                'background' => []
            ];
            $text .= "<div id='imageData' data-json='" . htmlspecialchars(json_encode($imageData), ENT_QUOTES) . "' />\n";
        }
        
        // Description
        if (!empty($char['mw_formatted_description'])) {
            $text .= "\n" . $char['mw_formatted_description'];
        } elseif (!empty($char['deck'])) {
            $text .= "\n" . htmlspecialchars($char['deck'], ENT_XML1);
        }
        
        return $text;
    }
}

$maintClass = ImportCharactersFromDb::class;
require_once RUN_MAINTENANCE_IF_MAIN;
