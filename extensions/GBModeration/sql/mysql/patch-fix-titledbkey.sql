-- Replace spaces with underscores in mod_title (since Moderation 1.1.31).
-- This is for standardization: other MediaWiki tables use underscores in *_title.

UPDATE /*_*/moderation
	SET mod_title=REPLACE(mod_title,' ','_');
