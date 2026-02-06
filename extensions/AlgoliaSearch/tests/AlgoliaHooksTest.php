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

	public function testGetTypeFromTitleMatchesGame(): void {
		$prefixMap = [
			'Game' => 'Games',
			'Character' => 'Characters',
		];
		$config = $this->createMockConfig( $prefixMap );
		$title = $this->createMockTitle( 'Games/Half-Life 2' );

		$result = AlgoliaHooks::getTypeFromTitle( $title, $config );

		$this->assertSame( 'Game', $result );
	}

	public function testGetTypeFromTitleMatchesCharacter(): void {
		$prefixMap = [
			'Game' => 'Games',
			'Character' => 'Characters',
		];
		$config = $this->createMockConfig( $prefixMap );
		$title = $this->createMockTitle( 'Characters/Gordon Freeman' );

		$result = AlgoliaHooks::getTypeFromTitle( $title, $config );

		$this->assertSame( 'Character', $result );
	}

	public function testGetTypeFromTitleReturnsNullForSubpage(): void {
		$prefixMap = [
			'Game' => 'Games',
		];
		$config = $this->createMockConfig( $prefixMap );
		// Subpage should not match (Games/Half-Life 2/Images)
		$title = $this->createMockTitle( 'Games/Half-Life 2/Images' );

		$result = AlgoliaHooks::getTypeFromTitle( $title, $config );

		$this->assertNull( $result );
	}

	public function testGetTypeFromTitleReturnsNullForUnmatchedPrefix(): void {
		$prefixMap = [
			'Game' => 'Games',
		];
		$config = $this->createMockConfig( $prefixMap );
		$title = $this->createMockTitle( 'RandomPage' );

		$result = AlgoliaHooks::getTypeFromTitle( $title, $config );

		$this->assertNull( $result );
	}

	public function testGetTypeFromTitleReturnsNullForPartialPrefixMatch(): void {
		$prefixMap = [
			'Game' => 'Games',
		];
		$config = $this->createMockConfig( $prefixMap );
		// "Gameplay" starts with "Game" but isn't "Games/"
		$title = $this->createMockTitle( 'Gameplay Tips' );

		$result = AlgoliaHooks::getTypeFromTitle( $title, $config );

		$this->assertNull( $result );
	}

	public function testGetTypeFromTitleHandlesAllSupportedTypes(): void {
		$prefixMap = [
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
		$config = $this->createMockConfig( $prefixMap );

		$testCases = [
			'Games/Test' => 'Game',
			'Characters/Test' => 'Character',
			'Concepts/Test' => 'Concept',
			'Accessories/Test' => 'Accessory',
			'Locations/Test' => 'Location',
			'People/Test' => 'Person',
			'Franchises/Test' => 'Franchise',
			'Platforms/Test' => 'Platform',
			'Companies/Test' => 'Company',
			'Objects/Test' => 'Object',
		];

		foreach ( $testCases as $titleText => $expectedType ) {
			$title = $this->createMockTitle( $titleText );
			$result = AlgoliaHooks::getTypeFromTitle( $title, $config );
			$this->assertSame( $expectedType, $result, "Failed for title: $titleText" );
		}
	}
}
