<?php

use MediaWiki\MediaWikiServices;

if ( !defined( 'GB_LEGACY_UPLOAD_HOST' ) ) {
	define( 'GB_LEGACY_UPLOAD_HOST', 'https://www.giantbomb.com' );
}

if ( !defined( 'GB_PUBLIC_WIKI_HOST' ) ) {
	define( 'GB_PUBLIC_WIKI_HOST', 'https://www.giantbomb.com' );
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
		$title = Title::newFromText( $trimmed );
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

if ( !function_exists( 'gbSanitizeMetaText' ) ) {
	function gbSanitizeMetaText( string $text, int $limit = 280 ): string {
		$plain = trim( preg_replace( '/\s+/', ' ', strip_tags( $text ) ) ?? '' );
		if ( $plain === '' ) {
			return '';
		}
		if ( mb_strlen( $plain ) <= $limit ) {
			return $plain;
		}
		$cut = mb_substr( $plain, 0, $limit );
		$space = mb_strrpos( $cut, ' ' );
		if ( $space !== false && $space >= (int) ( $limit * 0.6 ) ) {
			$cut = mb_substr( $cut, 0, $space );
		}
		return rtrim( $cut, " \t\n\r\0\x0B.,;:–—-_" ) . '…';
	}
}

if ( !function_exists( 'gbBuildMetaTag' ) ) {
	function gbBuildMetaTag( array $attributes ): string {
		$parts = [];
		foreach ( $attributes as $name => $value ) {
			if ( $value === null || $value === '' ) {
				continue;
			}
			$parts[] = htmlspecialchars( $name, ENT_QUOTES, 'UTF-8' ) . '="' . htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ) . '"';
		}
		return '<meta ' . implode( ' ', $parts ) . ' />';
	}
}

if ( !function_exists( 'gbBuildJsonLdScript' ) ) {
	function gbBuildJsonLdScript( string $json ): string {
		return '<script type="application/ld+json">' . $json . '</script>';
	}
}

