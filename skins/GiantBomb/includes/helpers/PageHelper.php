<?php

namespace GiantBomb\Skin\Helpers;

use MediaWiki\MediaWikiServices;
use Title;

class PageHelper {
	public const LEGACY_UPLOAD_HOST = 'https://www.giantbomb.com';
	public const PUBLIC_WIKI_HOST = 'https://www.giantbomb.com';

	public static function parseLegacyImageData( string $text ): ?array {
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

	public static function chooseLegacySize( array $available, array $preferred ): ?string {
		foreach ( $preferred as $candidate ) {
			if ( in_array( $candidate, $available, true ) ) {
				return $candidate;
			}
		}
		return $available[0] ?? null;
	}

	public static function buildLegacyImageUrl( array $entry, array $preferredSizes ): ?string {
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
		$chosen = self::chooseLegacySize( $availableSizes, $preferredSizes );
		if ( $chosen === null ) {
			return null;
		}
		$normalizedPath = trim( $path, '/' );
		$relative = '/a/uploads/' . $chosen . '/' . ( $normalizedPath !== '' ? $normalizedPath . '/' : '' ) . $file;
		return self::LEGACY_UPLOAD_HOST . $relative;
	}

	public static function resolveWikiImageUrl( string $value ): ?string {
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

	public static function sanitizeMetaText( string $text, int $limit = 280 ): string {
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

	public static function buildMetaTag( array $attributes ): string {
		$parts = [];
		foreach ( $attributes as $name => $value ) {
			if ( $value === null || $value === '' ) {
				continue;
			}
			$parts[] = htmlspecialchars( $name, ENT_QUOTES, 'UTF-8' ) . '="' . htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ) . '"';
		}
		return '<meta ' . implode( ' ', $parts ) . ' />';
	}

	public static function buildJsonLdScript( string $json ): string {
		return '<script type="application/ld+json">' . $json . '</script>';
	}

	public static function extractInfoboxFields( string $text ): array {
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

	public static function getFieldValue( array $fields, array $keys ): string {
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

	public static function ensureUrlHasScheme( string $value ): string {
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

	public static function extractWikitext( string $text ): string {
		if ( preg_match( '/\}\}(.+)$/s', $text, $matches ) ) {
			return trim( $matches[1] );
		}
		return '';
	}

	public static function parseTemplateField( string $text, string $field ): string {
		if ( preg_match( '/\| ' . preg_quote( $field, '/' ) . '=([^\n]+)/', $text, $matches ) ) {
			return trim( $matches[1] );
		}
		return '';
	}

	public static function parseAliases( string $rawAliases ): array {
		if ( $rawAliases === '' ) {
			return [];
		}
		return array_values( array_filter(
			array_map(
				static function ( $alias ) {
					$alias = trim( $alias );
					return $alias !== '' ? str_replace( '_', ' ', $alias ) : null;
				},
				explode( ',', $rawAliases )
			)
		) );
	}

	public static function parseListField( string $rawList, array $prefixesToStrip = [] ): array {
		if ( $rawList === '' ) {
			return [];
		}
		$pattern = $prefixesToStrip ? '#^(' . implode( '|', $prefixesToStrip ) . ')/#' : '';
		return array_values( array_filter(
			array_map(
				static function ( $item ) use ( $pattern ) {
					$item = trim( $item );
					if ( $item === '' ) {
						return null;
					}
					if ( $pattern !== '' ) {
						$item = preg_replace( $pattern, '', $item );
					}
					return str_replace( '_', ' ', $item );
				},
				explode( ',', $rawList )
			)
		) );
	}

	public static function resolveImages( array &$data, ?array $legacyImageData ): void {
		$resolvedImage = self::resolveWikiImageUrl( $data['image'] ?? '' );
		$data['image'] = $resolvedImage ?? '';

		if ( !is_array( $legacyImageData ) ) {
			return;
		}

		if ( $data['image'] === '' && isset( $legacyImageData['infobox'] ) ) {
			$infoboxUrl = self::buildLegacyImageUrl(
				$legacyImageData['infobox'],
				[ 'scale_super', 'screen_kubrick', 'screen_medium', 'scale_large', 'scale_medium' ]
			);
			if ( $infoboxUrl !== null ) {
				$data['image'] = $infoboxUrl;
			}
		}

		if ( isset( $legacyImageData['background'] ) ) {
			$backgroundUrl = self::buildLegacyImageUrl(
				$legacyImageData['background'],
				[ 'screen_kubrick_wide', 'screen_kubrick', 'scale_super', 'scale_large', 'screen_medium' ]
			);
			if ( $backgroundUrl !== null ) {
				$data['backgroundImage'] = $backgroundUrl;
			}
		}

		if ( $data['image'] === '' && ( $data['backgroundImage'] ?? '' ) !== '' ) {
			$data['image'] = $data['backgroundImage'];
		}
	}

	public static function getMetaImage( string $image, string $backgroundImage ): ?string {
		if ( $image !== '' ) {
			return $image;
		}
		if ( $backgroundImage !== '' ) {
			return $backgroundImage;
		}
		return null;
	}

	public static function parseDescription(
		string $wikitext,
		Title $title,
		$wanCache,
		int $revisionId,
		string $cacheKeyPrefix,
		int $cacheTtl = 3600
	): string {
		if ( $wikitext === '' ) {
			return '';
		}

		$descCacheKey = $revisionId > 0 ? $wanCache->makeKey( $cacheKeyPrefix, $revisionId ) : null;
		$descData = $descCacheKey ? $wanCache->get( $descCacheKey ) : null;

		if ( is_array( $descData ) ) {
			return $descData['html'] ?? '';
		}

		$descHtml = '';
		try {
			$services = MediaWikiServices::getInstance();
			$parser = $services->getParser();
			$parserOptions = \ParserOptions::newFromAnon();
			$parserOutput = $parser->parse( $wikitext, $title, $parserOptions );
			$descHtml = $parserOutput->getText( [
				'allowTOC' => false,
				'enableSectionEditLinks' => false,
				'wrapperDivClass' => ''
			] );
		} catch ( \Throwable $e ) {
			error_log( "Failed to parse wikitext for {$cacheKeyPrefix}: " . $e->getMessage() );
			$descHtml = $wikitext;
		}

		if ( $descCacheKey ) {
			$wanCache->set( $descCacheKey, [ 'html' => $descHtml ], $cacheTtl );
		}

		return $descHtml;
	}

	public static function addOpenGraphTags( $out, array $tags, ?string $metaImage = null ): void {
		if ( $metaImage ) {
			$tags['og:image'] = $metaImage;
		}
		foreach ( $tags as $property => $content ) {
			if ( $content === '' || $content === null ) {
				continue;
			}
			$out->addHeadItem(
				'meta-' . str_replace( ':', '-', $property ),
				self::buildMetaTag( [ 'property' => $property, 'content' => $content ] )
			);
		}
	}

	public static function addTwitterTags( $out, array $tags, ?string $metaImage = null, string $imageAlt = '' ): void {
		if ( $metaImage ) {
			$tags['twitter:image'] = $metaImage;
			if ( $imageAlt !== '' ) {
				$tags['twitter:image:alt'] = $imageAlt;
			}
		}
		foreach ( $tags as $name => $content ) {
			if ( $content === '' || $content === null ) {
				continue;
			}
			$out->addHeadItem(
				'meta-' . str_replace( [ ':', '/' ], '-', $name ),
				self::buildMetaTag( [ 'name' => $name, 'content' => $content ] )
			);
		}
	}

	public static function addStructuredData( $out, array $schema, string $key ): void {
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
			$out->addHeadItem( 'structured-data-' . $key, self::buildJsonLdScript( $schemaJson ) );
		}
	}

	public static function cleanPageName( string $pageTitle, string $namespace ): string {
		return str_replace( $namespace . '/', '', str_replace( '_', ' ', $pageTitle ) );
	}

	public static function extractReleaseData( string $releasesText ): array {
		$result = [ 'count' => 0, 'items' => [] ];
		if ( trim( $releasesText ) === '' ) {
			return $result;
		}

		$regionMap = [
			1 => 'United States', 2 => 'United Kingdom', 6 => 'Japan', 11 => 'Australia',
		];

		preg_match_all( '/\{\{ReleaseSubobject([^}]+)\}\}/s', $releasesText, $releaseMatches );
		foreach ( $releaseMatches[1] as $releaseContent ) {
			$release = [
				'name' => '', 'platform' => '', 'region' => '',
				'releaseDate' => 'N/A', 'rating' => 'N/A', 'resolutions' => 'N/A',
				'soundSystems' => 'N/A', 'widescreenSupport' => 'N/A',
			];

			if ( preg_match( '/\|Name=([^\n|]+)/', $releaseContent, $match ) ) {
				$release['name'] = trim( $match[1] );
			}
			if ( preg_match( '/\|Platform=([^\n|]+)/', $releaseContent, $match ) ) {
				$platform = trim( $match[1] );
				$release['platform'] = str_replace( '_', ' ', str_replace( 'Platforms/', '', $platform ) );
			}
			if ( preg_match( '/\|Region=([^\n|]+)/', $releaseContent, $match ) ) {
				$region = trim( $match[1] );
				$release['region'] = $regionMap[(int)$region] ?? $region;
			}
			if ( preg_match( '/\|ReleaseDate=([^\n|]+)/', $releaseContent, $match ) ) {
				$date = trim( $match[1] );
				if ( $date !== '' && $date !== 'None' ) {
					$release['releaseDate'] = $date;
				}
			}
			if ( preg_match( '/\|Rating=([^\n|]+)/', $releaseContent, $match ) ) {
				$rating = trim( $match[1] );
				if ( $rating !== '' ) {
					$release['rating'] = str_replace( '_', ' ', str_replace( 'Ratings/', '', $rating ) );
				}
			}

			$displayName = $release['platform'];
			if ( $displayName !== '' && $release['region'] !== '' ) {
				$displayName .= ' (' . $release['region'] . ')';
			} elseif ( $displayName === '' ) {
				$displayName = $release['name'];
			}
			$release['displayName'] = $displayName;

			$result['items'][] = $release;
		}

		$result['count'] = count( $result['items'] );
		return $result;
	}
}

