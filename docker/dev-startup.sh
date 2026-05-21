#!/bin/bash
# Dev startup script - clears caches on container start
# This runs BEFORE Apache starts

set -e

echo "=== Dev Environment Startup ==="

# sync LocalSettings from /config on every start
if [ -f /config/LocalSettings.php ]; then
    cp /config/LocalSettings.php /var/www/html/LocalSettings.php
    chown www-data:www-data /var/www/html/LocalSettings.php
    touch /var/.installed
    echo "Synced /config/LocalSettings.php to /var/www/html/"
fi

# pin SemanticScribunto to 2.3.3 - composer.lock might pull 7.x which needs SMW 7
if [ -f /var/www/html/extensions/SemanticScribunto/extension.json ]; then
    ss_ver=$(php -r 'echo json_decode(file_get_contents("/var/www/html/extensions/SemanticScribunto/extension.json"))->version;')
    case "$ss_ver" in
        7.*|*alpha*|*dev*)
            echo "Replacing SemanticScribunto $ss_ver with 2.3.3 (SMW 6.x)..."
            rm -rf /var/www/html/extensions/SemanticScribunto
            git clone --depth 1 --branch 2.3.3 https://github.com/SemanticMediaWiki/SemanticScribunto.git \
                /var/www/html/extensions/SemanticScribunto
            ;;
    esac
fi

# Only run in dev environment
if [ "$MV_ENV" != "dev" ]; then
    echo "Not in dev mode, skipping cache clear"
    exit 0
fi

echo "Clearing caches for dev environment..."

# Wait for database to be ready
echo "Waiting for database..."
MW_MAINTENANCE=(php /var/www/html/maintenance/run.php)
if [ -f /var/www/html/LocalSettings.php ]; then
    :
elif [ -f /config/LocalSettings.php ]; then
    MW_MAINTENANCE+=(--conf /config/LocalSettings.php)
fi
until "${MW_MAINTENANCE[@]}" showSiteStats.php > /dev/null 2>&1; do
    sleep 1
done
echo "Database ready!"

# Clear localisation cache (interface messages)
echo "Rebuilding localisation cache..."
"${MW_MAINTENANCE[@]}" rebuildLocalisationCache.php --force 2>/dev/null || true

# Clear parser cache
echo "Clearing parser cache..."
"${MW_MAINTENANCE[@]}" purgeParserCache.php --age=0 2>/dev/null || true

# Clear APCu cache (ResourceLoader versions, etc.)
echo "Clearing APCu cache..."
php -r "if (function_exists('apcu_clear_cache')) { apcu_clear_cache(); echo 'APCu cleared'; } else { echo 'APCu not available'; }" || true

# maintenance runs as root so fix log ownership
chown -R www-data:www-data /var/log/mediawiki/ 2>/dev/null || true

echo "=== Dev startup complete ==="
