#!/usr/bin/env sh
set -e

cd /var/www/html/

# Copy LocalSettings.php from config if the wiki was previously installed
# (Skip for fresh installs - installwiki.sh handles that case)
if [ -f /var/.installed ] && [ -f /config/LocalSettings.php ] && [ ! -f /var/www/html/LocalSettings.php ]; then
    cp /config/LocalSettings.php /var/www/html/LocalSettings.php
    chown www-data:www-data /var/www/html/LocalSettings.php
    echo "Copied /config/LocalSettings.php to /var/www/html/"
fi

exec apache2-foreground
