<?php
use MediaWiki\MediaWikiServices;
/**
 * Concepts Helper
 * 
 * Provides utility functions for looking up concepts data from Semantic MediaWiki
 * with caching support for performance.
 */

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
    $concepts = [];
    $totalCount = 0;
    
    try {
        // First, get total count for pagination
        $totalCount = getConceptCountFromDB($filterLetter, $filterGameTitles, $requireAllGames);
        
        // Calculate pagination
        $totalPages = max(1, ceil($totalCount / $limit));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $limit;
        
        // Now get the actual data
        $queryConditions = '[[Category:Concepts]]';
        
        if (!empty($filterLetter)) {
            if ($filterLetter === '#') {
                $queryConditions .= '[[Has name::~0*||~1*||~2*||~3*||~4*||~5*||~6*||~7*||~8*||~9*]]';
            } else {
                $queryConditions .= '[[Has name::~' . $filterLetter . '*]]';
            }
        }
        
        if (!empty($filterGameTitles) && is_array($filterGameTitles)) {
            if ($requireAllGames && count($filterGameTitles) > 1) {
                // AND logic: Add the Has game:: conditions for all selected games
                foreach ($filterGameTitles as $filterGameTitle) {
                    $queryConditions .= '[[Has game::' . $filterGameTitle . ']]';
                }
            } else {
                // OR logic: Add the Has game:: condition with multiple OR conditions
                $queryConditions .= '[[Has game::' . implode('||', $filterGameTitles) . ']]';
            }
        }
        
        $printouts = '|?Has name|?Has deck|?Has image|?Has caption';
        
        // Set sort order using switch statement
        switch ($sort) {
            case 'alphabetical':
                $params = '|sort=Has name|order=asc';
                break;
            case 'last_edited':
                $params = '|sort=Modification date|order=desc';
                break;
            case 'last_created':
                $params = '|sort=Creation date|order=desc';
                break;
            default:
                $params = '|sort=Has name|order=asc';
                break;
        }
        
        $params .= '|limit=' . $limit . '|offset=' . $offset;
        
        $fullQuery = $queryConditions . $printouts . $params;
        
        $api = new ApiMain(
            new DerivativeRequest(
                RequestContext::getMain()->getRequest(),
                [
                    'action' => 'ask',
                    'query' => $fullQuery,
                    'format' => 'json',
                ],
                true
            ),
            true
        );
        
        $api->execute();
        $result = $api->getResult()->getResultData(null, ['Strip' => 'all']);
        
        if (isset($result['query']['results']) && is_array($result['query']['results'])) {
            $concepts = processConceptQueryResults($result['query']['results']);
        }
        
    } catch (Exception $e) {
        error_log("Error querying concepts: " . $e->getMessage());
    }
    
    return [
        'concepts' => $concepts,
        'totalCount' => $totalCount,
        'currentPage' => $page,
        'totalPages' => max(1, ceil($totalCount / $limit)),
        'pageSize' => $limit,
    ];
}

/**
 * Process the results of the concept query from Semantic MediaWiki and returns an array of concept data
 * 
 * @param array $results The results of the concept query from Semantic MediaWiki
 * @return array Array of concept data with 'url', 'title', 'deck', 'image', 'caption' keys
 */
function processConceptQueryResults($results) {
    $concepts = [];
    
    if (isset($results) && is_array($results)) {
        foreach ($results as $pageName => $pageData) {
            $conceptData = [];
            $printouts = $pageData['printouts'];
            
            // Add URL for the concept
            $conceptData['url'] = $pageData['fullurl'] ?? '';
            
            if (isset($printouts['Has name']) && count($printouts['Has name']) > 0) {
                $name = $printouts['Has name'][0];
                $conceptData['title'] = $name;
            } else {
                // Fallback to page name without namespace
                $conceptData['title'] = str_replace('Concepts/', '', $pageName);
            }
            
            if (isset($printouts['Has deck']) && count($printouts['Has deck']) > 0) {
                $deck = $printouts['Has deck'][0];
                $conceptData['deck'] = $deck;
            }
            
            if (isset($printouts['Has image']) && count($printouts['Has image']) > 0) {
                $image = $printouts['Has image'][0];
                $conceptData['image'] = $image['fullurl'] ?? '';
                $conceptData['image'] = str_replace('http://localhost:8080/wiki/', '', $conceptData['image']);
            }
            
            if (isset($printouts['Has caption']) && count($printouts['Has caption']) > 0) {
                $caption = $printouts['Has caption'][0];
                $conceptData['caption'] = $caption;
            }
            
            $concepts[] = $conceptData;
        }
    }
    return $concepts;
}

/**
 * Get the total number of concepts from the database with optional filters
 * 
 * Uses direct DB queries instead of SMW Ask API to avoid record limits.
 * 
 * @param string $filterLetter Optional letter filter (A-Z or # for numbers)
 * @param array $filterGameTitles Optional array of game title filters
 * @param bool $requireAllGames If true, concepts must be linked to ALL games (AND logic). If false, ANY game (OR logic)
 * @return int Total number of concepts
 */
