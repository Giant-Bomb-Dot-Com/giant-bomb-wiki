<?php
use MediaWiki\Html\TemplateParser;
use MediaWiki\MediaWikiServices;

// Load helper functions
require_once __DIR__ . '/../helpers/Constants.php';
require_once __DIR__ . '/../helpers/GamesHelper.php';
require_once __DIR__ . '/../helpers/PlatformHelper.php';


// Define available category buttons
$buttons = [
	[ 'label' => 'Home', 'url' => '/wiki/Main_Page' ],
	[ 'label' => 'Games', 'url' => '/wiki/Category:Games' ],
	[ 'label' => 'Characters', 'url' => '/wiki/Category:Characters' ],
	[ 'label' => 'Companies', 'url' => '/wiki/Category:Companies' ],
	[ 'label' => 'Concepts', 'url' => '/wiki/Category:Concepts' ],
	[ 'label' => 'Franchises', 'url' => '/wiki/Category:Franchises' ],
	[ 'label' => 'Locations', 'url' => '/wiki/Category:Locations' ],
	[ 'label' => 'People', 'url' => '/wiki/Category:People' ],
	[ 'label' => 'Platforms', 'url' => '/wiki/Category:Platforms' ],
	[ 'label' => 'Objects', 'url' => '/wiki/Category:Objects' ],
	[ 'label' => 'Accessories', 'url' => '/wiki/Category:Accessories' ],
	[ 'label' => 'Releases', 'url' => '/wiki/Special:NewReleases' ],
];

$wikiTypes = [
	[ 'title' => 'All', 'label' => 'All' ],
	[ 'title' => 'Games', 'label' => 'Games' ],
	[ 'title' => 'Characters', 'label' => 'Characters' ],
	[ 'title' => 'Companies', 'label' => 'Companies' ],
	[ 'title' => 'Concepts', 'label' => 'Concepts' ],
	[ 'title' => 'Franchises', 'label' => 'Franchises' ],
	[ 'title' => 'Locations', 'label' => 'Locations' ],
	[ 'title' => 'People', 'label' => 'People' ],
	[ 'title' => 'Platforms', 'label' => 'Platforms' ],
	[ 'title' => 'Objects', 'label' => 'Objects' ],
	[ 'title' => 'Accessories', 'label' => 'Accessories' ],
];

$request = RequestContext::getMain()->getRequest();
$currentPage = max(1, $request->getInt('page', 1));
$itemsPerPage = max(24, min(100, $request->getInt('perPage', DEFAULT_PAGE_SIZE)));
$searchQuery = trim($request->getText('search', ''));
$platformFilter = trim($request->getText('platform', ''));
$sortOrder = $request->getText('sort', 'title-asc');

$result = queryGamesFromSMW($searchQuery, $platformFilter, $sortOrder, $currentPage, $itemsPerPage);
$games = $result['games'];
$totalGames = $result['totalGames'];

$platforms = getAllPlatforms();

$totalPages = max(1, ceil($totalGames / $itemsPerPage));
$startItem = $totalGames > 0 ? ($currentPage - 1) * $itemsPerPage + 1 : 0;
$endItem = min($currentPage * $itemsPerPage, $totalGames);

$data = [
	'buttons' => $buttons,
	'wikiTypes' => $wikiTypes,
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

$templateDir = realpath(__DIR__ . '/../templates');
$templateParser = new TemplateParser($templateDir);
echo $templateParser->processTemplate('landing-page', $data);
