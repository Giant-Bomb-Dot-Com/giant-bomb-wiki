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

$platformData = [
	'name' => str_replace( 'Platforms/', '', str_replace( '_', ' ', $pageTitle ) ),
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
		$legacyImageData = gbParseLegacyImageData( $text );

		$wikitext = '';
		if ( preg_match( '/\}\}(.+)$/s', $text, $matches ) ) {
			$wikitext = trim( $matches[1] );
		}

		if ( $wikitext !== '' ) {
			$descCacheKey = $latestRevisionId > 0 ? $wanCache->makeKey( 'giantbomb-platform-desc', $latestRevisionId ) : null;
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
					error_log( 'Failed to parse platform wikitext: ' . $e->getMessage() );
					$descHtml = $wikitext;
				}
				$descData = [ 'html' => $descHtml ];
				if ( $descCacheKey ) {
					$wanCache->set( $descCacheKey, $descData, $cacheTtl );
				}
			}
			$platformData['description'] = $descData['html'] ?? '';
		}

		if ( preg_match( '/\| Name=([^\n]+)/', $text, $matches ) ) {
			$platformData['name'] = trim( $matches[1] );
		}
		if ( preg_match( '/\| Deck=([^\n]+)/', $text, $matches ) ) {
			$platformData['deck'] = trim( $matches[1] );
		}
		if ( preg_match( '/\| Image=([^\n]+)/', $text, $matches ) ) {
			$platformData['image'] = trim( $matches[1] );
		}
		if ( preg_match( '/\| ReleaseDate=([^\n]+)/', $text, $matches ) ) {
			$platformData['releaseDate'] = trim( $matches[1] );
		}
		if ( preg_match( '/\| ReleaseDateType=([^\n]+)/', $text, $matches ) ) {
			$platformData['releaseDateType'] = trim( $matches[1] );
		}
		if ( preg_match( '/\| ShortName=([^\n]+)/', $text, $matches ) ) {
			$platformData['shortName'] = trim( $matches[1] );
		}
		if ( preg_match( '/\| InstallBase=([^\n]+)/', $text, $matches ) ) {
			$platformData['installBase'] = trim( $matches[1] );
		}
		if ( preg_match( '/\| OnlineSupport=([^\n]+)/', $text, $matches ) ) {
			$platformData['onlineSupport'] = ucfirst( strtolower( trim( $matches[1] ) ) );
		}
		if ( preg_match( '/\| OriginalPrice=([^\n]+)/', $text, $matches ) ) {
			$platformData['originalPrice'] = trim( $matches[1] );
		}
		if ( preg_match( '/\| Manufacturer=([^\n]+)/', $text, $matches ) ) {
			$manufacturers = array_filter(
				array_map(
					static function ( $item ) {
						$item = trim( $item );
						if ( $item === '' ) {
							return null;
						}
						if ( stripos( $item, 'Companies/' ) === 0 ) {
							$item = substr( $item, strlen( 'Companies/' ) );
						}
						return str_replace( '_', ' ', $item );
					},
					explode( ',', $matches[1] )
				)
			);
			$platformData['manufacturers'] = array_values( $manufacturers );
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
			$platformData['aliases'] = array_values( $aliases );
		}
		if ( preg_match( '/\| Guid=([^\n]+)/', $text, $matches ) ) {
			$platformData['guid'] = trim( $matches[1] );
		}

		$resolvedTemplateImage = gbResolveWikiImageUrl( $platformData['image'] );
		$platformData['image'] = $resolvedTemplateImage ?? '';

		if ( isset( $legacyImageData ) && is_array( $legacyImageData ) ) {
			if ( $platformData['image'] === '' && isset( $legacyImageData['infobox'] ) ) {
				$infoboxUrl = gbBuildLegacyImageUrl(
					$legacyImageData['infobox'],
					[ 'scale_super', 'screen_kubrick', 'screen_medium', 'scale_large', 'scale_medium' ]
				);
				if ( $infoboxUrl !== null ) {
					$platformData['image'] = $infoboxUrl;
				}
			}
			if ( isset( $legacyImageData['background'] ) ) {
				$backgroundUrl = gbBuildLegacyImageUrl(
					$legacyImageData['background'],
					[ 'screen_kubrick_wide', 'screen_kubrick', 'scale_super', 'scale_large', 'screen_medium' ]
				);
				if ( $backgroundUrl !== null ) {
					$platformData['backgroundImage'] = $backgroundUrl;
				}
			}
			if ( $platformData['image'] === '' && $platformData['backgroundImage'] !== '' ) {
				$platformData['image'] = $platformData['backgroundImage'];
			}
		}
	}
} catch ( \Throwable $e ) {
	error_log( 'Platform page error: ' . $e->getMessage() );
}

