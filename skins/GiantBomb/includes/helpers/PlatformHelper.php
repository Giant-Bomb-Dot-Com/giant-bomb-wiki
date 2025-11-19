<?php
/**
 * Platform Helper
 * 
 * Provides utility functions for looking up platform data from Semantic MediaWiki
 * with caching support for performance.
 */

 // Load date helper functions
 require_once __DIR__ . '/DateHelper.php';
 
/**
 * Load platform name to abbreviation mappings from SMW with caching
 * 
 * This function queries all platforms from Semantic MediaWiki and creates
 * a lookup map from platform names to their abbreviations (short names).
 * The result is cached for 24 hours using MediaWiki's WANObjectCache.
 * 
 * The returned array contains multiple keys for each platform to support
 * different naming formats:
 * - Full page name with namespace: "Platforms/PlayStation 5"
 * - Clean name without namespace: "PlayStation 5"
 * - Display name if different from page name
 * 
 * @return array Mapping of platform names (with and without namespace) to their abbreviations
 * 
 * @example
 * $platforms = loadPlatformMappings();
 * $abbrev = $platforms['Platforms/PlayStation 5'] ?? 'PS5'; // Returns "PS5"
 */
function loadPlatformMappings() {
    $cache = MediaWiki\MediaWikiServices::getInstance()->getMainWANObjectCache();
    $cacheKey = $cache->makeKey('platforms', 'abbreviations', 'v1');
    
    // Check if we have cached data
    $cachedData = $cache->get($cacheKey);
    if ($cachedData !== false) {
        error_log("✓ Platform mappings: CACHE HIT (using cached data)");
        return $cachedData;
    }
    
    error_log("⚠ Platform mappings: CACHE MISS (querying database)");
    
    return $cache->getWithSetCallback(
        $cacheKey,
        $cache::TTL_DAY,
        function() {
            $platforms = [];
            
            // Query SMW for all platforms
            $queryConditions = '[[Category:Platforms]]';
            $printouts = '|?Has name|?Has short name';
            $params = '|limit=500';
            $fullQuery = $queryConditions . $printouts . $params;
            
            try {
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
                
                if (isset($result['query']['results'])) {
                    foreach ($result['query']['results'] as $pageName => $data) {
                        $shortName = '';
                        if (isset($data['printouts']['Has short name'][0])) {
                            $shortName = $data['printouts']['Has short name'][0];
                        }
                        
                        // Get the display name if available
                        $displayName = '';
                        if (isset($data['printouts']['Has name'][0])) {
                            $displayName = $data['printouts']['Has name'][0];
                        }
                        
                        $cleanName = str_replace('Platforms/', '', $pageName);
                        $fallback = $shortName ?: $cleanName;
                        
                        // Store by page name (with Platforms/ prefix)
                        $platforms[$pageName] = $fallback;
                        // Store by clean name (without prefix)
                        $platforms[$cleanName] = $fallback;
                        // Store by display name if different
                        if ($displayName && $displayName !== $cleanName) {
                            $platforms[$displayName] = $fallback;
                        }
                    }
                }
                
                error_log("✓ Platform mappings: Loaded " . count($platforms) . " entries from database (now cached for 24 hours)");
            } catch (Exception $e) {
                error_log("✗ Platform query failed: " . $e->getMessage());
            }
            
            return $platforms;
        }
    );
}

/**
 * Get platform abbreviation for a given platform name
 * 
 * Convenience function that loads the platform mappings (with caching)
 * and returns the abbreviation for a specific platform.
 * 
 * @param string $platformName The platform name (with or without "Platforms/" prefix)
 * @return string The platform abbreviation, or the basename if not found
 * 
 * @example
 * echo getPlatformAbbreviation('Platforms/PlayStation 5'); // Returns "PS5"
 * echo getPlatformAbbreviation('Xbox Series X'); // Returns "XBSX"
 */
function getPlatformAbbreviation($platformName) {
    static $platformCache = null;
    
    // Load platform mappings once per request
    if ($platformCache === null) {
        $platformCache = loadPlatformMappings();
    }
    
    // Try to find the abbreviation
    if (isset($platformCache[$platformName])) {
        return $platformCache[$platformName];
    }
    
    // Fallback to basename (remove Platforms/ prefix)
    return basename($platformName);
}

/**
 * Get full platform data for a given platform name
 * 
 * Returns an associative array with platform information if available.
 * This can be extended to include more platform properties as needed.
 * 
 * @param string $platformName The platform name
 * @return array|null Platform data array or null if not found
 */
function getPlatformData($platformName) {
    $mappings = loadPlatformMappings();
    
    if (isset($mappings[$platformName])) {
        return [
            'name' => $platformName,
            'abbreviation' => $mappings[$platformName]
        ];
    }
    
    return null;
}

