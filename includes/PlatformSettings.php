<?php

/**
 * Platform settings for MediaWiki Docker.
 *
 * Don't add settings here; instead use LocalSettings.php for your local site
 * overrides, or if you want to contribute shared settings for users of the
 * development environment, PlatformSettings.php is managed in the
 * https://gitlab.wikimedia.org/releng/dev-images repository.
 */

require_once "$IP/includes/DevelopmentSettings.php";

// Use same URL scheme as in production (T248178)
$wgArticlePath = "/wiki/$1";

// Rely on the separate jobrunner container to execute jobs
// https://gerrit.wikimedia.org/r/c/mediawiki/core/+/597844#message-98035dbaa804e7c5aee7157d46fda09c7af57626
// https://www.mediawiki.org/wiki/Manual:$wgJobRunRate
$wgJobRunRate = 0;

// Matches production value:
$wgMaxShellMemory = 1024 * 1024;
