<?php

namespace MediaWiki\Skin\GiantBomb\Test\Helpers;

use MediaWikiIntegrationTestCase;

/**
 * @covers ::processPlatformQueryResults
 * @covers ::getPlatformAbbreviation
 *
 * @group Database
 */
class PlatformHelperTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Load the helper functions using MediaWiki's install path
		global $IP;
		require_once "$IP/skins/GiantBomb/includes/helpers/PlatformHelper.php";
	}

	// Tests for processPlatformQueryResults

	public function testProcessPlatformQueryResultsWithValidData(): void {
		$results = [
			'Platforms/PlayStation 5' => [
				'fullurl' => 'http://localhost:8080/wiki/Platforms/PlayStation_5',
				'printouts' => [
					'Has name' => ['PlayStation 5'],
					'Has short name' => ['PS5'],
					'Has deck' => ['Sony\'s next-generation console'],
					'Has release date' => [
						[
							'raw' => '11/12/2020',
							'timestamp' => 1605139200,
						],
					],
					'Has release date type' => ['Full'],
					'Has image' => [
						[
							'fullurl' => 'http://localhost:8080/wiki/images/PlayStation_5.jpg',
						],
					],
				],
			],
		];

		$platforms = processPlatformQueryResults($results);

		$this->assertCount(1, $platforms);
		$platform = $platforms[0];
		$this->assertSame('PlayStation 5', $platform['title']);
		$this->assertSame('PS5', $platform['shortName']);
		$this->assertSame('Sony\'s next-generation console', $platform['deck']);
		$this->assertSame('http://localhost:8080/wiki/Platforms/PlayStation_5', $platform['url']);
		$this->assertSame('11/12/2020', $platform['releaseDate']);
		$this->assertSame(1605139200, $platform['releaseDateTimestamp']);
		$this->assertSame('full', $platform['dateSpecificity']);
		$this->assertSame('images/PlayStation_5.jpg', $platform['image']);
		$this->assertArrayHasKey('releaseDateFormatted', $platform);
	}

	public function testProcessPlatformQueryResultsWithMultiplePlatforms(): void {
		$results = [
			'Platforms/PlayStation 5' => [
				'fullurl' => 'http://localhost:8080/wiki/Platforms/PlayStation_5',
				'printouts' => [
					'Has name' => ['PlayStation 5'],
					'Has short name' => ['PS5'],
					'Has release date' => [
						[
							'raw' => '11/12/2020',
							'timestamp' => 1605139200,
						],
					],
					'Has release date type' => ['Full'],
				],
			],
			'Platforms/Xbox Series X' => [
				'fullurl' => 'http://localhost:8080/wiki/Platforms/Xbox_Series_X',
				'printouts' => [
					'Has name' => ['Xbox Series X'],
					'Has short name' => ['XSX'],
					'Has release date' => [
						[
							'raw' => '11/10/2020',
							'timestamp' => 1604966400,
						],
					],
					'Has release date type' => ['Full'],
				],
			],
		];

		$platforms = processPlatformQueryResults($results);

		$this->assertCount(2, $platforms);
		$this->assertSame('PlayStation 5', $platforms[0]['title']);
		$this->assertSame('PS5', $platforms[0]['shortName']);
		$this->assertSame('Xbox Series X', $platforms[1]['title']);
		$this->assertSame('XSX', $platforms[1]['shortName']);
	}

	public function testProcessPlatformQueryResultsWithMissingOptionalFields(): void {
		$results = [
			'Platforms/Minimal Platform' => [
				'fullurl' => 'http://localhost:8080/wiki/Platforms/Minimal_Platform',
				'printouts' => [
					'Has name' => ['Minimal Platform'],
					// No short name, deck, or release date
				],
			],
		];

		$platforms = processPlatformQueryResults($results);

		$this->assertCount(1, $platforms);
		$platform = $platforms[0];
		$this->assertSame('Minimal Platform', $platform['title']);
		$this->assertSame('http://localhost:8080/wiki/Platforms/Minimal_Platform', $platform['url']);
		$this->assertArrayNotHasKey('shortName', $platform);
		$this->assertArrayNotHasKey('deck', $platform);
		$this->assertArrayNotHasKey('releaseDate', $platform);
	}

	public function testProcessPlatformQueryResultsFallbackToPageName(): void {
		$results = [
			'Platforms/Unknown Platform' => [
				'fullurl' => 'http://localhost:8080/wiki/Platforms/Unknown_Platform',
				'printouts' => [
					// No Has name field
				],
			],
		];

		$platforms = processPlatformQueryResults($results);

		$this->assertCount(1, $platforms);
		$platform = $platforms[0];
		// Should use page name without namespace as fallback
		$this->assertSame('Unknown Platform', $platform['title']);
	}

	public function testProcessPlatformQueryResultsWithEmptyData(): void {
		$results = [];
		$platforms = processPlatformQueryResults($results);

		$this->assertCount(0, $platforms);
	}

	public function testProcessPlatformQueryResultsWithMonthDateType(): void {
		$results = [
			'Platforms/Month Release Platform' => [
				'fullurl' => 'http://localhost:8080/wiki/Platforms/Month_Release_Platform',
				'printouts' => [
					'Has name' => ['Month Release Platform'],
					'Has release date' => [
						[
							'raw' => '11/2020',
							'timestamp' => 1604188800,
						],
					],
					'Has release date type' => ['Month'],
				],
			],
		];

		$platforms = processPlatformQueryResults($results);

		$this->assertCount(1, $platforms);
		$platform = $platforms[0];
		$this->assertSame('month', $platform['dateSpecificity']);
		$this->assertSame('11/2020', $platform['releaseDate']);
	}

	public function testProcessPlatformQueryResultsWithQuarterDateType(): void {
		$results = [
			'Platforms/Quarter Release Platform' => [
				'fullurl' => 'http://localhost:8080/wiki/Platforms/Quarter_Release_Platform',
				'printouts' => [
					'Has name' => ['Quarter Release Platform'],
					'Has release date' => [
						[
							'raw' => 'Q4/2020',
							'timestamp' => 1601510400,
						],
					],
					'Has release date type' => ['Quarter'],
				],
			],
		];

		$platforms = processPlatformQueryResults($results);

		$this->assertCount(1, $platforms);
		$platform = $platforms[0];
		$this->assertSame('quarter', $platform['dateSpecificity']);
	}

	public function testProcessPlatformQueryResultsWithYearDateType(): void {
		$results = [
			'Platforms/Year Release Platform' => [
				'fullurl' => 'http://localhost:8080/wiki/Platforms/Year_Release_Platform',
				'printouts' => [
					'Has name' => ['Year Release Platform'],
					'Has release date' => [
						[
							'raw' => '2020',
							'timestamp' => 1577836800,
						],
					],
					'Has release date type' => ['Year'],
				],
			],
		];

		$platforms = processPlatformQueryResults($results);

		$this->assertCount(1, $platforms);
		$platform = $platforms[0];
		$this->assertSame('year', $platform['dateSpecificity']);
		$this->assertSame('2020', $platform['releaseDate']);
	}

	public function testProcessPlatformQueryResultsWithImageUrlCleaning(): void {
		$results = [
			'Platforms/Test Platform' => [
				'fullurl' => 'http://localhost:8080/wiki/Platforms/Test_Platform',
				'printouts' => [
					'Has name' => ['Test Platform'],
					'Has image' => [
						[
							'fullurl' => 'http://localhost:8080/wiki/images/test.jpg',
						],
					],
				],
			],
		];

		$platforms = processPlatformQueryResults($results);

		$this->assertCount(1, $platforms);
		$platform = $platforms[0];
		// Should strip the localhost wiki prefix
		$this->assertSame('images/test.jpg', $platform['image']);
	}

	public function testProcessPlatformQueryResultsWithMissingImage(): void {
		$results = [
			'Platforms/No Image Platform' => [
				'fullurl' => 'http://localhost:8080/wiki/Platforms/No_Image_Platform',
				'printouts' => [
					'Has name' => ['No Image Platform'],
					// No image field
				],
			],
		];

		$platforms = processPlatformQueryResults($results);

		$this->assertCount(1, $platforms);
		$platform = $platforms[0];
		$this->assertArrayNotHasKey('image', $platform);
	}

	public function testProcessPlatformQueryResultsHandlesNullResults(): void {
		$platforms = processPlatformQueryResults(null);

		$this->assertCount(0, $platforms);
	}

	public function testProcessPlatformQueryResultsWithAllFields(): void {
		$results = [
			'Platforms/Complete Platform' => [
				'fullurl' => 'http://localhost:8080/wiki/Platforms/Complete_Platform',
				'printouts' => [
					'Has name' => ['Complete Platform'],
					'Has short name' => ['CP'],
					'Has deck' => ['A platform with all fields populated'],
					'Has release date' => [
						[
							'raw' => '1/15/2021',
							'timestamp' => 1610668800,
						],
					],
					'Has release date type' => ['Full'],
					'Has image' => [
						[
							'fullurl' => 'http://localhost:8080/wiki/images/complete.jpg',
						],
					],
				],
			],
		];

		$platforms = processPlatformQueryResults($results);

		$this->assertCount(1, $platforms);
		$platform = $platforms[0];
		
		// Verify all fields are present and correctly processed
		$this->assertSame('Complete Platform', $platform['title']);
		$this->assertSame('CP', $platform['shortName']);
		$this->assertSame('A platform with all fields populated', $platform['deck']);
		$this->assertSame('http://localhost:8080/wiki/Platforms/Complete_Platform', $platform['url']);
		$this->assertSame('1/15/2021', $platform['releaseDate']);
		$this->assertSame(1610668800, $platform['releaseDateTimestamp']);
		$this->assertSame('full', $platform['dateSpecificity']);
		$this->assertSame('images/complete.jpg', $platform['image']);
		$this->assertArrayHasKey('releaseDateFormatted', $platform);
		$this->assertArrayHasKey('gameCount', $platform);
	}

	public function testProcessPlatformQueryResultsWithEmptyPrintouts(): void {
		$results = [
			'Platforms/Empty Printouts' => [
				'fullurl' => 'http://localhost:8080/wiki/Platforms/Empty_Printouts',
				'printouts' => [],
			],
		];

		$platforms = processPlatformQueryResults($results);

		$this->assertCount(1, $platforms);
		$platform = $platforms[0];
		// Should fallback to page name
		$this->assertSame('Empty Printouts', $platform['title']);
		$this->assertSame('http://localhost:8080/wiki/Platforms/Empty_Printouts', $platform['url']);
	}

	public function testProcessPlatformQueryResultsDateSpecificityIsLowercase(): void {
		$results = [
			'Platforms/Case Test' => [
				'fullurl' => 'http://localhost:8080/wiki/Platforms/Case_Test',
				'printouts' => [
					'Has name' => ['Case Test'],
					'Has release date' => [
						[
							'raw' => '2020',
							'timestamp' => 1577836800,
						],
					],
					'Has release date type' => ['YEAR'], // Uppercase
				],
			],
		];

		$platforms = processPlatformQueryResults($results);

		$this->assertCount(1, $platforms);
		$platform = $platforms[0];
		// Should be converted to lowercase
		$this->assertSame('year', $platform['dateSpecificity']);
	}

	// Tests for getPlatformAbbreviation

	public function testGetPlatformAbbreviationFallbackWithNamespace(): void {
		// When platform is not in cache, should return basename (remove Platforms/ prefix)
		$result = getPlatformAbbreviation('Platforms/PlayStation 5');
		
		// Falls back to basename which removes the Platforms/ prefix
		$this->assertSame('PlayStation 5', $result);
	}

	public function testGetPlatformAbbreviationFallbackWithoutNamespace(): void {
		// When platform name doesn't have namespace prefix
		$result = getPlatformAbbreviation('Xbox Series X');
		
		// Should return the name as-is
		$this->assertSame('Xbox Series X', $result);
	}

	public function testGetPlatformAbbreviationFallbackWithUnderscores(): void {
		// Test with underscores (common in MediaWiki page names)
		$result = getPlatformAbbreviation('Platforms/Nintendo_Switch');
		
		// Should return just the basename
		$this->assertSame('Nintendo_Switch', $result);
	}

	public function testGetPlatformAbbreviationFallbackWithNestedPath(): void {
		// Test edge case with nested path-like structure
		$result = getPlatformAbbreviation('Platforms/Some/Nested/Path');
		
		// basename() should return only the last component
		$this->assertSame('Path', $result);
	}

	public function testGetPlatformAbbreviationFallbackWithEmptyString(): void {
		// Test edge case with empty string
		$result = getPlatformAbbreviation('');
		
		$this->assertSame('', $result);
	}

	public function testGetPlatformAbbreviationConsistentBehavior(): void {
		// Test that multiple calls with same input return same result
		$input = 'Platforms/Test Platform';
		
		$result1 = getPlatformAbbreviation($input);
		$result2 = getPlatformAbbreviation($input);
		
		$this->assertSame($result1, $result2);
		$this->assertSame('Test Platform', $result1);
	}
}