/**
 * Get all platforms
 * 
 * Returns an array of platforms with name, displayName, and abbreviation.
 * Results are cached along with the platform mappings.
 * 
 * @return array Array of platform objects with 'name', 'displayName', and 'abbreviation' keys
 * 
 * @example
 * $platforms = getAllPlatforms();
 * foreach ($platforms as $platform) {
 *     echo $platform['displayName']; // "PlayStation 5"
 * }
 */
function getAllPlatforms() {
    $cache = MediaWiki\MediaWikiServices::getInstance()->getMainWANObjectCache();
    $cacheKey = $cache->makeKey('platforms', 'list-all', 'v1');
    
    // Check if we have cached data
    $cachedData = $cache->get($cacheKey);
    if ($cachedData !== false) {
        error_log("✓ Platform list: CACHE HIT (using cached data)");
        return $cachedData;
    }
    
    error_log("⚠ Platform list: CACHE MISS (querying database)");
    
    return $cache->getWithSetCallback(
        $cacheKey,
        $cache::TTL_DAY,
        function() {
            $platforms = [];
            
            // Query SMW for all platforms
            $queryConditions = '[[Category:Platforms]]';
            $printouts = '|?Has name|?Has short name';
            $params = '|sort=Has name|order=asc|limit=500';
            $fullQuery = $queryConditions . $printouts . $params;
            
            try {
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
                
                if (isset($result['query']['results'])) {
                    foreach ($result['query']['results'] as $pageName => $data) {
                        $printouts = $data['printouts'];
                        $cleanName = str_replace('Platforms/', '', $pageName);
                        
                        // Get display name or fall back to clean name
                        $displayName = $cleanName;
                        if (isset($printouts['Has name'][0]) && !empty($printouts['Has name'][0])) {
                            $displayName = $printouts['Has name'][0];
                        }
                        
                        // Get abbreviation
                        $abbrev = '';
                        if (isset($printouts['Has short name'][0])) {
                            $abbrev = $printouts['Has short name'][0];
                        }
                        
                        $platforms[] = [
                            'name' => $cleanName,
                            'displayName' => $displayName,
                            'abbreviation' => $abbrev ?: $cleanName,
                        ];
                    }
                }
                
                error_log("✓ Platform dropdown list: Loaded " . count($platforms) . " platforms (now cached for 24 hours)");
            } catch (Exception $e) {
                error_log("✗ Platform dropdown query failed: " . $e->getMessage());
            }
            
            return $platforms;
        }
    );
}


/**
 * Query platforms from Semantic MediaWiki with optional filters
 * 
 * @param string $filterLetter Optional letter filter (A-Z or # for numbers)
 * @param string $filterGameTitle Optional game title filter
 * @param string $sort Sort method ('alphabetical' or 'release_date')
 * @param int $page Current page number (1-based)
 * @param int $limit Results per page
 * @return array Array with 'platforms', 'totalCount', 'currentPage', 'totalPages'
 */
function queryPlatformsFromSMW($filterLetter = '', $filterGameTitle = '', $sort = 'release_date', $page = 1, $limit = 48) {
    $platforms = [];
    $totalCount = 0;
    
    try {
        // First, get total count for pagination
        $totalCount = getPlatformCountFromSMW($filterLetter, $filterGameTitle);
        
        // Calculate pagination
        $totalPages = max(1, ceil($totalCount / $limit));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $limit;
        
        // Now get the actual data
        $queryConditions = '[[Category:Platforms]]';
        
        if (!empty($filterLetter)) {
            if ($filterLetter === '#') {
                $queryConditions .= '[[Has name::~0*||~1*||~2*||~3*||~4*||~5*||~6*||~7*||~8*||~9*]]';
            } else {
                $queryConditions .= '[[Has name::~' . $filterLetter . '*]]';
            }
        }
        
        if (!empty($filterGameTitle)) {
            $queryConditions .= '[[Has games::*' . $filterGameTitle . '*]]';
        }
        
        $printouts = '|?Has name|?Has short name|?Has image|?Has deck|?Has release date|?Has release date type';
        
        // Set sort order using switch statement
        switch ($sort) {
            case 'release_date':
                $params = '|sort=Has release date|order=desc';
                break;
            case 'alphabetical':
                $params = '|sort=Has name|order=asc';
                break;
            default:
                $params = '|sort=Has release date|order=desc';
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
            $platforms = processPlatformQueryResults($result['query']['results']);
        }
        
    } catch (Exception $e) {
        error_log("Error querying platforms: " . $e->getMessage());
    }
    
    return [
        'platforms' => $platforms,
        'totalCount' => $totalCount,
        'currentPage' => $page,
        'totalPages' => max(1, ceil($totalCount / $limit)),
    ];
}

/**
 * Get the number of games for a given platform from Semantic MediaWiki
 * 
 * @param string $platformName The platform name (e.g. PC or Platforms/PC)
 * @return int The number of games associated with the platform
 */
