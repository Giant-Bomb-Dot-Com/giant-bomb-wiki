<?php /** @phpstan-ignore-file */
/**
 * People Helper
 *
 * Provides utility functions for looking up people data from Semantic MediaWiki.
 * Filtering support (letter + game filters, sorting, pagination).
 */
use MediaWiki\Extension\AlgoliaSearch\LegacyImageHelper;
use GiantBomb\Skin\Helpers\PageHelper;

require_once __DIR__ . '/QueryHelper.php';

/**
 * Query people from Semantic MediaWiki with optional filters
 *
 * @param string $filterLetter Optional letter filter (A-Z or # for numbers)
 * @param array $filterGameTitles Optional array of game title filters
 * @param string $sort Sort method ('alphabetical', 'last_edited', 'last_created')
 * @param int $page Current page number (1-based)
 * @param int $limit Results per page
 * @param bool $requireAllGames If true, people must be linked to ALL games (AND logic). If false, ANY game (OR logic)
 * @return array Array with 'people', 'totalCount', 'currentPage', 'totalPages', 'pageSize'
 */
function queryPeopleFromSMW($filterLetter = '', $filterGameTitles = [], $sort = 'alphabetical', $page = 1, $limit = 48, $requireAllGames = false) {
	return fetchPeopleFromSMW($filterLetter, $filterGameTitles, $sort, $page, $limit, $requireAllGames);
}

/**
 * Internal function to fetch people from SMW
 */
function fetchPeopleFromSMW($filterLetter, $filterGameTitles, $sort, $page, $limit, $requireAllGames) {
	$people = [];
	$totalCount = 0;

	try {
		$store = \SMW\StoreFactory::getStore();

		// Build SMW query conditions
		$queryConditions = '[[Category:People]]';

		// Add letter filter
		if (!empty($filterLetter)) {
			if ($filterLetter === '#') {
				// Match people starting with numbers (0-9)
				$queryConditions .= '[[Has name::~0*||~1*||~2*||~3*||~4*||~5*||~6*||~7*||~8*||~9*]]';
			} else {
				$queryConditions .= '[[Has name::~' . $filterLetter . '*]]';
			}
		}

		// Add game filters
		if (!empty($filterGameTitles) && is_array($filterGameTitles)) {
			if ($requireAllGames && count($filterGameTitles) > 1) {
				// AND logic: Add separate Has games:: condition for each selected game
				foreach ($filterGameTitles as $filterGameTitle) {
					$safeGameTitle = removeSpecialSMWQueryCharacters($filterGameTitle);
					$queryConditions .= '[[Has games::' . $safeGameTitle . ']]';
				}
			} else {
				// OR logic
				$safeGameTitles = array_map(function ($title) {
					return removeSpecialSMWQueryCharacters($title);
				}, $filterGameTitles);
				$queryConditions .= '[[Has games::' . implode('||', $safeGameTitles) . ']]';
			}
		}

		// Determine sort property and order
		$smwSort = 'Has name';
		$smwOrder = 'asc';
		switch ($sort) {
			case 'alphabetical':
				$smwSort = 'Has name';
				$smwOrder = 'asc';
				break;
			case 'last_edited':
				$smwSort = 'Modification date';
				$smwOrder = 'desc';
				break;
			case 'last_created':
				$smwSort = 'Creation date';
				$smwOrder = 'desc';
				break;
			default:
				$smwSort = 'Has name';
				$smwOrder = 'asc';
				break;
		}

		// Total count via SMW count query
		$countParams = [
			$queryConditions,
			'format=count'
		];
		list($countQueryString, $countParamsProcessed, $countPrintouts) = \SMWQueryProcessor::getComponentsFromFunctionParams(
			$countParams,
			false
		);
		$countQuery = \SMWQueryProcessor::createQuery(
			$countQueryString,
			\SMWQueryProcessor::getProcessedParams($countParamsProcessed),
			\SMWQueryProcessor::INLINE_QUERY,
			'count',
			$countPrintouts
		);
		$countResult = $store->getQueryResult($countQuery);
		$totalCount = $countResult->getCountValue() ?: 0;

		// Pagination
		$totalPages = max(1, ceil($totalCount / $limit));
		$page = max(1, min($page, $totalPages));
		$offset = ($page - 1) * $limit;

		// Data query params
		$rawParams = [
			$queryConditions,
			'limit=' . $limit,
			'offset=' . $offset,
			'sort=' . $smwSort,
			'order=' . $smwOrder,
			'?Has name',
			'?Has deck',
			'?Has image',
			'?Has caption',
            '?Has games'
		];

		list($queryString, $params, $printouts) = \SMWQueryProcessor::getComponentsFromFunctionParams(
			$rawParams,
			false
		);

		$query = \SMWQueryProcessor::createQuery(
			$queryString,
			\SMWQueryProcessor::getProcessedParams($params),
			\SMWQueryProcessor::INLINE_QUERY,
			'',
			$printouts
		);

		$result = $store->getQueryResult($query);

		// Process results
		while ($row = $result->getNext()) {
			$subject = $row[0]->getResultSubject();
			$title = $subject->getTitle();

			$personData = [];
			$personData['url'] = '/wiki/' . $title->getPrefixedDBkey();
			$personData['title'] = str_replace('_', ' ', str_replace('People/', '', $title->getText()));
			$personData['deck'] = '';
			$personData['image'] = '';
			$personData['caption'] = '';
			$personData['games'] = [];

			for ($i = 1; $i < count($row); $i++) {
				$field = $row[$i];
				$pr = $field->getPrintRequest();
				$label = $pr->getLabel();

				$values = [];
				while ($dv = $field->getNextDataValue()) {
					$values[] = $dv->getShortWikiText();
				}

				switch ($label) {
					case 'Has name':
						if (!empty($values[0])) {
							$personData['title'] = $values[0];
						}
						break;
					case 'Has deck':
						$personData['deck'] = $values[0] ?? '';
						break;
					case 'Has image':
						$rawImg = $values[0] ?? '';
						if ($rawImg !== '' && class_exists(PageHelper::class)) {
							$resolved = PageHelper::resolveWikiImageUrl($rawImg);
							$personData['image'] = $resolved ?? '';
						}
						break;
					case 'Has caption':
						$personData['caption'] = $values[0] ?? '';
						break;
					case 'Has games':
						$personData['games'] = array_map(function($game) {
							return str_replace('Games/', '', $game);
						}, $values);
						break;
				}
			}

			// If no image from SMW, try legacy image fallback
			if (empty($personData['image']) && class_exists(LegacyImageHelper::class)) {
				$legacyImage = LegacyImageHelper::findLegacyImageForTitle($title);
				if ($legacyImage && !empty($legacyImage['thumb'])) {
					$personData['image'] = $legacyImage['thumb'];
				}
			}

			// Default caption
			if (empty($personData['caption'])) {
				$personData['caption'] = $personData['title'];
			}

			$people[] = $personData;
		}
	} catch (Exception $e) {
		error_log("PeopleHelper error: " . $e->getMessage());
	}

	return [
		'people' => $people,
		'totalCount' => $totalCount,
		'currentPage' => $page,
		'totalPages' => max(1, ceil($totalCount / $limit)),
		'pageSize' => $limit,
	];
}


