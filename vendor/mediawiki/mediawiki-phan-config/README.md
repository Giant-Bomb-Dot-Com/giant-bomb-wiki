MediaWiki phan configuration
============================

There are two base phan configurations for MediaWiki projects:
* `config.php` for MediaWiki code, like extensions and skins
* `config-library.php` for PHP libraries and other code external to MediaWiki

Choose the file more suitable for your project, then include it in the phan
configuration and extend/modify it as you see fit.

See <https://www.mediawiki.org/wiki/Continuous_integration/Phan> for
more details.
