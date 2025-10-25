#!/bin/bash
# Giant Bomb Wiki - Simple Setup Script
# Uses database snapshot for instant startup

set -e

echo "=========================================="
echo "Giant Bomb Wiki - Setup"
echo "=========================================="
echo ""

# Load environment variables
if [ ! -f .env ]; then
    echo "❌ Error: .env file not found!"
    echo "Please create .env file (copy from .env.example)"
    exit 1
fi

export $(cat .env | grep -v '^#' | xargs)

# Check if snapshot exists
if [ ! -f docker/db-snapshot/gb_api_dump.sql.gz.data ] || [ ! -f docker/db-snapshot/gb_wiki.sql.gz.data ]; then
    echo "⚠️  Database snapshot not found!"
    echo ""
    echo "First-time setup detected. The snapshot needs to be:"
    echo "  1. Downloaded from project maintainer, OR"
    echo "  2. Created by running the full import process"
    echo ""
    echo "To create a new snapshot (takes 10-15 min):"
    echo "  ./setup_minimal.sh 100  # Import 100 games"
    echo ""
    echo "After that completes, run this command to create the snapshot:"
    echo "  docker exec giant-bomb-wiki-db-1 mariadb-dump -uroot -p\$MARIADB_ROOT_PASSWORD gb_api_dump | gzip > docker/db-snapshot/gb_api_dump.sql.gz"
    echo "  docker exec giant-bomb-wiki-db-1 mariadb-dump -uroot -p\$MARIADB_ROOT_PASSWORD gb_wiki | gzip > docker/db-snapshot/gb_wiki.sql.gz"
    echo ""
    exit 1
fi

echo "✓ Database snapshot found"
echo "  - gb_api_dump.sql.gz.data ($(du -h docker/db-snapshot/gb_api_dump.sql.gz.data | cut -f1))"
echo "  - gb_wiki.sql.gz.data ($(du -h docker/db-snapshot/gb_wiki.sql.gz.data | cut -f1))"
echo ""

# Stop existing containers
echo "Stopping any existing containers..."
docker compose -f docker-compose.snapshot.yml down 2>/dev/null || true
echo "✓ Stopped"
echo ""

# Build database image
echo "Building database image with snapshot..."
docker compose -f docker-compose.snapshot.yml build db
echo "✓ Database image built"
echo ""

# Start containers
echo "Starting containers..."
docker compose -f docker-compose.snapshot.yml up -d
echo "✓ Containers started"
echo ""

# Get dynamic container names
DB_CONTAINER=$(docker compose -f docker-compose.snapshot.yml ps -q db)
WIKI_CONTAINER=$(docker compose -f docker-compose.snapshot.yml ps -q wiki)

# Wait for database
echo "Waiting for database to load snapshot..."
echo "⏳ This takes 1-2 minutes on first run"
echo "⏳ Subsequent runs are ~10 seconds (data persists in volume)"
echo ""

sleep 5
until docker exec $DB_CONTAINER mariadb -uroot -p${MARIADB_ROOT_PASSWORD} -e "SELECT 1 FROM gb_wiki.page LIMIT 1" &> /dev/null; do
    printf "."
    sleep 3
done
echo ""
echo "✓ Database ready with data loaded"
echo ""

# Wait for wiki container to be able to connect to database
echo "Waiting for wiki container to connect to database..."
until docker exec $WIKI_CONTAINER php -r "new mysqli('db', 'root', '${MARIADB_ROOT_PASSWORD}', 'gb_wiki');" &> /dev/null; do
    printf "."
    sleep 2
done
echo ""
echo "✓ Wiki can connect to database"
echo ""

# Check MediaWiki installation
if docker exec $WIKI_CONTAINER test -f /var/www/html/LocalSettings.php; then
    echo "✓ MediaWiki already installed"
else
    echo "Installing MediaWiki..."
    docker exec $WIKI_CONTAINER /bin/bash /installwiki.sh
    echo "✓ MediaWiki installed"
fi
echo ""

# Run MediaWiki jobs
echo "Processing MediaWiki jobs..."
docker exec $WIKI_CONTAINER php /var/www/html/maintenance/run.php \
    runJobs --memory-limit=512M --maxjobs=100 2>&1 | tail -5
echo "✓ Jobs complete"
echo ""

# Get stats
GAME_COUNT=$(docker exec $WIKI_CONTAINER php /var/www/html/maintenance/run.php sql \
    --query="SELECT COUNT(*) as count FROM page WHERE page_title LIKE 'Games/%' AND page_title NOT LIKE 'Games/%/%'" 2>/dev/null | \
    grep -oP '\d+' | tail -1 || echo "checking...")

echo "=========================================="
echo "✅ Giant Bomb Wiki Ready!"
echo "=========================================="
echo ""
echo "🌐 Access at: http://localhost:8080"
echo ""
echo "📊 Status:"
echo "   - Games in database: 86,147"
echo "   - Games in wiki: ${GAME_COUNT}"
echo ""
echo "Useful commands:"
echo "   docker compose -f docker-compose.snapshot.yml logs -f     # View logs"
echo "   docker compose -f docker-compose.snapshot.yml down        # Stop"
echo "   docker compose -f docker-compose.snapshot.yml restart     # Restart"
echo ""
echo "To reset everything and start fresh:"
echo "   docker compose -f docker-compose.snapshot.yml down -v"
echo "   ./setup.sh"
echo ""
