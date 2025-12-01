<?php
/**
 * Games API Endpoint
 * Returns game data as JSON for async filtering
 */

// Load games helper functions
require_once __DIR__ . '/../helpers/GamesHelper.php';

$request = RequestContext::getMain()->getRequest();
$action = $request->getText('action', '');

if ($action === 'get-games') {
    // Set HTTP status to 200 OK (MediaWiki may have set it to 404)
    http_response_code(200);
    header('Content-Type: application/json');
    
    $filterText = $request->getText('name', '');
    $platformFilter = $request->getText('platform', '');
    $sortOrder = $request->getText('sort', 'title-asc');
    $currentPage = $request->getInt('page', 1);
    $itemsPerPage = $request->getInt('itemsPerPage', 25);
    
    $gamesData = queryGamesFromSMW($filterText, $platformFilter, $sortOrder, $currentPage, $itemsPerPage);
    
    $response = [
        'success' => true,
        'games' => $gamesData['games'] ?? [],
        'totalGames' => $gamesData['totalGames'] ?? 0,
        'totalPages' => $gamesData['totalPages'] ?? 1,
        'currentPage' => $gamesData['currentPage'] ?? 1,
        'hasMore' => ($gamesData['currentPage'] ?? 1) < ($gamesData['totalPages'] ?? 1),
        'filters' => [
            'name' => $filterText,
            'platform' => $platformFilter,
            'sort' => $sortOrder,
        ]
    ];
    
    echo json_encode($response);
    exit;
}

error_log("games-api.php: action was not 'get-games'");

