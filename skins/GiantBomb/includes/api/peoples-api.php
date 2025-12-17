<?php /** @phpstan-ignore-file */
/**
 * People API Endpoint
 * Returns people data as JSON for async filtering and pagination
 */

require_once __DIR__ . '/../helpers/PeopleHelper.php';
require_once __DIR__ . '/../helpers/Constants.php';

$request = \RequestContext::getMain()->getRequest();
$action = $request->getText('action', '');

if ($action === 'get-people') {
	// Set HTTP status to 200 OK (MediaWiki responds with 404 for non-existent wiki pages)
	http_response_code(200);
	header('Content-Type: application/json');

	$filterLetter = $request->getText('letter', '');
	$filterGameTitles = $request->getArray('game_title');
	$requireAllGames = $request->getBool('require_all_games', false);
	$sort = $request->getText('sort', 'alphabetical');
	$page = $request->getInt('page', 1);
	$pageSize = $request->getInt('page_size', DEFAULT_PAGE_SIZE);

	$result = queryPeopleFromSMW($filterLetter, $filterGameTitles, $sort, $page, $pageSize, $requireAllGames);

	$response = [
		'success' => true,
		'people' => $result['people'],
		'totalCount' => $result['totalCount'],
		'currentPage' => $result['currentPage'],
		'totalPages' => $result['totalPages'],
		'pageSize' => $result['pageSize'],
		'filters' => [
			'letter' => $filterLetter,
			'game_titles' => $filterGameTitles,
			'require_all_games' => $requireAllGames,
			'sort' => $sort,
			'page' => $page,
			'pageSize' => $pageSize,
		]
	];

	echo json_encode($response);
	exit;
}

error_log("peoples-api.php: action was not 'get-people'");


