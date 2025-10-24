# Custom MariaDB image with Giant Bomb Wiki data pre-loaded
FROM mariadb:11.2

# Copy database setup SQL (creates databases and grants permissions)
COPY 00-setup-databases.sql /docker-entrypoint-initdb.d/

# Copy compressed database dumps (with .data extension to prevent auto-processing)
COPY gb_api_dump.sql.gz.data /docker-entrypoint-initdb.d/
COPY gb_wiki.sql.gz.data /docker-entrypoint-initdb.d/

# Copy import scripts that will load the SQL dumps into the correct databases
COPY 01-import-api-dump.sh /docker-entrypoint-initdb.d/
COPY 02-import-wiki.sh /docker-entrypoint-initdb.d/

# Make scripts executable
RUN chmod +x /docker-entrypoint-initdb.d/*.sh

# The mariadb image automatically runs scripts from /docker-entrypoint-initdb.d/
# on first startup. Files are processed in alphabetical order:
# 1. 00-setup-databases.sql - Creates databases and grants permissions
# 2. 01-import-api-dump.sh - Imports gb_api_dump data
# 3. 02-import-wiki.sh - Imports gb_wiki data
#
# .data files are ignored by the entrypoint and only processed by our scripts.

LABEL maintainer="Giant Bomb Wiki"
LABEL description="MariaDB with Giant Bomb API data and MediaWiki data pre-loaded"
LABEL version="1.0"
