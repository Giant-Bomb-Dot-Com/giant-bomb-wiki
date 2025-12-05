<?php

use MediaWiki\MediaWikiServices;

if ( !defined( 'GB_LEGACY_UPLOAD_HOST' ) ) {
	define( 'GB_LEGACY_UPLOAD_HOST', 'https://www.giantbomb.com' );
}

if ( !defined( 'GB_PUBLIC_WIKI_HOST' ) ) {
	define( 'GB_PUBLIC_WIKI_HOST', 'https://www.giantbomb.com' );
}

if ( !function_exists( 'gbExtractInfoboxFields' ) ) {
	function gbExtractInfoboxFields( string $text ): array {
		if ( !preg_match( '/\{\{[^\n]+(\n.*?\n)\}\}/s', $text, $matches ) ) {
			return [];
		}
		$block = $matches[1];
		$fields = [];
		foreach ( preg_split( '/\r?\n/', $block ) as $line ) {
			if ( preg_match( '/^\|\s*([^=]+?)\s*=(.*)$/', $line, $fieldMatch ) ) {
				$keyRaw = trim( $fieldMatch[1] );
				$key = strtolower( $keyRaw );
				$keyNormalized = preg_replace( '/[^a-z0-9]+/', '', $key );
				$value = trim( $fieldMatch[2] );
				$fields[$key] = $value;
				if ( $keyNormalized !== '' ) {
					$fields[$keyNormalized] = $value;
				}
			}
		}
		return $fields;
	}

	function gbGetFieldValue( array $fields, array $keys ): string {
		foreach ( $keys as $key ) {
			$normalized = strtolower( $key );
			$normalized = preg_replace( '/[^a-z0-9]+/', '', $normalized );
			if ( isset( $fields[$normalized] ) && trim( $fields[$normalized] ) !== '' ) {
				return trim( $fields[$normalized] );
			}
			if ( isset( $fields[$key] ) && trim( $fields[$key] ) !== '' ) {
				return trim( $fields[$key] );
			}
		}
		return '';
	}

	function gbEnsureUrlHasScheme( string $value ): string {
		$trimmed = trim( $value );
		if ( $trimmed === '' ) {
			return '';
		}
		if ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $trimmed ) ) {
			return $trimmed;
		}
		if ( substr( $trimmed, 0, 2 ) === '//' ) {
			return 'https:' . $trimmed;
		}
		return 'https://' . ltrim( $trimmed, '/' );
	}
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

$personData = [
	'name' => str_replace( 'People/', '', str_replace( '_', ' ', $pageTitle ) ),
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
		$infoboxFields = gbExtractInfoboxFields( $text );
		$legacyImageData = gbParseLegacyImageData( $text );

		$wikitext = '';
		if ( preg_match( '/\}\}(.+)$/s', $text, $matches ) ) {
			$wikitext = trim( $matches[1] );
		}

		if ( $wikitext !== '' ) {
			$descCacheKey = $latestRevisionId > 0 ? $wanCache->makeKey( 'giantbomb-person-desc', $latestRevisionId ) : null;
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
					error_log( 'Failed to parse person wikitext: ' . $e->getMessage() );
					$descHtml = $wikitext;
				}
				$descData = [ 'html' => $descHtml ];
				if ( $descCacheKey ) {
					$wanCache->set( $descCacheKey, $descData, $cacheTtl );
				}
			}
			$personData['description'] = $descData['html'] ?? '';
		}

		$personData['name'] = gbGetFieldValue( $infoboxFields, [ 'name' ] ) ?: $personData['name'];
		$personData['deck'] = gbGetFieldValue( $infoboxFields, [ 'deck' ] );
		$personData['image'] = gbGetFieldValue( $infoboxFields, [ 'image', 'infoboximage' ] );
		$personData['guid'] = gbGetFieldValue( $infoboxFields, [ 'guid', 'id' ] );
		$personData['realName'] = gbGetFieldValue( $infoboxFields, [ 'realname', 'real name' ] );
		$personData['gender'] = gbGetFieldValue( $infoboxFields, [ 'gender' ] );
		$personData['birthday'] = gbGetFieldValue( $infoboxFields, [ 'birthday', 'birthdate', 'birth date', 'born' ] );
		$personData['death'] = gbGetFieldValue( $infoboxFields, [ 'death', 'deathdate', 'death date', 'died' ] );
		$personData['hometown'] = gbGetFieldValue( $infoboxFields, [ 'hometown', 'birthplace', 'placeofbirth' ] );
		$personData['country'] = gbGetFieldValue( $infoboxFields, [ 'country', 'nationality' ] );
		$personData['twitter'] = gbGetFieldValue( $infoboxFields, [ 'twitter', 'twitterhandle' ] );
		$personData['website'] = gbGetFieldValue( $infoboxFields, [ 'website', 'url', 'site' ] );

		$rawAliases = gbGetFieldValue( $infoboxFields, [ 'aliases', 'alias' ] );
		if ( $rawAliases === '' && preg_match( '/\| Aliases=([^\n]+)/', $text, $aliasMatches ) ) {
			$rawAliases = trim( $aliasMatches[1] );
		}
		if ( $rawAliases !== '' ) {
			$aliases = array_filter(
				array_map(
					static function ( $alias ) {
						$alias = trim( $alias );
						return $alias !== '' ? str_replace( '_', ' ', $alias ) : null;
					},
					explode( ',', $rawAliases )
				)
			);
			$personData['aliases'] = array_values( $aliases );
			$personData['aliasesDisplay'] = implode( ', ', $personData['aliases'] );
		}

		$listFields = [
			'games' => 'Games',
			'characters' => 'Characters',
			'franchises' => 'Franchises',
			'concepts' => 'Concepts',
			'locations' => 'Locations',
			'objects' => 'Objects',
			'people' => 'People',
		];

		foreach ( $listFields as $key => $field ) {
			$rawList = gbGetFieldValue( $infoboxFields, [ strtolower( $field ), $field ] );
			if ( $rawList === '' && preg_match( '/\| ' . $field . '=([^\n]+)/', $text, $matches ) ) {
				$rawList = trim( $matches[1] );
			}
			if ( $rawList === '' ) {
				continue;
			}
			$items = array_filter(
				array_map(
					static function ( $item ) {
						$item = trim( $item );
						if ( $item === '' ) {
							return null;
						}
						$item = preg_replace( '#^(Games|Characters|Concepts|Locations|Objects|People|Franchises|Companies)/#', '', $item );
						return str_replace( '_', ' ', $item );
					},
					explode( ',', $rawList )
				)
			);
			$personData['relations'][$key] = array_values( $items );
		}

		$resolvedTemplateImage = gbResolveWikiImageUrl( $personData['image'] );
		$personData['image'] = $resolvedTemplateImage ?? '';

		if ( isset( $legacyImageData ) && is_array( $legacyImageData ) ) {
			if ( $personData['image'] === '' && isset( $legacyImageData['infobox'] ) ) {
				$infoboxUrl = gbBuildLegacyImageUrl(
					$legacyImageData['infobox'],
					[ 'scale_super', 'screen_kubrick', 'screen_medium', 'scale_large', 'scale_medium' ]
				);
				if ( $infoboxUrl !== null ) {
					$personData['image'] = $infoboxUrl;
				}
			}
			if ( isset( $legacyImageData['background'] ) ) {
				$backgroundUrl = gbBuildLegacyImageUrl(
					$legacyImageData['background'],
					[ 'screen_kubrick_wide', 'screen_kubrick', 'scale_super', 'scale_large', 'screen_medium' ]
				);
				if ( $backgroundUrl !== null ) {
					$personData['backgroundImage'] = $backgroundUrl;
				}
			}
			if ( $personData['image'] === '' && $personData['backgroundImage'] !== '' ) {
				$personData['image'] = $personData['backgroundImage'];
			}
		}
	}
} catch ( \Throwable $e ) {
	error_log( 'Person page error: ' . $e->getMessage() );
}

