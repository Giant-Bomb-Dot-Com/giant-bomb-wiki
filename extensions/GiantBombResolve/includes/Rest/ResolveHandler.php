<?php

namespace MediaWiki\Extension\GiantBombResolve\Rest;

use ApiMain;
use FauxRequest;
use MediaWiki\Config\Config;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\SimpleRequestInterface;
use RequestContext;
use Title;
use User;

class ResolveHandler extends SimpleHandler {

	private const GUID_PATTERN = '/^(\d{3,4})-(\d{1,12})$/';

	/** @var Config */
	private $config;

	/** @var array<string> */
	private $allowedFields = [];

	/** @var \Psr\Log\LoggerInterface */
	private $logger;

	public function __construct() {
		$services = MediaWikiServices::getInstance();
		$this->config = $services->getMainConfig();
		$this->logger = LoggerFactory::getInstance( 'GiantBombResolve' );
		$this->allowedFields = array_values(
			array_unique(
				array_map(
					static function ( $field ) {
						return trim( (string)$field );
					},
					(array)$this->config->get( 'GiantBombResolveFields' )
				)
			)
		);
	}

	public function getParamSettings() {
		return [];
	}

	public function needsWriteAccess() {
		return false;
	}

	public function checkPermissions() {
		$this->assertRequestIsAllowed();
	}

	public function execute() {
		$request = $this->getRequest();
		$queryParams = method_exists( $request, 'getQueryParams' )
			? $request->getQueryParams()
			: [];
		$guids = $this->parseGuids( $queryParams['guids'] ?? null );
		$this->enforceBatchLimit( count( $guids ) );
		$fields = $this->parseFields( $queryParams['fields'] ?? null );

		$records = [];
		$errors = 0;
		$missing = 0;
		foreach ( $guids as $guid ) {
			$records[] = $this->resolveGuid( $guid, $fields );
		}
		foreach ( $records as $record ) {
			if ( $record['status'] === 'missing' ) {
				$missing++;
			} elseif ( $record['status'] === 'error' || $record['status'] === 'invalid' ) {
				$errors++;
			}
		}

		$this->logger->info(
			'Resolved GUID batch',
			[
				'count' => count( $records ),
				'missing' => $missing,
				'errors' => $errors,
			]
		);

		return $this->createResponse( [
			'guids' => $records,
			'cache' => [
				'ttl' => 3600,
				'staleIfError' => 86400,
			],
		] );
	}

	private function resolveGuid( string $guid, array $fields ): array {
		$parts = $this->splitGuid( $guid );
		if ( !$parts ) {
			return $this->makeInvalidRecord( $guid, 'invalid-guid' );
		}

		try {
			$data = $this->fetchGuidData( $guid, $fields );
		} catch ( \Throwable $e ) {
			$this->logger->error(
				'Failed resolving GUID',
				[
					'guid' => $guid,
					'exception' => $e,
				]
			);
			return $this->makeErrorRecord( $guid, 'internal-error' );
		}

		if ( $data === null ) {
			return $this->makeMissingRecord( $guid, $parts['assocTypeId'], $parts['assocId'] );
		}

		return [
			'guid' => $guid,
			'assocTypeId' => $parts['assocTypeId'],
			'assocId' => $parts['assocId'],
			'status' => 'ok',
			'data' => $data,
		];
	}

