<?php
use MediaWiki\MediaWikiServices;

/**
 * CacheHelper
 * 
 * A generic helper class for caching data using MediaWiki's WANObjectCache.
 * Provides simple methods for storing and retrieving cached data with
 * configurable TTL and automatic cache key generation.
 * 
 * @example Using getOrSet callback:
 * $cache = CacheHelper::getInstance();
 * 
 * // Generate a cache key from query parameters
 * $cacheKey = $cache->buildQueryKey(CacheHelper::PREFIX_RELEASES, [
 *     'date' => $today,
 *     'region' => $filterRegion,
 *     'platform' => $filterPlatform
 * ]);
 * 
 * // Get the data from the cache, or compute and store it if not found
 * $data = $cache->getOrSet($cacheKey, function() use ($filterRegion, $filterPlatform) {
 *     return fetchReleasesFromSMW($filterRegion, $filterPlatform);
 * }, CacheHelper::TTL_HOUR);
 */
class CacheHelper {
    /** @var CacheHelper|null Singleton instance */
    private static $instance = null;
    
    /** @var \WANObjectCache The underlying MediaWiki cache */
    private $cache;
    
    /** @var string Prefix for all cache keys */
    private $prefix = 'giantbomb';
    
    /** @var bool Whether to log cache hits/misses */
    private $debugLogging = true;
    
    /** @var string Database table name for cache versions */
    private const VERSION_TABLE = 'giantbomb_cache_versions';
    
    /** @var bool Whether the version table has been verified to exist */
    private static $tableVerified = false;
    
    // Common TTL constants (in seconds)
    const TTL_MINUTE = 60;
    const TTL_HOUR = 3600;
    const TTL_DAY = 86400;
    const TTL_WEEK = 604800;
    
    // Default cache time for queries is 1 hour
    const QUERY_TTL = self::TTL_HOUR;
    
    // Cache key prefix constants - used with buildQueryKey() or buildSimpleKey()
    const PREFIX_GAMES = 'games';
    const PREFIX_CONCEPTS = 'concepts';
    const PREFIX_PLATFORMS = 'platforms';
    const PREFIX_PLATFORMS_COUNT = 'platforms-count';
    const PREFIX_PLATFORMS_LIST = 'platforms-list';
    const PREFIX_PLATFORMS_ABBREV = 'platforms-abbrev';
    const PREFIX_PLATFORMS_FOR_GAME = 'platforms-for-game';
    const PREFIX_RELEASES = 'releases';
    
    /**
     * Private constructor - use getInstance()
     */
    private function __construct() {
        $this->cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
    }
    
