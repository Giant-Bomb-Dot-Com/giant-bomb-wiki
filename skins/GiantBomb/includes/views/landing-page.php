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

// Query games using SemanticMediaWiki API for efficient server-side sorting
$games = [];
$totalGames = 0;

try {
	$store = \SMW\StoreFactory::getStore();

	// Build SMW query conditions
	$queryConditions = '[[Category:Games]]';

	// Add search filter if provided (search in page title)
	if (!empty($searchQuery)) {
		$queryConditions .= '[[~*' . str_replace(['[', ']', '|'], '', $searchQuery) . '*]]';
	}

	// Add platform filter if provided
	if (!empty($platformFilter)) {
		$queryConditions .= '[[Has platforms::~*' . str_replace(['[', ']', '|'], '', $platformFilter) . '*]]';
	}

	// Determine sort property and order
	$smwSort = '';
	$smwOrder = 'asc';
	switch ($sortOrder) {
		case 'title-desc':
			$smwSort = '';  // Empty means sort by page title
			$smwOrder = 'desc';
			break;
		case 'date-desc':
			$smwSort = 'Has release date';
			$smwOrder = 'desc';
			break;
		case 'date-asc':
			$smwSort = 'Has release date';
			$smwOrder = 'asc';
			break;
		default:
			$smwSort = '';
			$smwOrder = 'asc';
	}

	// Calculate offset
	$offset = ($currentPage - 1) * $itemsPerPage;

	// Build raw params array for SMW query
	$rawParams = [
		$queryConditions,
		'limit=' . $itemsPerPage,
		'offset=' . $offset,
		'order=' . $smwOrder,
		'?Has name',
		'?Has deck',
		'?Has image',
		'?Has release date',
		'?Has platforms'
	];

	if (!empty($smwSort)) {
		$rawParams[] = 'sort=' . $smwSort;
	}

	// Parse query components
	list($queryString, $params, $printouts) = \SMWQueryProcessor::getComponentsFromFunctionParams(
		$rawParams,
		false
	);

	// Create and execute query
	$query = \SMWQueryProcessor::createQuery(
		$queryString,
		\SMWQueryProcessor::getProcessedParams($params),
		\SMWQueryProcessor::INLINE_QUERY,
		'',
		$printouts
	);

	$result = $store->getQueryResult($query);

	// Get total count with a separate count query using format=count
	$countParams = [
		$queryConditions,
		'format=count'
	];
	list($countQueryString, $countParamsProcessed, $countPrintouts) = \SMWQueryProcessor::getComponentsFromFunctionParams(
		$countParams,
		false
	);
	$countQuery = \SMWQueryProcessor::createQuery(
		$countQueryString,
		\SMWQueryProcessor::getProcessedParams($countParamsProcessed),
		\SMWQueryProcessor::INLINE_QUERY,
		'count',
		$countPrintouts
	);
	$countResult = $store->getQueryResult($countQuery);
	$totalGames = $countResult->getCountValue() ?: 0;

	// Process results
	$index = 0;
	while ($row = $result->getNext()) {
		$subject = $row[0]->getResultSubject();
		$title = $subject->getTitle();

		$pageData = [];
		$pageData['index'] = $index++;
		$pageData['url'] = '/wiki/' . $title->getPrefixedDBkey();

		// Default title from page name
		$pageData['title'] = str_replace('_', ' ', str_replace('Games/', '', $title->getText()));
		$pageData['desc'] = '';
		$pageData['img'] = '';
		$pageData['date'] = '';
		$pageData['platforms'] = [];

		// Extract property values from printouts
		for ($i = 1; $i < count($row); $i++) {
			$field = $row[$i];
			$pr = $field->getPrintRequest();
			$label = $pr->getLabel();

			$values = [];
			while ($dv = $field->getNextDataValue()) {
				$values[] = $dv->getShortWikiText();
			}

			switch ($label) {
				case 'Has name':
					if (!empty($values[0])) {
						$pageData['title'] = $values[0];
					}
					break;
				case 'Has deck':
					$pageData['desc'] = $values[0] ?? '';
					break;
				case 'Has image':
					$pageData['img'] = $values[0] ?? '';
					break;
				case 'Has release date':
					if (!empty($values[0])) {
						// SMW returns formatted date like "15 March 1993"
						// Convert to YYYY-MM-DD format
						$timestamp = strtotime($values[0]);
						if ($timestamp !== false) {
							$pageData['date'] = date('Y-m-d', $timestamp);
						}
					}
					break;
				case 'Has platforms':
					$pageData['platforms'] = array_map(function($p) {
						return str_replace('_', ' ', str_replace('Platforms/', '', $p));
					}, $values);
					break;
			}
		}

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
