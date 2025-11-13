<?php
use MediaWiki\Html\TemplateParser;
use MediaWiki\MediaWikiServices;

// Load platform helper functions
require_once __DIR__ . '/../helpers/PlatformHelper.php';

/**
 * New releases page View
 * Displays the latest game releases, grouped by time period
 * (week for specific dates, month for month-only dates, year for year-only dates)
 */

/**
 * Format a date based on the "Has release date type" property
 * 
 * @param string $rawDate The raw date from SMW (e.g., "1/1986", "10/2003", "12/31/2024")
 * @param int $timestamp The timestamp of the date
 * @param string $dateType The date type: "Year", "Month", "Quarter", "Full", or "None"
 * @return string The formatted date string
 */
function formatDateByType($rawDate, $timestamp, $dateType) {
    if (!$timestamp || $dateType === 'None') {
        return $rawDate;
    }
    
    switch ($dateType) {
        case 'Year':
            // For year-only dates, extract just the year
            return date('Y', $timestamp);
            
        case 'Month':
            // For month+year dates
            return date('F Y', $timestamp);
            
        case 'Quarter':
            // For quarter dates (Q1 2023, Q2 2023, etc.)
            $quarter = ceil(date('n', $timestamp) / 3);
            return 'Q' . $quarter . ' ' . date('Y', $timestamp);
            
        case 'Full':
        default:
            // For full dates
            return date('F j, Y', $timestamp);
    }
}

/**
 * Group releases by time period based on date specificity
 */
function groupReleases($releases) {
    $groups = [];
    
    foreach ($releases as $release) {
        if (!isset($release['sortTimestamp']) || !isset($release['dateSpecificity'])) {
            continue;
        }
        
        $specificity = $release['dateSpecificity'];
        $timestamp = $release['sortTimestamp'];
        
        // Group by specificity
        if ($specificity === 'full') {
            // Group by week (Sunday - Saturday) for full dates
            $weekStart = strtotime('sunday this week', $timestamp);
            if (date('w', $timestamp) == 0) {
                $weekStart = $timestamp;
            }
            $weekEnd = strtotime('+6 days', $weekStart);
            
            $groupKey = date('Y-W', $weekStart);
            $groupLabel = date('F j, Y', $weekStart) . ' - ' . date('F j, Y', $weekEnd);
            $sortKey = date('Ymd', $weekStart);
            
        } elseif ($specificity === 'month') {
            // Group by month
            $groupKey = date('Y-m', $timestamp);
            $groupLabel = date('F Y', $timestamp);
            $sortKey = date('Ym', $timestamp) . '00';
            
        } elseif ($specificity === 'quarter') {
            // Group by quarter
            $quarter = ceil(date('n', $timestamp) / 3);
            $groupKey = date('Y', $timestamp) . '-Q' . $quarter;
            $groupLabel = 'Q' . $quarter . ' ' . date('Y', $timestamp);
            $sortKey = date('Y', $timestamp) . '0' . $quarter;
            
        } else { // year or none
            // Group by year
            $groupKey = date('Y', $timestamp);
            $groupLabel = date('Y', $timestamp);
            $sortKey = date('Y', $timestamp) . '0000';
        }
        
        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'label' => $groupLabel,
                'releases' => [],
                'sortKey' => $sortKey
            ];
        }
        
        $groups[$groupKey]['releases'][] = $release;
    }
    
    // Sort groups by date (newest first)
    uasort($groups, function($a, $b) {
        return strcmp($b['sortKey'], $a['sortKey']);
    });
    
    return array_values($groups);
}

$releases = [];

// Load platform mappings once with caching
$platformMappings = loadPlatformMappings();

