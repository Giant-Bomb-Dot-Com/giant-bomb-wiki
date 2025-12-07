<?php
/**
 * Concepts Helper
 * 
 * Provides utility functions for looking up concepts data from Semantic MediaWiki
 * with caching support for performance.
 */

require_once __DIR__ . '/CacheHelper.php';

/**
 * Query concepts from Semantic MediaWiki with optional filters
 * 
 * Results are cached based on query parameters for improved performance.
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
    $cache = CacheHelper::getInstance();
    
    // Build cache key from query parameters
    $cacheKey = $cache->buildQueryKey(CacheHelper::PREFIX_CONCEPTS, [
        'letter' => $filterLetter,
        'games' => $filterGameTitles,
        'sort' => $sort,
        'page' => $page,
        'limit' => $limit,
        'requireAll' => $requireAllGames ? '1' : '0'
    ]);
    
    // Try to get from cache, or compute and store
    return $cache->getOrSet($cacheKey, function() use ($filterLetter, $filterGameTitles, $sort, $page, $limit, $requireAllGames) {
        return fetchConceptsFromSMW($filterLetter, $filterGameTitles, $sort, $page, $limit, $requireAllGames);
    }, CacheHelper::TTL_HOUR); // Cache for 1 hour
}

/**
 * Internal function to fetch concepts from SMW (not cached)
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
                    $safeGameTitle = str_replace(['[', ']', '|'], '', $filterGameTitle);
                    $queryConditions .= '[[Has games::' . $safeGameTitle . ']]';
                }
            } else {
                // OR logic: Add Has games:: condition with multiple OR conditions
                $safeGameTitles = array_map(function($title) {
                    return str_replace(['[', ']', '|'], '', $title);
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
                        if ($dv) {
                            // For wiki page types (like File:), get URL from the Title object
                            $dataItem = $dv->getDataItem();
                            if ($dataItem instanceof \SMW\DIWikiPage) {
                                $imageTitle = $dataItem->getTitle();
                                if ($imageTitle) {
                                    $conceptData['image'] = $imageTitle->getFullURL();
                                    $conceptData['image'] = str_replace('http://localhost:8080/wiki/', '', $conceptData['image']);
                                }
                            }
                        }
                        break;
                    case 'Has caption':
                        $conceptData['caption'] = $values[0] ?? '';
                        break;
                }
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
