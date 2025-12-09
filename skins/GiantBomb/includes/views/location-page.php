<?php

use GiantBomb\Skin\Helpers\PageHelper;
use MediaWiki\MediaWikiServices;

require_once __DIR__ . '/../helpers/PageHelper.php';

$title = $this->getSkin()->getTitle();
$pageTitle = $title->getText();
$pageTitleDB = $title->getDBkey();

$services = MediaWikiServices::getInstance();
$wanCache = $services->getMainWANObjectCache();
$cacheTtl = 3600;
$wikiPageFactory = $services->getWikiPageFactory();
$page = $wikiPageFactory->newFromTitle( $title );
$latestRevisionId = $page ? (int)$page->getLatest() : 0;

$locationData = [
	'name' => PageHelper::cleanPageName( $pageTitle, 'Locations' ),
	'url' => '/wiki/' . $pageTitleDB,
	'image' => '',
	'backgroundImage' => '',
	'deck' => '',
	'description' => '',
	'guid' => '',
	'aliases' => [],
	'aliasesDisplay' => '',
	'locationType' => '',
	'planet' => '',
	'country' => '',
	'state' => '',
	'city' => '',
	'population' => '',
	'stats' => [],
	'hasStats' => false,
	'relations' => [
		'characters' => [],
		'concepts' => [],
		'objects' => [],
		'locations' => [],
		'games' => [],
		'franchises' => [],
	],
];

try {
	$content = $page ? $page->getContent() : null;

	if ( $content ) {
		$text = $content->getText();
		$infoboxFields = PageHelper::extractInfoboxFields( $text );
		$legacyImageData = PageHelper::parseLegacyImageData( $text );
		$wikitext = PageHelper::extractWikitext( $text );

		$locationData['description'] = PageHelper::parseDescription(
			$wikitext, $title, $wanCache, $latestRevisionId, 'giantbomb-location-desc', $cacheTtl
		);

		$locationData['name'] = PageHelper::getFieldValue( $infoboxFields, [ 'name' ] ) ?: $locationData['name'];
		$locationData['deck'] = PageHelper::getFieldValue( $infoboxFields, [ 'deck' ] );
		$locationData['image'] = PageHelper::getFieldValue( $infoboxFields, [ 'image', 'infoboximage' ] );
		$locationData['guid'] = PageHelper::getFieldValue( $infoboxFields, [ 'guid', 'id' ] );
		$locationData['locationType'] = PageHelper::getFieldValue( $infoboxFields, [ 'type', 'locationtype' ] );
		$locationData['planet'] = PageHelper::getFieldValue( $infoboxFields, [ 'planet' ] );
		$locationData['country'] = PageHelper::getFieldValue( $infoboxFields, [ 'country' ] );
		$locationData['state'] = PageHelper::getFieldValue( $infoboxFields, [ 'state', 'province', 'region' ] );
		$locationData['city'] = PageHelper::getFieldValue( $infoboxFields, [ 'city' ] );
		$locationData['population'] = PageHelper::getFieldValue( $infoboxFields, [ 'population' ] );

		$rawAliases = PageHelper::getFieldValue( $infoboxFields, [ 'aliases', 'alias' ] );
		if ( $rawAliases === '' ) {
			$rawAliases = PageHelper::parseTemplateField( $text, 'Aliases' );
		}
		$locationData['aliases'] = PageHelper::parseAliases( $rawAliases );
		$locationData['aliasesDisplay'] = implode( ', ', $locationData['aliases'] );

		$listFields = [
			'characters' => 'Characters',
			'concepts' => 'Concepts',
			'objects' => 'Objects',
			'locations' => 'Locations',
			'games' => 'Games',
			'franchises' => 'Franchises',
		];
		$prefixes = [ 'Games', 'Characters', 'Concepts', 'Locations', 'Objects', 'People', 'Franchises', 'Companies' ];

		foreach ( $listFields as $key => $field ) {
			$rawList = PageHelper::getFieldValue( $infoboxFields, [ strtolower( $field ), $field ] );
			if ( $rawList === '' ) {
				$rawList = PageHelper::parseTemplateField( $text, $field );
			}
			$locationData['relations'][$key] = PageHelper::parseListField( $rawList, $prefixes );
		}

		PageHelper::resolveImages( $locationData, $legacyImageData );
	}
} catch ( \Throwable $e ) {
	error_log( 'Location page error: ' . $e->getMessage() );
}