	private function fetchGuidData( string $guid, array $fields ): ?array {
		$query = '[[Has guid::' . $guid . ']]';
		if ( in_array( 'image', $fields, true ) || in_array( 'printouts', $fields, true ) ) {
			$query .= '|?Has image=Primary image';
		}
		$timer = microtime( true );
		$result = $this->runAskQuery( $query );
		$elapsed = microtime( true ) - $timer;
		$threshold = (float)$this->config->get( 'GiantBombResolveTimeout' );
		if ( $threshold > 0 && $elapsed > $threshold ) {
			$this->logger->warning(
				'Slow resolve query',
				[ 'guid' => $guid, 'duration' => $elapsed ]
			);
		}
		if ( !$result ) {
			return null;
		}
		$first = reset( $result );
		$pageKey = key( $result );
		$titleText = $pageKey ?? ( $first['fulltext'] ?? null );

		$data = [];
		$baseOrigin = $this->getRewriteBaseOrigin();

		foreach ( $fields as $field ) {
			switch ( $field ) {
				case 'displaytitle':
					$data['displayTitle'] = $first['displaytitle'] ?? null;
					break;
				case 'fullurl':
					$fullUrl = $first['fullurl'] ?? null;
					if ( $fullUrl && $baseOrigin ) {
						$data['fullUrl'] = $this->rewriteFullUrl( $fullUrl, $baseOrigin, $titleText );
					} else {
						$data['fullUrl'] = $fullUrl;
					}
					break;
				case 'fulltext':
					$data['fullText'] = $first['fulltext'] ?? $titleText;
					break;
				case 'namespace':
					$data['namespace'] = $first['namespace'] ?? null;
					break;
				case 'pageid':
					$data['pageId'] = $first['pageid'] ?? null;
					break;
				case 'printouts':
					$data['printouts'] = $first['printouts'] ?? [];
					break;
				case 'image':
					$image = $this->extractPrimaryImageData( $first['printouts'] ?? [] );
					if ( $image !== null ) {
						$data['image'] = $image;
					}
					break;
			}
		}

		if ( $titleText ) {
			$title = Title::newFromText( $titleText );
			if ( $title ) {
				$data['title'] = $title->getText();
				$data['prefixedTitle'] = $title->getPrefixedText();
			}
		}

		return $data;
	}

	private function rewriteFullUrl( string $wikiUrl, string $baseOrigin, ?string $titleText ): string {
		$parsed = parse_url( $wikiUrl );
		if ( $titleText ) {
			$title = Title::newFromText( $titleText );
			if ( $title ) {
				return rtrim( $baseOrigin, '/' ) . '/' . $title->getPrefixedURL();
			}
		}
		if ( $parsed && isset( $parsed['path'] ) ) {
			return rtrim( $baseOrigin, '/' ) . '/' . ltrim( $parsed['path'], '/' );
		}
		return $wikiUrl;
	}

	private function getRewriteBaseOrigin(): ?string {
		$baseOrigin = $this->config->get( 'GiantBombResolveBaseOrigin' );
		if ( is_string( $baseOrigin ) ) {
			$trimmed = trim( $baseOrigin );
			if ( $trimmed !== '' ) {
				return rtrim( $trimmed, '/' );
			}
		}

		$canonical = $this->config->get( 'CanonicalServer' );
		if ( is_string( $canonical ) && $canonical !== '' ) {
			return rtrim( $canonical, '/' ) . '/wiki';
		}

		return null;
	}

	private function runAskQuery( string $query ): array {
		$params = [
			'action' => 'ask',
			'query' => $query,
			'format' => 'json',
		];

		$fauxRequest = new FauxRequest( $params );
		$context = new RequestContext();
		$context->setRequest( $fauxRequest );
		$systemUser = User::newSystemUser( 'GiantBombResolve', [ 'steal' => true ] );
		if ( $systemUser ) {
			$context->setUser( $systemUser );
		}

		$api = new ApiMain( $context, true );
		$api->execute();
		$data = $api->getResult()->getResultData( null, [
			'Strip' => 'all',
			'BC' => [],
		] );

		return $data['query']['results'] ?? [];
	}

