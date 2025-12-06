<?php

use MediaWiki\MediaWikiServices;

if ( !defined( 'GB_LEGACY_UPLOAD_HOST' ) ) {
	define( 'GB_LEGACY_UPLOAD_HOST', 'https://www.giantbomb.com' );
}

if ( !defined( 'GB_PUBLIC_WIKI_HOST' ) ) {
	define( 'GB_PUBLIC_WIKI_HOST', 'https://www.giantbomb.com' );
}

if ( !function_exists( 'gbParseLegacyImageData' ) ) {
	function gbParseLegacyImageData( string $text ): ?array {
		if ( !preg_match(
			'/<div[^>]*id=(["\'])imageData\\1[^>]*data-json=(["\'])(.*?)\\2/si',
			$text,
			$matches
		) ) {
			return null;
		}
		$raw = html_entity_decode( $matches[3], ENT_QUOTES | ENT_HTML5 );
		$raw = trim( $raw );
		if ( $raw === '' ) {
			return null;
		}
		$data = json_decode( $raw, true );
		if ( !is_array( $data ) || json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}
		return $data;
	}

	function gbChooseLegacySize( array $available, array $preferred ): ?string {
		foreach ( $preferred as $candidate ) {
			if ( in_array( $candidate, $available, true ) ) {
				return $candidate;
			}
		}
		return $available[0] ?? null;
	}

	function gbBuildLegacyImageUrl( array $entry, array $preferredSizes ): ?string {
		$file = isset( $entry['file'] ) ? trim( (string)$entry['file'] ) : '';
		$path = isset( $entry['path'] ) ? trim( (string)$entry['path'] ) : '';
		$sizes = isset( $entry['sizes'] ) ? (string)$entry['sizes'] : '';
		if ( $file === '' || $path === '' || $sizes === '' ) {
			return null;
		}
		$availableSizes = array_values(
			array_filter(
				array_map( 'trim', explode( ',', $sizes ) ),
				static fn ( $size ) => $size !== ''
			)
		);
		if ( !$availableSizes ) {
			return null;
		}
		$chosen = gbChooseLegacySize( $availableSizes, $preferredSizes );
		if ( $chosen === null ) {
			return null;
		}
		$normalizedPath = trim( $path, '/' );
		$relative = '/a/uploads/' . $chosen . '/' . ( $normalizedPath !== '' ? $normalizedPath . '/' : '' ) . $file;
		return GB_LEGACY_UPLOAD_HOST . $relative;
	}

	function gbResolveWikiImageUrl( string $value ): ?string {
		$trimmed = trim( $value );
		if ( $trimmed === '' ) {
			return null;
		}
		if ( preg_match( '#^https?://#i', $trimmed ) ) {
			return $trimmed;
		}
		if ( stripos( $trimmed, 'File:' ) !== 0 ) {
			$trimmed = 'File:' . $trimmed;
		}
		$title = Title::newFromText( $trimmed );
		if ( !$title ) {
			return null;
		}
		$services = MediaWikiServices::getInstance();
		$file = $services->getRepoGroup()->findFile( $title );
		if ( !$file ) {
			return null;
		}
		$url = $file->getFullUrl();
		if ( !is_string( $url ) || $url === '' ) {
			return null;
		}
		return \wfExpandUrl( $url, \PROTO_CANONICAL );
	}
}

if ( !function_exists( 'gbSanitizeMetaText' ) ) {
	function gbSanitizeMetaText( string $text, int $limit = 280 ): string {
		$plain = trim( preg_replace( '/\s+/', ' ', strip_tags( $text ) ) ?? '' );
		if ( $plain === '' ) {
			return '';
		}
		if ( mb_strlen( $plain ) <= $limit ) {
			return $plain;
		}
		$cut = mb_substr( $plain, 0, $limit );
		$space = mb_strrpos( $cut, ' ' );
		if ( $space !== false && $space >= (int) ( $limit * 0.6 ) ) {
			$cut = mb_substr( $cut, 0, $space );
		}
		return rtrim( $cut, " \t\n\r\0\x0B.,;:–—-_" ) . '…';
	}
}

if ( !function_exists( 'gbBuildMetaTag' ) ) {
	function gbBuildMetaTag( array $attributes ): string {
		$parts = [];
		foreach ( $attributes as $name => $value ) {
			if ( $value === null || $value === '' ) {
				continue;
			}
			$parts[] = htmlspecialchars( $name, ENT_QUOTES, 'UTF-8' ) . '="' . htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ) . '"';
		}
		return '<meta ' . implode( ' ', $parts ) . ' />';
	}
}

