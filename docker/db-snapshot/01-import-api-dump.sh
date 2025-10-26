#!/bin/bash
# Import gb_api_dump database data (OPTIONAL)
# (Database creation and permissions handled by 00-setup-databases.sql)
#
# This database contains 86,147 games from Giant Bomb API and is only needed
# for generating new wiki pages. Skip this if you only want to work on wiki UI/skin.

set -e

# Check if the snapshot file exists
if [ ! -f /docker-entrypoint-initdb.d/gb_api_dump.sql.gz.data ]; then
  echo "⚠️  Skipping gb_api_dump import (file not found)"
  echo "   This is optional - only needed for generating new wiki pages"
  echo ""
  exit 0
fi

echo "Importing gb_api_dump data (optional)..."
echo "  → This may take 1-2 minutes..."

# Import data into pre-created database
gunzip -c /docker-entrypoint-initdb.d/gb_api_dump.sql.gz.data | \
  mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" gb_api_dump

# Verify import
GAME_COUNT=$(mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" gb_api_dump -sN -e "SELECT COUNT(*) FROM wiki_game;")
echo "  ✓ Imported ${GAME_COUNT} games"