$platformData['aliasesDisplay'] = $platformData['aliases'] ? implode( ', ', $platformData['aliases'] ) : '';

$stats = [];
if ( $platformData['releaseDate'] !== '' ) {
	$stats[] = [
		'label' => 'Released',
		'value' => $platformData['releaseDate'],
	];
}
if ( $platformData['installBase'] !== '' ) {
	$stats[] = [
		'label' => 'Install base',
		'value' => $platformData['installBase'],
	];
}
if ( $platformData['originalPrice'] !== '' ) {
	$stats[] = [
		'label' => 'Launch price',
		'value' => $platformData['originalPrice'],
	];
}
if ( $platformData['onlineSupport'] !== '' ) {
	$stats[] = [
		'label' => 'Online support',
		'value' => $platformData['onlineSupport'],
	];
}
if ( !empty( $platformData['manufacturers'] ) ) {
	$stats[] = [
		'label' => count( $platformData['manufacturers'] ) > 1 ? 'Manufacturers' : 'Manufacturer',
		'value' => implode( ', ', $platformData['manufacturers'] ),
	];
}
$platformData['stats'] = $stats;
$platformData['hasStats'] = !empty( $stats );

$metaTitle = $platformData['name'] !== ''
	? $platformData['name'] . ' platform - Giant Bomb Wiki'
	: 'Giant Bomb Wiki';
$metaDescription = gbSanitizeMetaText( $platformData['deck'] ?? '' );
if ( $metaDescription === '' ) {
	$metaDescription = gbSanitizeMetaText( $platformData['description'] ?? '' );
}
if ( $metaDescription === '' && $platformData['name'] !== '' ) {
	$metaDescription = 'Learn about the ' . $platformData['name'] . ' platform on the Giant Bomb wiki.';
}
$metaImage = $platformData['image'] !== '' ? $platformData['image'] : ( $platformData['backgroundImage'] !== '' ? $platformData['backgroundImage'] : null );
$canonicalUrl = rtrim( GB_PUBLIC_WIKI_HOST, '/' ) . $platformData['url'];

$out = $this->getSkin()->getOutput();
if ( $platformData['name'] !== '' ) {
	$out->setPageTitle( $platformData['name'] );
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
	'og:type' => 'website',
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
	$twitterTags['twitter:image:alt'] = $platformData['name'] !== '' ? $platformData['name'] . ' hardware' : 'Giant Bomb platform cover art';
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
	'@type' => 'VideoGamePlatform',
	'name' => $platformData['name'],
	'url' => $canonicalUrl,
	'description' => $metaDescription,
];
if ( $platformData['guid'] !== '' ) {
	$schema['identifier'] = $platformData['guid'];
}
if ( $metaImage ) {
	$schema['image'] = $metaImage;
}
if ( $platformData['releaseDate'] !== '' ) {
	$schema['datePublished'] = $platformData['releaseDate'];
}
if ( !empty( $platformData['manufacturers'] ) ) {
	$schema['manufacturer'] = array_map(
		static fn ( $name ) => [
			'@type' => 'Organization',
			'name' => $name,
		],
		array_slice( $platformData['manufacturers'], 0, 3 )
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
		'structured-data-platform',
		gbBuildJsonLdScript( $schemaJson )
	);
}

$templateDir = realpath( __DIR__ . '/../templates' );
$templateParser = new \MediaWiki\Html\TemplateParser( $templateDir );
$data = [
	'platform' => $platformData,
	'hasBasicInfo' => !empty( $platformData['deck'] ) || !empty( $platformData['releaseDate'] ),
];

echo $templateParser->processTemplate( 'platform-page', $data );


