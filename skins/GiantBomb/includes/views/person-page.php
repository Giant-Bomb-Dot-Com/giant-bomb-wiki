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

$personData = [
	'name' => PageHelper::cleanPageName( $pageTitle, 'People' ),
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
	'death' => '',
	'hometown' => '',
	'country' => '',
	'twitter' => '',
	'website' => '',
	'stats' => [],
	'hasStats' => false,
	'relations' => [
		'games' => [],
		'characters' => [],
		'franchises' => [],
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
		$infoboxFields = PageHelper::extractInfoboxFields( $text );
		$legacyImageData = PageHelper::parseLegacyImageData( $text );
		$wikitext = PageHelper::extractWikitext( $text );

		$personData['description'] = PageHelper::parseDescription(
			$wikitext, $title, $wanCache, $latestRevisionId, 'giantbomb-person-desc', $cacheTtl
		);

		$personData['name'] = PageHelper::getFieldValue( $infoboxFields, [ 'name' ] ) ?: $personData['name'];
		$personData['deck'] = PageHelper::getFieldValue( $infoboxFields, [ 'deck' ] );
		$personData['image'] = PageHelper::getFieldValue( $infoboxFields, [ 'image', 'infoboximage' ] );
		$personData['guid'] = PageHelper::getFieldValue( $infoboxFields, [ 'guid', 'id' ] );
		$personData['realName'] = PageHelper::getFieldValue( $infoboxFields, [ 'realname', 'real name' ] );
		$personData['gender'] = PageHelper::getFieldValue( $infoboxFields, [ 'gender' ] );
		$personData['birthday'] = PageHelper::getFieldValue( $infoboxFields, [ 'birthday', 'birthdate', 'birth date', 'born' ] );
		$personData['death'] = PageHelper::getFieldValue( $infoboxFields, [ 'death', 'deathdate', 'death date', 'died' ] );
		$personData['hometown'] = PageHelper::getFieldValue( $infoboxFields, [ 'hometown', 'birthplace', 'placeofbirth' ] );
		$personData['country'] = PageHelper::getFieldValue( $infoboxFields, [ 'country', 'nationality' ] );
		$personData['twitter'] = PageHelper::getFieldValue( $infoboxFields, [ 'twitter', 'twitterhandle' ] );
		$personData['website'] = PageHelper::getFieldValue( $infoboxFields, [ 'website', 'url', 'site' ] );

		$rawAliases = PageHelper::getFieldValue( $infoboxFields, [ 'aliases', 'alias' ] );
		if ( $rawAliases === '' ) {
			$rawAliases = PageHelper::parseTemplateField( $text, 'Aliases' );
		}
		$personData['aliases'] = PageHelper::parseAliases( $rawAliases );
		$personData['aliasesDisplay'] = implode( ', ', $personData['aliases'] );

		$listFields = [
			'games' => 'Games',
			'characters' => 'Characters',
			'franchises' => 'Franchises',
			'concepts' => 'Concepts',
			'locations' => 'Locations',
			'objects' => 'Objects',
			'people' => 'People',
		];
		$prefixes = [ 'Games', 'Characters', 'Concepts', 'Locations', 'Objects', 'People', 'Franchises', 'Companies' ];

		foreach ( $listFields as $key => $field ) {
			$rawList = PageHelper::getFieldValue( $infoboxFields, [ strtolower( $field ), $field ] );
			if ( $rawList === '' ) {
				$rawList = PageHelper::parseTemplateField( $text, $field );
			}
			$personData['relations'][$key] = PageHelper::parseListField( $rawList, $prefixes );
		}

		PageHelper::resolveImages( $personData, $legacyImageData );
	}
} catch ( \Throwable $e ) {
	error_log( 'Person page error: ' . $e->getMessage() );
}

