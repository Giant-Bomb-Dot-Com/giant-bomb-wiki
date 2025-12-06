<?php
use MediaWiki\MediaWikiServices;

/**
 * CacheHelper
 * 
 * A generic helper class for caching data using MediaWiki's WANObjectCache.
 * Provides simple methods for storing and retrieving cached data with
 * configurable TTL and automatic cache key generation.
 * 
 * @example Basic usage:
 * $cache = CacheHelper::getInstance();
 * 
 * // Simple get/set
 * $data = $cache->get('my-key');
 * if ($data === false) {
 *     $data = expensiveOperation();
 *     $cache->set('my-key', $data, CacheHelper::TTL_HOUR);
 * }
 * 
 * @example Using getOrSet callback:
 * $data = $cache->getOrSet('my-key', function() {
 *     return expensiveOperation();
 * }, CacheHelper::TTL_DAY);
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
    
    // Common TTL constants (in seconds)
    const TTL_MINUTE = 60;
    const TTL_HOUR = 3600;
    const TTL_DAY = 86400;
    const TTL_WEEK = 604800;
    
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
        
        // First check if we have a cached value
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
        
        // Use getWithSetCallback for proper stampede protection
        return $this->cache->getWithSetCallback(
            $cacheKey,
            $ttl,
            function() use ($callback, $key) {
                $value = $callback();
                if ($this->debugLogging) {
                    error_log("✓ Cache SET: {$key} (value computed and cached)");
                }
                return $value;
            }
        );
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
     * // Returns something like: "concepts-letter_A-sort_alphabetical-page_1"
     */
    public function buildQueryKey(string $prefix, array $params): string {
        // Sort params for consistent key generation
        ksort($params);
        
        $parts = [$prefix];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                // Handle array values (e.g., game filters)
                $value = implode(',', $value);
            }
            // Sanitize and add to key
            $sanitizedValue = preg_replace('/[^a-zA-Z0-9_,-]/', '', (string)$value);
            if ($sanitizedValue !== '') {
                $parts[] = "{$key}_{$sanitizedValue}";
            }
        }
        
        return implode('-', $parts);
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
