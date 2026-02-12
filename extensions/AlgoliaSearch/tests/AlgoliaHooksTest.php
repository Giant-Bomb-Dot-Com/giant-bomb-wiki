<?php

namespace MediaWiki\Extension\AlgoliaSearch\Tests;

use MediaWiki\Extension\AlgoliaSearch\AlgoliaHooks;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\AlgoliaSearch\AlgoliaHooks
 */
class AlgoliaHooksTest extends TestCase {

	private function createMockTitle( string $text ): \Title {
		$title = $this->createMock( \Title::class );
		$title->method( 'getText' )->willReturn( $text );
		return $title;
	}

	private function createMockConfig( array $prefixMap ): object {
		return new class( $prefixMap ) {
			private array $prefixMap;
			public function __construct( array $prefixMap ) {
				$this->prefixMap = $prefixMap;
			}
			public function get( string $key ) {
				if ( $key === 'AlgoliaTypePrefixMap' ) {
					return $this->prefixMap;
				}
				return null;
			}
		};
	}

	private static function fullPrefixMap(): array {
		return [
			'Game' => 'Games',
			'Character' => 'Characters',
			'Concept' => 'Concepts',
			'Accessory' => 'Accessories',
			'Location' => 'Locations',
			'Person' => 'People',
			'Franchise' => 'Franchises',
			'Platform' => 'Platforms',
			'Company' => 'Companies',
			'Object' => 'Objects',
		];
	}

	public static function provideMatchingTitles(): array {
		return [
			'game' => [ 'Games/Half-Life 2', 'Game' ],
			'character' => [ 'Characters/Gordon Freeman', 'Character' ],
			'concept' => [ 'Concepts/Test', 'Concept' ],
			'accessory' => [ 'Accessories/Test', 'Accessory' ],
			'location' => [ 'Locations/Test', 'Location' ],
			'person' => [ 'People/Test', 'Person' ],
			'franchise' => [ 'Franchises/Test', 'Franchise' ],
			'platform' => [ 'Platforms/Test', 'Platform' ],
			'company' => [ 'Companies/Test', 'Company' ],
			'object' => [ 'Objects/Test', 'Object' ],
		];
	}

	/**
	 * @dataProvider provideMatchingTitles
	 */
	public function testGetTypeFromTitleMatchesType( string $titleText, string $expectedType ): void {
		$config = $this->createMockConfig( self::fullPrefixMap() );
		$title = $this->createMockTitle( $titleText );

		$result = AlgoliaHooks::getTypeFromTitle( $title, $config );

		$this->assertSame( $expectedType, $result );
	}

	public static function provideNonMatchingTitles(): array {
		return [
			'subpage' => [ 'Games/Half-Life 2/Images' ],
			'unmatched prefix' => [ 'RandomPage' ],
			'partial prefix' => [ 'Gameplay Tips' ],
		];
	}

	/**
	 * @dataProvider provideNonMatchingTitles
	 */
	public function testGetTypeFromTitleReturnsNull( string $titleText ): void {
		$config = $this->createMockConfig( [ 'Game' => 'Games' ] );
		$title = $this->createMockTitle( $titleText );

		$result = AlgoliaHooks::getTypeFromTitle( $title, $config );

		$this->assertNull( $result );
	}
}
