<?php
use MediaWiki\Html\TemplateParser;
use MediaWiki\MediaWikiServices;

// Load helper functions
require_once __DIR__ . '/../helpers/GamesHelper.php';
require_once __DIR__ . '/../helpers/PlatformHelper.php';

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

// Query games using helper function
$result = queryGamesFromSMW($searchQuery, $platformFilter, $sortOrder, $currentPage, $itemsPerPage);
$games = $result['games'];
$totalGames = $result['totalGames'];

// Get all platforms for filter dropdown (cached for 24 hours)
$platforms = getAllPlatforms();
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
        'platformsJson' => htmlspecialchars(json_encode($platforms), ENT_QUOTES, 'UTF-8'),
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
