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

$objectData = [
	'name' => PageHelper::cleanPageName( $pageTitle, 'Objects' ),
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
		'franchises' => [],
		'concepts' => [],
	],
];

try {
	$content = $page ? $page->getContent() : null;

	if ( $content ) {
		$text = $content->getText();
		$legacyImageData = PageHelper::parseLegacyImageData( $text );
		$wikitext = PageHelper::extractWikitext( $text );

		$objectData['description'] = PageHelper::parseDescription(
			$wikitext, $title, $wanCache, $latestRevisionId, 'giantbomb-object-desc', $cacheTtl
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
				$objectData[$key] = $value;
			}
		}

		$rawAliases = PageHelper::parseTemplateField( $text, 'Aliases' );
		$objectData['aliases'] = PageHelper::parseAliases( $rawAliases );
		$objectData['aliasesDisplay'] = implode( ', ', $objectData['aliases'] );

		$listFields = [
			'games' => 'Games',
			'franchises' => 'Franchises',
			'concepts' => 'Concepts',
		];
		$prefixes = [ 'Games', 'Franchises', 'Concepts' ];

		foreach ( $listFields as $key => $field ) {
			$rawList = PageHelper::parseTemplateField( $text, $field );
			$objectData['relations'][$key] = PageHelper::parseListField( $rawList, $prefixes );
		}

		PageHelper::resolveImages( $objectData, $legacyImageData );
	}
} catch ( \Throwable $e ) {
	error_log( 'Object page error: ' . $e->getMessage() );
}

$metaTitle = $objectData['name'] !== ''
	? $objectData['name'] . ' object - Giant Bomb Wiki'
	: 'Giant Bomb Wiki';
$metaDescription = PageHelper::sanitizeMetaText( $objectData['deck'] ?? '' );
if ( $metaDescription === '' ) {
	$metaDescription = PageHelper::sanitizeMetaText( $objectData['description'] ?? '' );
}
if ( $metaDescription === '' && $objectData['name'] !== '' ) {
	$metaDescription = $objectData['name'] . ' on Giant Bomb.';
}
$metaImage = PageHelper::getMetaImage( $objectData['image'], $objectData['backgroundImage'] );
$canonicalUrl = rtrim( PageHelper::PUBLIC_WIKI_HOST, '/' ) . $objectData['url'];

$out = $this->getSkin()->getOutput();
if ( $objectData['name'] !== '' ) {
	$out->setPageTitle( $objectData['name'] );
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
], $metaImage, $objectData['name'] !== '' ? $objectData['name'] . ' artwork' : 'Giant Bomb object cover art' );

$schema = [
	'@context' => 'https://schema.org',
	'@type' => 'CreativeWork',
	'name' => $objectData['name'],
	'url' => $canonicalUrl,
	'description' => $metaDescription,
];
if ( $objectData['guid'] !== '' ) {
	$schema['identifier'] = $objectData['guid'];
}
if ( $metaImage ) {
	$schema['image'] = $metaImage;
}
if ( !empty( $objectData['relations']['games'] ) ) {
	$schema['subjectOf'] = array_map(
		static fn ( $name ) => [ '@type' => 'VideoGame', 'name' => $name ],
		array_slice( $objectData['relations']['games'], 0, 5 )
	);
}
PageHelper::addStructuredData( $out, $schema, 'object' );

$templateDir = realpath( __DIR__ . '/../templates' );
$templateParser = new \MediaWiki\Html\TemplateParser( $templateDir );
$data = [
	'object' => $objectData,
	'hasBasicInfo' => !empty( $objectData['deck'] ) || !empty( $objectData['aliasesDisplay'] ),
];

echo $templateParser->processTemplate( 'object-page', $data );
