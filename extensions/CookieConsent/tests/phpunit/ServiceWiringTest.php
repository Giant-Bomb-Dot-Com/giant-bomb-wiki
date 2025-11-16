<?php

namespace CookieConsent\Tests;

use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;

/**
 * Tests CookieConsent.wiring.php.
 *
 * @coversNothing PHPUnit does not support covering annotations for files
 * @group CookieConsent
 */
class ServiceWiringTest extends MediaWikiIntegrationTestCase {
	/**
	 * @dataProvider provideService
	 */
	public function testService( string $name ) {
		MediaWikiServices::getInstance()->get( $name );
		$this->addToAssertionCount( 1 );
	}

	public static function provideService() {
		$wiring = require __DIR__ . '/../../src/CookieConsent.wiring.php';
		foreach ( $wiring as $name => $_ ) {
			yield $name => [ $name ];
		}
	}
}
