<?php

use GiantBomb\Skin\Helpers\PageHelper;
use MediaWiki\MediaWikiServices;

$title = $this->getSkin()->getTitle();
$pageTitle = $title->getText();
$pageTitleDB = $title->getDBkey();

$services = MediaWikiServices::getInstance();
$wanCache = $services->getMainWANObjectCache();
$cacheTtl = 3600;
$wikiPageFactory = $services->getWikiPageFactory();
$page = $wikiPageFactory->newFromTitle( $title );
$latestRevisionId = $page ? (int)$page->getLatest() : 0;

$gameData = [
	'name' => PageHelper::cleanPageName( $pageTitle, 'Games' ),
	'url' => '/wiki/' . $pageTitleDB,
	'image' => '',
	'backgroundImage' => '',
	'deck' => '',
	'description' => '',
	'releaseDate' => '',
	'releaseDateType' => '',
	'aliases' => '',
	'guid' => '',
	'developers' => [],
	'publishers' => [],
	'platforms' => [],
	'genres' => [],
	'themes' => [],
	'franchise' => '',
	'characters' => [],
	'concepts' => [],
	'locations' => [],
	'objects' => [],
	'similarGames' => [],
	'hasReleases' => false,
	'hasDLC' => false,
	'hasCredits' => false,
	'features' => [],
	'multiplayer' => [],
	'reviewScore' => 0,
	'reviewCount' => 0,
	'reviewDistribution' => [],
];

