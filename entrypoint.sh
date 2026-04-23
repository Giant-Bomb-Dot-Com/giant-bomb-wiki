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

# Run dev startup script if in dev mode and script exists
if [ "$MV_ENV" = "dev" ] && [ -f /docker/dev-startup.sh ]; then
    echo "Running dev startup script..."
    /bin/bash /docker/dev-startup.sh &
fi

# Dump runtime env to a shell-sourceable file so cron jobs (which otherwise
# run with a stripped environment) can pick up CANONICAL_SERVER, DB creds, etc.
# Keep key=value lines only; strip anything without an = to avoid shell errors.
env | grep -E '^[A-Za-z_][A-Za-z0-9_]*=' | sed 's/^\(.*\)$/export \1/' > /etc/container.env
chmod 644 /etc/container.env

# Start cron if installed (prod image installs it; dev image does not).
if command -v cron >/dev/null 2>&1; then
    echo "Starting cron daemon..."
    cron
fi

exec apache2-foreground
