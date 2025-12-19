<?php
/**
 * Helper functions for querying games data using SemanticMediaWiki
 */

use MediaWiki\Extension\AlgoliaSearch\LegacyImageHelper;
use GiantBomb\Skin\Helpers\PageHelper;

require_once __DIR__ . '/PlatformHelper.php';
require_once __DIR__ . '/QueryHelper.php';

/**
 * Query games from SMW with filters, sorting, and pagination
 *
 * @param string $searchQuery Search term to filter by title
 * @param string $platformFilter Platform to filter by
 * @param string $sortOrder Sort order (title-asc, title-desc, release-date-asc, release-date-desc)
 * @param int $currentPage Current page number
 * @param int $itemsPerPage Items per page
 * @return array Array with 'games' and 'totalGames' keys
 */
function queryGamesFromSMW($searchQuery = '', $platformFilter = '', $sortOrder = '', $currentPage = 1, $itemsPerPage = 25) {
    
    // Validation on searchQuery input 
	$searchQuery = (string) $searchQuery;
	$searchQuery = trim($searchQuery);

	// Trim searchQuery to 255 characters
	if (strlen($searchQuery) > 255) {
		$searchQuery = substr($searchQuery, 0, 255);
	}

	// Remove special SMW query characters
	$searchQuery = removeSpecialSMWQueryCharacters($searchQuery);
    
    return fetchGamesFromSMW($searchQuery, $platformFilter, $sortOrder, $currentPage, $itemsPerPage);
}

/**
 * Internal function to fetch games from SMW
 * 
 * @param string $searchQuery search query
 * @param string $platformFilter platform filter
 * @param string $sortOrder sort order
 * @param int $currentPage current page number
 * @param int $itemsPerPage items per page
 * @return array Query results
 */
function fetchGamesFromSMW($searchQuery, $platformFilter, $sortOrder, $currentPage, $itemsPerPage) {
    $games = [];
	$totalGames = 0;

    $platformMappings = loadPlatformMappings();

	// If searchQuery is now empty after removing special characters, treat as no search filter
	// (don't return early, just continue with no search filter applied)

	try {
		$store = \SMW\StoreFactory::getStore();

		// Build SMW query conditions
		$queryConditions = '[[Category:Games]]';

		if (!empty($searchQuery)) {
            // Need to add quotes to match the phrase, otherwise full-text-search runs an OR on each word
			$queryConditions .= '[[Has name::~*"' . $searchQuery . '"*]]';
		}

		if (!empty($platformFilter)) {
			$queryConditions .= '[[Has platforms::~*' . removeSpecialSMWQueryCharacters($platformFilter) . ']]';
		}

		// Determine sort property and order
		$smwSort = '';
		$smwOrder = '';
		switch ($sortOrder) {
			case 'title-desc':
                $smwSort = 'Has name';
				$smwOrder = 'desc';
				break;
            case 'title-asc':
                $smwSort = 'Has name';
                $smwOrder = 'asc';
                break;
			case 'release-date-desc':
				$smwSort = 'Has release date';
				$smwOrder = 'desc';
				break;
			case 'release-date-asc':
				$smwSort = 'Has release date';
				$smwOrder = 'asc';
				break;
		}

		// Calculate offset
		$offset = ($currentPage - 1) * $itemsPerPage;

		// Build query params
		$rawParams = [
			$queryConditions,
			'limit=' . $itemsPerPage,
			'offset=' . $offset,
			'?Has name',
			'?Has deck',
			'?Has image',
			'?Has release date',
			'?Has platforms'
		];

		if (!empty($smwSort)) {
			$rawParams[] = 'sort=' . $smwSort;
		}
        if (!empty($smwOrder)) {
            $rawParams[] = 'order=' . $smwOrder;
        }

		// Execute query
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

		// Get total count using SMW
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
		$totalGames = $countResult->getCountValue() ?: 0;

		// Process results
		$index = 0;
		$gamesBySmwId = [];
		$pageIds = [];

		while ($row = $result->getNext()) {
			$subject = $row[0]->getResultSubject();
			$title = $subject->getTitle();

			$pageData = [];
			$pageData['index'] = $index++;
			$pageData['smw_id'] = $subject->getSerialization();
			$pageData['url'] = '/wiki/' . $title->getPrefixedDBkey();
			$pageData['title'] = str_replace('_', ' ', str_replace('Games/', '', $title->getText()));
			$pageData['desc'] = '';
			$pageData['img'] = '';
			$pageData['date'] = '';
			$pageData['platforms'] = [];

			// Extract property values
			for ($i = 1; $i < count($row); $i++) {
				$field = $row[$i];
				$pr = $field->getPrintRequest();
				$label = $pr->getLabel();

				$values = [];
                $dv = null;
				while ($tempDV = $field->getNextDataValue()) {
                    $dv = $tempDV;
					$values[] = $dv->getShortWikiText();
				}
                
                $pageData['searchName'] = $title->getText();

				switch ($label) {
					case 'Has name':
						if (!empty($values[0])) {
							$pageData['title'] = $values[0];
						}
						break;
					case 'Has deck':
						$pageData['desc'] = $values[0] ?? '';
						break;
					case 'Has image':
						$rawImg = $values[0] ?? '';
						if ( $rawImg !== '' && class_exists( PageHelper::class ) ) {
							$resolved = PageHelper::resolveWikiImageUrl( $rawImg );
							$pageData['img'] = $resolved ?? '';
						}
						break;
					case 'Has release date':
						if (!empty($values[0])) {
							$timestamp = strtotime($values[0]);
							if ($timestamp !== false) {
								$pageData['date'] = date('Y-m-d', $timestamp);
                                $pageData['releaseYear'] = date('Y', $timestamp);
							}
						}
						break;
					case 'Has platforms':
						$pageData['platforms'] = array_map(function($p) {
                            $platformName = str_replace('_', ' ', str_replace('Platforms/', '', $p));
                            $abbrev = $platformMappings[$platformName] ?? basename($platformName);
                            
							return [
                                'title' => $platformName,
                                'abbrev' => $abbrev,
                            ];
						}, $values);
						break;
				}
			}

			// If no image from SMW, try legacy image fallback
			if ( empty( $pageData['img'] ) && class_exists( LegacyImageHelper::class ) ) {
				$legacyImage = LegacyImageHelper::findLegacyImageForTitle( $title );
				if ( $legacyImage && !empty( $legacyImage['thumb'] ) ) {
					$pageData['img'] = $legacyImage['thumb'];
				}
			}

			$games[] = $pageData;
		}

	} catch (Exception $e) {
		error_log("GamesHelper error: " . $e->getMessage());
	}

	return [
		'games' => $games,
		'totalGames' => $totalGames
	];
}