try {
	$content = $page ? $page->getContent() : null;

	if ( $content ) {
		$text = $content->getText();
		$legacyImageData = PageHelper::parseLegacyImageData( $text );

		$wikitext = PageHelper::extractWikitext( $text );
		if ( $wikitext !== '' ) {
			$gameData['description'] = PageHelper::parseDescription(
				$wikitext, $title, $wanCache, $latestRevisionId, 'giantbomb-game-desc', $cacheTtl
			);
		}

		$gameData['name'] = PageHelper::parseTemplateField( $text, 'Name' ) ?: $gameData['name'];
		$gameData['deck'] = PageHelper::parseTemplateField( $text, 'Deck' );
		$gameData['image'] = PageHelper::parseTemplateField( $text, 'Image' );
		$gameData['releaseDate'] = PageHelper::parseTemplateField( $text, 'ReleaseDate' );
		$gameData['releaseDateType'] = PageHelper::parseTemplateField( $text, 'ReleaseDateType' );
		$gameData['aliases'] = PageHelper::parseTemplateField( $text, 'Aliases' );
		$gameData['guid'] = PageHelper::parseTemplateField( $text, 'Guid' );

		PageHelper::resolveImages( $gameData, $legacyImageData );

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

		foreach ( $arrayFields as $templateField => $dataField ) {
			$raw = PageHelper::parseTemplateField( $text, $templateField );
			if ( $raw !== '' ) {
				$gameData[$dataField] = PageHelper::parseListField( $raw, [
					'Developers', 'Publishers', 'Platforms', 'Genres', 'Themes',
					'Characters', 'Concepts', 'Locations', 'Objects', 'Games'
				] );
			}
		}

		$franchiseRaw = PageHelper::parseTemplateField( $text, 'Franchise' );
		if ( $franchiseRaw !== '' ) {
			$gameData['franchise'] = str_replace( '_', ' ', preg_replace( '#^Franchises/#', '', $franchiseRaw ) );
		}

		$allFeatures = [
			'Camera Support', 'Voice control', 'Motion control',
			'Driving wheel (native)', 'Flightstick (native)', 'PC gamepad (native)', 'Head tracking (native)',
		];
		$enabledFeatures = PageHelper::parseListField( PageHelper::parseTemplateField( $text, 'Features' ) );
		foreach ( $allFeatures as $feature ) {
			$gameData['features'][] = [ 'name' => $feature, 'enabled' => in_array( $feature, $enabledFeatures ) ];
		}

		$allMultiplayer = [
			'Local co-op', 'Online co-op', 'LAN competitive', 'Local split screen',
			'Voice control', 'Driving wheel (native)', 'PC gameload (native)',
		];
		$enabledMultiplayer = PageHelper::parseListField( PageHelper::parseTemplateField( $text, 'Multiplayer' ) );
		foreach ( $allMultiplayer as $option ) {
			$gameData['multiplayer'][] = [ 'name' => $option, 'enabled' => in_array( $option, $enabledMultiplayer ) ];
		}

		// Hardcoded review data for testing
		$gameData['reviewScore'] = number_format( 4.0, 1, '.', '' );
		$gameData['reviewCount'] = 4;
		$reviewCounts = [ '5' => 2, '4' => 1, '3' => 0, '2' => 0, '1' => 1 ];
		foreach ( $reviewCounts as $star => $count ) {
			$percentage = $gameData['reviewCount'] > 0 ? ( $count / $gameData['reviewCount'] ) * 100 : 0;
			$gameData['reviewDistribution'][$star] = [ 'count' => $count, 'percentage' => round( $percentage, 1 ) ];
		}
		$gameData['reviewStars'] = [];
		for ( $i = 1; $i <= 5; $i++ ) {
			$gameData['reviewStars'][] = [ 'filled' => $i <= floor( $gameData['reviewScore'] ) ];
		}
	}

	$releasesTitleObj = Title::newFromText( $pageTitle . '/Releases' );
	$dlcTitleObj = Title::newFromText( $pageTitle . '/DLC' );
	$creditsTitleObj = Title::newFromText( $pageTitle . '/Credits' );
	$gameData['hasReleases'] = $releasesTitleObj && $releasesTitleObj->exists();
	$gameData['hasDLC'] = $dlcTitleObj && $dlcTitleObj->exists();
	$gameData['hasCredits'] = $creditsTitleObj && $creditsTitleObj->exists();

	$gameData['images'] = [];
	$pageId = $title->getArticleID();
	if ( $pageId ) {
		$imageCacheKey = $latestRevisionId > 0
			? $wanCache->makeKey( 'giantbomb-game-images', $pageId, $latestRevisionId )
			: null;
		$cachedImages = $imageCacheKey ? $wanCache->get( $imageCacheKey ) : null;
		if ( is_array( $cachedImages ) ) {
			$gameData['images'] = $cachedImages;
		} else {
			$db = $services->getDBLoadBalancer()->getConnection( \DB_REPLICA );
			$result = $db->select( 'imagelinks', [ 'il_to' ], [ 'il_from' => $pageId ], __METHOD__ );
			$images = [];
			foreach ( $result as $row ) {
				$images[] = [ 'url' => $row->il_to, 'caption' => basename( $row->il_to ), 'width' => 0, 'height' => 0 ];
			}
			if ( $imageCacheKey ) {
				$wanCache->set( $imageCacheKey, $images, $cacheTtl );
			}
			$gameData['images'] = $images;
		}
	}
	$gameData['imagesCount'] = count( $gameData['images'] );

	$gameData['releasesCount'] = 0;
	$gameData['releases'] = [];
	if ( $gameData['hasReleases'] && $releasesTitleObj ) {
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
				$releaseData = PageHelper::extractReleaseData( $releasesText );
				if ( $releaseCacheKey ) {
					$wanCache->set( $releaseCacheKey, $releaseData, $cacheTtl );
				}
			}
			$gameData['releasesCount'] = $releaseData['count'] ?? 0;
			$gameData['releases'] = $releaseData['items'] ?? [];
		}
	}

	$gameData['hasReleasesStr'] = $gameData['hasReleases'] ? 'true' : 'false';
	$gameData['hasDLCStr'] = $gameData['hasDLC'] ? 'true' : 'false';

} catch ( Exception $e ) {
	error_log( 'Game page error: ' . $e->getMessage() );
}

$vueData = [
	'platformsStr' => implode( ',', $gameData['platforms'] ),
	'genresStr' => implode( ',', $gameData['genres'] ),
	'themesStr' => implode( ',', $gameData['themes'] ),
	'charactersStr' => implode( ',', $gameData['characters'] ),
	'conceptsStr' => implode( ',', $gameData['concepts'] ),
	'locationsStr' => implode( ',', $gameData['locations'] ),
	'objectsStr' => implode( ',', $gameData['objects'] ),
	'similarGamesStr' => implode( ',', $gameData['similarGames'] ),
	'releasesJson' => htmlspecialchars( json_encode( $gameData['releases'] ), ENT_QUOTES, 'UTF-8' ),
];

