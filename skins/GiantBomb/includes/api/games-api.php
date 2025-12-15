<?php
/**
 * Games API Endpoint
 * Returns game data as JSON for async filtering
 */

// Load games helper functions
require_once __DIR__ . '/../helpers/GamesHelper.php';
require_once __DIR__ . '/../helpers/Constants.php';

$request = RequestContext::getMain()->getRequest();
$action = $request->getText('action', '');

if ($action === 'get-games') {
	// Set HTTP status to 200 OK (MediaWiki may have set it to 404)
	http_response_code(200);
	header('Content-Type: application/json');

	// Get filter parameters
	$searchQuery = trim($request->getText('search', ''));
	$platformFilter = trim($request->getText('platform', ''));
	$sortOrder = $request->getText('sort', 'title-asc');
	$currentPage = max(1, $request->getInt('page', 1));
	$itemsPerPage = max(1, min(100, $request->getInt('perPage', DEFAULT_PAGE_SIZE)));

	// Query games using helper function
	$result = queryGamesFromSMW($searchQuery, $platformFilter, $sortOrder, $currentPage, $itemsPerPage);
	$games = $result['games'];
	$totalGames = $result['totalGames'];

	// Calculate pagination
	$totalPages = max(1, ceil($totalGames / $itemsPerPage));
	$startItem = $totalGames > 0 ? ($currentPage - 1) * $itemsPerPage + 1 : 0;
	$endItem = min($currentPage * $itemsPerPage, $totalGames);

	$response = [
		'success' => true,
		'games' => $games,
		'pagination' => [
			'currentPage' => $currentPage,
			'totalPages' => $totalPages,
			'itemsPerPage' => $itemsPerPage,
			'totalItems' => $totalGames,
			'startItem' => $startItem,
			'endItem' => $endItem
		],
		'filters' => [
			'search' => $searchQuery,
			'platform' => $platformFilter,
			'sort' => $sortOrder
		]
	];

	echo json_encode($response);
	exit;
}

error_log("games-api.php: action was not 'get-games'");
