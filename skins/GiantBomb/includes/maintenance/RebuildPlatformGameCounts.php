<?php
use MediaWiki\MediaWikiServices;
/**
 * Rebuild platform game count cache
 * 
 * Usage:
 *   php maintenance/rebuildPlatformGameCounts.php
 *   php maintenance/rebuildPlatformGameCounts.php --platform="PlayStation 5"
 */

require_once __DIR__ . '/Maintenance.php';

class RebuildPlatformGameCounts extends Maintenance {
    public function __construct() {
        parent::__construct();
        
        // Load the helper functions using MediaWiki's install path
		global $IP;
		require_once "$IP/skins/GiantBomb/includes/helpers/PlatformHelper.php";
        
        $this->addDescription('Rebuild the platform game count cache');
        $this->addOption('platform', 'Specific platform name to rebuild (optional)', false, true);
        $this->addOption('batch-size', 'Number of platforms to process at once', false, true);
        $this->addOption('sleep', 'Seconds to sleep between batches to reduce load', false, true);
    }

    public function execute() {
        $this->output("Starting platform game count cache rebuild...\n");
        
        // Ensure the cache table exists
        $this->ensureCacheTableExists();
        
        $platformName = $this->getOption('platform');
        $batchSize = $this->getOption('batch-size', 10);
        $sleepTime = $this->getOption('sleep', 2);
        
        if ($platformName) {
            // Rebuild specific platform
            $this->output("Rebuilding counts for: $platformName\n");
            $stats = $this->rebuildPlatformGameCountCache([$platformName]);
        } else {
            // Rebuild all platforms
            $this->output("Rebuilding counts for all platforms...\n");
            $stats = $this->rebuildPlatformGameCountCache();
        }
        
        $this->output("\n=== Rebuild Complete ===\n");
        $this->output("Processed: {$stats['processed']}\n");
        $this->output("Updated: {$stats['updated']}\n");
        $this->output("Errors: {$stats['errors']}\n");
    }
    
    /**
     * Ensure the platform_game_counts table exists, create it if not
     */
    private function ensureCacheTableExists() {
        $dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
        
        // Check if table exists
        if ($dbw->tableExists('platform_game_counts', __METHOD__)) {
            $this->output("✓ Cache table 'platform_game_counts' already exists\n");
            return;
        }
        
        $this->output("→ Creating cache table 'platform_game_counts'...\n");
        
        try {
            $sql = "CREATE TABLE platform_game_counts (
                platform_name VARCHAR(255) PRIMARY KEY,
                game_count INT NOT NULL,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $dbw->query($sql, __METHOD__);
            $this->output("✓ Cache table created successfully\n");
            
        } catch (Exception $e) {
            $this->fatalError("Failed to create cache table: " . $e->getMessage());
        }
    }
    
    /**
     * Rebuild the game count cache for all platforms (run via maintenance script)
     * 
     * @param array $platformNames Optional array of specific platforms to rebuild. If empty, rebuilds all.
     * @return array Statistics about the rebuild (processed, updated, errors)
     */
    function rebuildPlatformGameCountCache($platformNames = []) {
        $dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
        
        $stats = ['processed' => 0, 'updated' => 0, 'errors' => 0];
        
        // If no specific platforms provided, get all platforms
        if (empty($platformNames)) {
            $queryConditions = '[[Category:Platforms]]';
            $params = '|limit=5000|mainlabel=-';
            $fullQuery = $queryConditions . $params;
            
            try {
                $api = new ApiMain(
                    new DerivativeRequest(
                        RequestContext::getMain()->getRequest(),
                        [
                            'action' => 'ask',
                            'query' => $fullQuery,
                            'format' => 'json',
                        ],
                        true
                    ),
                    true
                );
                
                $api->execute();
                $result = $api->getResult()->getResultData(null, ['Strip' => 'all']);
                
                if (isset($result['query']['results'])) {
                    foreach ($result['query']['results'] as $pageName => $pageData) {
                        $platformNames[] = str_replace('Platforms/', '', $pageName);
                    }
                }
            } catch (Exception $e) {
                error_log("Error fetching platforms for cache rebuild: " . $e->getMessage());
                $stats['errors']++;
                return $stats;
            }
        }
        
        // Process each platform
        foreach ($platformNames as $platformName) {
            $stats['processed']++;
            
            try {
                // Get the actual count from SMW
                $gameCount = getGameCountForPlatformFromSMW($platformName);
                
                // Update or insert into cache table
                $dbw->upsert(
                    'platform_game_counts',
                    [
                        'platform_name' => $platformName,
                        'game_count' => $gameCount,
                        'last_updated' => $dbw->timestamp()
                    ],
                    ['platform_name'],
                    [
                        'game_count' => $gameCount,
                        'last_updated' => $dbw->timestamp()
                    ],
                    __METHOD__
                );
                
                $stats['updated']++;
                error_log("✓ Updated game count for $platformName: $gameCount games");
                
            } catch (Exception $e) {
                error_log("✗ Error updating game count for $platformName: " . $e->getMessage());
                $stats['errors']++;
            }
        }
        
        return $stats;
    }
}

$maintClass = RebuildPlatformGameCounts::class;
require_once RUN_MAINTENANCE_IF_MAIN;