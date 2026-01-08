<?php
/**
 * Maintenance script to purge GiantBomb skin cache entries
 * 
 * This works by incrementing version numbers for cache prefixes.
 * Existing cached entries become orphaned and are ignored; new requests
 * fetch fresh data using the new version number.
 * 
 * Usage from within the MediaWiki container:
 *   php maintenance/run.php /var/www/html/skins/GiantBomb/includes/maintenance/PurgeGiantBombSMWCache.php
 * 
 * Options:
 *   --all         Purge all known cache prefixes (increment all versions)
 *   --clear       Clear entire cache (APCu only, use with caution)
 *   --prefix      Purge specific prefix (games, concepts, platforms, releases)
 *   --key         Purge a specific cache key
 *   --list        List known cache prefixes
 * 
 * Examples:
 *   php maintenance/run.php .../PurgeGiantBombSMWCache.php --all
 *   php maintenance/run.php .../PurgeGiantBombSMWCache.php --prefix=games
 *   php maintenance/run.php .../PurgeGiantBombSMWCache.php --prefix=platforms --prefix=concepts
 *   php maintenance/run.php .../PurgeGiantBombSMWCache.php --key=games-v1-currentPage_1-itemsPerPage_48
 *   php maintenance/run.php .../PurgeGiantBombSMWCache.php --list
 */

$IP = getenv('MW_INSTALL_PATH');
if ($IP === false || $IP === '') {
    $IP = dirname(__DIR__, 4);
}
require_once "$IP/maintenance/Maintenance.php";

// Load the CacheHelper
require_once dirname(__DIR__) . '/helpers/CacheHelper.php';

class PurgeGiantBombSMWCache extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->addDescription('Purge GiantBomb skin cache entries by incrementing version numbers');
        
        $this->addOption('all', 'Purge all known cache prefixes', false, false);
        $this->addOption('clear', 'Clear entire cache backend (APCu only)', false, false);
        $this->addOption('prefix', 'Purge specific prefix(es)', false, true, 'p', true);
        $this->addOption('key', 'Purge specific cache key(s)', false, true, 'k', true);
        $this->addOption('list', 'List known cache prefixes', false, false);
    }

    public function execute() {
        $cache = CacheHelper::getInstance();
        
        // Disable debug logging for cleaner output
        #$cache->setDebugLogging(false);
        
        // List known prefixes
        if ($this->hasOption('list')) {
            $this->listPrefixes();
            return;
        }
        
        // Clear entire cache
        if ($this->hasOption('clear')) {
            $this->clearAll($cache);
            return;
        }
        
        // Purge all known prefixes
        if ($this->hasOption('all')) {
            $this->purgeAll($cache);
            return;
        }
        
        // Purge specific prefixes
        $prefixes = $this->getOption('prefix');
        if ($prefixes) {
            if (!is_array($prefixes)) {
                $prefixes = [$prefixes];
            }
            $this->purgePrefixes($cache, $prefixes);
        }
        
        // Purge specific keys
        $keys = $this->getOption('key');
        if ($keys) {
            if (!is_array($keys)) {
                $keys = [$keys];
            }
            $this->purgeKeys($cache, $keys);
        }
        
        // If no options provided, show help
        if (!$prefixes && !$keys && !$this->hasOption('all') && !$this->hasOption('clear')) {
            $this->output("No action specified. Use --help for usage information.\n");
            $this->output("\nQuick reference:\n");
            $this->output("  --all            Purge all known cache prefixes\n");
            $this->output("  --prefix=NAME    Purge specific prefix (can be repeated)\n");
            $this->output("  --key=KEY        Purge specific cache key (can be repeated)\n");
            $this->output("  --list           List known cache prefixes\n");
            $this->output("  --clear          Clear entire APCu cache\n");
        }
    }
    
    private function listPrefixes(): void {
        $prefixes = CacheHelper::getKnownCachePrefixes();
        
        $this->output("Known GiantBomb cache prefixes:\n");
        $this->output("================================\n");
        foreach ($prefixes as $prefix) {
            $this->output("  - {$prefix}\n");
        }
        $this->output("\nTotal: " . count($prefixes) . " prefixes\n");
    }
    
    private function purgeAll(CacheHelper $cache): void {
        $this->output("Purging all known GiantBomb cache entries...\n");
        $this->output("=============================================\n");
        $this->output("(Incrementing version numbers to invalidate cached data)\n\n");
        
        $results = $cache->purgeAll();
        
        foreach ($results as $prefix => $versions) {
            $this->output("  ✓ {$prefix}: v{$versions['old']} -> v{$versions['new']}\n");
        }
        
        $this->output("\nDone! " . count($results) . " prefixes invalidated.\n");
        $this->output("Old cached entries will be ignored; new data will be fetched on next request.\n");
    }
    
    private function clearAll(CacheHelper $cache): void {
        $this->output("Clearing entire cache backend...\n");
        $this->output("================================\n");
        
        if (!function_exists('apcu_clear_cache')) {
            $this->output("⚠ APCu not available. Falling back to purgeAll().\n");
            $this->purgeAll($cache);
            return;
        }
        
        $result = $cache->clearAll();
        
        if ($result) {
            $this->output("✓ Cache cleared successfully.\n");
        } else {
            $this->output("✗ Failed to clear cache.\n");
        }
    }
    
    private function purgePrefixes(CacheHelper $cache, array $prefixes): void {
        $knownPrefixes = CacheHelper::getKnownCachePrefixes();
        
        $this->output("Purging cache by prefix...\n");
        $this->output("==========================\n");
        $this->output("(Incrementing version numbers to invalidate cached data)\n\n");
        
        foreach ($prefixes as $prefix) {
            if (!in_array($prefix, $knownPrefixes)) {
                $this->output("  ⚠ Unknown prefix: {$prefix} (purging anyway)\n");
            }
            
            $newVersion = $cache->purgeByPrefix($prefix);
            $this->output("  ✓ {$prefix}: now at v{$newVersion}\n");
        }
        
        $this->output("\nDone! Old cached entries will be ignored on next request.\n");
    }
    
    private function purgeKeys(CacheHelper $cache, array $keys): void {
        $this->output("Purging specific cache keys...\n");
        $this->output("==============================\n");
        
        $success = 0;
        $failed = 0;
        
        foreach ($keys as $key) {
            $result = $cache->purge($key);
            $status = $result ? '✓' : '✗';
            $this->output("  {$status} {$key}\n");
            
            if ($result) {
                $success++;
            } else {
                $failed++;
            }
        }
        
        $this->output("\nDone! Success: {$success}, Failed: {$failed}\n");
    }
}

$maintClass = PurgeGiantBombSMWCache::class;
require_once RUN_MAINTENANCE_IF_MAIN;