    /**
     * Get the singleton instance
     * 
     * @return CacheHelper
     */
    public static function getInstance(): CacheHelper {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Generate a cache key with the configured prefix
     * 
     * @param string ...$components Key components to join
     * @return string The generated cache key
     */
    public function makeKey(string ...$components): string {
        return $this->cache->makeKey($this->prefix, ...$components);
    }
    
    /**
     * Get a value from the cache
     * 
     * @param string $key The cache key (will be prefixed automatically if not already)
     * @return mixed The cached value, or false if not found
     */
    public function get(string $key) {
        $cacheKey = $this->ensureKey($key);
        $value = $this->cache->get($cacheKey);
        
        if ($this->debugLogging) {
            if ($value !== false) {
                error_log("✓ Cache HIT: {$key}");
            } else {
                error_log("⚠ Cache MISS: {$key}");
            }
        }
        
        return $value;
    }
    
    /**
     * Set a value in the cache
     * 
     * @param string $key The cache key
     * @param mixed $value The value to cache
     * @param int $ttl Time to live in seconds (default: 1 day)
     * @return bool True on success
     */
    public function set(string $key, $value, int $ttl = self::TTL_DAY): bool {
        $cacheKey = $this->ensureKey($key);
        $result = $this->cache->set($cacheKey, $value, $ttl);
        
        if ($this->debugLogging) {
            $ttlHuman = $this->formatTTL($ttl);
            error_log("✓ Cache SET: {$key} (TTL: {$ttlHuman})");
        }
        
        return $result;
    }
    
    /**
     * Delete a value from the cache
     * 
     * @param string $key The cache key
     * @return bool True on success
     */
    public function delete(string $key): bool {
        $cacheKey = $this->ensureKey($key);
        $result = $this->cache->delete($cacheKey);
        
        if ($this->debugLogging) {
            error_log("✓ Cache DELETE: {$key}");
        }
        
        return $result;
    }
    
    /**
     * Get a value from cache, or compute and store it if not found
     * 
     * This is the recommended way to use the cache - it handles the
     * get/compute/set pattern automatically with proper locking.
     * 
     * @param string $key The cache key
     * @param callable $callback Function to compute the value if not cached
     * @param int $ttl Time to live in seconds (default: 1 day)
     * @return mixed The cached or computed value
     * 
     * @example
     * $concepts = $cache->getOrSet('concepts-all', function() {
     *     return queryConceptsFromSMW();
     * }, CacheHelper::TTL_HOUR);
     */
    public function getOrSet(string $key, callable $callback, int $ttl = self::TTL_DAY) {
        $cacheKey = $this->ensureKey($key);
        
        // Try to get from cache first
        $cachedValue = $this->cache->get($cacheKey);
        if ($cachedValue !== false) {
            if ($this->debugLogging) {
                error_log("✓ Cache HIT: {$key}");
            }
            return $cachedValue;
        }
        
        if ($this->debugLogging) {
            error_log("⚠ Cache MISS: {$key} (computing value)");
        }
        
        // Compute the value
        $value = $callback();
        
        // Store in cache using explicit set() for reliable storage
        $setResult = $this->cache->set($cacheKey, $value, $ttl);
        
        if ($this->debugLogging) {
            $ttlHuman = $this->formatTTL($ttl);
            if ($setResult) {
                error_log("✓ Cache SET: {$key} (TTL: {$ttlHuman})");
            } else {
                error_log("✗ Cache SET FAILED: {$key} - value not stored");
            }
        }
        
        return $value;
    }
    
    /**
     * Get a value with a versioned key
     * 
     * Useful when you need to invalidate cache by incrementing a version number.
     * 
     * @param string $key Base cache key
     * @param string $version Version string (e.g., 'v1', 'v2')
     * @param callable $callback Function to compute the value if not cached
     * @param int $ttl Time to live in seconds
     * @return mixed The cached or computed value
     */
    public function getOrSetVersioned(string $key, string $version, callable $callback, int $ttl = self::TTL_DAY) {
        $versionedKey = "{$key}-{$version}";
        return $this->getOrSet($versionedKey, $callback, $ttl);
    }
    
    /**
     * Build a cache key from query parameters
     * 
     * Creates a deterministic cache key from an array of parameters,
     * useful for caching query results with different filters.
     * Includes a version number to support cache invalidation.
     * 
     * @param string $prefix Key prefix (e.g., 'concepts', 'platforms')
     * @param array $params Query parameters
     * @return string The generated cache key
     * 
     * @example
     * $key = $cache->buildQueryKey('concepts', [
     *     'letter' => 'A',
     *     'sort' => 'alphabetical',
     *     'page' => 1
     * ]);
     * // Returns something like: "concepts-v1-letter_A-sort_alphabetical-page_1"
     */
    public function buildQueryKey(string $prefix, array $params): string {
        // Sort params for consistent key generation
        ksort($params);
        
        // Get the current version for this prefix
        $version = $this->getPrefixVersion($prefix);
        
        $parts = [$prefix, "v{$version}"];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                // Handle array values (e.g., game filters)
                $value = implode(',', $value);
            }
            // Sanitize and add to key
            $sanitizedValue = preg_replace('/[^a-zA-Z0-9_#,-]/', '', (string)$value);
            if ($sanitizedValue !== '') {
                $parts[] = "{$key}_{$sanitizedValue}";
            }
        }
        
