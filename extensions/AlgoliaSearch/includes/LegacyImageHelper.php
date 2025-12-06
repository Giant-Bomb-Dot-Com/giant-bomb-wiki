<?php

namespace MediaWiki\Extension\AlgoliaSearch;

use MediaWiki\MediaWikiServices;
use Title;

class LegacyImageHelper {
	private const LEGACY_UPLOAD_HOST = 'https://www.giantbomb.com';

	/** @var array<int,string> */
	private const DEFAULT_FULL_SIZES = [
		'scale_super',
		'screen_kubrick',
		'screen_kubrick_wide',
		'scale_large',
	];

	/** @var array<int,string> */
	private const DEFAULT_THUMB_SIZES = [
		'screen_kubrick',
		'screen_medium',
		'scale_medium',
		'scale_large',
		'scale_small',
		'square_medium',
	];

	/**
	 * Locate a legacy image for the given title using preferred keys and sizes.
	 *
	 * @param Title $title
	 * @param array<int,string> $preferredKeys
	 * @param array<int,string> $fullPreferredSizes
	 * @param array<int,string> $thumbPreferredSizes
	 * @return array{
	 *   full: ?string,
	 *   thumb: ?string,
	 *   caption: ?string,
	 *   file: ?string,
	 *   sourceKey: ?string
	 * }|null
	 */
	public static function findLegacyImageForTitle(
		Title $title,
		array $preferredKeys = [ 'infobox', 'background' ],
		array $fullPreferredSizes = self::DEFAULT_FULL_SIZES,
		array $thumbPreferredSizes = self::DEFAULT_THUMB_SIZES
	): ?array {
		$entries = self::fetchLegacyImageEntries( $title );
		if ( !$entries ) {
			return null;
		}

		foreach ( $preferredKeys as $key ) {
			if ( isset( $entries[$key] ) && is_array( $entries[$key] ) ) {
				$full = self::buildLegacyImageUrl( $entries[$key], $fullPreferredSizes );
				$thumb = self::buildLegacyImageUrl( $entries[$key], $thumbPreferredSizes );
				if ( $full || $thumb ) {
					if ( !$thumb ) {
						$thumb = $full;
					}
					$caption = null;
					if ( isset( $entries[$key]['caption'] ) && $entries[$key]['caption'] !== '' ) {
						$caption = (string)$entries[$key]['caption'];
					}
					return [
						'full' => $full ?? $thumb,
						'thumb' => $thumb ?? $full,
						'caption' => $caption,
						'file' => isset( $entries[$key]['file'] ) ? (string)$entries[$key]['file'] : null,
						'sourceKey' => $key,
					];
				}
			}
		}

		return null;
	}

	/**
	 * Retrieve the decoded legacy image JSON entries for a title.
	 *
	 * @param Title $title
	 * @return array<string,array>|null
	 */
	public static function fetchLegacyImageEntries( Title $title ): ?array {
		try {
			$services = MediaWikiServices::getInstance();
			$page = $services->getWikiPageFactory()->newFromTitle( $title );
			if ( !$page ) {
				return null;
			}
			$content = $page->getContent();
			if ( !$content ) {
				return null;
			}
			$text = $content->getText();
			if ( $text === '' || stripos( $text, 'imageData' ) === false ) {
				return null;
			}
			return self::parseLegacyImageDataFromText( $text );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Parse the inline legacy image JSON block from raw wikitext.
	 *
	 * @param string $text
	 * @return array<string,array>|null
	 */
	public static function parseLegacyImageDataFromText( string $text ): ?array {
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

	/**
	 * Build a fully qualified legacy upload URL for a given entry and preferred sizes.
	 *
	 * @param array<string,mixed> $entry
	 * @param array<int,string> $preferredSizes
	 * @return string|null
	 */
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
		$useSize = self::chooseLegacySize( $availableSizes, $preferredSizes );
		if ( $useSize === null ) {
			return null;
		}
		$normalizedPath = trim( $path, '/' );
		$relative = '/a/uploads/' . $useSize . '/' . ( $normalizedPath !== '' ? $normalizedPath . '/' : '' ) . $file;
		return self::LEGACY_UPLOAD_HOST . $relative;
	}

	/**
	 * @param array<int,string> $available
	 * @param array<int,string> $preferred
	 * @return string|null
	 */
	private static function chooseLegacySize( array $available, array $preferred ): ?string {
		foreach ( $preferred as $candidate ) {
			if ( in_array( $candidate, $available, true ) ) {
				return $candidate;
			}
		}
		return $available[0] ?? null;
	}
}


