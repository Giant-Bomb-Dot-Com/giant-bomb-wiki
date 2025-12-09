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

$accessoryData = [
	'name' => PageHelper::cleanPageName( $pageTitle, 'Accessories' ),
	'url' => '/wiki/' . $pageTitleDB,
	'image' => '',
	'backgroundImage' => '',
	'deck' => '',
	'description' => '',
	'guid' => '',
	'aliases' => [],
	'aliasesDisplay' => '',
	'manufacturer' => '',
	'developers' => '',
	'releaseDate' => '',
	'platforms' => [],
	'connectivity' => '',
	'price' => '',
	'stats' => [],
	'hasStats' => false,
	'relations' => [
		'games' => [],
		'franchises' => [],
		'companies' => [],
		'objects' => [],
	],
];

try {
	$content = $page ? $page->getContent() : null;

	if ( $content ) {
		$text = $content->getText();
		$infoboxFields = PageHelper::extractInfoboxFields( $text );
		$legacyImageData = PageHelper::parseLegacyImageData( $text );
		$wikitext = PageHelper::extractWikitext( $text );

		$accessoryData['description'] = PageHelper::parseDescription(
			$wikitext, $title, $wanCache, $latestRevisionId, 'giantbomb-accessory-desc', $cacheTtl
		);

		$accessoryData['name'] = PageHelper::getFieldValue( $infoboxFields, [ 'name' ] ) ?: $accessoryData['name'];
		$accessoryData['deck'] = PageHelper::getFieldValue( $infoboxFields, [ 'deck' ] );
		$accessoryData['image'] = PageHelper::getFieldValue( $infoboxFields, [ 'image', 'infoboximage' ] );
		$accessoryData['guid'] = PageHelper::getFieldValue( $infoboxFields, [ 'guid', 'id' ] );
		$accessoryData['manufacturer'] = PageHelper::getFieldValue( $infoboxFields, [ 'manufacturer', 'manufacturers' ] );
		$accessoryData['developers'] = PageHelper::getFieldValue( $infoboxFields, [ 'developer', 'developers' ] );
		$accessoryData['releaseDate'] = PageHelper::getFieldValue( $infoboxFields, [ 'release', 'releasedate', 'release date' ] );
		$accessoryData['connectivity'] = PageHelper::getFieldValue( $infoboxFields, [ 'connectivity', 'connection' ] );
		$accessoryData['price'] = PageHelper::getFieldValue( $infoboxFields, [ 'price', 'msrp' ] );

		$rawAliases = PageHelper::getFieldValue( $infoboxFields, [ 'aliases', 'alias' ] );
		if ( $rawAliases === '' ) {
			$rawAliases = PageHelper::parseTemplateField( $text, 'Aliases' );
		}
		$accessoryData['aliases'] = PageHelper::parseAliases( $rawAliases );
		$accessoryData['aliasesDisplay'] = implode( ', ', $accessoryData['aliases'] );

		$rawPlatforms = PageHelper::getFieldValue( $infoboxFields, [ 'platforms', 'platform' ] );
		$accessoryData['platforms'] = PageHelper::parseListField( $rawPlatforms, [ 'Platforms', 'Games' ] );

		$listFields = [
			'games' => 'Games',
			'franchises' => 'Franchises',
			'companies' => 'Companies',
			'objects' => 'Objects',
		];
		$prefixes = [ 'Games', 'Franchises', 'Companies', 'Objects', 'Platforms' ];

		foreach ( $listFields as $key => $field ) {
			$rawList = PageHelper::getFieldValue( $infoboxFields, [ strtolower( $field ), $field ] );
			if ( $rawList === '' ) {
				$rawList = PageHelper::parseTemplateField( $text, $field );
			}
			$accessoryData['relations'][$key] = PageHelper::parseListField( $rawList, $prefixes );
		}

		PageHelper::resolveImages( $accessoryData, $legacyImageData );
	}
} catch ( \Throwable $e ) {
	error_log( 'Accessory page error: ' . $e->getMessage() );
}

