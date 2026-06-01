-- Make moderation_load into UNIQUE INDEX (since Moderation 1.2.9).
-- See [patch-moderation.sql] for details.

-- Step 1. Expand mod_preloadable field to hold more than 0/1 tinyint.

ALTER TABLE /*_*/moderation
	MODIFY mod_preloadable int unsigned NOT NULL default 0;

-- Step 2. Mark all rows as non-preloadable,
-- thus ensuring no conflicts during CREATE UNIQUE INDEX.

UPDATE /*_*/moderation SET mod_preloadable=mod_id;

-- Step 3. Make index UNIQUE.

DROP INDEX /*i*/moderation_load ON /*_*/moderation;
CREATE UNIQUE INDEX /*i*/moderation_load ON /*_*/moderation (mod_preloadable, mod_namespace, mod_title, mod_preload_id);

-- Step 4. Mark changes in Pending and Spam folders as preloadable.

UPDATE IGNORE /*_*/moderation SET mod_preloadable=0 WHERE mod_merged_revid=0 AND ( mod_rejected=0 OR mod_rejected_auto=1 );
