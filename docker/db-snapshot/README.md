# Database Snapshot

This directory contains the database snapshot for quick setup.

## Files

- `Dockerfile` - Docker image definition (tracked in git)
- `gb_api_dump.sql.gz` - Giant Bomb API data (~89MB, not in git)
- `gb_wiki.sql.gz` - MediaWiki data (~4MB, not in git)

## Sharing the Snapshot

The SQL dump files are **not tracked in git** due to their size. Share them via:

### Option 1: Git LFS (Recommended for GitHub)
```bash
# Install Git LFS
git lfs install

# Track SQL dumps
git lfs track "docker/db-snapshot/*.sql.gz"

# Commit
git add .gitattributes docker/db-snapshot/*.sql.gz
git commit -m "Add database snapshots via LFS"
git push
```

### Option 2: External Storage
Upload to:
- Google Drive / Dropbox
- GitHub Releases (attach as binary)
- Cloud storage (S3, GCS, etc.)

Provide download instructions in main README.

### Option 3: Docker Registry
Build and push the complete image:
```bash
# Build image
docker build -t yourusername/giant-bomb-wiki-db:latest .

# Push to Docker Hub
docker push yourusername/giant-bomb-wiki-db:latest
```

Then others can pull directly:
```yaml
# docker-compose.snapshot.yml
services:
  db:
    image: yourusername/giant-bomb-wiki-db:latest
    # Remove build section
```

## Creating a New Snapshot

From the project root:
```bash
# Export databases
docker exec giant-bomb-wiki-db-1 mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" \
  gb_api_dump | gzip > docker/db-snapshot/gb_api_dump.sql.gz

docker exec giant-bomb-wiki-db-1 mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" \
  gb_wiki | gzip > docker/db-snapshot/gb_wiki.sql.gz

# Rebuild image
docker compose -f docker-compose.snapshot.yml build db
```

## File Sizes

- `gb_api_dump.sql` (uncompressed): ~388MB
- `gb_api_dump.sql.gz` (compressed): ~89MB
- `gb_wiki.sql` (uncompressed): ~11MB
- `gb_wiki.sql.gz` (compressed): ~4MB
- **Total**: ~93MB compressed

## Image Size

The built Docker image is approximately **500MB** including:
- Base MariaDB image (~400MB)
- Compressed SQL dumps (~93MB)
- Additional layers (~7MB)
