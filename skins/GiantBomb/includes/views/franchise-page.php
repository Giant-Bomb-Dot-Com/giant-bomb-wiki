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

$franchiseData = [
	'name' => PageHelper::cleanPageName( $pageTitle, 'Franchises' ),
	'url' => '/wiki/' . $pageTitleDB,
	'image' => '',
	'backgroundImage' => '',
	'deck' => '',
	'description' => '',
	'guid' => '',
	'aliases' => [],
	'aliasesDisplay' => '',
	'relations' => [
		'games' => [],
		'characters' => [],
		'concepts' => [],
		'locations' => [],
		'objects' => [],
		'people' => [],
	],
];

try {
	$content = $page ? $page->getContent() : null;

	if ( $content ) {
		$text = $content->getText();
		$legacyImageData = PageHelper::parseLegacyImageData( $text );
		$wikitext = PageHelper::extractWikitext( $text );

		$franchiseData['description'] = PageHelper::parseDescription(
			$wikitext, $title, $wanCache, $latestRevisionId, 'giantbomb-franchise-desc', $cacheTtl
		);

		$singleFields = [
			'name' => 'Name',
			'deck' => 'Deck',
			'image' => 'Image',
			'guid' => 'Guid',
		];

		foreach ( $singleFields as $key => $field ) {
			$value = PageHelper::parseTemplateField( $text, $field );
			if ( $value !== '' ) {
				$franchiseData[$key] = $value;
			}
		}

		$rawAliases = PageHelper::parseTemplateField( $text, 'Aliases' );
		$franchiseData['aliases'] = PageHelper::parseAliases( $rawAliases );
		$franchiseData['aliasesDisplay'] = implode( ', ', $franchiseData['aliases'] );

		$listFields = [
			'games' => 'Games',
			'characters' => 'Characters',
			'concepts' => 'Concepts',
			'locations' => 'Locations',
			'objects' => 'Objects',
			'people' => 'People',
		];
		$prefixes = [ 'Games', 'Characters', 'Concepts', 'Locations', 'Objects', 'People' ];

		foreach ( $listFields as $key => $field ) {
			$rawList = PageHelper::parseTemplateField( $text, $field );
			$franchiseData['relations'][$key] = PageHelper::parseListField( $rawList, $prefixes );
		}

		PageHelper::resolveImages( $franchiseData, $legacyImageData );
	}
} catch ( \Throwable $e ) {
	error_log( 'Franchise page error: ' . $e->getMessage() );
}

$metaTitle = $franchiseData['name'] !== ''
	? $franchiseData['name'] . ' franchise - Giant Bomb Wiki'
	: 'Giant Bomb Wiki';
$metaDescription = PageHelper::sanitizeMetaText( $franchiseData['deck'] ?? '' );
if ( $metaDescription === '' ) {
	$metaDescription = PageHelper::sanitizeMetaText( $franchiseData['description'] ?? '' );
}
if ( $metaDescription === '' && $franchiseData['name'] !== '' ) {
	$metaDescription = 'Browse the Giant Bomb wiki franchise page for ' . $franchiseData['name'] . '.';
}
$metaImage = PageHelper::getMetaImage( $franchiseData['image'], $franchiseData['backgroundImage'] );
$canonicalUrl = rtrim( PageHelper::PUBLIC_WIKI_HOST, '/' ) . $franchiseData['url'];

$out = $this->getSkin()->getOutput();
if ( $franchiseData['name'] !== '' ) {
	$out->setPageTitle( $franchiseData['name'] );
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
], $metaImage, $franchiseData['name'] !== '' ? $franchiseData['name'] . ' artwork' : 'Giant Bomb franchise cover art' );

$schema = [
	'@context' => 'https://schema.org',
	'@type' => 'CreativeWorkSeries',
	'name' => $franchiseData['name'],
	'url' => $canonicalUrl,
	'description' => $metaDescription,
];
if ( $franchiseData['guid'] !== '' ) {
	$schema['identifier'] = $franchiseData['guid'];
}
if ( $metaImage ) {
	$schema['image'] = $metaImage;
}
if ( !empty( $franchiseData['relations']['games'] ) ) {
	$schema['hasPart'] = array_map(
		static fn ( $name ) => [ '@type' => 'VideoGame', 'name' => $name ],
		array_slice( $franchiseData['relations']['games'], 0, 5 )
	);
}
PageHelper::addStructuredData( $out, $schema, 'franchise' );

$templateDir = realpath( __DIR__ . '/../templates' );
$templateParser = new \MediaWiki\Html\TemplateParser( $templateDir );
$data = [
	'franchise' => $franchiseData,
	'hasBasicInfo' => !empty( $franchiseData['deck'] ) || !empty( $franchiseData['aliasesDisplay'] ),
];

echo $templateParser->processTemplate( 'franchise-page', $data );
