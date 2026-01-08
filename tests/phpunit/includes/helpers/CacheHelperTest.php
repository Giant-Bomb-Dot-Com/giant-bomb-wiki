<?php

namespace MediaWiki\Skin\GiantBomb\Test\Helpers;

use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use WANObjectCache;
use CacheHelper;

/**
 * @covers CacheHelper
 *
 * @group Database
 */
class CacheHelperTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Load the CacheHelper class using MediaWiki's install path
		global $IP;
		require_once "$IP/skins/GiantBomb/includes/helpers/CacheHelper.php";
	}

	protected function tearDown(): void {
		// Reset singleton instance between tests
		$reflection = new \ReflectionClass(CacheHelper::class);
		$instanceProperty = $reflection->getProperty('instance');
		$instanceProperty->setAccessible(true);
		$instanceProperty->setValue(null, null); // null object for static property
		
		parent::tearDown();
	}

	// Tests for singleton pattern

	public function testGetInstanceReturnsSameInstance(): void {
		$instance1 = CacheHelper::getInstance();
		$instance2 = CacheHelper::getInstance();

		$this->assertSame($instance1, $instance2);
	}

	public function testGetInstanceReturnsCacheHelperInstance(): void {
		$instance = CacheHelper::getInstance();

		$this->assertInstanceOf(CacheHelper::class, $instance);
	}

	// Tests for getPrefix

	public function testGetPrefix(): void {
		$cache = CacheHelper::getInstance();
		$prefix = $cache->getPrefix();

		$this->assertSame('giantbomb', $prefix);
	}

	// Tests for getKnownCachePrefixes

	public function testGetKnownCachePrefixes(): void {
		$prefixes = CacheHelper::getKnownCachePrefixes();

		$this->assertIsArray($prefixes);
		$this->assertContains(CacheHelper::PREFIX_GAMES, $prefixes);
		$this->assertContains(CacheHelper::PREFIX_CONCEPTS, $prefixes);
		$this->assertContains(CacheHelper::PREFIX_PLATFORMS, $prefixes);
		$this->assertContains(CacheHelper::PREFIX_RELEASES, $prefixes);
		$this->assertCount(8, $prefixes);
	}

	// Tests for setDebugLogging

	public function testSetDebugLogging(): void {
		$cache = CacheHelper::getInstance();
		$result = $cache->setDebugLogging(false);

		// Should return self for method chaining
		$this->assertSame($cache, $result);
	}

	// Tests for getCache

	public function testGetCacheReturnsWANObjectCache(): void {
		$cache = CacheHelper::getInstance();
		$wanCache = $cache->getCache();

		$this->assertInstanceOf(WANObjectCache::class, $wanCache);
	}

	// Tests for makeKey

	public function testMakeKeyWithSingleComponent(): void {
		$cache = CacheHelper::getInstance();
		$key = $cache->makeKey('test');

		$this->assertStringContainsString('giantbomb', $key);
		$this->assertStringContainsString('test', $key);
	}

	public function testMakeKeyWithMultipleComponents(): void {
		$cache = CacheHelper::getInstance();
		$key = $cache->makeKey('prefix', 'component1', 'component2');

		$this->assertStringContainsString('giantbomb', $key);
		$this->assertStringContainsString('prefix', $key);
		$this->assertStringContainsString('component1', $key);
		$this->assertStringContainsString('component2', $key);
	}

	// Tests for buildQueryKey

	public function testBuildQueryKeyWithSimpleParams(): void {
		$cache = CacheHelper::getInstance();
		$key = $cache->buildQueryKey('test-prefix', ['param1' => 'value1', 'param2' => 'value2']);

		$this->assertStringStartsWith('test-prefix-v', $key);
		$this->assertStringContainsString('param1', $key);
		$this->assertStringContainsString('param2', $key);
		$this->assertStringContainsString('value1', $key);
		$this->assertStringContainsString('value2', $key);
	}

	public function testBuildQueryKeyWithEmptyParams(): void {
		$cache = CacheHelper::getInstance();
		$key = $cache->buildQueryKey('test-prefix', []);

		$this->assertStringStartsWith('test-prefix-v', $key);
		$this->assertStringEndsWith('1', $key); // Default version is 1
	}

	public function testBuildQueryKeyWithArrayValue(): void {
		$cache = CacheHelper::getInstance();
		$key = $cache->buildQueryKey('test-prefix', ['platforms' => ['PS5', 'Xbox']]);

		$this->assertStringContainsString('platforms', $key);
		$this->assertStringContainsString('PS5', $key);
		$this->assertStringContainsString('Xbox', $key);
	}

	public function testBuildQueryKeyWithEmptyStringValue(): void {
		$cache = CacheHelper::getInstance();
		$key = $cache->buildQueryKey('test-prefix', ['param1' => 'value1', 'param2' => '']);

		// Empty string values should be excluded
		$this->assertStringContainsString('param1', $key);
		$this->assertStringNotContainsString('param2', $key);
	}

	public function testBuildQueryKeySortsParams(): void {
		$cache = CacheHelper::getInstance();
		$key1 = $cache->buildQueryKey('test', ['z' => 'last', 'a' => 'first']);
		$key2 = $cache->buildQueryKey('test', ['a' => 'first', 'z' => 'last']);

		// Should produce same key regardless of param order
		$this->assertSame($key1, $key2);
	}

	public function testBuildQueryKeyWithSpecialCharacters(): void {
		$cache = CacheHelper::getInstance();
		$key = $cache->buildQueryKey('test', ['param' => 'value with spaces & special chars!']);

		// Should URL encode special characters
		$this->assertStringContainsString('param', $key);
		// The value should be encoded
		$this->assertStringNotContainsString('value with spaces', $key);
	}

	// Tests for buildSimpleKey

	public function testBuildSimpleKeyWithoutSuffix(): void {
		$cache = CacheHelper::getInstance();
		$key = $cache->buildSimpleKey('test-prefix');

		$this->assertStringStartsWith('test-prefix-v', $key);
		$this->assertStringEndsWith('1', $key); // Default version is 1
	}

	public function testBuildSimpleKeyWithSuffix(): void {
		$cache = CacheHelper::getInstance();
		$key = $cache->buildSimpleKey('test-prefix', 'suffix-value');

		$this->assertStringStartsWith('test-prefix-v', $key);
		$this->assertStringContainsString('suffix-value', $key);
	}

	// Tests for cache operations (if WANObjectCache is available)

	public function testSetAndGet(): void {
		$cache = CacheHelper::getInstance();
		$testKey = 'test-key-' . uniqid();
		$testValue = ['data' => 'test', 'number' => 42];

		// Set the value
		$setResult = $cache->set($testKey, $testValue, CacheHelper::TTL_MINUTE);
		$this->assertTrue($setResult);

		// Get the value
		$retrievedValue = $cache->get($testKey);
		$this->assertEquals($testValue, $retrievedValue);
	}

	public function testGetReturnsFalseForNonExistentKey(): void {
		$cache = CacheHelper::getInstance();
		$nonExistentKey = 'non-existent-key-' . uniqid();

		$result = $cache->get($nonExistentKey);
		$this->assertFalse($result);
	}

	public function testDelete(): void {
		$cache = CacheHelper::getInstance();
		$testKey = 'test-delete-' . uniqid();
		$testValue = 'test value';

		// Set a value
		$cache->set($testKey, $testValue, CacheHelper::TTL_MINUTE);

		// Verify it exists
		$this->assertNotFalse($cache->get($testKey));

		// Delete it
		$deleteResult = $cache->delete($testKey);
		$this->assertTrue($deleteResult);

		// Verify it's gone
		$this->assertFalse($cache->get($testKey));
	}

	public function testGetOrSetWithCacheMiss(): void {
		$cache = CacheHelper::getInstance();
		$testKey = 'test-getorset-' . uniqid();
		$callCount = 0;

		$callback = function() use (&$callCount) {
			$callCount++;
			return ['computed' => 'value', 'count' => $callCount];
		};

		// First call should compute the value
		$result1 = $cache->getOrSet($testKey, $callback, CacheHelper::TTL_MINUTE);
		$this->assertEquals(['computed' => 'value', 'count' => 1], $result1);
		$this->assertSame(1, $callCount);

		// Second call should return cached value (callback not called again)
		$result2 = $cache->getOrSet($testKey, $callback, CacheHelper::TTL_MINUTE);
		$this->assertEquals(['computed' => 'value', 'count' => 1], $result2);
		// Call count should still be 1 (callback not called again)
		$this->assertSame(1, $callCount);
	}

	public function testGetOrSetWithDifferentTTL(): void {
		$cache = CacheHelper::getInstance();
		$testKey = 'test-ttl-' . uniqid();

		$result = $cache->getOrSet($testKey, function() {
			return 'test value';
		}, CacheHelper::TTL_HOUR);

		$this->assertSame('test value', $result);
	}

	public function testGetOrSetVersioned(): void {
		$cache = CacheHelper::getInstance();
		$baseKey = 'test-versioned';
		$version = 'v2';
		$testValue = 'versioned value';

		$result = $cache->getOrSetVersioned($baseKey, $version, function() use ($testValue) {
			return $testValue;
		}, CacheHelper::TTL_MINUTE);

		$this->assertSame($testValue, $result);

		// Verify the versioned key was used
		$versionedKey = "{$baseKey}-{$version}";
		$cached = $cache->get($versionedKey);
		$this->assertSame($testValue, $cached);
	}

	public function testPurge(): void {
		$cache = CacheHelper::getInstance();
		$testKey = 'test-purge-' . uniqid();
		$testValue = 'purge test';

		// Set a value
		$cache->set($testKey, $testValue, CacheHelper::TTL_MINUTE);
		$this->assertNotFalse($cache->get($testKey));

		// Purge it
		$purgeResult = $cache->purge($testKey);
		$this->assertTrue($purgeResult);

		// Verify it's gone
		$this->assertFalse($cache->get($testKey));
	}

	// Tests for version management (requires database)

	public function testBuildQueryKeyIncrementsVersion(): void {
		$cache = CacheHelper::getInstance();
		$prefix = 'test-version-prefix-' . uniqid();

		// First call should use version 1
		$key1 = $cache->buildQueryKey($prefix, ['param' => 'value']);
		$this->assertStringContainsString('v1', $key1);

		// Purge the prefix to increment version
		$newVersion = $cache->purgeByPrefix($prefix);
		$this->assertSame(2, $newVersion);

		// Next call should use version 2
		$key2 = $cache->buildQueryKey($prefix, ['param' => 'value']);
		$this->assertStringContainsString('v2', $key2);
		$this->assertNotSame($key1, $key2);
	}

	public function testPurgeByPrefix(): void {
		$cache = CacheHelper::getInstance();
		$prefix = 'test-purge-prefix-' . uniqid();

		// Get initial version
		$key1 = $cache->buildQueryKey($prefix, ['test' => 'value']);
		$initialVersion = (int)substr($key1, strpos($key1, 'v') + 1, 1);

		// Purge by prefix
		$newVersion = $cache->purgeByPrefix($prefix);
		$this->assertSame($initialVersion + 1, $newVersion);

		// Verify new keys use new version
		$key2 = $cache->buildQueryKey($prefix, ['test' => 'value']);
		$this->assertStringContainsString("v{$newVersion}", $key2);
	}

	public function testPurgeAll(): void {
		$cache = CacheHelper::getInstance();
		$knownPrefixes = CacheHelper::getKnownCachePrefixes();

		$results = $cache->purgeAll();

		$this->assertIsArray($results);
		$this->assertCount(count($knownPrefixes), $results);

		// Verify each prefix has old and new version
		foreach ($knownPrefixes as $prefix) {
			$this->assertArrayHasKey($prefix, $results);
			$this->assertArrayHasKey('old', $results[$prefix]);
			$this->assertArrayHasKey('new', $results[$prefix]);
			$this->assertGreaterThan($results[$prefix]['old'], $results[$prefix]['new']);
		}
	}

	public function testClearAll(): void {
		$cache = CacheHelper::getInstance();

		// This should not throw an exception
		$result = $cache->clearAll();
		$this->assertTrue($result);
	}

	// Tests for edge cases

	public function testBuildQueryKeyWithNumericValues(): void {
		$cache = CacheHelper::getInstance();
		$key = $cache->buildQueryKey('test', ['page' => 1, 'limit' => 10]);

		$this->assertStringContainsString('page', $key);
		$this->assertStringContainsString('limit', $key);
		$this->assertStringContainsString('1', $key);
		$this->assertStringContainsString('10', $key);
	}

	public function testBuildQueryKeyWithBooleanValues(): void {
		$cache = CacheHelper::getInstance();
		$key = $cache->buildQueryKey('test', ['active' => true, 'inactive' => false]);

		$this->assertStringContainsString('active', $key);
		$this->assertStringContainsString('inactive', $key);
	}

	public function testGetOrSetWithNullValue(): void {
		$cache = CacheHelper::getInstance();
		$testKey = 'test-null-' . uniqid();

		$result = $cache->getOrSet($testKey, function() {
			return null;
		}, CacheHelper::TTL_MINUTE);

		$this->assertNull($result);
	}

	public function testGetOrSetWithZeroValue(): void {
		$cache = CacheHelper::getInstance();
		$testKey = 'test-zero-' . uniqid();

		$result = $cache->getOrSet($testKey, function() {
			return 0;
		}, CacheHelper::TTL_MINUTE);

		$this->assertSame(0, $result);
	}

	public function testGetOrSetWithEmptyArray(): void {
		$cache = CacheHelper::getInstance();
		$testKey = 'test-empty-array-' . uniqid();

		$result = $cache->getOrSet($testKey, function() {
			return [];
		}, CacheHelper::TTL_MINUTE);

		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	public function testBuildSimpleKeyWithEmptySuffix(): void {
		$cache = CacheHelper::getInstance();
		$key1 = $cache->buildSimpleKey('test');
		$key2 = $cache->buildSimpleKey('test', '');

		// Empty suffix should produce same result as no suffix
		$this->assertSame($key1, $key2);
	}

	public function testBuildQueryKeyWithNullValues(): void {
		$cache = CacheHelper::getInstance();
		$key = $cache->buildQueryKey('test', ['param1' => 'value', 'param2' => null]);

		// Null should be converted to empty string and excluded
		$this->assertStringContainsString('param1', $key);
		$this->assertStringNotContainsString('param2', $key);
	}
}

