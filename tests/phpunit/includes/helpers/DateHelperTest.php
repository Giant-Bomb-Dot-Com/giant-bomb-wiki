<?php

namespace MediaWiki\Skin\GiantBomb\Test\Helpers;

use MediaWikiIntegrationTestCase;

/**
 * @covers ::formatReleaseDate
 *
 * @group Database
 */
class DateHelperTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Load the helper functions using MediaWiki's install path
		global $IP;
		require_once "$IP/skins/GiantBomb/includes/helpers/DateHelper.php";
	}

	// Tests for formatReleaseDate

	public function testFormatReleaseDateWithFullDateType(): void {
		$rawDate = '12/25/2024';
		$timestamp = strtotime('2024-12-25');
		$dateType = 'Full';

		$result = formatReleaseDate($rawDate, $timestamp, $dateType);

		$this->assertSame('December 25, 2024', $result);
	}

	public function testFormatReleaseDateWithMonthDateType(): void {
		$rawDate = '11/2020';
		$timestamp = strtotime('2020-11-01');
		$dateType = 'Month';

		$result = formatReleaseDate($rawDate, $timestamp, $dateType);

		$this->assertSame('November 2020', $result);
	}

	public function testFormatReleaseDateWithQuarterDateTypeQ1(): void {
		$rawDate = '1/2024';
		$timestamp = strtotime('2024-01-01'); // January is Q1
		$dateType = 'Quarter';

		$result = formatReleaseDate($rawDate, $timestamp, $dateType);

		$this->assertSame('Q1 2024', $result);
	}

	public function testFormatReleaseDateWithQuarterDateTypeQ2(): void {
		$timestamp = strtotime('2024-04-01'); // April is Q2
		$dateType = 'Quarter';

		$result = formatReleaseDate('', $timestamp, $dateType);

		$this->assertSame('Q2 2024', $result);
	}

	public function testFormatReleaseDateWithQuarterDateTypeQ3(): void {
		$timestamp = strtotime('2024-07-01'); // July is Q3
		$dateType = 'Quarter';

		$result = formatReleaseDate('', $timestamp, $dateType);

		$this->assertSame('Q3 2024', $result);
	}

	public function testFormatReleaseDateWithQuarterDateTypeQ4(): void {
		$timestamp = strtotime('2024-10-01'); // October is Q4
		$dateType = 'Quarter';

		$result = formatReleaseDate('', $timestamp, $dateType);

		$this->assertSame('Q4 2024', $result);
	}

	public function testFormatReleaseDateWithYearDateType(): void {
		$rawDate = '2025';
		$timestamp = strtotime('2025-01-01');
		$dateType = 'Year';

		$result = formatReleaseDate($rawDate, $timestamp, $dateType);

		$this->assertSame('2025', $result);
	}

	public function testFormatReleaseDateWithNoneDateType(): void {
		$rawDate = 'TBD';
		$timestamp = strtotime('2024-01-01');
		$dateType = 'None';

		$result = formatReleaseDate($rawDate, $timestamp, $dateType);

		// Should return raw date when type is 'None'
		$this->assertSame('TBD', $result);
	}

	public function testFormatReleaseDateWithZeroTimestamp(): void {
		$rawDate = 'Unknown';
		$timestamp = 0;
		$dateType = 'Full';

		$result = formatReleaseDate($rawDate, $timestamp, $dateType);

		// Should return raw date when timestamp is 0
		$this->assertSame('Unknown', $result);
	}

	public function testFormatReleaseDateWithNullTimestamp(): void {
		$rawDate = 'Coming Soon';
		$timestamp = null;
		$dateType = 'Full';

		$result = formatReleaseDate($rawDate, $timestamp, $dateType);

		// Should return raw date when timestamp is null
		$this->assertSame('Coming Soon', $result);
	}

	public function testFormatReleaseDateWithUnknownDateType(): void {
		$rawDate = '6/15/2024';
		$timestamp = strtotime('2024-06-15');
		$dateType = 'Unknown';

		$result = formatReleaseDate($rawDate, $timestamp, $dateType);

		// Should default to Full format
		$this->assertSame('June 15, 2024', $result);
	}

	public function testFormatReleaseDateHandlesLeapYear(): void {
		$rawDate = '2/29/2024';
		$timestamp = strtotime('2024-02-29');
		$dateType = 'Full';

		$result = formatReleaseDate($rawDate, $timestamp, $dateType);

		$this->assertSame('February 29, 2024', $result);
	}

	public function testFormatReleaseDateHandlesFirstDayOfYear(): void {
		$rawDate = '1/1/2024';
		$timestamp = strtotime('2024-01-01');
		$dateType = 'Full';

		$result = formatReleaseDate($rawDate, $timestamp, $dateType);

		$this->assertSame('January 1, 2024', $result);
	}

	public function testFormatReleaseDateHandlesLastDayOfYear(): void {
		$rawDate = '12/31/2024';
		$timestamp = strtotime('2024-12-31');
		$dateType = 'Full';

		$result = formatReleaseDate($rawDate, $timestamp, $dateType);

		$this->assertSame('December 31, 2024', $result);
	}

	public function testFormatReleaseDateQuarterBoundaries(): void {
		// Test month 3 (end of Q1)
		$timestamp1 = strtotime('2024-03-31');
		$result1 = formatReleaseDate('', $timestamp1, 'Quarter');
		$this->assertSame('Q1 2024', $result1);

		// Test month 6 (end of Q2)
		$timestamp2 = strtotime('2024-06-30');
		$result2 = formatReleaseDate('', $timestamp2, 'Quarter');
		$this->assertSame('Q2 2024', $result2);

		// Test month 9 (end of Q3)
		$timestamp3 = strtotime('2024-09-30');
		$result3 = formatReleaseDate('', $timestamp3, 'Quarter');
		$this->assertSame('Q3 2024', $result3);

		// Test month 12 (end of Q4)
		$timestamp4 = strtotime('2024-12-31');
		$result4 = formatReleaseDate('', $timestamp4, 'Quarter');
		$this->assertSame('Q4 2024', $result4);
	}

	public function testFormatReleaseDateCaseSensitivity(): void {
		// Test lowercase date type still works
		$timestamp = strtotime('2024-06-15');
		
		$result1 = formatReleaseDate('', $timestamp, 'full');
		$this->assertSame('June 15, 2024', $result1);

		$result2 = formatReleaseDate('', $timestamp, 'month');
		$this->assertSame('June 2024', $result2);

		$result3 = formatReleaseDate('', $timestamp, 'quarter');
		$this->assertSame('Q2 2024', $result3);

		$result4 = formatReleaseDate('', $timestamp, 'year');
		$this->assertSame('2024', $result4);
	}
}