$stats = [];
if ( $personData['realName'] !== '' && strcasecmp( $personData['realName'], $personData['name'] ) !== 0 ) {
	$stats[] = [
		'label' => 'Real name',
		'value' => $personData['realName'],
	];
}
if ( $personData['gender'] !== '' ) {
	$stats[] = [
		'label' => 'Gender',
		'value' => $personData['gender'],
	];
}
if ( $personData['birthday'] !== '' ) {
	$stats[] = [
		'label' => 'Born',
		'value' => $personData['birthday'],
	];
}
if ( $personData['death'] !== '' ) {
	$stats[] = [
		'label' => 'Died',
		'value' => $personData['death'],
	];
}
$locationPieces = array_filter( [
	$personData['hometown'],
	$personData['country'],
] );
if ( !empty( $locationPieces ) ) {
	$stats[] = [
		'label' => 'Hometown',
		'value' => implode( ', ', array_unique( $locationPieces ) ),
	];
}
if ( $personData['twitter'] !== '' ) {
	$handle = ltrim( $personData['twitter'], '@' );
	$handle = $handle !== '' ? $handle : $personData['twitter'];
	$stats[] = [
		'label' => 'Twitter',
		'value' => '@' . $handle,
	];
}
if ( $personData['website'] !== '' ) {
	$stats[] = [
		'label' => 'Website',
		'value' => $personData['website'],
	];
}
$personData['stats'] = $stats;
$personData['hasStats'] = !empty( $stats );

$metaTitle = $personData['name'] !== ''
	? $personData['name'] . ' - Giant Bomb Wiki'
	: 'Giant Bomb Wiki';
$metaDescription = gbSanitizeMetaText( $personData['deck'] ?? '' );
if ( $metaDescription === '' ) {
	$metaDescription = gbSanitizeMetaText( $personData['description'] ?? '' );
}
if ( $metaDescription === '' && $personData['name'] !== '' ) {
	$metaDescription = 'Explore the Giant Bomb wiki entry for ' . $personData['name'] . '.';
}
$metaImage = $personData['image'] !== '' ? $personData['image'] : ( $personData['backgroundImage'] !== '' ? $personData['backgroundImage'] : null );
$canonicalUrl = rtrim( GB_PUBLIC_WIKI_HOST, '/' ) . $personData['url'];

$out = $this->getSkin()->getOutput();
if ( $personData['name'] !== '' ) {
	$out->setPageTitle( $personData['name'] );
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
	$twitterTags['twitter:image:alt'] = $personData['name'] !== '' ? $personData['name'] . ' portrait' : 'Giant Bomb person artwork';
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
		'name' => implode( ', ', array_unique( array_filter( [
			$personData['hometown'],
			$personData['country'],
		] ) ) ),
	] );
}
$sameAs = [];
if ( $personData['website'] !== '' ) {
	$sameAs[] = gbEnsureUrlHasScheme( $personData['website'] );
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
		static fn ( $name ) => [
			'@type' => 'VideoGame',
			'name' => $name,
		],
		array_slice( $personData['relations']['games'], 0, 5 )
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
		'structured-data-person',
		gbBuildJsonLdScript( $schemaJson )
	);
}

$templateDir = realpath( __DIR__ . '/../templates' );
$templateParser = new \MediaWiki\Html\TemplateParser( $templateDir );
$data = [
	'person' => $personData,
];

echo $templateParser->processTemplate( 'person-page', $data );


