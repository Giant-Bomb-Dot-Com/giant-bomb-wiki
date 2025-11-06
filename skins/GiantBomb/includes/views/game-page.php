<?php
use MediaWiki\Html\TemplateParser;
use MediaWiki\MediaWikiServices;

/**
 * Game Page View
 * Displays comprehensive game information with all related data
 */

// Get the current page title
$title = $this->getSkin()->getTitle();
$pageTitle = $title->getText();
$pageTitleDB = $title->getDBkey(); // Database format with underscores

// Initialize game data structure
$gameData = [
	'name' => str_replace('Games/', '', str_replace('_', ' ', $pageTitle)),
	'url' => '/' . $pageTitle,
	'image' => '',
	'deck' => '',
	'description' => '',
	'releaseDate' => '',
	'releaseDateType' => '',
	'aliases' => '',
	'guid' => '',

	// Companies
	'developers' => [],
	'publishers' => [],

	// Classification
	'platforms' => [],
	'genres' => [],
	'themes' => [],
	'franchise' => '',

	// Related content
	'characters' => [],
	'concepts' => [],
	'locations' => [],
	'objects' => [],
	'similarGames' => [],

	// Sub-pages
	'hasReleases' => false,
	'hasDLC' => false,
	'hasCredits' => false,
];

try {
	// Get page content
	$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
	$page = $wikiPageFactory->newFromTitle($title);
	$content = $page->getContent();

	if ($content) {
		$text = $content->getText();

		// Extract wikitext (content after the template closing}})
		$wikitext = '';
		if (preg_match('/\}\}(.+)$/s', $text, $matches)) {
			$wikitext = trim($matches[1]);
			error_log("Extracted wikitext length: " . strlen($wikitext));
		} else {
			error_log("No content found after template closing");
		}

		// Parse the wikitext to HTML
		if (!empty($wikitext)) {
			error_log("Parsing wikitext...");
			try {
				$services = MediaWikiServices::getInstance();
				$parser = $services->getParser();
				$parserOptions = \ParserOptions::newFromAnon();

				$parserOutput = $parser->parse($wikitext, $title, $parserOptions);
				$gameData['description'] = $parserOutput->getText([
					'allowTOC' => false,
					'enableSectionEditLinks' => false,
					'wrapperDivClass' => ''
				]);
			} catch (Exception $e) {
				error_log("Failed to parse wikitext: " . $e->getMessage());
				// Fallback to raw wikitext if parsing fails
				$gameData['description'] = $wikitext;
			}
		}

		// Parse template parameters
		if (preg_match('/\| Name=([^\n]+)/', $text, $matches)) {
			$gameData['name'] = trim($matches[1]);
		}
		if (preg_match('/\| Deck=([^\n]+)/', $text, $matches)) {
			$gameData['deck'] = trim($matches[1]);
		}
		if (preg_match('/\| Image=([^\n]+)/', $text, $matches)) {
			$gameData['image'] = trim($matches[1]);
		}
		if (preg_match('/\| ReleaseDate=([^\n]+)/', $text, $matches)) {
			$gameData['releaseDate'] = trim($matches[1]);
		}
		if (preg_match('/\| ReleaseDateType=([^\n]+)/', $text, $matches)) {
			$gameData['releaseDateType'] = trim($matches[1]);
		}
		if (preg_match('/\| Aliases=([^\n]+)/', $text, $matches)) {
			$gameData['aliases'] = trim($matches[1]);
		}
		if (preg_match('/\| Guid=([^\n]+)/', $text, $matches)) {
			$gameData['guid'] = trim($matches[1]);
		}

		// Parse array fields (comma-separated values)
		$arrayFields = [
			'Developers' => 'developers',
			'Publishers' => 'publishers',
			'Platforms' => 'platforms',
			'Genres' => 'genres',
			'Themes' => 'themes',
			'Characters' => 'characters',
			'Concepts' => 'concepts',
			'Locations' => 'locations',
			'Objects' => 'objects',
			'Games' => 'similarGames',
		];

		foreach ($arrayFields as $templateField => $dataField) {
			if (preg_match('/\| ' . $templateField . '=([^\n]+)/', $text, $matches)) {
				$values = explode(',', trim($matches[1]));
				$gameData[$dataField] = array_filter(array_map(function($v) {
					$cleaned = trim($v);
					// Remove namespace prefix (e.g., "Platforms/PlayStation 4" -> "PlayStation 4")
					if (strpos($cleaned, '/') !== false) {
						$parts = explode('/', $cleaned, 2);
						$cleaned = $parts[1];
					}
					// Replace underscores with spaces for better display
					$cleaned = str_replace('_', ' ', $cleaned);
					return $cleaned;
				}, $values));
			}
		}

		// Parse franchise (single value)
		if (preg_match('/\| Franchise=([^\n]+)/', $text, $matches)) {
			$franchise = trim($matches[1]);
			if (strpos($franchise, '/') !== false) {
				$parts = explode('/', $franchise, 2);
				$franchise = $parts[1];
			}
			// Replace underscores with spaces for better display
			$franchise = str_replace('_', ' ', $franchise);
			$gameData['franchise'] = $franchise;
		}
	}

	// Check for sub-pages
	$gameData['hasReleases'] = \Title::newFromText($pageTitle . '/Releases')->exists();
	$gameData['hasDLC'] = \Title::newFromText($pageTitle . '/DLC')->exists();
	$gameData['hasCredits'] = \Title::newFromText($pageTitle . '/Credits')->exists();

	// Get images linked from this page
	$gameData['images'] = [];
	try {
		$services = MediaWikiServices::getInstance();
		$dbLoadBalancer = $services->getDBLoadBalancer();
		$db = $dbLoadBalancer->getConnection(DB_REPLICA);

		// Get page ID
		$pageId = $title->getArticleID();

		// Query imagelinks table for image references
		$result = $db->select(
			'imagelinks',
			['il_to'],
			['il_from' => $pageId],
			__METHOD__
		);

		foreach ($result as $row) {
			$gameData['images'][] = [
				'url' => $row->il_to,
				'caption' => basename($row->il_to),
				'width' => 0,
				'height' => 0
			];
		}
	} catch (Exception $e) {
		error_log("Failed to fetch game images: " . $e->getMessage());
	}

	$gameData['imagesCount'] = count($gameData['images']);

	// TODO: Get actual release count from database
	$gameData['releasesCount'] = 0;

	// Convert booleans to strings for Vue props
	$gameData['hasReleasesStr'] = $gameData['hasReleases'] ? 'true' : 'false';
	$gameData['hasDLCStr'] = $gameData['hasDLC'] ? 'true' : 'false';

} catch (Exception $e) {
	error_log("Game page error: " . $e->getMessage());
}