static $gameMetaApplied = false;
if ( !$gameMetaApplied ) {
	$gameMetaApplied = true;
	$out = $this->getSkin()->getOutput();
	$metaTitle = $gameData['name'] !== '' ? $gameData['name'] . ' - Giant Bomb Wiki' : 'Giant Bomb Wiki';
	$metaDescription = PageHelper::sanitizeMetaText( $gameData['deck'] ?? '' );
	if ( $metaDescription === '' ) {
		$metaDescription = PageHelper::sanitizeMetaText( $gameData['description'] ?? '' );
	}
	if ( $metaDescription === '' && $gameData['name'] !== '' ) {
		$metaDescription = $gameData['name'] . ' on Giant Bomb.';
	}
	$metaImage = PageHelper::getMetaImage( $gameData['image'], $gameData['backgroundImage'] );
	$canonicalUrl = rtrim( PageHelper::PUBLIC_WIKI_HOST, '/' ) . $gameData['url'];

	if ( $gameData['name'] !== '' ) {
		$out->setPageTitle( $gameData['name'] );
	}
	$out->setHTMLTitle( $metaTitle );
	if ( $metaDescription !== '' ) {
		$out->addMeta( 'description', $metaDescription );
	}
	$out->setCanonicalUrl( $canonicalUrl );

	PageHelper::addOpenGraphTags( $out, [
		'og:title' => $metaTitle,
		'og:description' => $metaDescription,
		'og:url' => $canonicalUrl,
		'og:site_name' => 'Giant Bomb Wiki',
		'og:type' => 'article',
		'og:locale' => 'en_US',
	], $metaImage );

	PageHelper::addTwitterTags( $out, [
		'twitter:card' => $metaImage ? 'summary_large_image' : 'summary',
		'twitter:title' => $metaTitle,
		'twitter:description' => $metaDescription,
		'twitter:site' => '@giantbomb',
	], $metaImage, $gameData['name'] !== '' ? $gameData['name'] . ' cover art' : '' );

	$schema = [
		'@context' => 'https://schema.org',
		'@type' => 'VideoGame',
		'name' => $gameData['name'],
		'url' => $canonicalUrl,
		'description' => $metaDescription,
		'identifier' => $gameData['guid'] ?? null,
		'image' => $metaImage,
		'datePublished' => $gameData['releaseDate'] ?: null,
		'genre' => $gameData['genres'] ?: null,
		'gamePlatform' => $gameData['platforms'] ?: null,
	];
	if ( !empty( $gameData['developers'] ) ) {
		$schema['developer'] = array_map(
			static fn ( $name ) => [ '@type' => 'Organization', 'name' => $name ],
			array_slice( $gameData['developers'], 0, 3 )
		);
	}
	if ( !empty( $gameData['publishers'] ) ) {
		$schema['publisher'] = array_map(
			static fn ( $name ) => [ '@type' => 'Organization', 'name' => $name ],
			array_slice( $gameData['publishers'], 0, 3 )
		);
	}
	if ( !empty( $gameData['franchise'] ) ) {
		$schema['isPartOf'] = [ '@type' => 'CreativeWorkSeries', 'name' => $gameData['franchise'] ];
	}
	PageHelper::addStructuredData( $out, $schema, 'video-game' );
}

$data = [
	'game' => $gameData,
	'vue' => $vueData,
	'hasBasicInfo' => !empty( $gameData['deck'] ) || !empty( $gameData['releaseDate'] ) || !empty( $gameData['aliases'] ),
	'hasCompanies' => !empty( $gameData['developers'] ) || !empty( $gameData['publishers'] ),
	'hasClassification' => !empty( $gameData['platforms'] ) || !empty( $gameData['genres'] ) || !empty( $gameData['themes'] ) || !empty( $gameData['franchise'] ),
	'hasRelatedContent' => !empty( $gameData['characters'] ) || !empty( $gameData['concepts'] ) || !empty( $gameData['locations'] ) || !empty( $gameData['objects'] ) || !empty( $gameData['similarGames'] ),
	'hasSubPages' => $gameData['hasReleases'] || $gameData['hasDLC'] || $gameData['hasCredits'],
];

$templateDir = realpath( __DIR__ . '/../templates' );
$templateParser = new \MediaWiki\Html\TemplateParser( $templateDir );
echo $templateParser->processTemplate( 'game-page', $data );
