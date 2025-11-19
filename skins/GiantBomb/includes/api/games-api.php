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
    $page = $request->getInt('page', 1);
    $returnLimit = $request->getInt('returnLimit', 10);
    
    $gamesData = queryGamesFromSMW($filterText, $page, $returnLimit);
    
    $response = [
        'success' => true,
        'games' => $games,
        'count' => count($games),
        'filters' => [
            'name' => $filterText
        ]
    ];
    
    echo json_encode($response);
    exit;
}

error_log("games-api.php: action was not 'get-games'");

