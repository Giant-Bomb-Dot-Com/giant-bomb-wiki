<?php
/**
 * Releases API Endpoint
 * Returns release data as JSON for async filtering
 */

// Load releases helper functions
require_once __DIR__ . '/../helpers/ReleasesHelper.php';

$request = RequestContext::getMain()->getRequest();
$action = $request->getText('action', '');

if ($action === 'get-releases') {
    // Set HTTP status to 200 OK (MediaWiki may have set it to 404)
    http_response_code(200);
    header('Content-Type: application/json');
    
    $filterRegion = $request->getText('region', '');
    $filterPlatform = $request->getText('platform', '');
    
    $releases = queryReleasesFromSMW($filterRegion, $filterPlatform);
    $weekGroups = groupReleasesByPeriod($releases);
    
    $response = [
        'success' => true,
        'weekGroups' => $weekGroups,
        'count' => count($releases),
        'filters' => [
            'region' => $filterRegion,
            'platform' => $filterPlatform
        ]
    ];
    
    echo json_encode($response);
    exit;
}

error_log("releases-api.php: action was not 'get-releases'");

