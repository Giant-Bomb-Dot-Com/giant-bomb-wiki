<?php
/**
 * Games Helper
 * 
 * Provides utility functions for querying and formatting game data
 */

// Load platform helper functions
require_once __DIR__ . '/PlatformHelper.php';

/**
 * Query games from Semantic MediaWiki with optional filters
 * 
 * @param string $filterText Optional text filter
 * @return array Array of game data
 */
function queryGamesFromSMW($searchQuery = '', $platformFilter = '', $sortOrder = 'title-asc', $currentPage = 1, $itemsPerPage = 25) {
    $games = [];
	$totalGames = 0;
    
    try {
        $queryConditions = '[[Category:Games]][[Has name::~*' . $searchQuery . '*]]';
        
        if (!empty($platformFilter)) {
            $queryConditions .= '[[Has platforms::Platforms/' . str_replace('Platforms/', '', $platformFilter) . ']]';
        }
        
        switch ($sortOrder) {
            case 'title-asc':
                $queryConditions .= '|sort=Has name|order=asc';
                break;
            case 'title-desc':
                $queryConditions .= '|sort=Has name|order=desc';
                break;
            case 'release-date-asc':
                $queryConditions .= '|sort=Has release date|order=asc';
                break;
            case 'release-date-desc':
                $queryConditions .= '|sort=Has release date|order=desc';
                break;
            default:
                $queryConditions .= '|sort=Has name|order=asc';
                break;
        }
        
        $printouts = '|?Has name|?Has image|?Has platforms|?Has release date';
        $params = '|limit=' . $itemsPerPage;
        
        $countQuery = $queryConditions . $printouts;
        
        $api = new ApiMain(
            new DerivativeRequest(
                RequestContext::getMain()->getRequest(),
                [
                    'action' => 'ask',
                    'query' => $countQuery,
                    'format' => 'json',
                ],
                true
            ),
            true
        );
        
        $api->execute();
        $result = $api->getResult()->getResultData(null, ['Strip' => 'all']);
        
        if (isset($result['query']['results']) && is_array($result['query']['results'])) {
            $totalGames = count($result['query']['results']);
            $totalPages = max(1, ceil($totalGames / $itemsPerPage));
            $page = max(1, min($currentPage, $totalPages));
            $offset = ($page - 1) * $itemsPerPage;
        }
            
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
            $games = processGameQueryResults($result['query']['results']);
            $gamesData = [
                'games' => $games,
                'totalGames' => $totalGames,
                'totalPages' => $totalPages,
                'currentPage' => $page,
                'offset' => $offset,
                'itemsPerPage' => $itemsPerPage,
            ];
        }
        
    } catch (Exception $e) {
        error_log("Error querying games: " . $e->getMessage());
    }
    
    return $gamesData;
}

/**
 * Process the query results from Semantic MediaWiki and returns an array of game data
 * 
 * @param array $results The query results from Semantic MediaWiki
 * @return array Array of game data
 */
function processGameQueryResults($results) {
    $platformMappings = loadPlatformMappings();
    $games = [];
    if (isset($results) && is_array($results)) {
        foreach ($results as $pageName => $pageData) {
            $gameData = [];
            $printouts = $pageData['printouts'];
            
            $gameData['searchName'] = $pageName;
            
            if (isset($printouts['Has name']) && count($printouts['Has name']) > 0) {
                $name = $printouts['Has name'][0];
                $gameData['title'] = $name;
            }
            
            if (isset($printouts['Has image']) && count($printouts['Has image']) > 0) {
                $image = $printouts['Has image'][0];
                $gameData['image'] = $image['fullurl'] ?? '';
                $gameData['image'] = str_replace('http://localhost:8080/wiki/', '', $gameData['image']);
            }
            
            if (isset($printouts['Has platforms']) && count($printouts['Has platforms']) > 0) {
                $platforms = [];
                foreach ($printouts['Has platforms'] as $platform) {
                    $platformName = $platform['displaytitle'] ?? $platform['fulltext'];
                    $abbrev = $platformMappings[$platformName] ?? basename($platformName);
                    
                    $platforms[] = [
                        'title' => $platformName,
                        'url' => $platform['fullurl'],
                        'abbrev' => $abbrev,
                    ];
                }
                $gameData['platforms'] = $platforms;
            }
            
            if (isset($printouts['Has release date']) && count($printouts['Has release date']) > 0) {
                $releaseDate = $printouts['Has release date'][0];
                // Use the timestamp to get the release year
                $releaseYear = date('Y', $releaseDate['timestamp']);
                $gameData['releaseYear'] = $releaseYear;
            }
            
            $games[] = $gameData;
        }
    }
    return $games;
}