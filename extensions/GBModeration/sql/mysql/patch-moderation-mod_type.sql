-- Add mod_type, mod_page2_namespace, mod_page2_title fields (since Moderation 1.2.17).
-- See [patch-moderation.sql] for details.

ALTER TABLE /*_*/moderation
	ADD COLUMN mod_type varchar(16) binary not null default 'edit',
	ADD COLUMN mod_page2_namespace int NOT NULL default 0,
	ADD COLUMN mod_page2_title varchar(255) binary NOT NULL default '';

-- Add mod_type to UNIQUE INDEX.

DROP INDEX /*i*/moderation_load ON /*_*/moderation;
CREATE UNIQUE INDEX /*i*/moderation_load ON /*_*/moderation (mod_preloadable, mod_type, mod_namespace, mod_title, mod_preload_id);
