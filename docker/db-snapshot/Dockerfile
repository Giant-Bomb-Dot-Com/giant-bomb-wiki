# Custom MariaDB image with Giant Bomb Wiki data pre-loaded
FROM mariadb:11.2

# Copy database setup SQL (creates databases and grants permissions)
COPY 00-setup-databases.sql /docker-entrypoint-initdb.d/

# Copy compressed database dumps (with .data extension to prevent auto-processing)
# gb_wiki.sql.gz.data - Required: Contains 5 wiki pages for development
COPY gb_wiki.sql.gz.data /docker-entrypoint-initdb.d/

# Copy import scripts that will load the SQL dumps into the correct databases
COPY 01-import-api-dump.sh /docker-entrypoint-initdb.d/
COPY 02-import-wiki.sh /docker-entrypoint-initdb.d/

# Make scripts executable
RUN chmod +x /docker-entrypoint-initdb.d/*.sh

# The mariadb image automatically runs scripts from /docker-entrypoint-initdb.d/
# on first startup. Files are processed in alphabetical order:
# 1. 00-setup-databases.sql - Creates databases and grants permissions
# 2. 01-import-api-dump.sh - Imports gb_api_dump data (skipped if file not present)
# 3. 02-import-wiki.sh - Imports gb_wiki data (required)
#
# .data files are ignored by the entrypoint and only processed by our scripts.
#
# NOTE: gb_api_dump.sql.gz.data is NOT included by default (.dockerignore)
# This makes the image ~89MB smaller and startup much faster.
# Only include it if you need to generate new wiki pages from the API data.

LABEL maintainer="Giant Bomb Wiki"
LABEL description="MariaDB with Giant Bomb API data and MediaWiki data pre-loaded"
LABEL version="1.0"
