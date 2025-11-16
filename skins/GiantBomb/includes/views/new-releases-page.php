<?php
use MediaWiki\Html\TemplateParser;
use MediaWiki\MediaWikiServices;

/**
 * New releases page View
 * Displays the latest game releases, grouped by time period
 */

// Load helper functions
require_once __DIR__ . '/../helpers/ReleasesHelper.php';

// Get filter parameters from URL
$request = RequestContext::getMain()->getRequest();
$filterRegion = $request->getText('region', '');
$filterPlatform = $request->getText('platform', '');
$sort = $request->getText('sort', 'release_date');

// Query releases using helper function
$releases = queryReleasesFromSMW($filterRegion, $filterPlatform, $sort);

// Group releases by time period
$weekGroups = groupReleasesByPeriod($releases);

// Get all platforms for filter dropdown using helper function (cached)
$platforms = getAllPlatforms();

// Format data for Mustache template
$data = [
    'weekGroups' => $weekGroups,
    'hasReleases' => count($releases) > 0,
    'vue' => [
        'platformsJson' => htmlspecialchars(json_encode($platforms), ENT_QUOTES, 'UTF-8'),
        'weekGroupsJson' => htmlspecialchars(json_encode($weekGroups), ENT_QUOTES, 'UTF-8'),
    ],
];

// Path to Mustache templates
$templateDir = realpath(__DIR__ . '/../templates');

// Render Mustache template
$templateParser = new TemplateParser($templateDir);
echo $templateParser->processTemplate('new-releases-page', $data);
