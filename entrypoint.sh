#!/usr/bin/env sh
set -e

# If LocalSettings.php exists in the host-mounted config folder, copy it in
if [ -f /config/LocalSettings.php ]; then
  cp /config/LocalSettings.php /var/www/html/LocalSettings.php

  if [ ! -f /var/www/.patched ]; then
    cd /var/www/html \
    && php maintenance/run.php update \
    && touch /var/www/.patched
  fi
fi

exec apache2-foreground
