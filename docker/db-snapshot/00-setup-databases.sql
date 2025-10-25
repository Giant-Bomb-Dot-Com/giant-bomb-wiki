--
-- Giant Bomb Wiki Database Setup
-- This script creates databases and grants all necessary permissions
--

-- Create gb_wiki database (MediaWiki uses binary charset)
-- This is the main database for wiki pages and content
CREATE DATABASE IF NOT EXISTS gb_wiki CHARACTER SET binary;

-- Grant permissions to wiki_admin user for gb_wiki
GRANT ALL PRIVILEGES ON gb_wiki.* TO 'wiki_admin'@'%';

-- Create gb_api_dump database (optional - only needed for generating new wiki pages)
-- This contains 86,147 games from Giant Bomb API
-- If you only want to work on the wiki UI/skin, you can skip importing this database
CREATE DATABASE IF NOT EXISTS gb_api_dump CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant permissions to wiki_admin user for gb_api_dump
GRANT ALL PRIVILEGES ON gb_api_dump.* TO 'wiki_admin'@'%';

-- Grant permissions to MediaWiki database user (from env vars)
-- Note: MARIADB_USER is automatically created by the entrypoint
FLUSH PRIVILEGES;

-- Verify setup
SELECT 'Database setup complete' AS status;
SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME IN ('gb_api_dump', 'gb_wiki');
