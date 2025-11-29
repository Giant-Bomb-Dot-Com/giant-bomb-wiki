<?php

namespace MediaWiki\Extension\AlgoliaSearch;

class AlgoliaRestClient {
	private string $appId;
	private string $apiKey;

	public function __construct( string $appId, string $apiKey ) {
		$this->appId = $appId;
		$this->apiKey = $apiKey;
	}

	public static function create( string $appId, string $apiKey ): self {
		return new self( $appId, $apiKey );
	}

	public function initIndex( string $indexName ): AlgoliaRestIndex {
		return new AlgoliaRestIndex( $this->appId, $this->apiKey, $indexName );
	}
}

class AlgoliaRestIndex {
	private string $appId;
	private string $apiKey;
	private string $indexName;

	public function __construct( string $appId, string $apiKey, string $indexName ) {
		$this->appId = $appId;
		$this->apiKey = $apiKey;
		$this->indexName = $indexName;
	}

	public function setSettings( array $settings ): void {
		$this->request(
			'PUT',
			"/1/indexes/{$this->indexName}/settings",
			$settings
		);
	}

	public function saveObjects( array $objects ): void {
		$requests = [];
		foreach ( $objects as $object ) {
			if ( is_array( $object ) ) {
				$requests[] = [
					'action' => 'updateObject',
					'body' => $object,
				];
			}
		}
		if ( $requests ) {
			$this->request(
				'POST',
				"/1/indexes/{$this->indexName}/batch",
				[ 'requests' => $requests ]
			);
		}
	}

	private function request( string $method, string $path, array $body ): array {
		$url = "https://{$this->appId}.algolia.net{$path}";
		$payload = json_encode( $body, JSON_UNESCAPED_SLASHES );

		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			"X-Algolia-Application-Id: {$this->appId}",
			"X-Algolia-API-Key: {$this->apiKey}",
		] );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 60 );

		$response = curl_exec( $ch );
		if ( $response === false ) {
			$error = curl_error( $ch );
			curl_close( $ch );
			throw new \RuntimeException( "Algolia request failed: $error" );
		}
		$status = curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		curl_close( $ch );

		$decoded = json_decode( $response, true );
		if ( $status < 200 || $status >= 300 ) {
			$msg = is_array( $decoded ) ? ( $decoded['message'] ?? $response ) : $response;
			throw new \RuntimeException( "Algolia HTTP $status: $msg" );
		}
		return is_array( $decoded ) ? $decoded : [];
	}
}


