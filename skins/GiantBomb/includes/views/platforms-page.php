<?php
use MediaWiki\Html\TemplateParser;
use MediaWiki\MediaWikiServices;

/**
 * Platforms page View
 * Displays a list of all platforms with filtering and pagination
 */

require_once __DIR__ . '/../helpers/PlatformHelper.php';

// Set HTTP status to 200 OK (MediaWiki responds with 404 for non-existent wiki pages)
http_response_code(200);

const PAGE_SIZE = 48;

// Get filter parameters from URL
$request = RequestContext::getMain()->getRequest();
$filterLetter = $request->getText('letter', '');
$filterGameTitles = $request->getArray('game_title');
$requireAllGames = $request->getBool('require_all_games', false);
$sort = $request->getText('sort', 'release_date');
$page = $request->getInt('page', 1);
$pageSize = $request->getInt('page_size', PAGE_SIZE);

// Query platforms using helper function
$result = queryPlatformsFromSMW($filterLetter, $filterGameTitles, $sort, $page, $pageSize, $requireAllGames);

$filterGameTitlesString = $filterGameTitles ? implode("||", array_map(function($game) { return htmlspecialchars($game, ENT_QUOTES, 'UTF-8'); }, $filterGameTitles)) : "";

// Format data for Mustache template
$data = [
    'platforms' => $result['platforms'],
    'totalCount' => $result['totalCount'],
    'currentPage' => $result['currentPage'],
    'totalPages' => $result['totalPages'],
    'pageSize' => $result['pageSize'],
    'currentLetter' => htmlspecialchars($filterLetter, ENT_QUOTES, 'UTF-8'),
    'currentSort' => htmlspecialchars($sort, ENT_QUOTES, 'UTF-8'),
    'currentRequireAllGames' => $requireAllGames ? "true" : "false",
    'currentGames' => $filterGameTitlesString,
    'addPlatformUrl' => htmlspecialchars('/wiki/Form:Platform', ENT_QUOTES, 'UTF-8'),
    'vue' => [
        'platformsJson' => htmlspecialchars(json_encode($result['platforms']), ENT_QUOTES, 'UTF-8'),
    ],
];

// Path to Mustache templates
$templateDir = realpath(__DIR__ . '/../templates');

// Render Mustache template
$templateParser = new TemplateParser($templateDir);
echo $templateParser->processTemplate('platforms-page', $data);
