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

$characterData = [
	'name' => PageHelper::cleanPageName( $pageTitle, 'Characters' ),
	'url' => '/wiki/' . $pageTitleDB,
	'image' => '',
	'backgroundImage' => '',
	'deck' => '',
	'description' => '',
	'guid' => '',
	'aliases' => [],
	'aliasesDisplay' => '',
	'realName' => '',
	'gender' => '',
	'birthday' => '',
	'franchises' => [],
	'friends' => [],
	'enemies' => [],
	'concepts' => [],
	'games' => [],
	'locations' => [],
	'objects' => [],
	'people' => [],
];

try {
	$content = $page ? $page->getContent() : null;

	if ( $content ) {
		$text = $content->getText();
		$legacyImageData = PageHelper::parseLegacyImageData( $text );
		$wikitext = PageHelper::extractWikitext( $text );

		$characterData['description'] = PageHelper::parseDescription(
			$wikitext, $title, $wanCache, $latestRevisionId, 'giantbomb-character-desc', $cacheTtl
		);

		$singleFields = [
			'name' => 'Name',
			'deck' => 'Deck',
			'image' => 'Image',
			'guid' => 'Guid',
			'realName' => 'RealName',
			'gender' => 'Gender',
			'birthday' => 'Birthday',
		];
		foreach ( $singleFields as $key => $field ) {
			$value = PageHelper::parseTemplateField( $text, $field );
			if ( $value !== '' ) {
				$characterData[$key] = $value;
			}
		}

		$rawAliases = PageHelper::parseTemplateField( $text, 'Aliases' );
		$characterData['aliases'] = PageHelper::parseAliases( $rawAliases );
		$characterData['aliasesDisplay'] = implode( ', ', $characterData['aliases'] );

		$listFields = [
			'franchises' => 'Franchises',
			'friends' => 'Friends',
			'enemies' => 'Enemies',
			'concepts' => 'Concepts',
			'games' => 'Games',
			'locations' => 'Locations',
			'objects' => 'Objects',
			'people' => 'People',
		];
		$prefixes = [ 'Characters', 'Franchises', 'Concepts', 'Games', 'Locations', 'Objects', 'People', 'Companies' ];

		foreach ( $listFields as $key => $field ) {
			$rawList = PageHelper::parseTemplateField( $text, $field );
			$characterData[$key] = PageHelper::parseListField( $rawList, $prefixes );
		}

		PageHelper::resolveImages( $characterData, $legacyImageData );
	}
} catch ( \Throwable $e ) {
	error_log( 'Character page error: ' . $e->getMessage() );
}

$stats = [];
if ( $characterData['realName'] !== '' ) {
	$stats[] = [ 'label' => 'Real name', 'value' => $characterData['realName'] ];
}
if ( $characterData['gender'] !== '' ) {
	$stats[] = [ 'label' => 'Gender', 'value' => $characterData['gender'] ];
}
if ( $characterData['birthday'] !== '' ) {
	$stats[] = [ 'label' => 'Birthday', 'value' => $characterData['birthday'] ];
}
$characterData['stats'] = $stats;
$characterData['hasStats'] = !empty( $stats );

$metaTitle = $characterData['name'] !== ''
	? $characterData['name'] . ' character - Giant Bomb Wiki'
	: 'Giant Bomb Wiki';
$metaDescription = PageHelper::sanitizeMetaText( $characterData['deck'] ?? '' );
if ( $metaDescription === '' ) {
	$metaDescription = PageHelper::sanitizeMetaText( $characterData['description'] ?? '' );
}
if ( $metaDescription === '' && $characterData['name'] !== '' ) {
	$metaDescription = $characterData['name'] . ' on Giant Bomb.';
}
$metaImage = PageHelper::getMetaImage( $characterData['image'], $characterData['backgroundImage'] );
$canonicalUrl = rtrim( PageHelper::PUBLIC_WIKI_HOST, '/' ) . $characterData['url'];

$out = $this->getSkin()->getOutput();
if ( $characterData['name'] !== '' ) {
	$out->setPageTitle( $characterData['name'] );
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
	'og:type' => 'profile',
	'og:locale' => 'en_US',
], $metaImage );

PageHelper::addTwitterTags( $out, [
	'twitter:card' => $metaImage ? 'summary_large_image' : 'summary',
	'twitter:title' => $metaTitle,
	'twitter:description' => $metaDescription,
	'twitter:site' => '@giantbomb',
], $metaImage, $characterData['name'] !== '' ? $characterData['name'] . ' character art' : 'Giant Bomb character cover art' );

$schema = [
	'@context' => 'https://schema.org',
	'@type' => 'VideoGameCharacter',
	'name' => $characterData['name'],
	'url' => $canonicalUrl,
	'description' => $metaDescription,
];
if ( $characterData['guid'] !== '' ) {
	$schema['identifier'] = $characterData['guid'];
}
if ( $metaImage ) {
	$schema['image'] = $metaImage;
}
if ( $characterData['gender'] !== '' ) {
	$schema['gender'] = $characterData['gender'];
}
if ( $characterData['birthday'] !== '' ) {
	$schema['birthDate'] = $characterData['birthday'];
}
if ( !empty( $characterData['franchises'] ) ) {
	$schema['isPartOf'] = array_map(
		static fn ( $name ) => [ '@type' => 'CreativeWorkSeries', 'name' => $name ],
		array_slice( $characterData['franchises'], 0, 3 )
	);
}
PageHelper::addStructuredData( $out, $schema, 'character' );

$templateDir = realpath( __DIR__ . '/../templates' );
$templateParser = new \MediaWiki\Html\TemplateParser( $templateDir );
$data = [
	'character' => $characterData,
	'hasBasicInfo' => !empty( $characterData['deck'] ) || !empty( $characterData['realName'] ),
];

echo $templateParser->processTemplate( 'character-page', $data );