// Prepare data for Vue components (comma-separated strings)
$vueData = [
	'platformsStr' => !empty($gameData['platforms']) ? implode(',', $gameData['platforms']) : '',
	'genresStr' => !empty($gameData['genres']) ? implode(',', $gameData['genres']) : '',
	'themesStr' => !empty($gameData['themes']) ? implode(',', $gameData['themes']) : '',
	'charactersStr' => !empty($gameData['characters']) ? implode(',', $gameData['characters']) : '',
	'conceptsStr' => !empty($gameData['concepts']) ? implode(',', $gameData['concepts']) : '',
	'locationsStr' => !empty($gameData['locations']) ? implode(',', $gameData['locations']) : '',
	'objectsStr' => !empty($gameData['objects']) ? implode(',', $gameData['objects']) : '',
	'similarGamesStr' => !empty($gameData['similarGames']) ? implode(',', $gameData['similarGames']) : '',
];

// Format data for Mustache template
$data = [
	'game' => $gameData,
	'vue' => $vueData,
	'hasBasicInfo' => !empty($gameData['deck']) || !empty($gameData['releaseDate']) || !empty($gameData['aliases']),
	'hasCompanies' => !empty($gameData['developers']) || !empty($gameData['publishers']),
	'hasClassification' => !empty($gameData['platforms']) || !empty($gameData['genres']) || !empty($gameData['themes']) || !empty($gameData['franchise']),
	'hasRelatedContent' => !empty($gameData['characters']) || !empty($gameData['concepts']) || !empty($gameData['locations']) || !empty($gameData['objects']) || !empty($gameData['similarGames']),
	'hasSubPages' => $gameData['hasReleases'] || $gameData['hasDLC'] || $gameData['hasCredits'],
];

// Path to Mustache templates
$templateDir = realpath(__DIR__ . '/../templates');

// Render Mustache template
$templateParser = new TemplateParser($templateDir);
echo $templateParser->processTemplate('game-page', $data);