if ( !function_exists( 'gbBuildJsonLdScript' ) ) {
	function gbBuildJsonLdScript( string $json ): string {
		return '<script type="application/ld+json">' . $json . '</script>';
	}
}

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
	'name' => str_replace( 'Companies/', '', str_replace( '_', ' ', $pageTitle ) ),
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
		$legacyImageData = gbParseLegacyImageData( $text );

		$wikitext = '';
		if ( preg_match( '/\}\}(.+)$/s', $text, $matches ) ) {
			$wikitext = trim( $matches[1] );
		}

		if ( $wikitext !== '' ) {
			$descCacheKey = $latestRevisionId > 0 ? $wanCache->makeKey( 'giantbomb-company-desc', $latestRevisionId ) : null;
			$descData = $descCacheKey ? $wanCache->get( $descCacheKey ) : null;
			if ( !is_array( $descData ) ) {
				$descHtml = '';
				try {
					$parser = $services->getParser();
					$parserOptions = ParserOptions::newFromAnon();
					$parserOutput = $parser->parse( $wikitext, $title, $parserOptions );
					$descHtml = $parserOutput->getText( [
						'allowTOC' => false,
						'enableSectionEditLinks' => false,
						'wrapperDivClass' => ''
					] );
				} catch ( \Throwable $e ) {
					error_log( 'Failed to parse company wikitext: ' . $e->getMessage() );
					$descHtml = $wikitext;
				}
				$descData = [ 'html' => $descHtml ];
				if ( $descCacheKey ) {
					$wanCache->set( $descCacheKey, $descData, $cacheTtl );
				}
			}
			$companyData['description'] = $descData['html'] ?? '';
		}

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
			if ( preg_match( '/\| ' . $field . '=([^\n]+)/', $text, $matches ) ) {
				$value = trim( $matches[1] );
				if ( $value !== '' ) {
					$companyData[$key] = $value;
				}
			}
		}

		if ( preg_match( '/\| Aliases=([^\n]+)/', $text, $matches ) ) {
			$aliases = array_filter(
				array_map(
					static function ( $alias ) {
						$alias = trim( $alias );
						return $alias !== '' ? str_replace( '_', ' ', $alias ) : null;
					},
					explode( ',', $matches[1] )
				)
			);
			$companyData['aliases'] = array_values( $aliases );
			$companyData['aliasesDisplay'] = implode( ', ', $companyData['aliases'] );
		}

		$listFields = [
			'developed' => 'Developed',
			'published' => 'Published',
			'people' => 'People',
			'locations' => 'Locations',
		];

		foreach ( $listFields as $key => $field ) {
			if ( preg_match( '/\| ' . $field . '=([^\n]+)/', $text, $matches ) ) {
				$items = array_filter(
					array_map(
						static function ( $item ) {
							$item = trim( $item );
							if ( $item === '' ) {
								return null;
							}
							$item = preg_replace( '#^(Games|People|Locations|Companies)/#', '', $item );
							return str_replace( '_', ' ', $item );
						},
						explode( ',', $matches[1] )
					)
				);
				$companyData['relations'][$key] = array_values( $items );
			}
		}

		$resolvedTemplateImage = gbResolveWikiImageUrl( $companyData['image'] );
		$companyData['image'] = $resolvedTemplateImage ?? '';

		if ( isset( $legacyImageData ) && is_array( $legacyImageData ) ) {
			if ( $companyData['image'] === '' && isset( $legacyImageData['infobox'] ) ) {
				$infoboxUrl = gbBuildLegacyImageUrl(
					$legacyImageData['infobox'],
					[ 'scale_super', 'screen_kubrick', 'screen_medium', 'scale_large', 'scale_medium' ]
				);
				if ( $infoboxUrl !== null ) {
					$companyData['image'] = $infoboxUrl;
				}
			}
			if ( isset( $legacyImageData['background'] ) ) {
				$backgroundUrl = gbBuildLegacyImageUrl(
					$legacyImageData['background'],
					[ 'screen_kubrick_wide', 'screen_kubrick', 'scale_super', 'scale_large', 'screen_medium' ]
				);
				if ( $backgroundUrl !== null ) {
					$companyData['backgroundImage'] = $backgroundUrl;
				}
			}
			if ( $companyData['image'] === '' && $companyData['backgroundImage'] !== '' ) {
				$companyData['image'] = $companyData['backgroundImage'];
			}
		}
	}
} catch ( \Throwable $e ) {
	error_log( 'Company page error: ' . $e->getMessage() );
}

