#!/bin/bash
# Dev startup script - clears caches on container start
# This runs BEFORE Apache starts

set -e

echo "=== Dev Environment Startup ==="

# Only run in dev environment
if [ "$MV_ENV" != "dev" ]; then
    echo "Not in dev mode, skipping cache clear"
    exit 0
fi

echo "Clearing caches for dev environment..."

# Wait for database to be ready
echo "Waiting for database..."
until php /var/www/html/maintenance/run.php version.php > /dev/null 2>&1; do
    sleep 1
done
echo "Database ready!"

# Clear localisation cache (interface messages)
echo "Rebuilding localisation cache..."
php /var/www/html/maintenance/run.php rebuildLocalisationCache.php --force 2>/dev/null || true

# Clear parser cache
echo "Clearing parser cache..."
php /var/www/html/maintenance/run.php purgeParserCache.php --age=0 2>/dev/null || true

# Clear APCu cache (ResourceLoader versions, etc.)
echo "Clearing APCu cache..."
php -r "if (function_exists('apcu_clear_cache')) { apcu_clear_cache(); echo 'APCu cleared'; } else { echo 'APCu not available'; }" || true

echo "=== Dev startup complete ==="
