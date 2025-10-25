#!/bin/bash
# Import gb_wiki database data
# (Database creation and permissions handled by 01-setup-databases.sql)

set -e

echo "Importing gb_wiki data..."

# Import data into pre-created database
gunzip -c /docker-entrypoint-initdb.d/gb_wiki.sql.gz.data | \
  mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" gb_wiki

# Verify import
PAGE_COUNT=$(mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" gb_wiki -sN -e "SELECT COUNT(*) FROM page;" 2>/dev/null || echo "0")
echo "  ✓ Imported ${PAGE_COUNT} wiki pages"

echo ""
echo "=========================================="
echo "Database Import Complete"
echo "=========================================="
echo "  gb_api_dump: Ready"
echo "  gb_wiki: Ready"
echo "  Permissions: Configured"
echo "=========================================="
