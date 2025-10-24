--
-- Giant Bomb Wiki Database Setup
-- This script creates both databases and grants all necessary permissions
--

-- Create gb_api_dump database
CREATE DATABASE IF NOT EXISTS gb_api_dump CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create gb_wiki database (MediaWiki uses binary charset)
CREATE DATABASE IF NOT EXISTS gb_wiki CHARACTER SET binary;

-- Grant permissions to wiki_admin user
GRANT ALL PRIVILEGES ON gb_api_dump.* TO 'wiki_admin'@'%';
GRANT ALL PRIVILEGES ON gb_wiki.* TO 'wiki_admin'@'%';

-- Grant permissions to MediaWiki database user (from env vars)
-- Note: MARIADB_USER is automatically created by the entrypoint
FLUSH PRIVILEGES;

-- Verify setup
SELECT 'Database setup complete' AS status;
SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME IN ('gb_api_dump', 'gb_wiki');
