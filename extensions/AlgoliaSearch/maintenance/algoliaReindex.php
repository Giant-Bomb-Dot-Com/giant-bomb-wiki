<?php

use MediaWiki\Extension\AlgoliaSearch\AlgoliaClientFactory;
use MediaWiki\Extension\AlgoliaSearch\IndexSettings;
use MediaWiki\Extension\AlgoliaSearch\RecordMapper;
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false || $IP === '' ) {
	$IP = dirname( __DIR__, 3 );
}
require_once "$IP/maintenance/Maintenance.php";

class AlgoliaReindex extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Reindex MediaWiki/SMW content into Algolia.' );
		$this->addOption( 'apply-settings', 'Apply default index settings before indexing', false, false );
		$this->addOption( 'types', 'Comma-separated list of types to index', false, true );
		$this->addOption( 'since', 'Only index pages updated since YYYY-MM-DD', false, true );
		$this->addOption( 'batch', 'Batch size for saveObjects (default: 1000)', false, true );
	}

	public function execute() {
		$services = MediaWikiServices::getInstance();
		$config = $services->getMainConfig();

		$index = AlgoliaClientFactory::getIndexFromConfig( $config );
		if ( !$index ) {
			$this->fatalError( 'Algolia is disabled or misconfigured (no client/index)' );
		}

		if ( $this->hasOption( 'apply-settings' ) ) {
			IndexSettings::applyDefaultIndexSettings( $index );
			$this->output( "Applied default index settings.\n" );
		}

		$typesOpt = $this->getOption( 'types', '' );
		$types = array_filter( array_map( 'trim', preg_split( '/[,\s]+/', $typesOpt ) ?: [] ) );
		if ( !$types ) {
			$types = RecordMapper::getSupportedTypes();
		}

		$since = $this->getOption( 'since', '' );
		$sinceTs = null;
		if ( $since !== '' ) {
			$parts = explode( '-', $since );
			if ( count( $parts ) === 3 ) {
				$sinceTs = sprintf( '%04d%02d%02d000000', (int)$parts[0], (int)$parts[1], (int)$parts[2] );
			}
		}
		$batchSize = (int)$this->getOption( 'batch', '1000' );
		if ( $batchSize <= 0 ) {
			$batchSize = 1000;
		}

		$this->output( "Index: " . $config->get( 'AlgoliaIndexName' ) . "\n" );
		$this->output( "Types: " . implode( ',', $types ) . "\n" );
		if ( $since !== '' ) {
			$this->output( "Since: $since\n" );
		}
		$this->output( "Batch: $batchSize\n" );

		$typePrefixMap = (array)$config->get( 'AlgoliaTypePrefixMap' );
		$totalIndexed = 0;
		$totalSkipped = 0;
		$errors = 0;

		foreach ( $types as $type ) {
			$prefix = $typePrefixMap[ $type ] ?? null;
			if ( !is_string( $prefix ) || $prefix === '' ) {
				$this->output( "Skipping type '$type' (no prefix configured)\n" );
				continue;
			}
			$this->output( "Enumerating type '$type' with prefix '$prefix/'...\n" );

			$recordsBatch = [];
			$countForType = 0;
			$skippedForType = 0;
			$errorForType = 0;

			foreach ( $this->enumerateTitlesByPrefix( $prefix ) as $title ) {
				try {
					$record = RecordMapper::mapRecord( $type, $title );
					if ( $record === null ) {
						$skippedForType++;
						continue;
					}
					if ( $sinceTs && isset( $record['_updatedAt'] ) && is_string( $record['_updatedAt'] ) ) {
						if ( $record['_updatedAt'] < $sinceTs ) {
							$skippedForType++;
							continue;
						}
					}
					$recordsBatch[] = $record;
					$countForType++;
					if ( count( $recordsBatch ) >= $batchSize ) {
						$index->saveObjects( $recordsBatch );
						$recordsBatch = [];
						$this->output( "Upserted $countForType objects for '$type'...\n" );
					}
				} catch ( \Throwable $e ) {
					$errorForType++;
					$this->output( "Error mapping/upserting '{$title->getPrefixedText()}': " . $e->getMessage() . "\n" );
				}
			}
			if ( $recordsBatch ) {
				$index->saveObjects( $recordsBatch );
			}
			$totalIndexed += $countForType;
			$totalSkipped += $skippedForType;
			$errors += $errorForType;
			$this->output( "Type '$type': indexed=$countForType, skipped=$skippedForType, errors=$errorForType\n" );
		}

		$this->output( "Done. Total indexed=$totalIndexed, skipped=$totalSkipped, errors=$errors\n" );
	}

	private function enumerateTitlesByPrefix( string $prefix ): \Generator {
		$services = MediaWikiServices::getInstance();
		$dbr = $services->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$dbPrefix = str_replace( ' ', '_', $prefix ) . '/';
		$like = $dbr->buildLike( $dbPrefix, $dbr->anyString() );
		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'page_id', 'page_namespace', 'page_title', 'page_is_redirect' ] )
			->from( 'page' )
			->where( [
				'page_namespace' => NS_MAIN,
				'page_is_redirect' => 0,
			] )
			->andWhere( [ "page_title $like" ] )
			->caller( __METHOD__ )
			->fetchResultSet();
		foreach ( $res as $row ) {
			$dbTitle = (string)$row->page_title;
			$remainder = substr( $dbTitle, strlen( $dbPrefix ) );
			if ( $remainder === false ) {
				continue;
			}
			if ( strpos( $remainder, '/' ) !== false ) {
				continue;
			}
			$title = Title::makeTitle( (int)$row->page_namespace, $dbTitle );
			if ( $title ) {
				yield $title;
			}
		}
	}
}

$maintClass = AlgoliaReindex::class;
require_once RUN_MAINTENANCE_IF_MAIN;


