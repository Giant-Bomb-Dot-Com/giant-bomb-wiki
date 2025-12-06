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

$characterData = [
	'name' => str_replace( 'Characters/', '', str_replace( '_', ' ', $pageTitle ) ),
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
		$legacyImageData = gbParseLegacyImageData( $text );

		$wikitext = '';
		if ( preg_match( '/\}\}(.+)$/s', $text, $matches ) ) {
			$wikitext = trim( $matches[1] );
		}

		if ( $wikitext !== '' ) {
			$descCacheKey = $latestRevisionId > 0 ? $wanCache->makeKey( 'giantbomb-character-desc', $latestRevisionId ) : null;
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
					error_log( 'Failed to parse character wikitext: ' . $e->getMessage() );
					$descHtml = $wikitext;
				}
				$descData = [ 'html' => $descHtml ];
				if ( $descCacheKey ) {
					$wanCache->set( $descCacheKey, $descData, $cacheTtl );
				}
			}
			$characterData['description'] = $descData['html'] ?? '';
		}

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
			if ( preg_match( '/\| ' . $field . '=([^\n]+)/', $text, $matches ) ) {
				$value = trim( $matches[1] );
				if ( $value !== '' ) {
					$characterData[$key] = $value;
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
			$characterData['aliases'] = array_values( $aliases );
			$characterData['aliasesDisplay'] = implode( ', ', $characterData['aliases'] );
		}

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

		foreach ( $listFields as $key => $field ) {
			if ( preg_match( '/\| ' . $field . '=([^\n]+)/', $text, $matches ) ) {
				$items = array_filter(
					array_map(
						static function ( $item ) {
							$item = trim( $item );
							if ( $item === '' ) {
								return null;
							}
							$item = preg_replace( '#^(Characters|Franchises|Concepts|Games|Locations|Objects|People|Companies)/#', '', $item );
							return str_replace( '_', ' ', $item );
						},
						explode( ',', $matches[1] )
					)
				);
				$characterData[$key] = array_values( $items );
			}
		}

		$resolvedTemplateImage = gbResolveWikiImageUrl( $characterData['image'] );
		$characterData['image'] = $resolvedTemplateImage ?? '';

		if ( isset( $legacyImageData ) && is_array( $legacyImageData ) ) {
			if ( $characterData['image'] === '' && isset( $legacyImageData['infobox'] ) ) {
				$infoboxUrl = gbBuildLegacyImageUrl(
					$legacyImageData['infobox'],
					[ 'scale_super', 'screen_kubrick', 'screen_medium', 'scale_large', 'scale_medium' ]
				);
				if ( $infoboxUrl !== null ) {
					$characterData['image'] = $infoboxUrl;
				}
			}
			if ( isset( $legacyImageData['background'] ) ) {
				$backgroundUrl = gbBuildLegacyImageUrl(
					$legacyImageData['background'],
					[ 'screen_kubrick_wide', 'screen_kubrick', 'scale_super', 'scale_large', 'screen_medium' ]
				);
				if ( $backgroundUrl !== null ) {
					$characterData['backgroundImage'] = $backgroundUrl;
				}
			}
			if ( $characterData['image'] === '' && $characterData['backgroundImage'] !== '' ) {
				$characterData['image'] = $characterData['backgroundImage'];
			}
		}
	}
} catch ( \Throwable $e ) {
	error_log( 'Character page error: ' . $e->getMessage() );
}

$stats = [];
if ( $characterData['realName'] !== '' ) {
	$stats[] = [
		'label' => 'Real name',
		'value' => $characterData['realName'],
	];
}
if ( $characterData['gender'] !== '' ) {
	$stats[] = [
		'label' => 'Gender',
		'value' => $characterData['gender'],
	];
}
if ( $characterData['birthday'] !== '' ) {
	$stats[] = [
		'label' => 'Birthday',
		'value' => $characterData['birthday'],
	];
}
$characterData['stats'] = $stats;
$characterData['hasStats'] = !empty( $stats );

$metaTitle = $characterData['name'] !== ''
	? $characterData['name'] . ' character - Giant Bomb Wiki'
	: 'Giant Bomb Wiki';
$metaDescription = gbSanitizeMetaText( $characterData['deck'] ?? '' );
if ( $metaDescription === '' ) {
	$metaDescription = gbSanitizeMetaText( $characterData['description'] ?? '' );
}
if ( $metaDescription === '' && $characterData['name'] !== '' ) {
	$metaDescription = 'Explore the Giant Bomb wiki entry for ' . $characterData['name'] . '.';
}
$metaImage = $characterData['image'] !== '' ? $characterData['image'] : ( $characterData['backgroundImage'] !== '' ? $characterData['backgroundImage'] : null );
$canonicalUrl = rtrim( GB_PUBLIC_WIKI_HOST, '/' ) . $characterData['url'];

$out = $this->getSkin()->getOutput();
if ( $characterData['name'] !== '' ) {
	$out->setPageTitle( $characterData['name'] );
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
	$twitterTags['twitter:image:alt'] = $characterData['name'] !== '' ? $characterData['name'] . ' character art' : 'Giant Bomb character cover art';
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
		static fn ( $name ) => [
			'@type' => 'CreativeWorkSeries',
			'name' => $name,
		],
		array_slice( $characterData['franchises'], 0, 3 )
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
		'structured-data-character',
		gbBuildJsonLdScript( $schemaJson )
	);
}

$templateDir = realpath( __DIR__ . '/../templates' );
$templateParser = new \MediaWiki\Html\TemplateParser( $templateDir );
$data = [
	'character' => $characterData,
	'hasBasicInfo' => !empty( $characterData['deck'] ) || !empty( $characterData['realName'] ),
];

echo $templateParser->processTemplate( 'character-page', $data );