$stats = [];
if ( $accessoryData['manufacturer'] !== '' ) {
	$stats[] = [ 'label' => 'Manufacturer', 'value' => $accessoryData['manufacturer'] ];
}
if ( $accessoryData['developers'] !== '' ) {
	$stats[] = [ 'label' => 'Developer', 'value' => $accessoryData['developers'] ];
}
if ( $accessoryData['platforms'] !== [] ) {
	$stats[] = [ 'label' => 'Platforms', 'value' => implode( ', ', $accessoryData['platforms'] ) ];
}
if ( $accessoryData['releaseDate'] !== '' ) {
	$stats[] = [ 'label' => 'Release date', 'value' => $accessoryData['releaseDate'] ];
}
if ( $accessoryData['connectivity'] !== '' ) {
	$stats[] = [ 'label' => 'Connectivity', 'value' => $accessoryData['connectivity'] ];
}
if ( $accessoryData['price'] !== '' ) {
	$stats[] = [ 'label' => 'Launch price', 'value' => $accessoryData['price'] ];
}
$accessoryData['stats'] = $stats;
$accessoryData['hasStats'] = !empty( $stats );

$metaTitle = $accessoryData['name'] !== ''
	? $accessoryData['name'] . ' accessory - Giant Bomb Wiki'
	: 'Giant Bomb Wiki';
$metaDescription = PageHelper::sanitizeMetaText( $accessoryData['deck'] ?? '' );
if ( $metaDescription === '' ) {
	$metaDescription = PageHelper::sanitizeMetaText( $accessoryData['description'] ?? '' );
}
if ( $metaDescription === '' && $accessoryData['name'] !== '' ) {
	$metaDescription = $accessoryData['name'] . ' on Giant Bomb.';
}
$metaImage = PageHelper::getMetaImage( $accessoryData['image'], $accessoryData['backgroundImage'] );
$canonicalUrl = rtrim( PageHelper::PUBLIC_WIKI_HOST, '/' ) . $accessoryData['url'];

$out = $this->getSkin()->getOutput();
if ( $accessoryData['name'] !== '' ) {
	$out->setPageTitle( $accessoryData['name'] );
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
	'og:type' => 'product',
	'og:locale' => 'en_US',
], $metaImage );

PageHelper::addTwitterTags( $out, [
	'twitter:card' => $metaImage ? 'summary_large_image' : 'summary',
	'twitter:title' => $metaTitle,
	'twitter:description' => $metaDescription,
	'twitter:site' => '@giantbomb',
], $metaImage, $accessoryData['name'] !== '' ? $accessoryData['name'] . ' accessory image' : 'Giant Bomb accessory cover art' );

$schema = [
	'@context' => 'https://schema.org',
	'@type' => 'Product',
	'name' => $accessoryData['name'],
	'url' => $canonicalUrl,
	'description' => $metaDescription,
];
if ( $accessoryData['guid'] !== '' ) {
	$schema['identifier'] = $accessoryData['guid'];
}
if ( $metaImage ) {
	$schema['image'] = $metaImage;
}
if ( $accessoryData['manufacturer'] !== '' ) {
	$schema['manufacturer'] = [ '@type' => 'Organization', 'name' => $accessoryData['manufacturer'] ];
}
if ( $accessoryData['developers'] !== '' ) {
	$schema['brand'] = [ '@type' => 'Organization', 'name' => $accessoryData['developers'] ];
}
if ( $accessoryData['releaseDate'] !== '' ) {
	$schema['releaseDate'] = $accessoryData['releaseDate'];
}
if ( $accessoryData['price'] !== '' ) {
	$schema['offers'] = [
		'@type' => 'Offer',
		'price' => preg_replace( '/[^\d\.]/', '', $accessoryData['price'] ),
		'priceCurrency' => 'USD',
		'url' => $canonicalUrl,
	];
}
if ( $accessoryData['platforms'] !== [] ) {
	$schema['isAccessoryOrSparePartFor'] = array_map(
		static fn ( $platform ) => [ '@type' => 'Product', 'name' => $platform ],
		array_slice( $accessoryData['platforms'], 0, 5 )
	);
}
PageHelper::addStructuredData( $out, $schema, 'accessory' );

$templateDir = realpath( __DIR__ . '/../templates' );
$templateParser = new \MediaWiki\Html\TemplateParser( $templateDir );
$data = [ 'accessory' => $accessoryData ];

echo $templateParser->processTemplate( 'accessory-page', $data );