if ( !function_exists( 'gbExtractReleaseData' ) ) {
	/**
	 * @return array{count:int,items:array<int,array<string,string>>}
	 */
	function gbExtractReleaseData( string $releasesText ): array {
		$result = [
			'count' => 0,
			'items' => [],
		];
		if ( trim( $releasesText ) === '' ) {
			return $result;
		}

		$regionMap = [
			1 => 'United States',
			2 => 'United Kingdom',
			6 => 'Japan',
			11 => 'Australia',
		];

		$ratingsMap = [
			1 => 'Ratings/ESRB_T',
			2 => 'Ratings/PEGI_16',
			5 => 'Ratings/BBFC_15',
			6 => 'Ratings/ESRB_E',
			7 => 'Ratings/PEGI_3',
			9 => 'Ratings/ESRB_K_A',
			12 => 'Ratings/OFLC_MA15',
			13 => 'Ratings/OFLC_M15',
			14 => 'Ratings/OFLC_G',
			15 => 'Ratings/OFLC_G8',
			16 => 'Ratings/ESRB_M',
			17 => 'Ratings/BBFC_18',
			18 => 'Ratings/PEGI_7',
			19 => 'Ratings/CERO_All_Ages',
			20 => 'Ratings/BBFC_PG',
			21 => 'Ratings/BBFC_12',
			23 => 'Ratings/ESRB_AO',
			24 => 'Ratings/CERO_18',
			25 => 'Ratings/CERO_A',
			26 => 'Ratings/ESRB_EC',
			27 => 'Ratings/CERO_C',
			28 => 'Ratings/CERO_15',
			29 => 'Ratings/ESRB_E10',
			30 => 'Ratings/BBFC_U',
			31 => 'Ratings/OFLC_M',
			32 => 'Ratings/CERO_D',
			33 => 'Ratings/CERO_B',
			34 => 'Ratings/CERO_Z',
			36 => 'Ratings/PEGI_12',
			37 => 'Ratings/PEGI_18',
			38 => 'Ratings/OFLC_PG',
			39 => 'Ratings/OFLC_R18',
		];

		$resolutionsMap = [
			5 => 'Resolutions/1080p',
			6 => 'Resolutions/1080i',
			7 => 'Resolutions/720p',
			8 => 'Resolutions/480p',
			9 => 'Resolutions/PC_CGA_320x200',
			10 => 'Resolutions/PC_EGA_640x350',
			11 => 'Resolutions/PC_VGA_640x480',
			12 => 'Resolutions/PC_WVGA_768x480',
			13 => 'Resolutions/PC_SVGA_800x600',
			14 => 'Resolutions/PC_1024x768',
			15 => 'Resolutions/PC_1440x900',
			16 => 'Resolutions/PC_1600x1200',
			17 => 'Resolutions/PC_2560x1440',
			18 => 'Resolutions/PC_2560x1600',
			19 => 'Resolutions/Other_PC_Resolution',
			20 => 'Resolutions/Other_Console_Resolution',
		];

		$soundSystemsMap = [
			4 => 'Sound_Systems/Mono',
			5 => 'Sound_Systems/Stereo',
			6 => 'Sound_Systems/5.1',
			7 => 'Sound_Systems/7.1',
			8 => 'Sound_Systems/Dolby_Pro_Logic_II',
			9 => 'Sound_Systems/DTS',
		];

		preg_match_all( '/\{\{ReleaseSubobject([^}]+)\}\}/s', $releasesText, $releaseMatches );
		foreach ( $releaseMatches[1] as $releaseContent ) {
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

			if ( preg_match( '/\|Name=([^\n|]+)/', $releaseContent, $match ) ) {
				$release['name'] = trim( $match[1] );
			}

			if ( preg_match( '/\|Platform=([^\n|]+)/', $releaseContent, $match ) ) {
				$platform = trim( $match[1] );
				$platform = str_replace( 'Platforms/', '', $platform );
				$platform = str_replace( '_', ' ', $platform );
				$release['platform'] = $platform;
			}

			if ( preg_match( '/\|Region=([^\n|]+)/', $releaseContent, $match ) ) {
				$region = trim( $match[1] );
				$release['region'] = $regionMap[(int)$region] ?? $region;
			}

			if ( preg_match( '/\|ReleaseDate=([^\n|]+)/', $releaseContent, $match ) ) {
				$date = trim( $match[1] );
				if ( $date !== '' && $date !== 'None' ) {
					$release['releaseDate'] = $date;
				}
			}

			if ( preg_match( '/\|Rating=([^\n|]+)/', $releaseContent, $match ) ) {
				$rating = trim( $match[1] );
				if ( $rating !== '' ) {
					$rating = str_replace( 'Ratings/', '', $rating );
					$rating = str_replace( '_', ' ', $rating );
					$release['rating'] = $rating;
				}
			}

			if ( preg_match( '/\|Resolutions=([^\n|]+)/', $releaseContent, $match ) ) {
				$resolution = trim( $match[1] );
				if ( $resolution !== '' ) {
					$resolution = $resolutionsMap[(int)$resolution] ?? $resolution;
					$release['resolutions'] = $resolution;
				}
			}

			if ( preg_match( '/\|SoundSystems=([^\n|]+)/', $releaseContent, $match ) ) {
				$soundSystem = trim( $match[1] );
				if ( $soundSystem !== '' ) {
					$release['soundSystems'] = $soundSystemsMap[(int)$soundSystem] ?? $soundSystem;
				}
			}

			if ( preg_match( '/\|WidescreenSupport=([^\n|]+)/', $releaseContent, $match ) ) {
				$widescreen = trim( $match[1] );
				if ( $widescreen !== '' ) {
					$release['widescreenSupport'] = ucfirst( strtolower( $widescreen ) );
				}
			}

			$displayName = $release['platform'];
			if ( $displayName !== '' && $release['region'] !== '' ) {
				$displayName .= ' (' . $release['region'] . ')';
			} elseif ( $displayName === '' ) {
				$displayName = $release['name'];
			}
			$release['displayName'] = $displayName;

			$result['items'][] = $release;
		}

		$result['count'] = count( $result['items'] );
		return $result;
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

$services = MediaWikiServices::getInstance();
$wanCache = $services->getMainWANObjectCache();
$cacheTtl = 3600;
$wikiPageFactory = $services->getWikiPageFactory();
$page = $wikiPageFactory->newFromTitle( $title );
$latestRevisionId = $page ? (int)$page->getLatest() : 0;

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
	$content = $page ? $page->getContent() : null;

	if ( $content ) {
		$text = $content->getText();
		$legacyImageData = gbParseLegacyImageData( $text );

		// Extract wikitext (content after the template closing}})
		$wikitext = '';
		if ( preg_match( '/\}\}(.+)$/s', $text, $matches ) ) {
			$wikitext = trim( $matches[1] );
		}

		if ( $wikitext !== '' ) {
			$descCacheKey = $latestRevisionId > 0 ? $wanCache->makeKey( 'giantbomb-game-desc', $latestRevisionId ) : null;
			$descData = $descCacheKey ? $wanCache->get( $descCacheKey ) : null;
			if ( !is_array( $descData ) ) {
				$descHtml = '';
				try {
					$parser = $services->getParser();
					$parserOptions = ParserOptions::newFromAnon();
					$parserOutput = $parser->parse( $wikitext, $title, $parserOptions );
					$descHtml = $parserOutput->getText( [
						'allowTOC' => false,
						'enableSectionEditLinks' => false,
						'wrapperDivClass' => ''
					] );
				} catch ( \Throwable $e ) {
					error_log( 'Failed to parse wikitext: ' . $e->getMessage() );
					$descHtml = $wikitext;
				}
				$descData = [ 'html' => $descHtml ];
				if ( $descCacheKey ) {
					$wanCache->set( $descCacheKey, $descData, $cacheTtl );
				}
			}
			$gameData['description'] = $descData['html'] ?? '';
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
$releasesTitleObj = Title::newFromText( $pageTitle . '/Releases' );
$dlcTitleObj = Title::newFromText( $pageTitle . '/DLC' );
$creditsTitleObj = Title::newFromText( $pageTitle . '/Credits' );
$gameData['hasReleases'] = $releasesTitleObj && $releasesTitleObj->exists();
$gameData['hasDLC'] = $dlcTitleObj && $dlcTitleObj->exists();
$gameData['hasCredits'] = $creditsTitleObj && $creditsTitleObj->exists();

	// Get images linked from this page
$gameData['images'] = [];
try {
	$pageId = $title->getArticleID();
	if ( $pageId ) {
		$imageCacheKey = ( $latestRevisionId > 0 )
			? $wanCache->makeKey( 'giantbomb-game-images', $pageId, $latestRevisionId )
			: null;
		$cachedImages = $imageCacheKey ? $wanCache->get( $imageCacheKey ) : null;
		if ( is_array( $cachedImages ) ) {
			$gameData['images'] = $cachedImages;
		} else {
			$dbLoadBalancer = $services->getDBLoadBalancer();
			$db = $dbLoadBalancer->getConnection( \DB_REPLICA );
			$images = [];
			$result = $db->select(
				'imagelinks',
				[ 'il_to' ],
				[ 'il_from' => $pageId ],
				__METHOD__
			);
			foreach ( $result as $row ) {
				$images[] = [
					'url' => $row->il_to,
					'caption' => basename( $row->il_to ),
					'width' => 0,
					'height' => 0,
				];
			}
			if ( $imageCacheKey ) {
				$wanCache->set( $imageCacheKey, $images, $cacheTtl );
			}
			$gameData['images'] = $images;
		}
	}
} catch ( \Throwable $e ) {
	error_log( 'Failed to fetch game images: ' . $e->getMessage() );
}

$gameData['imagesCount'] = count( $gameData['images'] );

$gameData['releasesCount'] = 0;
$gameData['releases'] = [];

if ( $gameData['hasReleases'] && $releasesTitleObj ) {
	try {
		$releasesPage = $wikiPageFactory->newFromTitle( $releasesTitleObj );
		$releasesContent = $releasesPage ? $releasesPage->getContent() : null;
		if ( $releasesContent ) {
			$releasesText = $releasesContent->getText();
			$releaseRevisionId = (int)$releasesPage->getLatest();
			$releaseCacheKey = $releaseRevisionId > 0
				? $wanCache->makeKey( 'giantbomb-game-releases', $releaseRevisionId )
				: null;
			$releaseData = $releaseCacheKey ? $wanCache->get( $releaseCacheKey ) : null;
			if ( !is_array( $releaseData ) ) {
				$releaseData = gbExtractReleaseData( $releasesText );
				if ( $releaseCacheKey ) {
					$wanCache->set( $releaseCacheKey, $releaseData, $cacheTtl );
				}
			}
			$gameData['releasesCount'] = $releaseData['count'] ?? 0;
			$gameData['releases'] = $releaseData['items'] ?? [];
		}
	} catch ( Exception $e ) {
		error_log( 'Failed to fetch release details: ' . $e->getMessage() );
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

// Apply rich metadata for previews and SEO
static $gameMetaApplied = false;
if ( !$gameMetaApplied ) {
	$gameMetaApplied = true;
	$out = $this->getSkin()->getOutput();
	$metaTitle = $gameData['name'] !== '' ? $gameData['name'] . ' - Giant Bomb Wiki' : 'Giant Bomb Wiki';
	$metaDescription = gbSanitizeMetaText( $gameData['deck'] ?? '' );
	if ( $metaDescription === '' ) {
		$metaDescription = gbSanitizeMetaText( $gameData['description'] ?? '' );
	}
	if ( $metaDescription === '' && $gameData['name'] !== '' ) {
		$metaDescription = 'Explore the Giant Bomb wiki entry for ' . $gameData['name'] . '.';
	}
	$metaImage = $gameData['image'] !== '' ? $gameData['image'] : ( $gameData['backgroundImage'] !== '' ? $gameData['backgroundImage'] : null );
	$canonicalUrl = rtrim( GB_PUBLIC_WIKI_HOST, '/' ) . $gameData['url'];

	if ( $gameData['name'] !== '' ) {
		$out->setPageTitle( $gameData['name'] );
	}
	$out->setHTMLTitle( $metaTitle );
	if ( $metaDescription !== '' ) {
		$out->addMeta( 'description', $metaDescription );
	}
	$out->setCanonicalUrl( $canonicalUrl );

	$ogTags = [
		'og:title' => $metaTitle,
		'og:description' => $metaDescription,
		'og:url' => $canonicalUrl,
		'og:site_name' => 'Giant Bomb Wiki',
		'og:type' => 'article',
		'og:locale' => 'en_US',
	];
	if ( $metaImage ) {
		$ogTags['og:image'] = $metaImage;
	}
	foreach ( $ogTags as $property => $content ) {
		if ( $content === '' || $content === null ) {
			continue;
		}
		$out->addHeadItem(
			'meta-' . str_replace( ':', '-', $property ),
			gbBuildMetaTag( [ 'property' => $property, 'content' => $content ] )
		);
	}

	$twitterTags = [
		'twitter:card' => $metaImage ? 'summary_large_image' : 'summary',
		'twitter:title' => $metaTitle,
		'twitter:description' => $metaDescription,
		'twitter:site' => '@giantbomb',
	];
	if ( $metaImage ) {
		$twitterTags['twitter:image'] = $metaImage;
		$twitterTags['twitter:image:alt'] = $gameData['name'] !== '' ? $gameData['name'] . ' cover art' : 'Giant Bomb cover art';
	}
	foreach ( $twitterTags as $name => $content ) {
		if ( $content === '' || $content === null ) {
			continue;
		}
		$out->addHeadItem(
			'meta-' . str_replace( [ ':', '/' ], '-', $name ),
			gbBuildMetaTag( [ 'name' => $name, 'content' => $content ] )
		);
	}

	$schema = [
		'@context' => 'https://schema.org',
		'@type' => 'VideoGame',
		'name' => $gameData['name'],
		'url' => $canonicalUrl,
		'description' => $metaDescription,
		'identifier' => $gameData['guid'] ?? null,
	];
	if ( $metaImage ) {
		$schema['image'] = $metaImage;
	}
	if ( !empty( $gameData['releaseDate'] ) ) {
		$schema['datePublished'] = $gameData['releaseDate'];
	}
	if ( !empty( $gameData['genres'] ) ) {
		$schema['genre'] = array_values( $gameData['genres'] );
	}
	if ( !empty( $gameData['platforms'] ) ) {
		$schema['gamePlatform'] = array_values( $gameData['platforms'] );
	}
	if ( !empty( $gameData['developers'] ) ) {
		$schema['developer'] = array_map(
			static fn ( $name ) => [ '@type' => 'Organization', 'name' => $name ],
			array_slice( array_values( $gameData['developers'] ), 0, 3 )
		);
	}
	if ( !empty( $gameData['publishers'] ) ) {
		$schema['publisher'] = array_map(
			static fn ( $name ) => [ '@type' => 'Organization', 'name' => $name ],
			array_slice( array_values( $gameData['publishers'] ), 0, 3 )
		);
	}
	if ( !empty( $gameData['franchise'] ) ) {
		$schema['isPartOf'] = [
			'@type' => 'CreativeWorkSeries',
			'name' => $gameData['franchise'],
		];
	}
	$schema = array_filter(
		$schema,
		static function ( $value ) {
			if ( $value === null ) {
				return false;
			}
			if ( is_string( $value ) && trim( $value ) === '' ) {
				return false;
			}
			if ( is_array( $value ) && empty( $value ) ) {
				return false;
			}
			return true;
		}
	);
	$schemaJson = json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	if ( $schemaJson !== false ) {
		$out->addHeadItem(
			'structured-data-video-game',
			gbBuildJsonLdScript( $schemaJson )
		);
	}
}

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
$templateParser = new \MediaWiki\Html\TemplateParser( $templateDir );
echo $templateParser->processTemplate('game-page', $data);