$stats = [];
if ( $personData['realName'] !== '' && strcasecmp( $personData['realName'], $personData['name'] ) !== 0 ) {
	$stats[] = [ 'label' => 'Real name', 'value' => $personData['realName'] ];
}
if ( $personData['gender'] !== '' ) {
	$stats[] = [ 'label' => 'Gender', 'value' => $personData['gender'] ];
}
if ( $personData['birthday'] !== '' ) {
	$stats[] = [ 'label' => 'Born', 'value' => $personData['birthday'] ];
}
if ( $personData['death'] !== '' ) {
	$stats[] = [ 'label' => 'Died', 'value' => $personData['death'] ];
}
$locationPieces = array_filter( [ $personData['hometown'], $personData['country'] ] );
if ( !empty( $locationPieces ) ) {
	$stats[] = [ 'label' => 'Hometown', 'value' => implode( ', ', array_unique( $locationPieces ) ) ];
}
if ( $personData['twitter'] !== '' ) {
	$handle = ltrim( $personData['twitter'], '@' );
	$stats[] = [ 'label' => 'Twitter', 'value' => '@' . ( $handle !== '' ? $handle : $personData['twitter'] ) ];
}
if ( $personData['website'] !== '' ) {
	$stats[] = [ 'label' => 'Website', 'value' => $personData['website'] ];
}
$personData['stats'] = $stats;
$personData['hasStats'] = !empty( $stats );

$metaTitle = $personData['name'] !== ''
	? $personData['name'] . ' - Giant Bomb Wiki'
	: 'Giant Bomb Wiki';
$metaDescription = PageHelper::sanitizeMetaText( $personData['deck'] ?? '' );
if ( $metaDescription === '' ) {
	$metaDescription = PageHelper::sanitizeMetaText( $personData['description'] ?? '' );
}
if ( $metaDescription === '' && $personData['name'] !== '' ) {
	$metaDescription = $personData['name'] . ' on Giant Bomb.';
}
$metaImage = PageHelper::getMetaImage( $personData['image'], $personData['backgroundImage'] );
$canonicalUrl = rtrim( PageHelper::PUBLIC_WIKI_HOST, '/' ) . $personData['url'];

$out = $this->getSkin()->getOutput();
if ( $personData['name'] !== '' ) {
	$out->setPageTitle( $personData['name'] );
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
], $metaImage, $personData['name'] !== '' ? $personData['name'] . ' portrait' : 'Giant Bomb person artwork' );

$schema = [
	'@context' => 'https://schema.org',
	'@type' => 'Person',
	'name' => $personData['name'],
	'url' => $canonicalUrl,
	'description' => $metaDescription,
];
if ( $personData['guid'] !== '' ) {
	$schema['identifier'] = $personData['guid'];
}
if ( $metaImage ) {
	$schema['image'] = $metaImage;
}
if ( $personData['birthday'] !== '' ) {
	$schema['birthDate'] = $personData['birthday'];
}
if ( $personData['death'] !== '' ) {
	$schema['deathDate'] = $personData['death'];
}
if ( $personData['gender'] !== '' ) {
	$schema['gender'] = $personData['gender'];
}
if ( $personData['hometown'] !== '' || $personData['country'] !== '' ) {
	$schema['homeLocation'] = array_filter( [
		'@type' => 'Place',
		'name' => implode( ', ', array_unique( array_filter( [ $personData['hometown'], $personData['country'] ] ) ) ),
	] );
}
$sameAs = [];
if ( $personData['website'] !== '' ) {
	$sameAs[] = PageHelper::ensureUrlHasScheme( $personData['website'] );
}
if ( $personData['twitter'] !== '' ) {
	$handle = ltrim( $personData['twitter'], '@' );
	if ( $handle !== '' ) {
		$sameAs[] = 'https://twitter.com/' . $handle;
	}
}
if ( !empty( $sameAs ) ) {
	$schema['sameAs'] = array_values( array_unique( $sameAs ) );
}
if ( !empty( $personData['relations']['games'] ) ) {
	$schema['worksFor'] = array_map(
		static fn ( $name ) => [ '@type' => 'VideoGame', 'name' => $name ],
		array_slice( $personData['relations']['games'], 0, 5 )
	);
}
PageHelper::addStructuredData( $out, $schema, 'person' );

$templateDir = realpath( __DIR__ . '/../templates' );
$templateParser = new \MediaWiki\Html\TemplateParser( $templateDir );
$data = [ 'person' => $personData ];

echo $templateParser->processTemplate( 'person-page', $data );
