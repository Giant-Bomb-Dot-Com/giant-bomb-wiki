<?php
use MediaWiki\MediaWikiServices;
/**
 * Platform Helper
 * 
 * Provides utility functions for looking up platform data from Semantic MediaWiki
 * with caching support for performance.
 */

use MediaWiki\Extension\AlgoliaSearch\LegacyImageHelper;
use GiantBomb\Skin\Helpers\PageHelper;
 
require_once __DIR__ . '/DateHelper.php';
require_once __DIR__ . '/CacheHelper.php';

/**
 * Safely extract a string value from SMW printout data
 *
 * @param array $printouts The printouts array from SMW query results
 * @param string $propertyName The property name to extract
 * @param string $default Default value if property is missing or invalid
 * @return string The extracted string value or default
 */
function extractPrintoutString($printouts, $propertyName, $default = '') {
    if (isset($printouts[$propertyName]) && count($printouts[$propertyName]) > 0) {
        $value = $printouts[$propertyName][0];
        return is_string($value) ? $value : $default;
    }
    return $default;
}
 
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
    $cache = CacheHelper::getInstance();
    
    $cacheKey = $cache->buildSimpleKey(CacheHelper::PREFIX_PLATFORMS_ABBREV);
    return $cache->getOrSet($cacheKey, function() {
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
                    $printouts = $data['printouts'];
                    $shortName = extractPrintoutString($printouts, 'Has short name');
                    $displayName = extractPrintoutString($printouts, 'Has name');

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
        } catch (Exception $e) {
            error_log("✗ Platform query failed: " . $e->getMessage());
        }
        
        return $platforms;
    }, CacheHelper::TTL_DAY);
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
    $cache = CacheHelper::getInstance();
    
    $cacheKey = $cache->buildSimpleKey(CacheHelper::PREFIX_PLATFORMS_LIST);
    return $cache->getOrSet($cacheKey, function() {
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

                    $displayName = extractPrintoutString($printouts, 'Has name', $cleanName);
                    $abbrev = extractPrintoutString($printouts, 'Has short name');

                    $platforms[] = [
                        'name' => $cleanName,
                        'displayName' => $displayName,
                        'abbreviation' => $abbrev ?: $cleanName,
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("✗ Platform dropdown query failed: " . $e->getMessage());
        }
        
        return $platforms;
    }, CacheHelper::TTL_DAY);
}


/**
 * Query platforms from Semantic MediaWiki with optional filters
 * Results are cached based on query parameters for improved performance.
 * 
 * @param string $filterLetter Optional letter filter (A-Z or # for numbers)
 * @param array $filterGameTitles Optional array of game title filters
 * @param string $sort Sort method ('alphabetical' or 'release_date')
 * @param int $page Current page number (1-based)
 * @param int $limit Results per page
 * @param bool $requireAllGames If true, platforms must be linked to ALL games (AND logic). If false, ANY game (OR logic)
 * @return array Array with 'platforms', 'totalCount', 'currentPage', 'totalPages'
 */
function queryPlatformsFromSMW($filterLetter = '', $filterGameTitles = [], $sort = 'release_date', $page = 1, $limit = 48, $requireAllGames = false) {
    $cache = CacheHelper::getInstance();
    
    // Build cache key from query parameters
    $cacheKey = $cache->buildQueryKey(CacheHelper::PREFIX_PLATFORMS, [
        'letter' => $filterLetter,
        'games' => $filterGameTitles,
        'sort' => $sort,
        'page' => $page,
        'limit' => $limit,
        'requireAll' => $requireAllGames ? '1' : '0'
    ]);
    
    return $cache->getOrSet($cacheKey, function() use ($filterLetter, $filterGameTitles, $sort, $page, $limit, $requireAllGames) {
        return fetchPlatformsFromSMW($filterLetter, $filterGameTitles, $sort, $page, $limit, $requireAllGames);
    }, CacheHelper::QUERY_TTL);
}

/**
 * Internal function to fetch platforms from SMW (not cached)
 */
