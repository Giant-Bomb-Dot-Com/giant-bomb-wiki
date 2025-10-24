#!/bin/bash
# Import gb_api_dump database data
# (Database creation and permissions handled by 01-setup-databases.sql)

set -e

echo "Importing gb_api_dump data..."
echo "  → This may take 1-2 minutes..."

# Import data into pre-created database
gunzip -c /docker-entrypoint-initdb.d/gb_api_dump.sql.gz.data | \
  mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" gb_api_dump

# Verify import
GAME_COUNT=$(mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" gb_api_dump -sN -e "SELECT COUNT(*) FROM wiki_game;")
echo "  ✓ Imported ${GAME_COUNT} games"
