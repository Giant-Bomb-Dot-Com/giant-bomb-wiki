<?php

namespace MediaWiki\Skin\GiantBomb\Test\Helpers;

use MediaWikiIntegrationTestCase;

/**
 * @covers ::processDateForGrouping
 * @covers ::processReleaseQueryResults
 *
 * @group Database
 */
class ReleasesHelperTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Load the helper functions using MediaWiki's install path
		global $IP;
		require_once "$IP/skins/GiantBomb/includes/helpers/ReleasesHelper.php";
	}

	// Tests for processDateForGrouping

	public function testProcessDateForGroupingWithFullSpecificity(): void {
		// Test a date that falls on a Wednesday (2024-12-25)
		$timestamp = strtotime('2024-12-25');
		$result = processDateForGrouping($timestamp, 'full');

		// The week should start on Sunday (2024-12-22) and end on Saturday (2024-12-28)
		$this->assertSame('2024-51', $result['groupKey']);
		$this->assertSame('December 22, 2024 - December 28, 2024', $result['groupLabel']);
		$this->assertSame('20241222', $result['sortKey']);
	}

	public function testProcessDateForGroupingWithFullSpecificityOnSunday(): void {
		// Test a date that falls on a Sunday (2024-12-22)
		$timestamp = strtotime('2024-12-22');
		$result = processDateForGrouping($timestamp, 'full');

		// The week should start on the same Sunday
		$this->assertSame('2024-51', $result['groupKey']);
		$this->assertSame('December 22, 2024 - December 28, 2024', $result['groupLabel']);
		$this->assertSame('20241222', $result['sortKey']);
	}

	public function testProcessDateForGroupingWithMonthSpecificity(): void {
		$timestamp = strtotime('2024-12-15');
		$result = processDateForGrouping($timestamp, 'month');

		$this->assertSame('2024-12', $result['groupKey']);
		$this->assertSame('December 2024', $result['groupLabel']);
		$this->assertSame('20241200', $result['sortKey']);
	}

	public function testProcessDateForGroupingWithQuarterSpecificity(): void {
		// Test Q1 (January)
		$timestamp = strtotime('2024-01-15');
		$result = processDateForGrouping($timestamp, 'quarter');

		$this->assertSame('2024-Q1', $result['groupKey']);
		$this->assertSame('Q1 2024', $result['groupLabel']);
		$this->assertSame('202401', $result['sortKey']);

		// Test Q2 (April)
		$timestamp = strtotime('2024-04-15');
		$result = processDateForGrouping($timestamp, 'quarter');

		$this->assertSame('2024-Q2', $result['groupKey']);
		$this->assertSame('Q2 2024', $result['groupLabel']);
		$this->assertSame('202402', $result['sortKey']);

		// Test Q3 (July)
		$timestamp = strtotime('2024-07-15');
		$result = processDateForGrouping($timestamp, 'quarter');

		$this->assertSame('2024-Q3', $result['groupKey']);
		$this->assertSame('Q3 2024', $result['groupLabel']);
		$this->assertSame('202403', $result['sortKey']);

		// Test Q4 (October)
		$timestamp = strtotime('2024-10-15');
		$result = processDateForGrouping($timestamp, 'quarter');

		$this->assertSame('2024-Q4', $result['groupKey']);
		$this->assertSame('Q4 2024', $result['groupLabel']);
		$this->assertSame('202404', $result['sortKey']);
	}

	public function testProcessDateForGroupingWithYearSpecificity(): void {
		$timestamp = strtotime('2024-06-15');
		$result = processDateForGrouping($timestamp, 'year');

		$this->assertSame('2024', $result['groupKey']);
		$this->assertSame('2024', $result['groupLabel']);
		$this->assertSame('20240000', $result['sortKey']);
	}

	public function testProcessDateForGroupingWithUnknownSpecificity(): void {
		// Should default to year grouping
		$timestamp = strtotime('2024-06-15');
		$result = processDateForGrouping($timestamp, 'unknown');

		$this->assertSame('2024', $result['groupKey']);
		$this->assertSame('2024', $result['groupLabel']);
		$this->assertSame('20240000', $result['sortKey']);
	}

	// Tests for processReleaseQueryResults

	public function testProcessReleaseQueryResultsWithValidData(): void {
		$results = [
			'Games/Ratchet and Clank Rift Apart/Releases' => [
				'printouts' => [
					'Has games' => [
						[
							'fulltext' => 'Games/Ratchet and Clank Rift Apart',
							'fullurl' => 'http://localhost:8080/wiki/Games/Ratchet_and_Clank_Rift_Apart',
							'displaytitle' => 'Ratchet and Clank: Rift Apart',
						],
					],
					'Has name' => ['Ratchet and Clank: Rift Apart'],
					'Has release date' => [
						[
							'raw' => '1/2021/6/11',
							'timestamp' => 1623333600,
						],
					],
					'Has release date type' => ['Full'],
					'Has platforms' => [
						[
							'fulltext' => 'Platforms/PlayStation 5',
							'fullurl' => 'http://localhost:8080/wiki/Platforms/PlayStation_5',
							'displaytitle' => 'PlayStation 5',
						],
					],
					'Has region' => ['United States'],
					'Has image' => [
						[
							'fullurl' => 'http://example.com/images/Ratchet_and_Clank_Rift_Apart.jpg',
						],
					],
				],
			],
		];

		$releases = processReleaseQueryResults($results);

		$this->assertCount(1, $releases);
        $release = $releases[0];
		$this->assertSame('Ratchet and Clank: Rift Apart', $release['title']);
		$this->assertSame('http://localhost:8080/wiki/Games/Ratchet_and_Clank_Rift_Apart', $release['url']);
		$this->assertSame('Ratchet and Clank: Rift Apart', $release['text']);
		$this->assertSame('1/2021/6/11', $release['releaseDate']);
		$this->assertSame(1623333600, $release['releaseDateTimestamp']);
		$this->assertSame('full', $release['dateSpecificity']);
		$this->assertSame('United States', $release['region']);
		$this->assertCount(1, $release['platforms']);
		$this->assertSame('PlayStation 5', $release['platforms'][0]['title']);
		$this->assertSame('http://example.com/images/Ratchet_and_Clank_Rift_Apart.jpg', $release['image']);
	}

	public function testProcessReleaseQueryResultsWithMultiplePlatforms(): void {
		$results = [
			'Games/Assassins Creed Shadows/Releases' => [
				'printouts' => [
					'Has games' => [
						[
							'fulltext' => 'Games/Assassins Creed Shadows',
							'fullurl' => 'http://localhost:8080/wiki/Games/Assassins_Creed_Shadows',
							'displaytitle' => 'Assassin\'s Creed Shadows',
						],
					],
					'Has release date' => [
						[
							'raw' => '1/2025/3/20',
							'timestamp' => 1742389200,
						],
					],
					'Has release date type' => ['Month'],
					'Has platforms' => [
						[
							'fulltext' => 'Platforms/PlayStation 5',
							'fullurl' => 'http://example.com/Platforms/PlayStation_5',
							'displaytitle' => 'PlayStation 5',
						],
						[
							'fulltext' => 'Platforms/Xbox Series X',
							'fullurl' => 'http://example.com/Platforms/Xbox_Series_X',
							'displaytitle' => 'Xbox Series X',
						],
					],
					'Has region' => ['United Kingdom'],
                    'Has image' => [
                        [
                            'fullurl' => 'http://example.com/images/Assassins_Creed_Shadows.jpg',
                        ]
                    ]
				],
			],
		];

		$releases = processReleaseQueryResults($results);

		$this->assertCount(1, $releases);
		$release = $releases[0];
		$this->assertCount(2, $release['platforms']);
		$this->assertSame('Games/Assassins Creed Shadows', $release['title']);
		$this->assertSame('http://localhost:8080/wiki/Games/Assassins_Creed_Shadows', $release['url']);
		$this->assertSame('Assassin\'s Creed Shadows', $release['text']);
		$this->assertSame('1/2025/3/20', $release['releaseDate']);
		$this->assertSame(1742389200, $release['releaseDateTimestamp']);
		$this->assertSame('month', $release['dateSpecificity']);
		$this->assertSame('United Kingdom', $release['region']);
		$this->assertCount(2, $release['platforms']);
		$this->assertSame('PlayStation 5', $release['platforms'][0]['title']);
		$this->assertSame('Xbox Series X', $release['platforms'][1]['title']);
        $this->assertSame('http://example.com/images/Assassins_Creed_Shadows.jpg', $release['image']);
	}

	public function testProcessReleaseQueryResultsDeduplication(): void {
		$results = [
			'Games/Duplicate Game/Releases' => [
				'printouts' => [
					'Has games' => [
						[
							'fulltext' => 'Games/Duplicate Game',
							'fullurl' => 'http://localhost:8080/wiki/Games/Duplicate_Game',
							'displaytitle' => 'Duplicate Game',
						],
					],
					'Has name' => ['Duplicate Game'],
					'Has release date' => [
						[
							'raw' => '12/25/2024',
							'timestamp' => 1735084800,
						],
					],
					'Has release date type' => ['Full'],
					'Has platforms' => [
						[
							'fulltext' => 'Platforms/PlayStation 5',
							'fullurl' => 'http://example.com/Platforms/PlayStation_5',
							'displaytitle' => 'PlayStation 5',
						],
						[
							'fulltext' => 'Platforms/Xbox Series X',
							'fullurl' => 'http://example.com/Platforms/Xbox_Series_X',
							'displaytitle' => 'Xbox Series X',
						],
					],
					'Has region' => ['North America'],
					'Has image' => [
						[
							'fullurl' => 'http://example.com/images/Duplicate_Game.jpg',
						]
					]
				],
			],
			'Games/Duplicate Game/Releases' => [
				'printouts' => [
					'Has games' => [
						[
							'fulltext' => 'Games/Duplicate Game',
							'fullurl' => 'http://localhost:8080/wiki/Games/Duplicate_Game',
							'displaytitle' => 'Duplicate Game',
						],
					],
					'Has name' => ['Duplicate Game'],
					'Has release date' => [
						[
							'raw' => '12/25/2024',
							'timestamp' => 1735084800,
						],
					],
					'Has release date type' => ['Full'],
					'Has platforms' => [
						[
							'fulltext' => 'Platforms/PlayStation 5',
							'fullurl' => 'http://example.com/Platforms/PlayStation_5',
							'displaytitle' => 'PlayStation 5',
						],
						[
							'fulltext' => 'Platforms/Xbox Series X',
							'fullurl' => 'http://example.com/Platforms/Xbox_Series_X',
							'displaytitle' => 'Xbox Series X',
						],
					],
					'Has region' => ['North America'],
					'Has image' => [
						[
							'fullurl' => 'http://example.com/images/Duplicate_Game.jpg',
						]
					]
				],
			],
		];

		$releases = processReleaseQueryResults($results);

		// Should deduplicate identical releases
		$this->assertCount(1, $releases);
	}

	public function testProcessReleaseQueryResultsWithEmptyData(): void {
		$results = [];
		$releases = processReleaseQueryResults($results);

		$this->assertCount(0, $releases);
	}

	public function testProcessReleaseQueryResultsWithMissingFields(): void {
		$results = [
			'Games/Minimal Game/Releases' => [
				'printouts' => [
					'Has games' => [
						[
							'fulltext' => 'Games/Minimal Game',
							'fullurl' => 'http://localhost:8080/wiki/Games/Minimal_Game',
						],
					],
					// Missing most fields
				],
			],
		];

		$releases = processReleaseQueryResults($results);

		$this->assertCount(1, $releases);
		$release = $releases[0];
		$this->assertSame('Games/Minimal Game', $release['title']);
		$this->assertArrayNotHasKey('releaseDate', $release);
		$this->assertArrayNotHasKey('platforms', $release);
	}

	public function testProcessReleaseQueryResultsWithYearDateType(): void {
		$results = [
			'Games/Year Release/Releases' => [
				'printouts' => [
					'Has games' => [
						[
							'fulltext' => 'Games/Year Release',
							'fullurl' => 'http://localhost:8080/wiki/Games/Year_Release',
						],
					],
					'Has release date' => [
						[
							'raw' => '2025',
							'timestamp' => 1735689600,
						],
					],
					'Has release date type' => ['Year'],
				],
			],
		];

		$releases = processReleaseQueryResults($results);

		$this->assertCount(1, $releases);
		$release = $releases[0];
		$this->assertSame('year', $release['dateSpecificity']);
	}

	public function testProcessReleaseQueryResultsWithQuarterDateType(): void {
		$results = [
			'Games/Quarter Release/Releases' => [
				'printouts' => [
					'Has games' => [
						[
							'fulltext' => 'Games/Quarter Release',
							'fullurl' => 'http://localhost:8080/wiki/Games/Quarter_Release',
						],
					],
					'Has release date' => [
						[
							'raw' => '1/2025',
							'timestamp' => 1735689600,
						],
					],
					'Has release date type' => ['Quarter'],
				],
			],
		];

		$releases = processReleaseQueryResults($results);

		$this->assertCount(1, $releases);
		$release = $releases[0];
		$this->assertSame('quarter', $release['dateSpecificity']);
	}
}


