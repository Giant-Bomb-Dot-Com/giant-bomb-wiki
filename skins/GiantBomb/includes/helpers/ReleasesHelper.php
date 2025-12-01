<?php
/**
 * Releases Helper
 * 
 * Provides utility functions for querying and formatting release data
 * Used by both the new releases page view and the API endpoint
 */

use MediaWiki\MediaWikiServices;

// Load platform helper functions
require_once __DIR__ . '/PlatformHelper.php';

// Load date helper functions
require_once __DIR__ . '/DateHelper.php';

/**
 * Group releases by time period based on date specificity
 * 
 * @param array $releases Array of release data
 * @return array Grouped releases by time period
 */
function groupReleasesByPeriod($releases) {
    $groups = [];
    
    foreach ($releases as $release) {
        if (!isset($release['releaseDateTimestamp']) || !isset($release['dateSpecificity'])) {
            continue;
        }
        
        $specificity = $release['dateSpecificity'];
        $timestamp = $release['releaseDateTimestamp'];
        
        $groupData = processDateForGrouping($timestamp, $specificity);
        $groupKey = $groupData['groupKey'];
        $groupLabel = $groupData['groupLabel'];
        $sortKey = $groupData['sortKey'];
        
        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'label' => $groupLabel,
                'releases' => [],
                'sortKey' => $sortKey
            ];
        }
        
        $groups[$groupKey]['releases'][] = $release;
    }
    
    uasort($groups, function($a, $b) {
        return strcmp($a['sortKey'], $b['sortKey']);
    });
    
    return array_values($groups);
}

/**
 * Process date for grouping by time period
 * 
 * @param int $timestamp The timestamp of the date
 * @param string $specificity The specificity of the date: "full", "month", "quarter", or "year"
 * @return array The group key, label, and sort key
 */

 function processDateForGrouping($timestamp, $specificity) {

    if ($specificity === 'full') {
        // Calculate the Sunday that starts the week containing this date
        $dayOfWeek = date('w', $timestamp); // 0 (Sunday) through 6 (Saturday)
        $weekStart = strtotime('-' . $dayOfWeek . ' days', $timestamp);
        $weekEnd = strtotime('+6 days', $weekStart);
        
        $groupKey = date('Y-W', $weekStart);
        $groupLabel = date('F j, Y', $weekStart) . ' - ' . date('F j, Y', $weekEnd);
        $sortKey = date('Ymd', $weekStart);
        
    } elseif ($specificity === 'month') {
        $groupKey = date('Y-m', $timestamp);
        $groupLabel = date('F Y', $timestamp);
        $sortKey = date('Ym', $timestamp) . '00';
        
    } elseif ($specificity === 'quarter') {
        $quarter = ceil(date('n', $timestamp) / 3);
        $groupKey = date('Y', $timestamp) . '-Q' . $quarter;
        $groupLabel = 'Q' . $quarter . ' ' . date('Y', $timestamp);
        $sortKey = date('Y', $timestamp) . '0' . $quarter;
        
    } else {
        $groupKey = date('Y', $timestamp);
        $groupLabel = date('Y', $timestamp);
        $sortKey = date('Y', $timestamp) . '0000';
    }
    
    return [
        'groupKey' => $groupKey,
        'groupLabel' => $groupLabel,
        'sortKey' => $sortKey
    ];
}

/**
 * Query releases from Semantic MediaWiki with optional filters
 * 
 * @param string $filterRegion Optional region filter
 * @param string $filterPlatform Optional platform filter
 * @return array Array of release data
 */
