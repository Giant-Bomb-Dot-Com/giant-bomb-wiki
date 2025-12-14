<?php
/**
 * Concepts Helper
 * 
 * Provides utility functions for looking up concepts data from Semantic MediaWiki
 */
use MediaWiki\Extension\AlgoliaSearch\LegacyImageHelper;
use GiantBomb\Skin\Helpers\PageHelper;
 
require_once __DIR__ . '/QueryHelper.php';
 
/**
 * Query concepts from Semantic MediaWiki with optional filters
 * 
 * @param string $filterLetter Optional letter filter (A-Z or # for numbers)
 * @param array $filterGameTitles Optional array of game title filters
 * @param string $sort Sort method ('alphabetical', 'last_edited', 'last_created')
 * @param int $page Current page number (1-based)
 * @param int $limit Results per page
 * @param bool $requireAllGames If true, concepts must be linked to ALL games (AND logic). If false, ANY game (OR logic)
 * @return array Array with 'concepts', 'totalCount', 'currentPage', 'totalPages'
 */
function queryConceptsFromSMW($filterLetter = '', $filterGameTitles = [], $sort = 'alphabetical', $page = 1, $limit = 48, $requireAllGames = false) {
    return fetchConceptsFromSMW($filterLetter, $filterGameTitles, $sort, $page, $limit, $requireAllGames);
}

/**
 * Internal function to fetch concepts from SMW
 * 
 * @param string $filterLetter Optional letter filter
 * @param array $filterGameTitles Optional game title filters
 * @param string $sort Sort method
 * @param int $page Current page number
 * @param int $limit Results per page
 * @param bool $requireAllGames AND/OR logic for game filters
 * @return array Query results
 */
function fetchConceptsFromSMW($filterLetter, $filterGameTitles, $sort, $page, $limit, $requireAllGames) {
    $concepts = [];
    $totalCount = 0;
    
    try {
        $store = \SMW\StoreFactory::getStore();
        
        // Build SMW query conditions
        $queryConditions = '[[Category:Concepts]]';
        
        // Add letter filter
        if (!empty($filterLetter)) {
            if ($filterLetter === '#') {
                // Match concepts starting with numbers (0-9)
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
                // OR logic: Add Has games:: condition with multiple OR conditions
                $safeGameTitles = array_map(function($title) {
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
        
        // Get total count using SMW count query
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
        
        // Calculate pagination
        $totalPages = max(1, ceil($totalCount / $limit));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $limit;
        
        // Build query params for actual data
        $rawParams = [
            $queryConditions,
            'limit=' . $limit,
            'offset=' . $offset,
            'sort=' . $smwSort,
            'order=' . $smwOrder,
            '?Has name',
            '?Has deck',
            '?Has image',
            '?Has caption'
        ];
        
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
        
        // Process results
        while ($row = $result->getNext()) {
            $subject = $row[0]->getResultSubject();
            $title = $subject->getTitle();
            
            $conceptData = [];
            $conceptData['url'] = '/wiki/' . $title->getPrefixedDBkey();
            $conceptData['title'] = str_replace('_', ' ', str_replace('Concepts/', '', $title->getText()));
            $conceptData['deck'] = '';
            $conceptData['image'] = '';
            $conceptData['caption'] = '';
            
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
                
                switch ($label) {
                    case 'Has name':
                        if (!empty($values[0])) {
                            $conceptData['title'] = $values[0];
                        }
                        break;
                    case 'Has deck':
                        $conceptData['deck'] = $values[0] ?? '';
                        break;
                    case 'Has image':
                        $rawImg = $values[0] ?? '';
						if ( $rawImg !== '' && class_exists( PageHelper::class ) ) {
							$resolved = PageHelper::resolveWikiImageUrl( $rawImg );
							$conceptData['image'] = $resolved ?? '';
						}
                        break;
                    case 'Has caption':
                        $conceptData['caption'] = $values[0] ?? '';
                        break;
                }
            }
            
            // If no image from SMW, try legacy image fallback
			if ( empty( $conceptData['image'] ) && class_exists( LegacyImageHelper::class ) ) {
				$legacyImage = LegacyImageHelper::findLegacyImageForTitle( $title );
				if ( $legacyImage && !empty( $legacyImage['thumb'] ) ) {
					$conceptData['image'] = $legacyImage['thumb'];
				}
			}
            
            // Default to title if image caption is not set
            if (empty($conceptData['caption'])) {
                $conceptData['caption'] = $conceptData['title'];
            }
            
            $concepts[] = $conceptData;
        }
        
    } catch (Exception $e) {
        error_log("ConceptHelper error: " . $e->getMessage());
    }
    
    return [
        'concepts' => $concepts,
        'totalCount' => $totalCount,
        'currentPage' => $page,
        'totalPages' => max(1, ceil($totalCount / $limit)),
        'pageSize' => $limit,
    ];
}
