<?php
use MediaWiki\Html\TemplateParser;
use MediaWiki\MediaWikiServices;

// Define default category buttons in case JSON fails or is missing,
// or if specific properties are not set in the JSON.
// These are structured as objects, aligning with the expected JSON format.
$defaultCategoryButtons = [
    ['name' => 'Home', 'className' => '', 'iconClass' => ''],
    ['name' => 'Games', 'className' => '', 'iconClass' => ''],
    ['name' => 'Characters', 'className' => '', 'iconClass' => ''],
    ['name' => 'Companies', 'className' => '', 'iconClass' => ''],
    ['name' => 'Concepts', 'className' => '', 'iconClass' => ''],
    ['name' => 'Franchises', 'className' => '', 'iconClass' => ''],
    ['name' => 'Locations', 'className' => '', 'iconClass' => ''],
    ['name' => 'People', 'className' => '', 'iconClass' => ''],
    ['name' => 'Platforms', 'className' => '', 'iconClass' => ''],
    ['name' => 'Objects', 'className' => '', 'iconClass' => ''],
    // The Accessories button with new styling classes and an icon
    ['name' => 'Accessories', 'className' => 'category-button-accessories', 'iconClass' => 'icon-shopping-bag']
];

// File path to the category buttons JSON data
$jsonFilePath = __DIR__ . '/../../resources/data/categoryButtons.json'; // Corrected relative path

// Initialize with default buttons
$categoryButtons = $defaultCategoryButtons;

// Attempt to load and parse category buttons from JSON
if (file_exists($jsonFilePath)) {
    $jsonContent = file_get_contents($jsonFilePath);
    if ($jsonContent !== false) {
        $parsedData = json_decode($jsonContent, true);
        // Check if JSON was decoded successfully and is an array
        if (json_last_error() === JSON_ERROR_NONE && is_array($parsedData)) {
            $categoryButtons = $parsedData;
        } else {
            // Log error if JSON parsing failed but file exists and was read
            error_log("Landing page: Failed to decode categoryButtons.json. Error: " . json_last_error_msg());
            // Fallback to default buttons
        }
    } else {
        // Log error if file content could not be read
        error_log("Landing page: Failed to read categoryButtons.json content.");
        // Fallback to default buttons
    }
} else {
    // Log error if file does not exist
    error_log("Landing page: categoryButtons.json not found at " . $jsonFilePath);
    // Fallback to default buttons
}


// Query games from MediaWiki database directly
$games = [];
try {
	// Check if we can access the database
	$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection(DB_REPLICA);

	// Query pages in the Games namespace with semantic properties
	// Get only main game pages (not subpages like /Credits or /Releases)
	$res = $dbr->select(
		'page',
		['page_id', 'page_title'],
		[
			'page_namespace' => 0,
			'page_title' . $dbr->buildLike('Games/', $dbr->anyString()),
			// Exclude subpages by ensuring there's no second slash after Games/
			'page_title NOT' . $dbr->buildLike($dbr->anyString(), '/', $dbr->anyString(), '/', $dbr->anyString())
		],
		__METHOD__,
		[
			'LIMIT' => 100,
			'ORDER BY' => 'page_id ASC'
		]
	);

	$index = 0;
	foreach ($res as $row) {
		// Get page data
		$pageData = [];
		$pageData['index'] = $index++;
		$pageData['title'] = str_replace('Games/', '', str_replace('_', ' ', $row->page_title));
		$pageData['url'] = '/index.php/' . $row->page_title;

		// Get the page content directly and parse it
		try {
			$title = \Title::newFromID($row->page_id);
			$wikiPageFactory = \MediaWiki\MediaWikiServices::getInstance()->getWikiPageFactory();
			$page = $wikiPageFactory->newFromTitle($title);
			$content = $page->getContent();

			if ($content) {
				$text = $content->getText();

				// Parse the wikitext for Game template properties
				if (preg_match('/\| Name=([^\n]+)/', $text, $matches)) {
					$pageData['title'] = trim($matches[1]);
				}
				if (preg_match('/\| Deck=([^\n]+)/', $text, $matches)) {
					$pageData['desc'] = trim($matches[1]);
				}
				if (preg_match('/\| Image=([^\n]+)/', $text, $matches)) {
					$pageData['img'] = trim($matches[1]);
				}
				if (preg_match('/\| ReleaseDate=([^\n]+)/', $text, $matches)) {
					$releaseDate = trim($matches[1]);
					if ($releaseDate !== '0000-00-00' && !empty($releaseDate)) {
						$pageData['date'] = $releaseDate;
					}
				}
				if (preg_match('/\| Platforms=([^\n]+)/', $text, $matches)) {
					$platformsStr = trim($matches[1]);
					$platforms = explode(',', $platformsStr);
					$pageData['platforms'] = array_map(function($p) {
						return str_replace('Platforms/', '', trim($p));
					}, $platforms);
				}
			}
		} catch (Exception $e) {
			// Continue with defaults
		}

		// Set defaults for missing data
		if (!isset($pageData['desc'])) $pageData['desc'] = '';
		if (!isset($pageData['img'])) $pageData['img'] = '';
		if (!isset($pageData['date'])) $pageData['date'] = '';
		if (!isset($pageData['platforms'])) $pageData['platforms'] = [];

		$games[] = $pageData;
	}
} catch (Exception $e) {
	// Log error but don't show sample data
	error_log("Landing page error: " . $e->getMessage());
}

$buttonData = [];

// Populate buttonData from the loaded category buttons
// This loop extracts the 'name', 'className', and 'iconClass' from each category
// and prepares them for the Mustache template.
foreach ($categoryButtons as $category) {
    $buttonData[] = [
        'title'     => $category['name'] ?? '', // Use 'name' from JSON or default
        'label'     => $category['name'] ?? '', // Label for display, typically same as name
        'className' => $category['className'] ?? '', // Pass className for custom styling
        'iconClass' => $category['iconClass'] ?? ''  // Pass iconClass for custom icons
    ];
}

// Set Mustache data - just show all games
$data = [
    'buttons' => $buttonData,
    'games' => $games,
];

// Path to Mustache templates
$templateDir = realpath(__DIR__ . '/../templates');

// Render Mustache template
$templateParser = new TemplateParser($templateDir);
echo $templateParser->processTemplate('landing-page', $data);