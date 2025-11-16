<?php
use MediaWiki\Html\TemplateParser;
use MediaWiki\MediaWikiServices;

/**
 * Platforms page View
 * Displays a list of all platforms with filtering and pagination
 */

// Load API endpoint for AJAX requests
require_once __DIR__ . '/../api/platforms-api.php';

require_once __DIR__ . '/../helpers/PlatformHelper.php';

// Get filter parameters from URL
$request = RequestContext::getMain()->getRequest();
$filterLetter = $request->getText('letter', '');
$filterGameTitle = $request->getText('game_title', '');
$sort = $request->getText('sort', 'alphabetical');
$page = $request->getInt('page', 1);

// Query platforms using helper function
$result = queryPlatformsFromSMW($filterLetter, $filterGameTitle, $sort, $page);

// Format data for Mustache template
$data = [
    'platforms' => $result['platforms'],
    'totalCount' => $result['totalCount'],
    'currentPage' => $result['currentPage'],
    'totalPages' => $result['totalPages'],
    'currentLetter' => htmlspecialchars($filterLetter, ENT_QUOTES, 'UTF-8'),
    'currentSort' => htmlspecialchars($sort, ENT_QUOTES, 'UTF-8'),
    'vue' => [
        'platformsJson' => htmlspecialchars(json_encode($result['platforms']), ENT_QUOTES, 'UTF-8'),
    ],
];

// Path to Mustache templates
$templateDir = realpath(__DIR__ . '/../templates');

// Render Mustache template
$templateParser = new TemplateParser($templateDir);
echo $templateParser->processTemplate('platforms-page', $data);
