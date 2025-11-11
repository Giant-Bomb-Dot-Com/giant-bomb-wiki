<?php

namespace MediaWiki\Extension\GiantBombResolve\Rest;

use ApiMain;
use FauxRequest;
use MediaWiki\Config\Config;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\SimpleRequestInterface;
use RequestContext;
use Title;
use User;

class ResolveHandler extends SimpleHandler {

	private const GUID_PATTERN = '/^(\d{3,4})-(\d+)$/';

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
		return [
			'guids' => [
				self::PARAM_SOURCE => self::SELECT_SOURCE_QUERY,
				self::PARAM_TYPE => 'string',
				self::PARAM_REQUIRED => true,
			],
			'fields' => [
				self::PARAM_SOURCE => self::SELECT_SOURCE_QUERY,
				self::PARAM_TYPE => 'string',
				self::PARAM_REQUIRED => false,
			],
		];
	}

	public function needsWriteAccess() {
		return false;
	}

	public function checkPermissions() {
		$this->assertRequestIsAllowed();
	}

	public function execute() {
		$request = $this->getRequest();
		$guids = $this->parseGuids( $request->getQueryParam( 'guids' ) );
		$this->enforceBatchLimit( count( $guids ) );
		$fields = $this->parseFields( $request->getQueryParam( 'fields' ) );

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
		foreach ( $fields as $field ) {
			switch ( $field ) {
				case 'displaytitle':
					$data['displayTitle'] = $first['displaytitle'] ?? null;
					break;
				case 'fullurl':
					$data['fullUrl'] = $first['fullurl'] ?? null;
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

	private function parseGuids( ?string $guids ): array {
		if ( $guids === null || $guids === '' ) {
			throw new HttpException( 'resolve-missing-guids', 400 );
		}
		$parts = preg_split( '/[,\s]+/', trim( $guids ) );
		$out = [];
		foreach ( $parts as $part ) {
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

	private function parseFields( ?string $fields ): array {
		$allowed = $this->allowedFields ?: [ 'displaytitle', 'fullurl', 'fulltext', 'pageid', 'namespace' ];
		if ( $fields === null || trim( $fields ) === '' ) {
			return $allowed;
		}
		$requested = array_filter( array_map( 'trim', explode( ',', $fields ) ) );
		$filtered = [];
		foreach ( $requested as $field ) {
			if ( $field !== '' && in_array( $field, $allowed, true ) ) {
				$filtered[] = $field;
			}
		}
		return $filtered ?: $allowed;
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
		$expected = (string)$this->config->get( 'GiantBombResolveInternalToken' );
		if ( $expected === '' ) {
			return true;
		}
		$provided = $request->getHeaderLine( 'X-GB-Internal-Key' );
		return $provided !== '' && hash_equals( $expected, $provided );
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

