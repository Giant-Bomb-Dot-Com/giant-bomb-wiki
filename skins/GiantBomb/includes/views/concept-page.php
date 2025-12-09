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

$conceptData = [
	'name' => PageHelper::cleanPageName( $pageTitle, 'Concepts' ),
	'url' => '/wiki/' . $pageTitleDB,
	'image' => '',
	'backgroundImage' => '',
	'deck' => '',
	'description' => '',
	'guid' => '',
	'aliases' => [],
	'aliasesDisplay' => '',
	'related' => [
		'franchises' => [],
		'games' => [],
		'characters' => [],
		'locations' => [],
		'objects' => [],
		'people' => [],
		'concepts' => [],
	],
];

try {
	$content = $page ? $page->getContent() : null;

	if ( $content ) {
		$text = $content->getText();
		$legacyImageData = PageHelper::parseLegacyImageData( $text );
		$wikitext = PageHelper::extractWikitext( $text );

		$conceptData['description'] = PageHelper::parseDescription(
			$wikitext, $title, $wanCache, $latestRevisionId, 'giantbomb-concept-desc', $cacheTtl
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
				$conceptData[$key] = $value;
			}
		}

		$rawAliases = PageHelper::parseTemplateField( $text, 'Aliases' );
		$conceptData['aliases'] = PageHelper::parseAliases( $rawAliases );
		$conceptData['aliasesDisplay'] = implode( ', ', $conceptData['aliases'] );

		$listFields = [
			'concepts' => 'Concepts',
			'franchises' => 'Franchises',
			'games' => 'Games',
			'characters' => 'Characters',
			'locations' => 'Locations',
			'objects' => 'Objects',
			'people' => 'People',
		];
		$prefixes = [ 'Concepts', 'Franchises', 'Games', 'Characters', 'Locations', 'Objects', 'People' ];

		foreach ( $listFields as $key => $field ) {
			$rawList = PageHelper::parseTemplateField( $text, $field );
			$conceptData['related'][$key] = PageHelper::parseListField( $rawList, $prefixes );
		}

		PageHelper::resolveImages( $conceptData, $legacyImageData );
	}
} catch ( \Throwable $e ) {
	error_log( 'Concept page error: ' . $e->getMessage() );
}

$metaTitle = $conceptData['name'] !== ''
	? $conceptData['name'] . ' concept - Giant Bomb Wiki'
	: 'Giant Bomb Wiki';
$metaDescription = PageHelper::sanitizeMetaText( $conceptData['deck'] ?? '' );
if ( $metaDescription === '' ) {
	$metaDescription = PageHelper::sanitizeMetaText( $conceptData['description'] ?? '' );
}
if ( $metaDescription === '' && $conceptData['name'] !== '' ) {
	$metaDescription = 'Dive into the Giant Bomb wiki concept page for ' . $conceptData['name'] . '.';
}
$metaImage = PageHelper::getMetaImage( $conceptData['image'], $conceptData['backgroundImage'] );
$canonicalUrl = rtrim( PageHelper::PUBLIC_WIKI_HOST, '/' ) . $conceptData['url'];

$out = $this->getSkin()->getOutput();
if ( $conceptData['name'] !== '' ) {
	$out->setPageTitle( $conceptData['name'] );
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
], $metaImage, $conceptData['name'] !== '' ? $conceptData['name'] . ' concept art' : 'Giant Bomb concept cover art' );

$schema = [
	'@context' => 'https://schema.org',
	'@type' => 'CreativeWork',
	'name' => $conceptData['name'],
	'url' => $canonicalUrl,
	'description' => $metaDescription,
];
if ( $conceptData['guid'] !== '' ) {
	$schema['identifier'] = $conceptData['guid'];
}
if ( $metaImage ) {
	$schema['image'] = $metaImage;
}
if ( !empty( $conceptData['related']['games'] ) ) {
	$schema['subjectOf'] = array_map(
		static fn ( $name ) => [ '@type' => 'VideoGame', 'name' => $name ],
		array_slice( $conceptData['related']['games'], 0, 5 )
	);
}
PageHelper::addStructuredData( $out, $schema, 'concept' );

$templateDir = realpath( __DIR__ . '/../templates' );
$templateParser = new \MediaWiki\Html\TemplateParser( $templateDir );
$data = [
	'concept' => $conceptData,
	'hasBasicInfo' => !empty( $conceptData['deck'] ) || !empty( $conceptData['aliasesDisplay'] ),
];

echo $templateParser->processTemplate( 'concept-page', $data );
