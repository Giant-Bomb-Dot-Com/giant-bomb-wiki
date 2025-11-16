<?php
/**
 * Platforms API Endpoint
 * Returns platform data as JSON for async filtering and pagination
 */

// Load platform helper functions
require_once __DIR__ . '/../helpers/PlatformHelper.php';

$request = RequestContext::getMain()->getRequest();
$action = $request->getText('action', '');

if ($action === 'get-platforms') {
    // Set HTTP status to 200 OK (MediaWiki responds with 404 for non-existent wiki pages)
    http_response_code(200);
    header('Content-Type: application/json');
    
    $filterLetter = $request->getText('letter', '');
    $filterGameTitle = $request->getText('game_title', '');
    $sort = $request->getText('sort', 'alphabetical');
    $page = $request->getInt('page', 1);
    
    $result = queryPlatformsFromSMW($filterLetter, $filterGameTitle, $sort, $page);
    
    $response = [
        'success' => true,
        'platforms' => $result['platforms'],
        'totalCount' => $result['totalCount'],
        'currentPage' => $result['currentPage'],
        'totalPages' => $result['totalPages'],
        'filters' => [
            'letter' => $filterLetter,
            'game_title' => $filterGameTitle,
            'sort' => $sort,
            'page' => $page
        ]
    ];
    
    echo json_encode($response);
    exit;
}

error_log("platforms-api.php: action was not 'get-platforms'");

