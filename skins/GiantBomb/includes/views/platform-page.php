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

$platformData = [
	'name' => PageHelper::cleanPageName( $pageTitle, 'Platforms' ),
	'url' => '/wiki/' . $pageTitleDB,
	'image' => '',
	'backgroundImage' => '',
	'deck' => '',
	'description' => '',
	'releaseDate' => '',
	'releaseDateType' => '',
	'shortName' => '',
	'installBase' => '',
	'onlineSupport' => '',
	'originalPrice' => '',
	'manufacturers' => [],
	'aliases' => [],
	'guid' => '',
	'stats' => [],
];

try {
	$content = $page ? $page->getContent() : null;

	if ( $content ) {
		$text = $content->getText();
		$legacyImageData = PageHelper::parseLegacyImageData( $text );

		$wikitext = PageHelper::extractWikitext( $text );
		if ( $wikitext !== '' ) {
			$platformData['description'] = PageHelper::parseDescription(
				$wikitext, $title, $wanCache, $latestRevisionId, 'giantbomb-platform-desc', $cacheTtl
			);
		}

		$platformData['name'] = PageHelper::parseTemplateField( $text, 'Name' ) ?: $platformData['name'];
		$platformData['deck'] = PageHelper::parseTemplateField( $text, 'Deck' );
		$platformData['image'] = PageHelper::parseTemplateField( $text, 'Image' );
		$platformData['releaseDate'] = PageHelper::parseTemplateField( $text, 'ReleaseDate' );
		$platformData['releaseDateType'] = PageHelper::parseTemplateField( $text, 'ReleaseDateType' );
		$platformData['shortName'] = PageHelper::parseTemplateField( $text, 'ShortName' );
		$platformData['installBase'] = PageHelper::parseTemplateField( $text, 'InstallBase' );
		$platformData['originalPrice'] = PageHelper::parseTemplateField( $text, 'OriginalPrice' );
		$platformData['guid'] = PageHelper::parseTemplateField( $text, 'Guid' );

		$onlineSupport = PageHelper::parseTemplateField( $text, 'OnlineSupport' );
		$platformData['onlineSupport'] = $onlineSupport !== '' ? ucfirst( strtolower( $onlineSupport ) ) : '';

		$platformData['manufacturers'] = PageHelper::parseListField(
			PageHelper::parseTemplateField( $text, 'Manufacturer' ),
			[ 'Companies' ]
		);
		$platformData['aliases'] = PageHelper::parseAliases(
			PageHelper::parseTemplateField( $text, 'Aliases' )
		);

		PageHelper::resolveImages( $platformData, $legacyImageData );
	}
} catch ( \Throwable $e ) {
	error_log( 'Platform page error: ' . $e->getMessage() );
}

$platformData['aliasesDisplay'] = $platformData['aliases'] ? implode( ', ', $platformData['aliases'] ) : '';

$stats = [];
if ( $platformData['releaseDate'] !== '' ) {
	$stats[] = [ 'label' => 'Released', 'value' => $platformData['releaseDate'] ];
}
if ( $platformData['installBase'] !== '' ) {
	$stats[] = [ 'label' => 'Install base', 'value' => $platformData['installBase'] ];
}
if ( $platformData['originalPrice'] !== '' ) {
	$stats[] = [ 'label' => 'Launch price', 'value' => $platformData['originalPrice'] ];
}
if ( $platformData['onlineSupport'] !== '' ) {
	$stats[] = [ 'label' => 'Online support', 'value' => $platformData['onlineSupport'] ];
}
if ( !empty( $platformData['manufacturers'] ) ) {
	$stats[] = [
		'label' => count( $platformData['manufacturers'] ) > 1 ? 'Manufacturers' : 'Manufacturer',
		'value' => implode( ', ', $platformData['manufacturers'] ),
	];
}
$platformData['stats'] = $stats;
$platformData['hasStats'] = !empty( $stats );

$out = $this->getSkin()->getOutput();
$metaTitle = $platformData['name'] !== ''
	? $platformData['name'] . ' platform - Giant Bomb Wiki'
	: 'Giant Bomb Wiki';
$metaDescription = PageHelper::sanitizeMetaText( $platformData['deck'] ?? '' );
if ( $metaDescription === '' ) {
	$metaDescription = PageHelper::sanitizeMetaText( $platformData['description'] ?? '' );
}
if ( $metaDescription === '' && $platformData['name'] !== '' ) {
	$metaDescription = $platformData['name'] . ' on Giant Bomb.';
}
$metaImage = PageHelper::getMetaImage( $platformData['image'], $platformData['backgroundImage'] );
$canonicalUrl = rtrim( PageHelper::PUBLIC_WIKI_HOST, '/' ) . $platformData['url'];

if ( $platformData['name'] !== '' ) {
	$out->setPageTitle( $platformData['name'] );
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
	'og:type' => 'website',
	'og:locale' => 'en_US',
], $metaImage );

PageHelper::addTwitterTags( $out, [
	'twitter:card' => $metaImage ? 'summary_large_image' : 'summary',
	'twitter:title' => $metaTitle,
	'twitter:description' => $metaDescription,
	'twitter:site' => '@giantbomb',
], $metaImage, $platformData['name'] !== '' ? $platformData['name'] . ' hardware' : '' );

$schema = [
	'@context' => 'https://schema.org',
	'@type' => 'VideoGamePlatform',
	'name' => $platformData['name'],
	'url' => $canonicalUrl,
	'description' => $metaDescription,
	'identifier' => $platformData['guid'] ?: null,
	'image' => $metaImage,
	'datePublished' => $platformData['releaseDate'] ?: null,
];
if ( !empty( $platformData['manufacturers'] ) ) {
	$schema['manufacturer'] = array_map(
		static fn ( $name ) => [ '@type' => 'Organization', 'name' => $name ],
		array_slice( $platformData['manufacturers'], 0, 3 )
	);
}
PageHelper::addStructuredData( $out, $schema, 'platform' );

$templateDir = realpath( __DIR__ . '/../templates' );
$templateParser = new \MediaWiki\Html\TemplateParser( $templateDir );
$data = [
	'platform' => $platformData,
	'hasBasicInfo' => !empty( $platformData['deck'] ) || !empty( $platformData['releaseDate'] ),
];

echo $templateParser->processTemplate( 'platform-page', $data );