try {
    // Query for ReleaseSubobjects - properties have "Has" prefix in SMW
    // Filter to only Release type subobjects from /Releases pages and sort by release date
    // The -Has subobject property links subobjects to their parent page
    $queryConditions = '[[Has object type::Release]][[Has release date::+]][[-Has subobject::~*/Releases]]';
    $printouts = '|?Has games|?Has name|?Has release date|?Has release date type|?Has platforms|?Has region|?Has image';
    $params = '|sort=Has release date|order=desc|limit=500';
    
    $fullQuery = $queryConditions . $printouts . $params;
    
    error_log("Ask query: " . $fullQuery);
    
    // Use the API to execute the query
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
    
    error_log("API result: " . print_r($result, true));
    
    // Process the results with deduplication
    $seenReleases = []; // Track unique releases to prevent duplicates
    
    if (isset($result['query']['results']) && is_array($result['query']['results'])) {
        foreach ($result['query']['results'] as $pageName => $pageData) {
            $releaseData = [];
            $printouts = $pageData['printouts'];
            
            if (isset($printouts['Has games']) && count($printouts['Has games']) > 0) {
                $game = $printouts['Has games'][0];
                // Default to title from game object
                $releaseData['title'] = $game['fulltext'];
                $releaseData['url'] = $game['fullurl'];
                $releaseData['text'] = $game['displaytitle'] ?? $game['fulltext'];
            }
            
            if (isset($printouts['Has name']) && count($printouts['Has name']) > 0) {
                $name = $printouts['Has name'][0];
                // Override title with specific release name if exists
                $releaseData['title'] = $name;
            }
            
            if (isset($printouts['Has release date']) && count($printouts['Has release date']) > 0) {
                $releaseDate = $printouts['Has release date'][0];
                $rawDate = $releaseDate['raw'] ?? '';
                $timestamp = $releaseDate['timestamp'] ?? strtotime($rawDate);
                
                $releaseData['releaseDate'] = $rawDate;
                $releaseData['releaseDateTimestamp'] = $timestamp;
                $releaseData['sortTimestamp'] = $timestamp;
                
                // Get the date type (specificity) from the property
                $dateType = 'Full'; // Default
                if (isset($printouts['Has release date type']) && count($printouts['Has release date type']) > 0) {
                    $dateType = $printouts['Has release date type'][0];
                }
                
                // Format date based on the date type property
                $releaseData['dateSpecificity'] = strtolower($dateType);
                $releaseData['releaseDateFormatted'] = formatDateByType($rawDate, $timestamp, $dateType);
            }
            
            if (isset($printouts['Has platforms']) && count($printouts['Has platforms']) > 0) {
                $platforms = [];
                foreach($printouts['Has platforms'] as $platform) {
                    $platformName = $platform['displaytitle'] ?? $platform['fulltext'];
                    
                    // Look up abbreviation from cached platform mappings
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
                // Clean up local development URLs if present
                // Only for temp local development with externally hosted images
                $releaseData['image'] = str_replace('http://localhost:8080/wiki/', '', $releaseData['image']);
            }
            
            // Create a unique key to detect duplicates
            // Based on: game title + release date + region + platforms
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
            
            // Only add if we haven't seen this exact release before
            if (!isset($seenReleases[$uniqueKey])) {
                $seenReleases[$uniqueKey] = true;
                error_log("Adding release: " . $uniqueKey);
                $releases[] = $releaseData;
            } else {
                error_log("Skipping duplicate release: " . $uniqueKey);
            }
        }
    }
    
    error_log("Total releases found: " . count($releases));
}
catch (Exception $e) {
    error_log("Error querying releases: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $releases = [];
}

// Group releases by time period (week/month/year based on date specificity)
$weekGroups = groupReleases($releases);

// Format data for Mustache template
$data = [
    'weekGroups' => $weekGroups,
    'hasReleases' => count($releases) > 0,
];

// Path to Mustache templates
$templateDir = realpath(__DIR__ . '/../templates');

// Render Mustache template
$templateParser = new TemplateParser($templateDir);
echo $templateParser->processTemplate('new-releases-page', $data);