$stats = [];
if ( $locationData['locationType'] !== '' ) {
	$stats[] = [ 'label' => 'Type', 'value' => $locationData['locationType'] ];
}
$locationParts = array_filter( [
	$locationData['city'],
	$locationData['state'],
	$locationData['country'],
	$locationData['planet'],
] );
if ( !empty( $locationParts ) ) {
	$stats[] = [ 'label' => 'Location', 'value' => implode( ', ', array_unique( $locationParts ) ) ];
}
if ( $locationData['population'] !== '' ) {
	$stats[] = [ 'label' => 'Population', 'value' => $locationData['population'] ];
}
$locationData['stats'] = $stats;
$locationData['hasStats'] = !empty( $stats );

$metaTitle = $locationData['name'] !== ''
	? $locationData['name'] . ' location - Giant Bomb Wiki'
	: 'Giant Bomb Wiki';
$metaDescription = PageHelper::sanitizeMetaText( $locationData['deck'] ?? '' );
if ( $metaDescription === '' ) {
	$metaDescription = PageHelper::sanitizeMetaText( $locationData['description'] ?? '' );
}
if ( $metaDescription === '' && $locationData['name'] !== '' ) {
	$metaDescription = $locationData['name'] . ' on Giant Bomb.';
}
$metaImage = PageHelper::getMetaImage( $locationData['image'], $locationData['backgroundImage'] );
$canonicalUrl = rtrim( PageHelper::PUBLIC_WIKI_HOST, '/' ) . $locationData['url'];

$out = $this->getSkin()->getOutput();
if ( $locationData['name'] !== '' ) {
	$out->setPageTitle( $locationData['name'] );
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
	'og:type' => 'place',
	'og:locale' => 'en_US',
], $metaImage );

PageHelper::addTwitterTags( $out, [
	'twitter:card' => $metaImage ? 'summary_large_image' : 'summary',
	'twitter:title' => $metaTitle,
	'twitter:description' => $metaDescription,
	'twitter:site' => '@giantbomb',
], $metaImage, $locationData['name'] !== '' ? $locationData['name'] . ' location art' : 'Giant Bomb location cover art' );

$schema = [
	'@context' => 'https://schema.org',
	'@type' => 'Place',
	'name' => $locationData['name'],
	'url' => $canonicalUrl,
	'description' => $metaDescription,
];
if ( $locationData['guid'] !== '' ) {
	$schema['identifier'] = $locationData['guid'];
}
if ( $metaImage ) {
	$schema['image'] = $metaImage;
}
if ( !empty( $locationParts ) ) {
	$schema['address'] = implode( ', ', array_unique( $locationParts ) );
}
if ( $locationData['population'] !== '' ) {
	$schema['population'] = $locationData['population'];
}
if ( !empty( $locationData['relations']['games'] ) ) {
	$schema['subjectOf'] = array_map(
		static fn ( $name ) => [ '@type' => 'VideoGame', 'name' => $name ],
		array_slice( $locationData['relations']['games'], 0, 5 )
	);
}
PageHelper::addStructuredData( $out, $schema, 'location' );

$templateDir = realpath( __DIR__ . '/../templates' );
$templateParser = new \MediaWiki\Html\TemplateParser( $templateDir );
$data = [ 'location' => $locationData ];

echo $templateParser->processTemplate( 'location-page', $data );