	/**
	 * @param string|string[]|null $guids
	 */
	private function parseGuids( $guids ): array {
		$values = $this->normalizeQueryValues( $guids );
		if ( !$values ) {
			throw new HttpException( 'resolve-missing-guids', 400 );
		}
		$out = [];
		foreach ( $values as $part ) {
			if ( $part === '' ) {
				continue;
			}
			if ( !preg_match( self::GUID_PATTERN, $part ) ) {
				throw new HttpException( 'resolve-invalid-guid', 400, [ 'guid' => $part ] );
			}
			$out[] = $part;
		}
		if ( !$out ) {
			throw new HttpException( 'resolve-missing-guids', 400 );
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param string|string[]|null $fields
	 */
	private function parseFields( $fields ): array {
		$allowed = $this->allowedFields ?: [ 'displaytitle', 'fullurl', 'fulltext', 'pageid', 'namespace' ];
		$values = $this->normalizeQueryValues( $fields );
		if ( !$values ) {
			return $allowed;
		}
		$requested = array_filter( array_map( 'trim', $values ) );
		$filtered = [];
		foreach ( $requested as $field ) {
			if ( $field !== '' && in_array( $field, $allowed, true ) ) {
				$filtered[] = $field;
			}
		}
		return $filtered ?: $allowed;
	}

	/**
	 * Normalize query params that may arrive as strings, csv strings, or arrays.
	 *
	 * @param string|string[]|null $value
	 * @return array<int,string>
	 */
	private function normalizeQueryValues( $value ): array {
		if ( $value === null ) {
			return [];
		}
		if ( is_array( $value ) ) {
			$parts = [];
			foreach ( $value as $item ) {
				$parts = array_merge( $parts, $this->normalizeQueryValues( $item ) );
			}
			return $parts;
		}
		// $value is a string; split on commas and whitespace.
		$segments = preg_split( '/[,\s]+/', trim( (string)$value ) ) ?: [];
		return array_values( array_filter( $segments, static fn ( $segment ) => $segment !== '' ) );
	}

	private function enforceBatchLimit( int $count ): void {
		$limit = (int)$this->config->get( 'GiantBombResolveBatchLimit' );
		if ( $count > $limit ) {
			throw new HttpException( 'resolve-batch-limit', 400, [ 'limit' => $limit ] );
		}
	}

	private function assertRequestIsAllowed(): void {
		$request = $this->getRequest();
		$allowPublic = (bool)$this->config->get( 'GiantBombResolveAllowPublic' );
		if ( !$allowPublic && !$this->hasValidInternalToken( $request ) ) {
			throw new HttpException( 'resolve-auth-required', 401 );
		}
	}

	private function hasValidInternalToken( SimpleRequestInterface $request ): bool {
		if ( !$request->hasHeader( 'X-GB-Internal-Key' ) ) {
			return false;
		}
		$provided = trim( $request->getHeaderLine( 'X-GB-Internal-Key' ) );
		if ( $provided === '' ) {
			return false;
		}
		$expectedRaw = $this->config->get( 'GiantBombResolveInternalToken' );
		$expected = trim( is_string( $expectedRaw ) ? $expectedRaw : (string)$expectedRaw );
		if ( $expected === '' ) {
			$this->logger->warning(
				'GiantBombResolveInternalToken is not configured; blocking request'
			);
			return false;
		}
		return hash_equals( $expected, $provided );
	}

	private function splitGuid( string $guid ): ?array {
		if ( !preg_match( self::GUID_PATTERN, $guid, $matches ) ) {
			return null;
		}
		return [
			'assocTypeId' => (int)$matches[1],
			'assocId' => (int)$matches[2],
		];
	}

	private function makeMissingRecord( string $guid, int $assocTypeId, int $assocId ): array {
		return [
			'guid' => $guid,
			'assocTypeId' => $assocTypeId,
			'assocId' => $assocId,
			'status' => 'missing',
		];
	}

	private function makeInvalidRecord( string $guid, string $reason ): array {
		return [
			'guid' => $guid,
			'status' => 'invalid',
			'reason' => $reason,
		];
	}

	private function makeErrorRecord( string $guid, string $reason ): array {
		return [
			'guid' => $guid,
			'status' => 'error',
			'reason' => $reason,
		];
	}

	/**
	 * @param array<string,mixed> $printouts
	 */
	private function extractPrimaryImageData( array $printouts ): ?array {
		if ( !$printouts ) {
			return null;
		}
		$candidates = [ 'Primary image', 'Has image', 'Image' ];
		foreach ( $candidates as $key ) {
			if ( empty( $printouts[$key] ) ) {
				continue;
			}
			$entry = $printouts[$key][0] ?? null;
			if ( $entry === null ) {
				continue;
			}
			$titleText = $this->extractFileTitleFromPrintout( $entry );
			if ( !$titleText ) {
				continue;
			}
			if ( stripos( $titleText, 'File:' ) !== 0 ) {
				$titleText = 'File:' . $titleText;
			}
			$title = Title::newFromText( $titleText );
			if ( !$title ) {
				continue;
			}
			$services = MediaWikiServices::getInstance();
			$file = $services->getRepoGroup()->findFile( $title );
			if ( !$file ) {
				continue;
			}
			$thumbOutput = $file->transform( [ 'width' => 640 ] );
			$thumbUrl = null;
			$thumbWidth = null;
			$thumbHeight = null;
			if ( $thumbOutput && !$thumbOutput->isError() ) {
				$thumbUrl = $thumbOutput->getUrl();
				if ( $thumbUrl !== null ) {
					$thumbUrl = \wfExpandUrl( $thumbUrl, \PROTO_CANONICAL );
				}
				$thumbWidth = $thumbOutput->getWidth();
				$thumbHeight = $thumbOutput->getHeight();
			}
			$url = $file->getFullUrl();
			$descriptionUrl = null;
			if ( is_array( $entry ) && isset( $entry['fullurl'] ) && is_string( $entry['fullurl'] ) ) {
				$descriptionUrl = $entry['fullurl'];
			} else {
				$descriptionUrl = $file->getTitle()->getFullURL();
			}

			return [
				'title' => $title->getPrefixedText(),
				'descriptionUrl' => $descriptionUrl,
				'url' => $url,
				'width' => $file->getWidth(),
				'height' => $file->getHeight(),
				'thumbUrl' => $thumbUrl ?? $url,
				'thumbWidth' => $thumbWidth ?? $file->getWidth(),
				'thumbHeight' => $thumbHeight ?? $file->getHeight(),
			];
		}
		return null;
	}

	/**
	 * @param mixed $entry
	 */
	private function extractFileTitleFromPrintout( $entry ): ?string {
		if ( is_array( $entry ) ) {
			foreach ( [ 'fulltext', 'raw', 'title' ] as $key ) {
				if ( isset( $entry[$key] ) && is_string( $entry[$key] ) && $entry[$key] !== '' ) {
					return $entry[$key];
				}
			}
			if ( isset( $entry['fullurl'] ) && is_string( $entry['fullurl'] ) ) {
				$path = parse_url( $entry['fullurl'], PHP_URL_PATH );
				if ( is_string( $path ) && $path !== '' ) {
					$decoded = rawurldecode( $path );
					$parts = explode( '/', trim( $decoded, '/' ) );
					$last = end( $parts );
					if ( $last !== false && $last !== '' ) {
						return $last;
					}
				}
			}
		} elseif ( is_string( $entry ) && $entry !== '' ) {
			return $entry;
		}
		return null;
	}

	private function createResponse( array $payload ): Response {
		$response = $this->getResponseFactory()->createJson( $payload );

		$cacheControl = (string)$this->config->get( 'GiantBombResolveCacheControl' );
		if ( $cacheControl === '' ) {
			$cacheControl = 'public, max-age=900, stale-while-revalidate=300, stale-if-error=86400';
		}
		$response->setHeader( 'Cache-Control', $cacheControl );
		$response->setHeader( 'X-GB-Resolve-Version', '1' );
		$response->setHeader( 'Vary', 'Accept-Encoding' );

		$count = count( $payload['guids'] ?? [] );
		$response->setHeader( 'X-GB-Resolve-Count', (string)$count );

		$prefix = (string)$this->config->get( 'GiantBombResolveSurrogatePrefix' );
		if ( $prefix !== '' && $count > 0 ) {
			$keys = [];
			foreach ( $payload['guids'] as $record ) {
				if ( isset( $record['guid'] ) ) {
					$keys[] = $prefix . $record['guid'];
				}
			}
			if ( $keys ) {
				$response->setHeader( 'Surrogate-Key', implode( ' ', $keys ) );
			}
		}

		return $response;
	}
}

