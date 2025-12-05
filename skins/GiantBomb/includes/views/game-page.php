<?php
use MediaWiki\Html\TemplateParser;
use MediaWiki\MediaWikiServices;

if ( !defined( 'GB_LEGACY_UPLOAD_HOST' ) ) {
	define( 'GB_LEGACY_UPLOAD_HOST', 'https://www.giantbomb.com' );
}

if ( !function_exists( 'gbParseLegacyImageData' ) ) {
	function gbParseLegacyImageData( string $text ): ?array {
		if ( !preg_match(
			'/<div[^>]*id=(["\'])imageData\\1[^>]*data-json=(["\'])(.*?)\\2/si',
			$text,
			$matches
		) ) {
			return null;
		}
		$raw = html_entity_decode( $matches[3], ENT_QUOTES | ENT_HTML5 );
		$raw = trim( $raw );
		if ( $raw === '' ) {
			return null;
		}
		$data = json_decode( $raw, true );
		if ( !is_array( $data ) || json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}
		return $data;
	}

	function gbChooseLegacySize( array $available, array $preferred ): ?string {
		foreach ( $preferred as $candidate ) {
			if ( in_array( $candidate, $available, true ) ) {
				return $candidate;
			}
		}
		return $available[0] ?? null;
	}

	function gbBuildLegacyImageUrl( array $entry, array $preferredSizes ): ?string {
		$file = isset( $entry['file'] ) ? trim( (string)$entry['file'] ) : '';
		$path = isset( $entry['path'] ) ? trim( (string)$entry['path'] ) : '';
		$sizes = isset( $entry['sizes'] ) ? (string)$entry['sizes'] : '';
		if ( $file === '' || $path === '' || $sizes === '' ) {
			return null;
		}
		$availableSizes = array_values(
			array_filter(
				array_map( 'trim', explode( ',', $sizes ) ),
				static fn ( $size ) => $size !== ''
			)
		);
		if ( !$availableSizes ) {
			return null;
		}
		$chosen = gbChooseLegacySize( $availableSizes, $preferredSizes );
		if ( $chosen === null ) {
			return null;
		}
		$normalizedPath = trim( $path, '/' );
		$relative = '/a/uploads/' . $chosen . '/' . ( $normalizedPath !== '' ? $normalizedPath . '/' : '' ) . $file;
		return GB_LEGACY_UPLOAD_HOST . $relative;
	}

	function gbResolveWikiImageUrl( string $value ): ?string {
		$trimmed = trim( $value );
		if ( $trimmed === '' ) {
			return null;
		}
		if ( preg_match( '#^https?://#i', $trimmed ) ) {
			return $trimmed;
		}
		if ( stripos( $trimmed, 'File:' ) !== 0 ) {
			$trimmed = 'File:' . $trimmed;
		}
		$title = \Title::newFromText( $trimmed );
		if ( !$title ) {
			return null;
		}
		$services = MediaWikiServices::getInstance();
		$file = $services->getRepoGroup()->findFile( $title );
		if ( !$file ) {
			return null;
		}
		$url = $file->getFullUrl();
		if ( !is_string( $url ) || $url === '' ) {
			return null;
		}
		return \wfExpandUrl( $url, \PROTO_CANONICAL );
	}
}

/**
 * Game Page View
 * Displays comprehensive game information with all related data
 */

// Get the current page title
$title = $this->getSkin()->getTitle();
$pageTitle = $title->getText();
$pageTitleDB = $title->getDBkey(); // Database format with underscores

// Initialize game data structure
$gameData = [
	'name' => str_replace('Games/', '', str_replace('_', ' ', $pageTitle)),
	'url' => '/wiki/' . $pageTitleDB,
	'image' => '',
	'backgroundImage' => '',
	'deck' => '',
	'description' => '',
	'releaseDate' => '',
	'releaseDateType' => '',
	'aliases' => '',
	'guid' => '',

	// Companies
	'developers' => [],
	'publishers' => [],

	// Classification
	'platforms' => [],
	'genres' => [],
	'themes' => [],
	'franchise' => '',

	// Related content
	'characters' => [],
	'concepts' => [],
	'locations' => [],
	'objects' => [],
	'similarGames' => [],

	// Sub-pages
	'hasReleases' => false,
	'hasDLC' => false,
	'hasCredits' => false,

	// Features
	'features' => [],

	// Multiplayer
	'multiplayer' => [],

	// Reviews
	'reviewScore' => 0,
	'reviewCount' => 0,
	'reviewDistribution' => [
		'5' => 0,
		'4' => 0,
		'3' => 0,
		'2' => 0,
		'1' => 0,
	],
];

