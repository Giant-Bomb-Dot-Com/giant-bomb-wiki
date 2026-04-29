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

# Regenerate XML sitemaps in the background on every container start.
# The generated files live in the container's ephemeral filesystem
# (/var/www/html/wiki-sitemap.xml and /var/www/html/wiki-sitemaps/),
# so they vanish on every redeploy or restart. Without this, Apache's
# !-f rewrite in .htaccess falls through to MediaWiki and happily
# serves Main_Page HTML with a 200 for /wiki-sitemap.xml until the
# 04:00 UTC cron fires -- which is what just poisoned our CDN cache.
# Runs async so Apache can start accepting traffic immediately; the
# worker's non-XML guard covers the few seconds before regen finishes.
# Set SITEMAP_ON_STARTUP=0 to opt out (e.g. when debugging).
if [ "${SITEMAP_ON_STARTUP:-1}" = "1" ] \
    && [ -x /usr/local/bin/wiki-admin ] \
    && [ -f /var/www/html/LocalSettings.php ]; then
    (
        sleep 5
        /usr/local/bin/wiki-admin sitemap \
            >> /var/log/mediawiki/sitemap-cron.log 2>&1 || true
    ) &
fi

exec apache2-foreground