function queryReleasesFromSMW($filterRegion = '', $filterPlatform = '') {
    $releases = [];
    try {
        // Calculate date range: today to one month in the future
        $today = date('Y-m-d');
        $oneMonthFromNow = date('Y-m-d', strtotime('+1 month'));
        
        $queryConditions = '[[Has object type::Release]][[Has release date::>' . $today . ']][[Has release date::<' . $oneMonthFromNow . ']][[-Has subobject::~*/Releases]]';
        
        if (!empty($filterRegion)) {
            $queryConditions .= '[[Has region::' . $filterRegion . ']]';
        }
        
        if (!empty($filterPlatform)) {
            $queryConditions .= '[[Has platforms::Platforms/' . $filterPlatform . ']]';
        }
        
        $printouts = '|?Has games|?Has name|?Has release date|?Has release date type|?Has platforms|?Has region|?Has image';
        $params = '|sort=Has release date|order=desc|limit=50';
        
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
            $releases = processReleaseQueryResults($result['query']['results']);
        }
    } catch (Exception $e) {
        error_log("Error querying releases: " . $e->getMessage());
    }
    
    return $releases;
}

/**
 * Processes the query results from Semantic MediaWiki and returns an array of release data
 * 
 * @param array $results The query results from Semantic MediaWiki
 * @return array Array of release data
 */
function processReleaseQueryResults($results) {
    $releases = [];
    $seenReleases = [];
    $platformMappings = loadPlatformMappings();
    
    if (isset($results) && is_array($results)) {
        foreach ($results as $pageName => $pageData) {
            $releaseData = [];
            $printouts = $pageData['printouts'];
            
            if (isset($printouts['Has games']) && count($printouts['Has games']) > 0) {
                $game = $printouts['Has games'][0];
                $releaseData['title'] = $game['fulltext'];
                $releaseData['url'] = $game['fullurl'];
                $releaseData['text'] = $game['displaytitle'] ?? $game['fulltext'];
            }
            
            if (isset($printouts['Has name']) && count($printouts['Has name']) > 0) {
                $name = $printouts['Has name'][0];
                $releaseData['title'] = $name;
            }
            
            if (isset($printouts['Has release date']) && count($printouts['Has release date']) > 0) {
                $releaseDate = $printouts['Has release date'][0];
                $rawDate = $releaseDate['raw'] ?? '';
                $timestamp = $releaseDate['timestamp'] ?? strtotime($rawDate);
                
                $releaseData['releaseDate'] = $rawDate;
                $releaseData['releaseDateTimestamp'] = $timestamp;
                
                $dateType = 'Full';
                if (isset($printouts['Has release date type']) && count($printouts['Has release date type']) > 0) {
                    $dateType = $printouts['Has release date type'][0];
                }
                
                $releaseData['dateSpecificity'] = strtolower($dateType);
                $releaseData['releaseDateFormatted'] = formatReleaseDate($rawDate, $timestamp, $dateType);
            }
            
            if (isset($printouts['Has platforms']) && count($printouts['Has platforms']) > 0) {
                $platforms = [];
                foreach($printouts['Has platforms'] as $platform) {
                    $platformName = $platform['displaytitle'] ?? $platform['fulltext'];
                    $abbrev = $platformMappings[$platformName] ?? basename($platformName);
                    
                    $platforms[] = [
                        'title' => $platformName,
                        'url' => $platform['fullurl'],
                        'abbrev' => $abbrev,
                    ];
                }
                $releaseData['platforms'] = $platforms;
            }
            
            if (isset($printouts['Has region']) && count($printouts['Has region']) > 0) {
                $region = $printouts['Has region'][0];
                $releaseData['region'] = $region;
            }
            
            if (isset($printouts['Has image']) && count($printouts['Has image']) > 0) {
                $image = $printouts['Has image'][0];
                $releaseData['image'] = $image['fullurl'] ?? '';
                $releaseData['image'] = str_replace('http://localhost:8080/wiki/', '', $releaseData['image']);
            }
            
            // Create unique key for deduplication
            $platformsKey = isset($releaseData['platforms']) 
                ? implode(',', array_map(fn($p) => $p['title'], $releaseData['platforms']))
                : '';
            $uniqueKey = sprintf(
                '%s|%s|%s|%s',
                $releaseData['title'] ?? '',
                $releaseData['releaseDate'] ?? '',
                $releaseData['region'] ?? '',
                $platformsKey
            );
            
            if (!isset($seenReleases[$uniqueKey])) {
                $seenReleases[$uniqueKey] = true;
                $releases[] = $releaseData;
            }
        }
    }
    
    return $releases;
}