try {
	// Get page content
	$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
	$page = $wikiPageFactory->newFromTitle($title);
	$content = $page->getContent();

	if ($content) {
		$text = $content->getText();
		$legacyImageData = gbParseLegacyImageData( $text );

		// Extract wikitext (content after the template closing}})
		$wikitext = '';
		if (preg_match('/\}\}(.+)$/s', $text, $matches)) {
			$wikitext = trim($matches[1]);
			error_log("Extracted wikitext length: " . strlen($wikitext));
		} else {
			error_log("No content found after template closing");
		}

		// Parse the wikitext to HTML
		if (!empty($wikitext)) {
			error_log("Parsing wikitext...");
			try {
				$services = MediaWikiServices::getInstance();
				$parser = $services->getParser();
				$parserOptions = \ParserOptions::newFromAnon();

				$parserOutput = $parser->parse($wikitext, $title, $parserOptions);
				$gameData['description'] = $parserOutput->getText([
					'allowTOC' => false,
					'enableSectionEditLinks' => false,
					'wrapperDivClass' => ''
				]);
			} catch (Exception $e) {
				error_log("Failed to parse wikitext: " . $e->getMessage());
				// Fallback to raw wikitext if parsing fails
				$gameData['description'] = $wikitext;
			}
		}

		// Parse template parameters
		if (preg_match('/\| Name=([^\n]+)/', $text, $matches)) {
			$gameData['name'] = trim($matches[1]);
		}
		if (preg_match('/\| Deck=([^\n]+)/', $text, $matches)) {
			$gameData['deck'] = trim($matches[1]);
		}
		if (preg_match('/\| Image=([^\n]+)/', $text, $matches)) {
			$gameData['image'] = trim($matches[1]);
		}
		if (preg_match('/\| ReleaseDate=([^\n]+)/', $text, $matches)) {
			$gameData['releaseDate'] = trim($matches[1]);
		}
		if (preg_match('/\| ReleaseDateType=([^\n]+)/', $text, $matches)) {
			$gameData['releaseDateType'] = trim($matches[1]);
		}
		if (preg_match('/\| Aliases=([^\n]+)/', $text, $matches)) {
			$gameData['aliases'] = trim($matches[1]);
		}
		if (preg_match('/\| Guid=([^\n]+)/', $text, $matches)) {
			$gameData['guid'] = trim($matches[1]);
		}

		$resolvedTemplateImage = gbResolveWikiImageUrl( $gameData['image'] );
		$gameData['image'] = $resolvedTemplateImage ?? '';

		if ( isset( $legacyImageData ) && is_array( $legacyImageData ) ) {
			if ( $gameData['image'] === '' && isset( $legacyImageData['infobox'] ) ) {
				$infoboxUrl = gbBuildLegacyImageUrl(
					$legacyImageData['infobox'],
					[ 'scale_super', 'screen_kubrick', 'screen_medium', 'scale_large', 'scale_medium' ]
				);
				if ( $infoboxUrl !== null ) {
					$gameData['image'] = $infoboxUrl;
				}
			}
			if ( isset( $legacyImageData['background'] ) ) {
				$backgroundUrl = gbBuildLegacyImageUrl(
					$legacyImageData['background'],
					[ 'screen_kubrick_wide', 'screen_kubrick', 'scale_super', 'scale_large', 'screen_medium' ]
				);
				if ( $backgroundUrl !== null ) {
					$gameData['backgroundImage'] = $backgroundUrl;
				}
			}
			if ( $gameData['image'] === '' && $gameData['backgroundImage'] !== '' ) {
				$gameData['image'] = $gameData['backgroundImage'];
			}
		}

		// Parse array fields (comma-separated values)
		$arrayFields = [
			'Developers' => 'developers',
			'Publishers' => 'publishers',
			'Platforms' => 'platforms',
			'Genres' => 'genres',
			'Themes' => 'themes',
			'Characters' => 'characters',
			'Concepts' => 'concepts',
			'Locations' => 'locations',
			'Objects' => 'objects',
			'Games' => 'similarGames',
		];

		foreach ($arrayFields as $templateField => $dataField) {
			if (preg_match('/\| ' . $templateField . '=([^\n]+)/', $text, $matches)) {
				$values = explode(',', trim($matches[1]));
				$gameData[$dataField] = array_filter(array_map(function($v) {
					$cleaned = trim($v);
					// Remove namespace prefix (e.g., "Platforms/PlayStation 4" -> "PlayStation 4")
					if (strpos($cleaned, '/') !== false) {
						$parts = explode('/', $cleaned, 2);
						$cleaned = $parts[1];
					}
					// Replace underscores with spaces for better display
					$cleaned = str_replace('_', ' ', $cleaned);
					return $cleaned;
				}, $values));
			}
		}

		// Parse franchise (single value)
		if (preg_match('/\| Franchise=([^\n]+)/', $text, $matches)) {
			$franchise = trim($matches[1]);
			if (strpos($franchise, '/') !== false) {
				$parts = explode('/', $franchise, 2);
				$franchise = $parts[1];
			}
			// Replace underscores with spaces for better display
			$franchise = str_replace('_', ' ', $franchise);
			$gameData['franchise'] = $franchise;
		}

		// Parse features - all possible features with enabled status
		$allFeatures = [
			'Camera Support',
			'Voice control',
			'Motion control',
			'Driving wheel (native)',
			'Flightstick (native)',
			'PC gamepad (native)',
			'Head tracking (native)',
		];

		$enabledFeatures = [];
		if (preg_match('/\| Features=([^\n]+)/', $text, $matches)) {
			$featuresStr = trim($matches[1]);
			$featuresArr = explode(',', $featuresStr);
			$enabledFeatures = array_map(function($f) {
				return trim(str_replace('_', ' ', $f));
			}, $featuresArr);
		}

		// Build features array with enabled status
		foreach ($allFeatures as $feature) {
			$gameData['features'][] = [
				'name' => $feature,
				'enabled' => in_array($feature, $enabledFeatures),
			];
		}

		// Parse multiplayer options - all possible options with enabled status
		$allMultiplayerOptions = [
			'Local co-op',
			'Online co-op',
			'LAN competitive',
			'Local split screen',
			'Voice control',
			'Driving wheel (native)',
			'PC gameload (native)',
		];

		$enabledMultiplayer = [];
		if (preg_match('/\| Multiplayer=([^\n]+)/', $text, $matches)) {
			$multiplayerStr = trim($matches[1]);
			$multiplayerArr = explode(',', $multiplayerStr);
			$enabledMultiplayer = array_map(function($m) {
				return trim(str_replace('_', ' ', $m));
			}, $multiplayerArr);
		}

		// Build multiplayer array with enabled status
		foreach ($allMultiplayerOptions as $option) {
			$gameData['multiplayer'][] = [
				'name' => $option,
				'enabled' => in_array($option, $enabledMultiplayer),
			];
		}

		// Hardcoded review data for testing
		$gameData['reviewScore'] = number_format(4.0, 1, '.', '');
		$gameData['reviewCount'] = 4;
		$reviewCounts = [
			'5' => 2,
			'4' => 1,
			'3' => 0,
			'2' => 0,
			'1' => 1,
		];

		// Calculate percentages for the bars
		$gameData['reviewDistribution'] = [];
		foreach ($reviewCounts as $star => $count) {
			$percentage = $gameData['reviewCount'] > 0 ? ($count / $gameData['reviewCount']) * 100 : 0;
			$gameData['reviewDistribution'][$star] = [
				'count' => $count,
				'percentage' => round($percentage, 1),
			];
		}

		// Calculate filled stars (for display)
		$gameData['reviewStars'] = [];
		for ($i = 1; $i <= 5; $i++) {
			$gameData['reviewStars'][] = [
				'filled' => $i <= floor($gameData['reviewScore'])
			];
		}
	}

	// Check for sub-pages
	$gameData['hasReleases'] = \Title::newFromText($pageTitle . '/Releases')->exists();
	$gameData['hasDLC'] = \Title::newFromText($pageTitle . '/DLC')->exists();
	$gameData['hasCredits'] = \Title::newFromText($pageTitle . '/Credits')->exists();

	// Get images linked from this page
	$gameData['images'] = [];
	try {
		$services = MediaWikiServices::getInstance();
		$dbLoadBalancer = $services->getDBLoadBalancer();
		$db = $dbLoadBalancer->getConnection(DB_REPLICA);

		// Get page ID
		$pageId = $title->getArticleID();

		// Query imagelinks table for image references
		$result = $db->select(
			'imagelinks',
			['il_to'],
			['il_from' => $pageId],
			__METHOD__
		);

		foreach ($result as $row) {
			$gameData['images'][] = [
				'url' => $row->il_to,
				'caption' => basename($row->il_to),
				'width' => 0,
				'height' => 0
			];
		}
	} catch (Exception $e) {
		error_log("Failed to fetch game images: " . $e->getMessage());
	}

	$gameData['imagesCount'] = count($gameData['images']);

	// Get release count from /Releases subpage
	$gameData['releasesCount'] = 0;
	if ($gameData['hasReleases']) {
		try {
			$releasesTitle = \Title::newFromText($pageTitle . '/Releases');
			if ($releasesTitle && $releasesTitle->exists()) {
				$releasesPage = $wikiPageFactory->newFromTitle($releasesTitle);
				$releasesContent = $releasesPage->getContent();
				if ($releasesContent) {
					$releasesText = $releasesContent->getText();
					// Count occurrences of {{ReleaseSubobject
					preg_match_all('/\{\{ReleaseSubobject/i', $releasesText, $matches);
					$gameData['releasesCount'] = count($matches[0]);
				}
			}
		} catch (Exception $e) {
			error_log("Failed to fetch release count: " . $e->getMessage());
		}
	}

	// Get individual release details for dropdown
	$gameData['releases'] = [];

	if ($gameData['hasReleases']) {
		try {
			// Parse releases page content to extract release details
			$releasesTitle = \Title::newFromText($pageTitle . '/Releases');
			if ($releasesTitle && $releasesTitle->exists()) {
				$releasesPage = $wikiPageFactory->newFromTitle($releasesTitle);
				$releasesContent = $releasesPage->getContent();
				if ($releasesContent) {
					$releasesText = $releasesContent->getText();

					// Parse each ReleaseSubobject
					preg_match_all('/\{\{ReleaseSubobject([^}]+)\}\}/s', $releasesText, $releaseMatches);

					foreach ($releaseMatches[1] as $index => $releaseContent) {
						$release = [
							'name' => '',
							'platform' => '',
							'region' => '',
							'releaseDate' => 'N/A',
							'rating' => 'N/A',
							'resolutions' => 'N/A',
							'soundSystems' => 'N/A',
							'widescreenSupport' => 'N/A',
						];

						// Extract name
						if (preg_match('/\|Name=([^\n|]+)/', $releaseContent, $match)) {
							$release['name'] = trim($match[1]);
						}

						// Extract platform
						if (preg_match('/\|Platform=([^\n|]+)/', $releaseContent, $match)) {
							$platform = trim($match[1]);
							$platform = str_replace('Platforms/', '', $platform);
							$platform = str_replace('_', ' ', $platform);
							$release['platform'] = $platform;
						}

						// Extract region
						if (preg_match('/\|Region=([^\n|]+)/', $releaseContent, $match)) {
							$release['region'] = trim($match[1]);
						}

						// Extract release date
						if (preg_match('/\|ReleaseDate=([^\n|]+)/', $releaseContent, $match)) {
							$date = trim($match[1]);
							if (!empty($date) && $date !== 'None') {
								$release['releaseDate'] = $date;
							}
						}

						// Extract rating
						if (preg_match('/\|Rating=([^\n|]+)/', $releaseContent, $match)) {
							$rating = trim($match[1]);
							$rating = str_replace('Ratings/', '', $rating);
							$rating = str_replace('_', ' ', $rating);
							if (!empty($rating)) {
								$release['rating'] = $rating;
							}
						}

						// Extract resolutions
						if (preg_match('/\|Resolutions=([^\n|]+)/', $releaseContent, $match)) {
							$resolution = trim($match[1]);
							if (!empty($resolution)) {
								$release['resolutions'] = $resolution;
							}
						}

						// Extract sound systems
						if (preg_match('/\|SoundSystems=([^\n|]+)/', $releaseContent, $match)) {
							$soundSystem = trim($match[1]);
							if (!empty($soundSystem)) {
								$release['soundSystems'] = $soundSystem;
							}
						}

						// Check widescreen support
						if (preg_match('/\|WidescreenSupport=([^\n|]+)/', $releaseContent, $match)) {
							$widescreen = trim($match[1]);
							$release['widescreenSupport'] = ucfirst(strtolower($widescreen));
						}

						// Create display name for dropdown
						$release['displayName'] = $release['platform'];
						if (!empty($release['region'])) {
							$release['displayName'] .= ' (' . $release['region'] . ')';
						}

						$gameData['releases'][] = $release;
					}
				}
			}

		} catch (Exception $e) {
			error_log("Failed to fetch release details: " . $e->getMessage());
		}
	}

	// Convert booleans to strings for Vue props
	$gameData['hasReleasesStr'] = $gameData['hasReleases'] ? 'true' : 'false';
	$gameData['hasDLCStr'] = $gameData['hasDLC'] ? 'true' : 'false';

} catch (Exception $e) {
	error_log("Game page error: " . $e->getMessage());
}