$stats = [];
if ( $companyData['abbreviation'] !== '' ) {
	$stats[] = [
		'label' => 'Abbreviation',
		'value' => $companyData['abbreviation'],
	];
}
if ( $companyData['foundedDate'] !== '' ) {
	$stats[] = [
		'label' => 'Founded',
		'value' => $companyData['foundedDate'],
	];
}
if ( $companyData['country'] !== '' ) {
	$location = trim( implode( ', ', array_filter( [
		$companyData['city'],
		$companyData['state'],
		$companyData['country'],
	] ) ) );
	if ( $location !== '' ) {
		$stats[] = [
			'label' => 'Location',
			'value' => $location,
		];
	}
}
if ( $companyData['website'] !== '' ) {
	$stats[] = [
		'label' => 'Website',
		'value' => $companyData['website'],
	];
}
if ( $companyData['phone'] !== '' ) {
	$stats[] = [
		'label' => 'Phone',
		'value' => $companyData['phone'],
	];
}
$companyData['stats'] = $stats;
$companyData['hasStats'] = !empty( $stats );

$metaTitle = $companyData['name'] !== ''
	? $companyData['name'] . ' company - Giant Bomb Wiki'
	: 'Giant Bomb Wiki';
$metaDescription = gbSanitizeMetaText( $companyData['deck'] ?? '' );
if ( $metaDescription === '' ) {
	$metaDescription = gbSanitizeMetaText( $companyData['description'] ?? '' );
}
if ( $metaDescription === '' && $companyData['name'] !== '' ) {
	$metaDescription = 'Explore the Giant Bomb wiki entry for ' . $companyData['name'] . '.';
}
$metaImage = $companyData['image'] !== '' ? $companyData['image'] : ( $companyData['backgroundImage'] !== '' ? $companyData['backgroundImage'] : null );
$canonicalUrl = rtrim( GB_PUBLIC_WIKI_HOST, '/' ) . $companyData['url'];

$out = $this->getSkin()->getOutput();
if ( $companyData['name'] !== '' ) {
	$out->setPageTitle( $companyData['name'] );
}
$out->setHTMLTitle( $metaTitle );
if ( $metaDescription !== '' ) {
	$out->addMeta( 'description', $metaDescription );
}
$out->setCanonicalUrl( $canonicalUrl );

$ogTags = [
	'og:title' => $metaTitle,
	'og:description' => $metaDescription,
	'og:url' => $canonicalUrl,
	'og:site_name' => 'Giant Bomb Wiki',
	'og:type' => 'profile',
	'og:locale' => 'en_US',
];
if ( $metaImage ) {
	$ogTags['og:image'] = $metaImage;
}
foreach ( $ogTags as $property => $content ) {
	if ( $content === '' || $content === null ) {
		continue;
	}
	$out->addHeadItem(
		'meta-' . str_replace( ':', '-', $property ),
		gbBuildMetaTag( [ 'property' => $property, 'content' => $content ] )
	);
}

$twitterTags = [
	'twitter:card' => $metaImage ? 'summary_large_image' : 'summary',
	'twitter:title' => $metaTitle,
	'twitter:description' => $metaDescription,
	'twitter:site' => '@giantbomb',
];
if ( $metaImage ) {
	$twitterTags['twitter:image'] = $metaImage;
	$twitterTags['twitter:image:alt'] = $companyData['name'] !== '' ? $companyData['name'] . ' logo' : 'Giant Bomb company cover art';
}
foreach ( $twitterTags as $name => $content ) {
	if ( $content === '' || $content === null ) {
		continue;
	}
	$out->addHeadItem(
		'meta-' . str_replace( [ ':', '/' ], '-', $name ),
		gbBuildMetaTag( [ 'name' => $name, 'content' => $content ] )
	);
}

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
		static fn ( $name ) => [
			'@type' => 'VideoGame',
			'name' => $name,
		],
		array_slice( $companyData['relations']['developed'], 0, 5 )
	);
}
$schema = array_filter(
	$schema,
	static function ( $value ) {
		if ( $value === null ) {
			return false;
		}
		if ( is_string( $value ) && trim( $value ) === '' ) {
			return false;
		}
		if ( is_array( $value ) && empty( $value ) ) {
			return false;
		}
		return true;
	}
);
$schemaJson = json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
if ( $schemaJson !== false ) {
	$out->addHeadItem(
		'structured-data-company',
		gbBuildJsonLdScript( $schemaJson )
	);
}

$templateDir = realpath( __DIR__ . '/../templates' );
$templateParser = new \MediaWiki\Html\TemplateParser( $templateDir );
$data = [
	'company' => $companyData,
	'hasBasicInfo' => !empty( $companyData['deck'] ) || !empty( $companyData['aliasesDisplay'] ),
];

echo $templateParser->processTemplate( 'company-page', $data );


