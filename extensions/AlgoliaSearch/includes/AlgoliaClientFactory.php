<?php

namespace MediaWiki\Extension\AlgoliaSearch;

use MediaWiki\Config\Config;

class AlgoliaClientFactory {
	public static function createFromConfig( Config $config ) {
		$enabled = (bool)$config->get( 'AlgoliaSearchEnabled' );
		if ( !$enabled ) {
			return null;
		}

		$appId = trim( (string)$config->get( 'AlgoliaAppId' ) );
		$apiKey = trim( (string)$config->get( 'AlgoliaAdminApiKey' ) );

		if ( $appId === '' || $apiKey === '' ) {
			return null;
		}

		if ( class_exists( '\Algolia\AlgoliaSearch\SearchClient' ) ) {
			return \Algolia\AlgoliaSearch\SearchClient::create( $appId, $apiKey );
		}

		// Fallback: minimal REST client without Composer dependency
		return \MediaWiki\Extension\AlgoliaSearch\AlgoliaRestClient::create( $appId, $apiKey );
	}

	public static function getIndexFromConfig( Config $config ) {
		$client = self::createFromConfig( $config );
		if ( !$client ) {
			return null;
		}
		$indexName = trim( (string)$config->get( 'AlgoliaIndexName' ) );
		if ( $indexName === '' ) {
			return null;
		}
		return $client->initIndex( $indexName );
	}
}


