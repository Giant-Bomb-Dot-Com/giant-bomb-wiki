<?php
use MediaWiki\Html\TemplateParser;
use MediaWiki\MediaWikiServices;

/**
 * Concepts page View
 * Displays a list of all concepts with filtering and pagination
 */

require_once __DIR__ . '/../helpers/ConceptHelper.php';
require_once __DIR__ . '/../helpers/Constants.php';

// Set HTTP status to 200 OK (MediaWiki responds with 404 for non-existent wiki pages)
http_response_code(200);

// Get filter parameters from URL
$request = RequestContext::getMain()->getRequest();
$filterLetter = $request->getText('letter', '');
$filterGameTitles = $request->getArray('game_title');
$requireAllGames = $request->getBool('require_all_games', false);
$sort = $request->getText('sort', 'alphabetical');
$page = $request->getInt('page', 1);
$pageSize = $request->getInt('page_size', DEFAULT_PAGE_SIZE);

// Query platforms using helper function
$result = queryConceptsFromSMW($filterLetter, $filterGameTitles, $sort, $page, $pageSize, $requireAllGames);

$filterGameTitlesString = $filterGameTitles ? implode("||", array_map(function($game) { return htmlspecialchars($game, ENT_QUOTES, 'UTF-8'); }, $filterGameTitles)) : "";

// Format data for Mustache template
$data = [
    'concepts' => $result['concepts'],
    'totalCount' => $result['totalCount'],
    'currentPage' => $result['currentPage'],
    'totalPages' => $result['totalPages'],
    'pageSize' => $result['pageSize'],
    'currentLetter' => htmlspecialchars($filterLetter, ENT_QUOTES, 'UTF-8'),
    'currentSort' => htmlspecialchars($sort, ENT_QUOTES, 'UTF-8'),
    'currentRequireAllGames' => $requireAllGames ? "true" : "false",
    'currentGames' => $filterGameTitlesString,
    'addConceptUrl' => htmlspecialchars('/wiki/Form:Concept', ENT_QUOTES, 'UTF-8'),
    'vue' => [
        'conceptsJson' => htmlspecialchars(json_encode($result['concepts']), ENT_QUOTES, 'UTF-8'),
    ],
];

// Path to Mustache templates
$templateDir = realpath(__DIR__ . '/../templates');

// Render Mustache template
$templateParser = new TemplateParser($templateDir);
echo $templateParser->processTemplate('concepts-page', $data);