function fetchPlatformsFromSMW($filterLetter, $filterGameTitles, $sort, $page, $limit, $requireAllGames) {
    $platforms = [];
    $totalCount = 0;
    
    try {
        // First, get total count for pagination
        $totalCount = getPlatformCountFromSMW($filterLetter, $filterGameTitles, $requireAllGames);
        
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
        
        if (!empty($filterGameTitles) && is_array($filterGameTitles)) {
            if ($requireAllGames && count($filterGameTitles) > 1) {
                // AND logic: Find platforms that are linked to ALL selected games
                $allGamePlatforms = [];
                foreach ($filterGameTitles as $index => $filterGameTitle) {
                    $gamePlatforms = getPlatformsForGameFromSMW($filterGameTitle);
                    if ($index === 0) {
                        // First game: start with all its platforms
                        $allGamePlatforms = $gamePlatforms;
                    } else {
                        // Subsequent games: intersect with existing platforms
                        $allGamePlatforms = array_intersect($allGamePlatforms, $gamePlatforms);
                    }
                    
                    // If no platforms match all games so far, we can stop early
                    if (empty($allGamePlatforms)) {
                        break;
                    }
                }
                
                // If no platforms match all games, then we return empty data
                if (empty($allGamePlatforms)) {
                    return [
                        'platforms' => [],
                        'totalCount' => 0,
                        'currentPage' => $page,
                        'totalPages' => 0,
                        'pageSize' => $limit,
                    ];
                }
                
                $platformNames = array_map(function($p) {
                    return str_replace('"', '\"', $p);
                }, $allGamePlatforms);
                $queryConditions .= '[[Has name::' . implode('||', $platformNames) . ']]';
            } else {
                // OR logic: Find platforms linked to ANY selected game (default behavior)
                $allPlatforms = [];
                foreach ($filterGameTitles as $filterGameTitle) {
                    $gamePlatforms = getPlatformsForGameFromSMW($filterGameTitle);
                    if (!empty($gamePlatforms)) {
                        // Build Has name:: conditions for these platforms
                        $platformNames = array_map(function($p) {
                            // Escape double quotes for SMW queries, just in case
                            return str_replace('"', '\"', $p);
                        }, $gamePlatforms);
                        // Only add if we have something
                        if (count($platformNames) > 0) {
                            $allPlatforms = array_merge($allPlatforms, $platformNames);
                        }
                    }
                }
                
                if (!empty($allPlatforms)) {
                    $queryConditions .= '[[Has name::' . implode('||', $allPlatforms) . ']]';
                }
            }
        }
        
        $printouts = '|?Has name|?Has short name|?Has image|?Has deck|?Has release date|?Has release date type';
        
        $params = '';
        // Set sort order
        switch ($sort) {
            case 'release_date':
                $params = '|sort=Has release date|order=desc';
                break;
            case 'alphabetical':
                $params = '|sort=Has name|order=asc';
                break;
            case 'last_edited':
                $params = '|sort=Modification date|order=desc';
                break;
            case 'last_created':
                $params = '|sort=Creation date|order=desc';
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
        'pageSize' => $limit,
    ];
}

/**
 * Get the number of games for a platform from cache (fast lookup)
 * Falls back to live query if cache is empty
 * 
 * @param string $platformName The platform name (e.g. PC or Platforms/PC)
 * @return int The number of games associated with the platform
 */
function getGameCountForPlatform($platformName) {
    $platformName = str_replace('Platforms/', '', $platformName);
    
    // Try to get from database cache first
    $dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
    $row = $dbr->selectRow(
        'platform_game_counts',
        ['game_count', 'last_updated'],
        ['platform_name' => $platformName],
        __METHOD__
    );
    
    if ($row) {
        return (int)$row->game_count;
    }
    
    // Fallback to live query if not cached (slow)
    error_log("⚠ Game count cache miss for platform: $platformName - Consider running rebuild script");
    return getGameCountForPlatformFromSMW($platformName);
}

/**
 * Get the number of games for a given platform from Semantic MediaWiki database tables
 * Queries SMW's internal tables directly to avoid pagination issues with ask queries
 * 
 * @param string $platformName The platform name (e.g. PC or Platforms/PC)
 * @return int The number of games associated with the platform
 */
function getGameCountForPlatformFromSMW($platformName) {
    $gameCount = 0;
    $SMW_PROPERTY_NAMESPACE = 102;
    
    try {
        // Ensure platform name has the namespace prefix
        $platformName = str_replace('Platforms/', '', $platformName);
        // Replace spaces with underscores for SMW property names
        $fullPlatformName = 'Platforms/' . str_replace(' ', '_', $platformName);
        
        $dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
        
        // Get the platform's SMW page ID (s_id)
        // smw_object_ids stores the mapping of page names to SMW internal IDs
        $platformSmwId = $dbr->selectField(
            'smw_object_ids',
            'smw_id',
            [
                'smw_title' => $fullPlatformName,
                'smw_namespace' => 0,  // Main namespace
                'smw_subobject' => ''
            ],
            __METHOD__
        );
        
        if (!$platformSmwId) {
            error_log("⚠ Platform not found in SMW: $platformName");
            return 0;
        }
        
        // Get the property ID for "Has platforms"
        $propertyId = $dbr->selectField(
            'smw_object_ids',
            'smw_id',
            [
                'smw_title' => 'Has_platforms',
                'smw_namespace' => $SMW_PROPERTY_NAMESPACE,  // Property namespace
                'smw_subobject' => ''
            ],
            __METHOD__
        );
        
        if (!$propertyId) {
            error_log("⚠ Property 'Has platforms' not found in SMW");
            return 0;
        }
        
        // Count games that have this platform
        // smw_di_wikipage stores page-type property values
        // s_id = subject (the game page), p_id = property (Has platforms), o_id = object (the platform)
        $gameCount = $dbr->selectField(
            'smw_di_wikipage',
            'COUNT(DISTINCT s_id)',
            [
                'p_id' => $propertyId,
                'o_id' => $platformSmwId
            ],
            __METHOD__
        );
        
        error_log("✓ Platform '$platformName' (SMW ID: $platformSmwId): $gameCount games (direct SMW query)");
        
    } catch (Exception $e) {
        error_log("Error getting game count for platform '$platformName': " . $e->getMessage());
    }
    
    return (int)$gameCount;
}


/**
 * Get the platforms for a given game from Semantic MediaWiki
 * 
 * Results are cached for improved performance.
 * 
 * @param string $gamePageName The game page name
 * @return array Array of platform names
 */
function getPlatformsForGameFromSMW($gamePageName) {
    $cache = CacheHelper::getInstance();
    
    // URL-encode game name for cache key suffix to handle special characters
    $safeGameName = rawurlencode($gamePageName);
    $cacheKey = $cache->buildSimpleKey(CacheHelper::PREFIX_PLATFORMS_FOR_GAME, $safeGameName);
    
    return $cache->getOrSet($cacheKey, function() use ($gamePageName) {
        return fetchPlatformsForGameFromSMW($gamePageName);
    }, CacheHelper::QUERY_TTL);
}

/**
 * Internal function to fetch platforms for a game (not cached)
 */
function fetchPlatformsForGameFromSMW($gamePageName) {
    $platforms = [];
    try {
        $queryConditions = '[[Category:Games]][[' . $gamePageName . ']]';
        $printouts = '|?Has platforms';
        $params = '|limit=1';
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
        
        if (isset($result['query']['results'])) {
            foreach ($result['query']['results'] as $pageName => $data) {
                $platformResults = $data['printouts']['Has platforms'];
                if (!empty($platformResults)) {
                    foreach ($platformResults as $platformResult) {
                        $platforms[] = $platformResult['displaytitle'] ?? $platformResult['fulltext'];
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error getting platforms for game: " . $e->getMessage());
    }
    return $platforms;
}

/**
 * Process the results of the platform query from Semantic MediaWiki
 * 
 * @param array $results The results of the platform query from Semantic MediaWiki
 * @return array Array of platform data with 'url', 'title', 'shortName', 'deck', 'releaseDate', 'releaseDateFormatted', 'image' keys
 */
function processPlatformQueryResults($results) {
    $platforms = [];

    if (isset($results) && is_array($results)) {
        foreach ($results as $pageName => $pageData) {
            $printouts = $pageData['printouts'];
            $cleanName = str_replace('Platforms/', '', $pageName);

            $platformData = [
                'url' => $pageData['fullurl'] ?? '',
                'title' => extractPrintoutString($printouts, 'Has name', $cleanName),
            ];

            // Extract optional string properties
            $shortName = extractPrintoutString($printouts, 'Has short name');
            if ($shortName) {
                $platformData['shortName'] = $shortName;
            }

            $deck = extractPrintoutString($printouts, 'Has deck');
            if ($deck) {
                $platformData['deck'] = $deck;
            }

            // Handle release date (complex structure)
            if (isset($printouts['Has release date']) && count($printouts['Has release date']) > 0) {
                $releaseDate = $printouts['Has release date'][0];
                $rawDate = $releaseDate['raw'] ?? '';
                $timestamp = $releaseDate['timestamp'] ?? strtotime($rawDate);

                $dateType = extractPrintoutString($printouts, 'Has release date type', 'Full');

                $platformData['releaseDate'] = $rawDate;
                $platformData['releaseDateTimestamp'] = $timestamp;
                $platformData['dateSpecificity'] = strtolower($dateType);
                $platformData['releaseDateFormatted'] = formatReleaseDate($rawDate, $timestamp, $dateType);
            }

            // Handle image (complex structure)
            if (isset($printouts['Has image']) && count($printouts['Has image']) > 0) {
                $image = $printouts['Has image'][0];
                $imageUrl = $image['fulltext'] ?? '';
                if ( $imageUrl !== '' && class_exists( PageHelper::class ) ) {
                    $resolved = PageHelper::resolveWikiImageUrl( $imageUrl );
                    $platformData['image'] = $resolved ?? '';
                }
            }
            
            // If no image from SMW, try legacy image fallback
			if ( empty( $platformData['image'] ) && class_exists( LegacyImageHelper::class ) ) {
				$title = Title::newFromText( $pageName );
				$legacyImage = LegacyImageHelper::findLegacyImageForTitle( $title );
				if ( $legacyImage && !empty( $legacyImage['thumb'] ) ) {
					$platformData['image'] = $legacyImage['thumb'];
				}
			}

            $platformData['gameCount'] = getGameCountForPlatform($pageName);

            $platforms[] = $platformData;
        }
    }
    return $platforms;
}

/**
 * Get the total number of platforms from Semantic MediaWiki with optional filters
 * 
 * @param string $filterLetter Optional letter filter (A-Z or # for numbers)
 * @param array $filterGameTitles Optional array of game title filters
 * @param bool $requireAllGames If true, platforms must be linked to ALL games (AND logic). If false, ANY game (OR logic)
 * @return int Total number of platforms
 */
function getPlatformCountFromSMW($filterLetter = '', $filterGameTitles = [], $requireAllGames = false) {
    $cache = CacheHelper::getInstance();
    
    // Build cache key
    $cacheKey = $cache->buildQueryKey(CacheHelper::PREFIX_PLATFORMS_COUNT, [
        'letter' => $filterLetter,
        'games' => $filterGameTitles,
        'requireAll' => $requireAllGames ? '1' : '0'
    ]);
    
    return $cache->getOrSet($cacheKey, function() use ($filterLetter, $filterGameTitles, $requireAllGames) {
        return fetchPlatformCountFromSMW($filterLetter, $filterGameTitles, $requireAllGames);
    }, CacheHelper::QUERY_TTL);
}

/**
 * Internal function to fetch platform count (not cached)
 */
function fetchPlatformCountFromSMW($filterLetter, $filterGameTitles, $requireAllGames) {
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
        
        if (!empty($filterGameTitles) && is_array($filterGameTitles)) {
            if ($requireAllGames && count($filterGameTitles) > 1) {
                // AND logic: Find platforms that are linked to ALL selected games
                $allGamePlatforms = [];
                foreach ($filterGameTitles as $index => $filterGameTitle) {
                    $gamePlatforms = getPlatformsForGameFromSMW($filterGameTitle);
                    if ($index === 0) {
                        // First game: start with all its platforms
                        $allGamePlatforms = $gamePlatforms;
                    } else {
                        // Subsequent games: intersect with existing platforms
                        $allGamePlatforms = array_intersect($allGamePlatforms, $gamePlatforms);
                    }
                    
                    // If no platforms match all games so far, we can stop early
                    if (empty($allGamePlatforms)) {
                        break;
                    }
                }
                
                // Only add condition if we found common platforms
                if (!empty($allGamePlatforms)) {
                    $platformNames = array_map(function($p) {
                        return str_replace('"', '\"', $p);
                    }, $allGamePlatforms);
                    $countQuery .= '[[Has name::' . implode('||', $platformNames) . ']]';
                }
            } else {
                // OR logic: Find platforms linked to ANY selected game (default behavior)
                $allPlatforms = [];
                foreach ($filterGameTitles as $filterGameTitle) {
                    $gamePlatforms = getPlatformsForGameFromSMW($filterGameTitle);
                    if (!empty($gamePlatforms)) {
                        // Build Has name:: conditions for these platforms
                        $platformNames = array_map(function($p) {
                            // Escape double quotes for SMW queries, just in case
                            return str_replace('"', '\"', $p);
                        }, $gamePlatforms);
                        // Only add if we have something
                        if (count($platformNames) > 0) {
                            $allPlatforms = array_merge($allPlatforms, $platformNames);
                        }
                    }
                }
                
                if (!empty($allPlatforms)) {
                    $countQuery .= '[[Has name::' . implode('||', $allPlatforms) . ']]';
                }
            }
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
