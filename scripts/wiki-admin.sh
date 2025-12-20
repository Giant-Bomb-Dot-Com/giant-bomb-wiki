#!/usr/bin/env sh
set -eu

MW_ROOT="/var/www/html"
CONF_PATH="$MW_ROOT/LocalSettings.php"

run_php() {
  php "$MW_ROOT/maintenance/run.php" "$@"
}

cmd=${1:-}
shift || true

case "${cmd}" in
  install)
    : "${DB_NAME:?DB_NAME required}"
    : "${DB_USER:?DB_USER required}"
    : "${DB_PASSWORD:?DB_PASSWORD required}"
    : "${CLOUDSQL_INSTANCE:?CLOUDSQL_INSTANCE required}"
    SERVER_URL="${SERVER_URL:-${APP_BASE_ORIGIN:-http://localhost}}"
    SCRIPT_PATH="${SCRIPT_PATH:-${APP_BASE_PATH:-/wiki}}"
    SITE_NAME="${SITE_NAME:-gb}"
    ADMIN_USER="${ADMIN_USER:-admin}"
    : "${ADMIN_PASS:?ADMIN_PASS required}"

    if [ -f "$CONF_PATH" ]; then mv "$CONF_PATH" "$CONF_PATH.keep"; fi

    # Use Cloud SQL unix socket when provided: pass as host:socket
    DBSERVER_HOST="localhost"
    if [ -n "${CLOUDSQL_INSTANCE:-}" ]; then
      DBSERVER_HOST="localhost:/cloudsql/${CLOUDSQL_INSTANCE}"
    fi

    php "$MW_ROOT/maintenance/install.php" \
      --dbtype mysql \
      --dbserver "$DBSERVER_HOST" \
      --dbname "$DB_NAME" \
      --dbuser "$DB_USER" \
      --dbpass "$DB_PASSWORD" \
      --server "$SERVER_URL" \
      --scriptpath "$SCRIPT_PATH" \
      --lang en \
      --pass "$ADMIN_PASS" \
      "$SITE_NAME" "$ADMIN_USER"

    if [ -f "$CONF_PATH.keep" ]; then mv "$CONF_PATH.keep" "$CONF_PATH"; fi
    ;;

  install-innodb)
    : "${DB_NAME:?DB_NAME required}"
    : "${DB_USER:?DB_USER required}"
    : "${DB_PASSWORD:?DB_PASSWORD required}"
    SERVER_URL="${SERVER_URL:-${APP_BASE_ORIGIN:-http://localhost}}"
    SCRIPT_PATH="${SCRIPT_PATH:-${APP_BASE_PATH:-/wiki}}"
    SITE_NAME="${SITE_NAME:-gb}"
    ADMIN_USER="${ADMIN_USER:-admin}"
    : "${ADMIN_PASS:?ADMIN_PASS required}"

    # Choose DB server form
    DBSERVER_HOST="${DB_HOST:-localhost}"
    if [ -n "${DB_PORT:-}" ]; then DBSERVER_HOST="${DBSERVER_HOST}:${DB_PORT}"; fi
    if [ -n "${CLOUDSQL_INSTANCE:-}" ]; then DBSERVER_HOST="localhost:/cloudsql/${CLOUDSQL_INSTANCE}"; fi

    if [ -f "$CONF_PATH" ]; then mv "$CONF_PATH" "$CONF_PATH.keep"; fi

    ORIG_SQL="$MW_ROOT/maintenance/tables-generated.sql"
    BAK_SQL="/tmp/tables-generated.sql.orig"
    MOD_SQL="/tmp/tables-generated.sql.innodb"
    cp "$ORIG_SQL" "$BAK_SQL"
    sed 's/ENGINE = MyISAM/ENGINE=InnoDB/g' "$ORIG_SQL" > "$MOD_SQL"
    cp "$MOD_SQL" "$ORIG_SQL"

    php "$MW_ROOT/maintenance/install.php" \
      --dbtype mysql \
      --dbserver "$DBSERVER_HOST" \
      --dbname "$DB_NAME" \
      --dbuser "$DB_USER" \
      --dbpass "$DB_PASSWORD" \
      --server "$SERVER_URL" \
      --scriptpath "$SCRIPT_PATH" \
      --lang en \
      --pass "$ADMIN_PASS" \
      "$SITE_NAME" "$ADMIN_USER"

    mv "$BAK_SQL" "$ORIG_SQL" || true
    rm -f "$MOD_SQL" || true
    if [ -f "$CONF_PATH.keep" ]; then mv "$CONF_PATH.keep" "$CONF_PATH"; fi
    ;;

  update)
    # Optional bootstrap for empty DBs on providers that block MyISAM (e.g., Cloud SQL)
    if [ -n "${MW_BOOTSTRAP_SCHEMA:-}" ]; then
      echo "Bootstrapping core schema with InnoDB (MW_BOOTSTRAP_SCHEMA=1)"
      TMP_SQL="/tmp/tables-generated.innodb.sql"
      sed 's/ENGINE = MyISAM/ENGINE=InnoDB/g' "$MW_ROOT/maintenance/tables-generated.sql" > "$TMP_SQL"
      run_php "$MW_ROOT/maintenance/sql.php" -- -f "$TMP_SQL"
    fi
    run_php --conf "$CONF_PATH" update --quick
    ;;

  smw-setup)
    run_php "$MW_ROOT/extensions/SemanticMediaWiki/maintenance/setupStore.php" -- -f -v
    ;;

  smw-rebuild)
    run_php "$MW_ROOT/extensions/SemanticMediaWiki/maintenance/rebuildData.php" -v
    ;;

  # Generic runner for any maintenance script via run.php
  run)
    if [ $# -lt 1 ]; then
      echo "Usage: wiki-admin run <path-to-maintenance-script> [args...]" >&2
      exit 2
    fi
    run_php --conf "$CONF_PATH" "$@"
    ;;

  # Convenience alias for refreshLinks:
  refresh-links)
    # Pass any extra flags, e.g. --batch-size=500
    run_php --conf "$CONF_PATH" "$MW_ROOT/maintenance/refreshLinks.php" "$@"
    ;;

  # GiantBomb cache management (uses version-based invalidation)
  cache-purge)
    # Purge GiantBomb skin cache by incrementing version numbers
    # Old cached entries become orphaned; new requests fetch fresh data
    # Examples:
    #   cache-purge --all                    Purge all known cache prefixes
    #   cache-purge --prefix=games           Purge games cache
    #   cache-purge --prefix=platforms       Purge platforms cache
    #   cache-purge --list                   List known cache prefixes
    #   cache-purge --clear                  Clear entire APCu cache
    run_php --conf "$CONF_PATH" "$MW_ROOT/skins/GiantBomb/includes/maintenance/PurgeGiantBombSMWCache.php" "$@"
    ;;

  *)
    echo "Usage: wiki-admin {install|update|smw-setup|smw-rebuild|run|refresh-links|cache-purge}" >&2
    exit 2
    ;;
esac


