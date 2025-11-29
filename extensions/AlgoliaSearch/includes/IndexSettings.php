<?php

namespace MediaWiki\Extension\AlgoliaSearch;

class IndexSettings {
	public static function getDefaultIndexSettings(): array {
		return [
			'searchableAttributes' => [
				'title',
				'aliases',
				'unordered(categories)',
				'unordered(tags)',
				'excerpt',
			],
			'attributesForFaceting' => [
				'filterOnly(type)',
				'categories',
				'tags',
			],
			'customRanking' => [
				'desc(publishDate)',
				'desc(_updatedAt)',
			],
			'typoTolerance' => true,
			'ignorePlurals' => true,
			'removeStopWords' => true,
		];
	}

	public static function applyDefaultIndexSettings( $index ): void {
		$settings = self::getDefaultIndexSettings();
		$index->setSettings( $settings );
	}
}


