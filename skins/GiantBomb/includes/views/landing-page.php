<?php
use MediaWiki\Html\TemplateParser;
use MediaWiki\MediaWikiServices;

// Define available category buttons
$buttons = [
	'Home',
	'Games',
	'Characters',
	'Companies',
	'Concepts',
	'Franchises',
	'Locations',
	'People',
	'Platforms',
	'Objects',
	'Accessories'
];

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
			'LIMIT' => 150,
			'ORDER BY' => 'page_id ASC'
		]
	);

	$index = 0;
	foreach ($res as $row) {
		// Get page data
		$pageData = [];
		$pageData['index'] = $index++;
		$pageData['title'] = str_replace('Games/', '', str_replace('_', ' ', $row->page_title));
		$pageData['url'] = '/wiki/' . $row->page_title;

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

// Extract unique platforms from all games
$allPlatforms = [];
foreach ($games as $game) {
	if (isset($game['platforms']) && is_array($game['platforms'])) {
		foreach ($game['platforms'] as $platform) {
			if (!in_array($platform, $allPlatforms)) {
				$allPlatforms[] = $platform;
			}
		}
	}
}
sort($allPlatforms);

$buttonData = [];

// Populate buttonData from buttons array
foreach ($buttons as $button) {
    $buttonData[] = [
        'title' => $button,
        'label' => $button
    ];
}

// Set Mustache data - pass games and platforms as JSON for Vue components
$data = [
    'buttons' => $buttonData,
    'games' => $games,
    'vue' => [
        'gamesJson' => htmlspecialchars(json_encode($games), ENT_QUOTES, 'UTF-8'),
        'platformsJson' => htmlspecialchars(json_encode($allPlatforms), ENT_QUOTES, 'UTF-8'),
    ],
];

// Path to Mustache templates
$templateDir = realpath(__DIR__ . '/../templates');

// Render Mustache template
$templateParser = new TemplateParser($templateDir);
echo $templateParser->processTemplate('landing-page', $data);