function getConceptCountFromDB($filterLetter = '', $filterGameTitles = [], $requireAllGames = false) {
    $SMW_PROPERTY_NAMESPACE = 102;
    $GENERIC_NAMESPACE = 0; // Generic namespace ID
    
    try {
        $dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
        
        // Base query: count pages in Concepts category
        // We need to join categorylinks with page table to get page titles for letter filtering
        $tables = ['categorylinks', 'page'];
        $joinConds = [
            'page' => ['JOIN', 'cl_from = page_id']
        ];
        $conds = [
            'cl_to' => 'Concepts',
            'page_namespace' => $GENERIC_NAMESPACE
        ];
        
        // Add letter filter
        if (!empty($filterLetter)) {
            if ($filterLetter === '#') {
                // Match concepts starting with numbers (0-9)
                // Use buildLike for proper escaping
                $conds[] = 'page_title ' . $dbr->buildLike('0', $dbr->anyString()) .
                    ' OR page_title ' . $dbr->buildLike('1', $dbr->anyString()) .
                    ' OR page_title ' . $dbr->buildLike('2', $dbr->anyString()) .
                    ' OR page_title ' . $dbr->buildLike('3', $dbr->anyString()) .
                    ' OR page_title ' . $dbr->buildLike('4', $dbr->anyString()) .
                    ' OR page_title ' . $dbr->buildLike('5', $dbr->anyString()) .
                    ' OR page_title ' . $dbr->buildLike('6', $dbr->anyString()) .
                    ' OR page_title ' . $dbr->buildLike('7', $dbr->anyString()) .
                    ' OR page_title ' . $dbr->buildLike('8', $dbr->anyString()) .
                    ' OR page_title ' . $dbr->buildLike('9', $dbr->anyString());
            } else {
                // Match concepts starting with the specified letter (case-insensitive)
                $conds[] = 'page_title ' . $dbr->buildLike($filterLetter, $dbr->anyString()) .
                    ' OR page_title ' . $dbr->buildLike(strtolower($filterLetter), $dbr->anyString());
            }
        }
        
        // If we have game filters, we need to join with SMW tables
        if (!empty($filterGameTitles) && is_array($filterGameTitles)) {
            // Get the property ID for "Has games"
            $propertyId = $dbr->selectField(
                'smw_object_ids',
                'smw_id',
                [
                    'smw_title' => 'Has_games',
                    'smw_namespace' => $SMW_PROPERTY_NAMESPACE,
                    'smw_subobject' => ''
                ],
                __METHOD__
            );
            
            if (!$propertyId) {
                error_log("⚠ Property 'Has games' not found in SMW");
                return 0;
            }
            
            // Get SMW IDs for each game
            $gameSmwIds = [];
            foreach ($filterGameTitles as $gameTitle) {
                // Game titles come in format "Games/GameName" - need to convert for SMW lookup
                $smwTitle = str_replace(' ', '_', $gameTitle);
                
                $gameSmwId = $dbr->selectField(
                    'smw_object_ids',
                    'smw_id',
                    [
                        'smw_title' => $smwTitle,
                        'smw_namespace' => 0,  // Main namespace
                        'smw_subobject' => ''
                    ],
                    __METHOD__
                );
                
                if ($gameSmwId) {
                    $gameSmwIds[] = $gameSmwId;
                }
            }
            
            if (empty($gameSmwIds)) {
                // No valid games found, return 0
                return 0;
            }
            
            // Add SMW tables to the query
            $tables[] = 'smw_object_ids';
            $joinConds['smw_object_ids'] = ['JOIN', [
                'smw_title = page_title',
                'smw_namespace' => $GENERIC_NAMESPACE,
                'smw_subobject' => ''
            ]];
            
            if ($requireAllGames && count($gameSmwIds) > 1) {
                // AND logic: Concept must be linked to ALL selected games
                // We need a subquery for each game
                foreach ($gameSmwIds as $index => $gameSmwId) {
                    $subqueryAlias = "smw_games_$index";
                    $tables[$subqueryAlias] = 'smw_di_wikipage';
                    $joinConds[$subqueryAlias] = ['JOIN', [
                        "smw_object_ids.smw_id = $subqueryAlias.s_id",
                        "$subqueryAlias.p_id" => $propertyId,
                        "$subqueryAlias.o_id" => $gameSmwId
                    ]];
                }
            } else {
                // OR logic: Concept must be linked to ANY of the selected games
                $tables['smw_di_wikipage'] = 'smw_di_wikipage';
                $joinConds['smw_di_wikipage'] = ['JOIN', [
                    'smw_object_ids.smw_id = smw_di_wikipage.s_id',
                    'smw_di_wikipage.p_id' => $propertyId
                ]];
                $conds['smw_di_wikipage.o_id'] = $gameSmwIds;
            }
        }
        
        // Execute count query
        $conceptCount = $dbr->selectField(
            $tables,
            'COUNT(DISTINCT page_id)',
            $conds,
            __METHOD__,
            [],
            $joinConds
        );
        
        return (int)$conceptCount;
        
    } catch (Exception $e) {
        error_log("Error getting concept count: " . $e->getMessage());
        return 0;
    }
}
