<?php

use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false || $IP === '' ) {
	$IP = dirname( __DIR__, 3 );
}
require_once "$IP/maintenance/Maintenance.php";

class DebugAlgolia extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Print Algolia config and client availability.' );
	}

	public function execute() {
		$services = MediaWikiServices::getInstance();
		$config = $services->getMainConfig();
		$enabled = (bool)$config->get( 'AlgoliaSearchEnabled' );
		$appId = (string)$config->get( 'AlgoliaAppId' );
		$index = (string)$config->get( 'AlgoliaIndexName' );
		$hasClient = class_exists( '\Algolia\AlgoliaSearch\SearchClient' );
		$usingFallback = !$hasClient ? 'true' : 'false';

		$this->output( "AlgoliaSearchEnabled=" . ( $enabled ? 'true' : 'false' ) . "\n" );
		$this->output( "AlgoliaAppId=" . ( $appId !== '' ? $appId : 'null' ) . "\n" );
		$this->output( "AlgoliaIndexName=" . ( $index !== '' ? $index : 'null' ) . "\n" );
		$this->output( "HasAlgoliaClientClass=" . ( $hasClient ? 'true' : 'false' ) . "\n" );
		$this->output( "UsingRestFallback=" . $usingFallback . "\n" );
	}
}

$maintClass = DebugAlgolia::class;
require_once RUN_MAINTENANCE_IF_MAIN;