function getGameCountForPlatformFromSMW($platformName) {
    $gameCount = 0;
    try {
        $platformName = str_replace('Platforms/', '', $platformName);
        $queryConditions = '[[Category:Games]][[Has platforms::Platforms/' . $platformName . ']]';
        $printouts = '|?Has platforms';
        $params = '|limit=5000';
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
        
        error_log("Query: " . $fullQuery);
        
        if (isset($result['query']['results'])) {
            $gameCount = count($result['query']['results']);
        }
    } catch (Exception $e) {
        error_log("Error getting game count for platform: " . $e->getMessage());
    }
    return $gameCount;
}

/**
 * Process the results of the platform query from Semantic MediaWiki and returns an array of platform data
 * 
 * @param array $results The results of the platform query from Semantic MediaWiki
 * @return array Array of platform data with 'url', 'title', 'shortName', 'deck', 'releaseDate', 'releaseDateFormatted', 'image' keys
 */
function processPlatformQueryResults($results) {
    $platforms = [];
    
    if (isset($results) && is_array($results)) {
        foreach ($results as $pageName => $pageData) {
            $platformData = [];
            $printouts = $pageData['printouts'];
            
            // Add URL for the platform
            $platformData['url'] = $pageData['fullurl'] ?? '';
            
            if (isset($printouts['Has name']) && count($printouts['Has name']) > 0) {
                $name = $printouts['Has name'][0];
                $platformData['title'] = $name;
            } else {
                // Fallback to page name without namespace
                $platformData['title'] = str_replace('Platforms/', '', $pageName);
            }
            
            if (isset($printouts['Has short name']) && count($printouts['Has short name']) > 0) {
                $shortName = $printouts['Has short name'][0];
                $platformData['shortName'] = $shortName;
            }
            
            if (isset($printouts['Has deck']) && count($printouts['Has deck']) > 0) {
                $deck = $printouts['Has deck'][0];
                $platformData['deck'] = $deck;
            }
            
            if (isset($printouts['Has release date']) && count($printouts['Has release date']) > 0) {
                $releaseDate = $printouts['Has release date'][0];
                $rawDate = $releaseDate['raw'] ?? '';
                $timestamp = $releaseDate['timestamp'] ?? strtotime($rawDate);
                $platformData['releaseDate'] = $rawDate;
                $platformData['releaseDateTimestamp'] = $timestamp;
                $platformData['sortTimestamp'] = $timestamp;
                
                $dateType = 'Full';
                if (isset($printouts['Has release date type']) && count($printouts['Has release date type']) > 0) {
                    $dateType = $printouts['Has release date type'][0];
                }
                
                $platformData['dateSpecificity'] = strtolower($dateType);
                $platformData['releaseDateFormatted'] = formatReleaseDate($rawDate, $timestamp, $dateType);
            }
            
            if (isset($printouts['Has image']) && count($printouts['Has image']) > 0) {
                $image = $printouts['Has image'][0];
                $platformData['image'] = $image['fullurl'] ?? '';
                $platformData['image'] = str_replace('http://localhost:8080/wiki/', '', $platformData['image']);
            }
            
            $platformData['gameCount'] = getGameCountForPlatformFromSMW($pageName);
            
            $platforms[] = $platformData;
        }
    }
    return $platforms;
}

/**
 * Get the total number of platforms from Semantic MediaWiki with optional filters
 * 
 * @param string $filterLetter Optional letter filter (A-Z or # for numbers)
 * @param string $filterGameTitle Optional game title filter
 * @return int Total number of platforms
 */
function getPlatformCountFromSMW($filterLetter = '', $filterGameTitle = '') {
    $totalCount = 0;
    try {
        
        $countQuery = '[[Category:Platforms]]';
        
        if (!empty($filterLetter)) {
            if ($filterLetter === '#') {
                // Match platforms starting with numbers
                $countQuery .= '[[Has name::~0*||~1*||~2*||~3*||~4*||~5*||~6*||~7*||~8*||~9*]]';
            } else {
                $countQuery .= '[[Has name::~' . $filterLetter . '*]]';
            }
        }
        
        if (!empty($filterGameTitle)) {
            $countQuery .= '[[Has games::*' . $filterGameTitle . '*]]';
        }
        
        // Get count (need to specify release date so count matches queryPlatformsFromSMW)
        $countQueryFull = $countQuery . '|limit=5000|sort=Has release date|order=desc';
        
        $countApi = new ApiMain(
            new DerivativeRequest(
                RequestContext::getMain()->getRequest(),
                [
                    'action' => 'ask',
                    'query' => $countQueryFull,
                    'format' => 'json',
                ],
                true
            ),
            true
        );
        
        $countApi->execute();
        $countResult = $countApi->getResult()->getResultData(null, ['Strip' => 'all']);
        
        if (isset($countResult['query']['results'])) {
            $totalCount = count($countResult['query']['results']);
        }
    } catch (Exception $e) {
        error_log("Error getting platform count: " . $e->getMessage());
    }
    
    return $totalCount;
}
