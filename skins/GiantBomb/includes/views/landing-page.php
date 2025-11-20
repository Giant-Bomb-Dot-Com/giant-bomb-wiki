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

// Get pagination and filter parameters from URL
$request = RequestContext::getMain()->getRequest();
$currentPage = max(1, $request->getInt('page', 1));
$itemsPerPage = max(25, min(100, $request->getInt('perPage', 25)));
$searchQuery = trim($request->getText('search', ''));
$platformFilter = trim($request->getText('platform', ''));
$sortOrder = $request->getText('sort', 'title-asc');

// Query games from MediaWiki database directly
$games = [];
$totalGames = 0;

try {
	// Check if we can access the database
	$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection(DB_REPLICA);

	// Base conditions for game pages
	$conditions = [
		'page_namespace' => 0,
		'page_title' . $dbr->buildLike('Games/', $dbr->anyString()),
		// Exclude subpages by ensuring there's no second slash after Games/
		'page_title NOT' . $dbr->buildLike($dbr->anyString(), '/', $dbr->anyString(), '/', $dbr->anyString())
	];

	// Add search filter if provided
	if (!empty($searchQuery)) {
		$conditions[] = 'page_title ' . $dbr->buildLike($dbr->anyString(), $searchQuery, $dbr->anyString());
	}

	// First, get total count for pagination
	$totalGames = $dbr->selectField(
		'page',
		'COUNT(*)',
		$conditions,
		__METHOD__
	);

	// Determine sort order
	$orderBy = 'page_title ASC';
	switch ($sortOrder) {
		case 'title-desc':
			$orderBy = 'page_title DESC';
			break;
		case 'date-desc':
		case 'date-asc':
			// For date sorting, we'll need to sort after fetching since dates are in page content
			$orderBy = 'page_id ASC';
			break;
		default:
			$orderBy = 'page_title ASC';
	}

	// Calculate offset
	$offset = ($currentPage - 1) * $itemsPerPage;

	// Query pages with pagination
	$res = $dbr->select(
		'page',
		['page_id', 'page_title'],
		$conditions,
		__METHOD__,
		[
			'LIMIT' => $itemsPerPage,
			'OFFSET' => $offset,
			'ORDER BY' => $orderBy
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

		// Apply platform filter if needed
		if (!empty($platformFilter)) {
			$matchesPlatform = false;
			if (isset($pageData['platforms']) && is_array($pageData['platforms'])) {
				foreach ($pageData['platforms'] as $platform) {
					if (stripos($platform, $platformFilter) !== false) {
						$matchesPlatform = true;
						break;
					}
				}
			}
			if (!$matchesPlatform) {
				continue; // Skip this game
			}
		}

		$games[] = $pageData;
	}

	// Handle date sorting in PHP since dates are in page content
	if ($sortOrder === 'date-desc' || $sortOrder === 'date-asc') {
		usort($games, function($a, $b) use ($sortOrder) {
			$dateA = $a['date'] ?? '';
			$dateB = $b['date'] ?? '';

			if ($sortOrder === 'date-desc') {
				return strcmp($dateB, $dateA);
			} else {
				return strcmp($dateA, $dateB);
			}
		});
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

// Calculate pagination data
$totalPages = max(1, ceil($totalGames / $itemsPerPage));
$startItem = $totalGames > 0 ? ($currentPage - 1) * $itemsPerPage + 1 : 0;
$endItem = min($currentPage * $itemsPerPage, $totalGames);

// Set Mustache data - pass games and platforms as JSON for Vue components
$data = [
    'buttons' => $buttonData,
    'games' => $games,
    'pagination' => [
        'currentPage' => $currentPage,
        'totalPages' => $totalPages,
        'itemsPerPage' => $itemsPerPage,
        'totalGames' => $totalGames,
        'startItem' => $startItem,
        'endItem' => $endItem,
    ],
    'vue' => [
        'gamesJson' => htmlspecialchars(json_encode($games), ENT_QUOTES, 'UTF-8'),
        'platformsJson' => htmlspecialchars(json_encode($allPlatforms), ENT_QUOTES, 'UTF-8'),
        'paginationJson' => htmlspecialchars(json_encode([
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'itemsPerPage' => $itemsPerPage,
            'totalItems' => $totalGames,
        ]), ENT_QUOTES, 'UTF-8'),
    ],
];

// Path to Mustache templates
$templateDir = realpath(__DIR__ . '/../templates');

// Render Mustache template
$templateParser = new TemplateParser($templateDir);
echo $templateParser->processTemplate('landing-page', $data);