        return implode('-', $parts);
    }
    
    /**
     * Build a simple versioned cache key
     * 
     * Use this for keys that don't have query parameters, just a prefix
     * and optional suffix. The version is automatically included.
     * 
     * @param string $prefix Key prefix (e.g., 'platforms-list', 'platforms-abbrev')
     * @param string $suffix Optional suffix to append (e.g., game name)
     * @return string The generated cache key with version
     * 
     * @example
     * $key = $cache->buildSimpleKey('platforms-list');
     * // Returns: "platforms-list-v1"
     * 
     * $key = $cache->buildSimpleKey('platforms-for-game', 'HalfLife2');
     * // Returns: "platforms-for-game-v1-HalfLife2"
     */
    public function buildSimpleKey(string $prefix, string $suffix = ''): string {
        $version = $this->getPrefixVersion($prefix);
        
        if ($suffix !== '') {
            return "{$prefix}-v{$version}-{$suffix}";
        }
        
        return "{$prefix}-v{$version}";
    }
    
    /**
     * Ensure the cache versions table exists in the database
     * 
     * Creates the table if it doesn't exist. This is called lazily
     * on first version read/write operation.
     */
    private function ensureVersionTable(): void {
        if (self::$tableVerified) {
            return;
        }
        
        $dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
        
        if (!$dbw->tableExists(self::VERSION_TABLE, __METHOD__)) {
            $dbw->query(
                "CREATE TABLE IF NOT EXISTS " . self::VERSION_TABLE . " (
                    prefix VARCHAR(100) PRIMARY KEY,
                    version INT NOT NULL DEFAULT 1,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )",
                __METHOD__
            );
            
            if ($this->debugLogging) {
                error_log("✓ Cache version table created: " . self::VERSION_TABLE);
            }
        }
        
        self::$tableVerified = true;
    }
    
    /**
     * Get the current version number for a cache prefix
     * 
     * Versions are stored in the database to persist across PHP processes
     * (APCu is per-process so can't be used for this).
     * 
     * @param string $prefix The cache prefix
     * @return int The version number (defaults to 1)
     */
    private function getPrefixVersion(string $prefix): int {
        $this->ensureVersionTable();
        
        $dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
        
        $row = $dbr->selectRow(
            self::VERSION_TABLE,
            ['version'],
            ['prefix' => $prefix],
            __METHOD__
        );
        
        return $row ? (int)$row->version : 1;
    }
    
    /**
     * Increment the version number for a cache prefix
     * This effectively invalidates all cached entries for that prefix
     * 
     * @param string $prefix The cache prefix
     * @return int The new version number
     */
    private function incrementPrefixVersion(string $prefix): int {
        $this->ensureVersionTable();
        
        $currentVersion = $this->getPrefixVersion($prefix);
        $newVersion = $currentVersion + 1;
        
        $dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
        
        $dbw->upsert(
            self::VERSION_TABLE,
            [
                'prefix' => $prefix,
                'version' => $newVersion
            ],
            ['prefix'],
            [
                'version' => $newVersion
            ],
            __METHOD__
        );
        
        $setResult = $dbw->affectedRows() > 0;
        
        if ($this->debugLogging) {
            $status = $setResult ? 'success' : 'FAILED';
            error_log("✓ Cache VERSION INCREMENT: {$prefix} (v{$currentVersion} -> v{$newVersion}) [{$status}]");
        }
        
        return $newVersion;
    }
    
    /**
     * Enable or disable debug logging
     * 
     * @param bool $enabled Whether to enable logging
     * @return self For method chaining
     */
    public function setDebugLogging(bool $enabled): self {
        $this->debugLogging = $enabled;
        return $this;
    }
    
    /**
     * Get the underlying WANObjectCache instance
     * 
     * For advanced use cases that need direct cache access.
     * 
     * @return \WANObjectCache
     */
    public function getCache(): \WANObjectCache {
        return $this->cache;
    }
    
    /**
     * Get the cache prefix used by this helper
     * 
     * @return string The prefix string
     */
    public function getPrefix(): string {
        return $this->prefix;
    }
    
    /**
     * List of known cache key prefixes used by the GiantBomb skin
     * Used for bulk purging operations
     * 
     * @return array List of cache key prefixes
     */
    public static function getKnownCachePrefixes(): array {
        return [
            self::PREFIX_GAMES,
            self::PREFIX_CONCEPTS,
            self::PREFIX_PLATFORMS,
            self::PREFIX_PLATFORMS_COUNT,
            self::PREFIX_PLATFORMS_LIST,
            self::PREFIX_PLATFORMS_ABBREV,
            self::PREFIX_PLATFORMS_FOR_GAME,
            self::PREFIX_RELEASES,
        ];
    }
    
    /**
     * Purge a specific cache key
     * 
     * @param string $key The cache key to purge
     * @return bool True if deletion was successful
     */
    public function purge(string $key): bool {
        $result = $this->delete($key);
        if ($this->debugLogging) {
            error_log("🗑 Cache PURGE: {$key} - " . ($result ? 'success' : 'failed'));
        }
        return $result;
    }
    
    /**
     * Purge all cache entries for a specific prefix (e.g., 'games', 'concepts')
     * 
     * This works by incrementing the version number for the prefix.
     * All existing cached entries become orphaned (they use the old version)
     * and will naturally expire. New requests will use the new version.
     * 
     * @param string $prefix The prefix to purge (e.g., 'games', 'platforms')
     * @return int The new version number
     */
    public function purgeByPrefix(string $prefix): int {
        $oldVersion = $this->getPrefixVersion($prefix);
        $newVersion = $this->incrementPrefixVersion($prefix);
        
        if ($this->debugLogging) {
            error_log("🗑 Cache PURGE by prefix: {$prefix} (version {$oldVersion} -> {$newVersion})");
        }
        
        return $newVersion;
    }
    
    /**
     * Purge all known GiantBomb cache entries
     * 
     * Increments version numbers for all known prefixes, effectively
     * invalidating all cached entries.
     * 
     * @return array Results showing old and new versions for each prefix
     */
    public function purgeAll(): array {
        $results = [];
        $prefixes = self::getKnownCachePrefixes();
        
        foreach ($prefixes as $prefix) {
            $oldVersion = $this->getPrefixVersion($prefix);
            $newVersion = $this->purgeByPrefix($prefix);
            $results[$prefix] = ['old' => $oldVersion, 'new' => $newVersion];
        }
        
        if ($this->debugLogging) {
            error_log("🗑 Cache PURGE ALL: " . count($prefixes) . " prefixes invalidated");
        }
        
        return $results;
    }
    
    /**
     * Clear the entire cache (use with caution!)
     * 
     * This attempts to clear the entire cache backend.
     * Only works with certain cache backends (APCu, etc.)
     * 
     * @return bool True if the operation was attempted
     */
    public function clearAll(): bool {
        // For APCu
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
            if ($this->debugLogging) {
                error_log("🗑 Cache CLEAR ALL: APCu cache cleared");
            }
            return true;
        }
        
        // For other backends, fall back to purging known prefixes
        if ($this->debugLogging) {
            error_log("⚠ Cache CLEAR ALL: Backend doesn't support full clear, using purgeAll()");
        }
        $this->purgeAll();
        return true;
    }
    
    /**
     * Ensure the key has the proper prefix
     * 
     * @param string $key The key to check
     * @return string The prefixed key
     */
    private function ensureKey(string $key): string {
        // If key doesn't start with a colon (makeKey format), add prefix
        if (strpos($key, ':') === false) {
            return $this->makeKey($key);
        }
        return $key;
    }
    
    /**
     * Format TTL for human-readable logging
     * 
     * @param int $ttl TTL in seconds
     * @return string Human-readable TTL
     */
    private function formatTTL(int $ttl): string {
        if ($ttl >= self::TTL_DAY) {
            $days = round($ttl / self::TTL_DAY, 1);
            return "{$days} day(s)";
        } elseif ($ttl >= self::TTL_HOUR) {
            $hours = round($ttl / self::TTL_HOUR, 1);
            return "{$hours} hour(s)";
        } elseif ($ttl >= self::TTL_MINUTE) {
            $minutes = round($ttl / self::TTL_MINUTE, 1);
            return "{$minutes} minute(s)";
        }
        return "{$ttl} seconds";
    }
}
