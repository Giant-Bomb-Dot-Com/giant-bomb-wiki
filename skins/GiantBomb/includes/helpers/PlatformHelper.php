<?php
/**
 * Platform Helper
 * 
 * Provides utility functions for looking up platform data from Semantic MediaWiki
 * with caching support for performance.
 */

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

