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

$companyData = [
	'name' => PageHelper::cleanPageName( $pageTitle, 'Companies' ),
	'url' => '/wiki/' . $pageTitleDB,
	'image' => '',
	'backgroundImage' => '',
	'deck' => '',
	'description' => '',
	'guid' => '',
	'aliases' => [],
	'aliasesDisplay' => '',
	'abbreviation' => '',
	'foundedDate' => '',
	'address' => '',
	'city' => '',
	'state' => '',
	'country' => '',
	'phone' => '',
	'website' => '',
	'stats' => [],
	'relations' => [
		'developed' => [],
		'published' => [],
		'people' => [],
		'locations' => [],
	],
];

try {
	$content = $page ? $page->getContent() : null;

	if ( $content ) {
		$text = $content->getText();
		$legacyImageData = PageHelper::parseLegacyImageData( $text );
		$wikitext = PageHelper::extractWikitext( $text );

		$companyData['description'] = PageHelper::parseDescription(
			$wikitext, $title, $wanCache, $latestRevisionId, 'giantbomb-company-desc', $cacheTtl
		);

		$singleFields = [
			'name' => 'Name',
			'deck' => 'Deck',
			'image' => 'Image',
			'guid' => 'Guid',
			'abbreviation' => 'Abbreviation',
			'foundedDate' => 'FoundedDate',
			'address' => 'Address',
			'city' => 'City',
			'state' => 'State',
			'country' => 'Country',
			'phone' => 'Phone',
			'website' => 'Website',
		];

		foreach ( $singleFields as $key => $field ) {
			$value = PageHelper::parseTemplateField( $text, $field );
			if ( $value !== '' ) {
				$companyData[$key] = $value;
			}
		}

		$rawAliases = PageHelper::parseTemplateField( $text, 'Aliases' );
		$companyData['aliases'] = PageHelper::parseAliases( $rawAliases );
		$companyData['aliasesDisplay'] = implode( ', ', $companyData['aliases'] );

		$listFields = [
			'developed' => 'Developed',
			'published' => 'Published',
			'people' => 'People',
			'locations' => 'Locations',
		];
		$prefixes = [ 'Games', 'People', 'Locations', 'Companies' ];

		foreach ( $listFields as $key => $field ) {
			$rawList = PageHelper::parseTemplateField( $text, $field );
			$companyData['relations'][$key] = PageHelper::parseListField( $rawList, $prefixes );
		}

		PageHelper::resolveImages( $companyData, $legacyImageData );
	}
} catch ( \Throwable $e ) {
	error_log( 'Company page error: ' . $e->getMessage() );
}

$stats = [];
if ( $companyData['abbreviation'] !== '' ) {
	$stats[] = [ 'label' => 'Abbreviation', 'value' => $companyData['abbreviation'] ];
}
if ( $companyData['foundedDate'] !== '' ) {
	$stats[] = [ 'label' => 'Founded', 'value' => $companyData['foundedDate'] ];
}
if ( $companyData['country'] !== '' ) {
	$location = trim( implode( ', ', array_filter( [
		$companyData['city'],
		$companyData['state'],
		$companyData['country'],
	] ) ) );
	if ( $location !== '' ) {
		$stats[] = [ 'label' => 'Location', 'value' => $location ];
	}
}
if ( $companyData['website'] !== '' ) {
	$stats[] = [ 'label' => 'Website', 'value' => $companyData['website'] ];
}
if ( $companyData['phone'] !== '' ) {
	$stats[] = [ 'label' => 'Phone', 'value' => $companyData['phone'] ];
}
$companyData['stats'] = $stats;
$companyData['hasStats'] = !empty( $stats );

$metaTitle = $companyData['name'] !== ''
	? $companyData['name'] . ' company - Giant Bomb Wiki'
	: 'Giant Bomb Wiki';
$metaDescription = PageHelper::sanitizeMetaText( $companyData['deck'] ?? '' );
if ( $metaDescription === '' ) {
	$metaDescription = PageHelper::sanitizeMetaText( $companyData['description'] ?? '' );
}
if ( $metaDescription === '' && $companyData['name'] !== '' ) {
	$metaDescription = $companyData['name'] . ' on Giant Bomb.';
}
$metaImage = PageHelper::getMetaImage( $companyData['image'], $companyData['backgroundImage'] );
$canonicalUrl = rtrim( PageHelper::PUBLIC_WIKI_HOST, '/' ) . $companyData['url'];

$out = $this->getSkin()->getOutput();
if ( $companyData['name'] !== '' ) {
	$out->setPageTitle( $companyData['name'] );
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
], $metaImage, $companyData['name'] !== '' ? $companyData['name'] . ' logo' : 'Giant Bomb company cover art' );

$schema = [
	'@context' => 'https://schema.org',
	'@type' => 'Organization',
	'name' => $companyData['name'],
	'url' => $canonicalUrl,
	'description' => $metaDescription,
];
if ( $companyData['guid'] !== '' ) {
	$schema['identifier'] = $companyData['guid'];
}
if ( $metaImage ) {
	$schema['logo'] = $metaImage;
}
if ( $companyData['country'] !== '' ) {
	$schema['address'] = array_filter( [
		'@type' => 'PostalAddress',
		'streetAddress' => $companyData['address'] !== '' ? $companyData['address'] : null,
		'addressLocality' => $companyData['city'] !== '' ? $companyData['city'] : null,
		'addressRegion' => $companyData['state'] !== '' ? $companyData['state'] : null,
		'addressCountry' => $companyData['country'] !== '' ? $companyData['country'] : null,
	] );
}
if ( $companyData['phone'] !== '' ) {
	$schema['telephone'] = $companyData['phone'];
}
if ( $companyData['website'] !== '' ) {
	$schema['sameAs'] = $companyData['website'];
}
if ( !empty( $companyData['relations']['developed'] ) ) {
	$schema['makesOffer'] = array_map(
		static fn ( $name ) => [ '@type' => 'VideoGame', 'name' => $name ],
		array_slice( $companyData['relations']['developed'], 0, 5 )
	);
}
PageHelper::addStructuredData( $out, $schema, 'company' );

$templateDir = realpath( __DIR__ . '/../templates' );
$templateParser = new \MediaWiki\Html\TemplateParser( $templateDir );
$data = [
	'company' => $companyData,
	'hasBasicInfo' => !empty( $companyData['deck'] ) || !empty( $companyData['aliasesDisplay'] ),
];

echo $templateParser->processTemplate( 'company-page', $data );