// Prepare data for Vue components (comma-separated strings)
$vueData = [
	'platformsStr' => !empty($gameData['platforms']) ? implode(',', $gameData['platforms']) : '',
	'genresStr' => !empty($gameData['genres']) ? implode(',', $gameData['genres']) : '',
	'themesStr' => !empty($gameData['themes']) ? implode(',', $gameData['themes']) : '',
	'charactersStr' => !empty($gameData['characters']) ? implode(',', $gameData['characters']) : '',
	'conceptsStr' => !empty($gameData['concepts']) ? implode(',', $gameData['concepts']) : '',
	'locationsStr' => !empty($gameData['locations']) ? implode(',', $gameData['locations']) : '',
	'objectsStr' => !empty($gameData['objects']) ? implode(',', $gameData['objects']) : '',
	'similarGamesStr' => !empty($gameData['similarGames']) ? implode(',', $gameData['similarGames']) : '',
	'releasesJson' => !empty($gameData['releases']) ? htmlspecialchars(json_encode($gameData['releases']), ENT_QUOTES, 'UTF-8') : '[]',
];

// Format data for Mustache template
$data = [
	'game' => $gameData,
	'vue' => $vueData,
	'hasBasicInfo' => !empty($gameData['deck']) || !empty($gameData['releaseDate']) || !empty($gameData['aliases']),
	'hasCompanies' => !empty($gameData['developers']) || !empty($gameData['publishers']),
	'hasClassification' => !empty($gameData['platforms']) || !empty($gameData['genres']) || !empty($gameData['themes']) || !empty($gameData['franchise']),
	'hasRelatedContent' => !empty($gameData['characters']) || !empty($gameData['concepts']) || !empty($gameData['locations']) || !empty($gameData['objects']) || !empty($gameData['similarGames']),
	'hasSubPages' => $gameData['hasReleases'] || $gameData['hasDLC'] || $gameData['hasCredits'],
];

// Path to Mustache templates
$templateDir = realpath(__DIR__ . '/../templates');

// Render Mustache template
$templateParser = new TemplateParser($templateDir);
echo $templateParser->processTemplate('game-page', $data);
