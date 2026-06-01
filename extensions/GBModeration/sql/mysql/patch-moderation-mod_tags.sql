-- Add mod_tags field (since Moderation 1.1.29).
-- See [patch-moderation.sql] for details.

ALTER TABLE /*_*/moderation
	ADD COLUMN mod_tags blob NULL default '';
