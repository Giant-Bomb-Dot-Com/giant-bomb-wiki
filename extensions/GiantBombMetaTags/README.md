 MetaDescriptionTag MediaWiki extension
=======================================

This is very simple MediaWiki extension for adding a
`<meta>` description tag to the page's `<head>`.


The original author is Joshua C. Lerner, based on a [tutorial] by Jim R. Wilson.
Since version 0.3.0 it is maintained by Dror S. [FFS].


[tutorial]: http://jimbojw.com/wiki/index.php?title=Doing_more_with_MediaWiki_parser_extensions


## Requirements
Version 0.4.0 was only tested with MediaWiki 1.27; in theory, it should work with MediaWiki 1.25+.
Version 0.3 should work with MediaWiki 1.16+.


## Configuration
This extension has no configuration options.


## Usage
You can use MetaDescriptionTag by adding the <metadesc> tag to articles: 
`<metadesc>Home page for the MetaDescriptionTag MediaWiki extension</metadesc>`

For use in templates, you can call it using `{{#tag:metadesc}}`, for example: 
`{{#tag:metadesc | A description - {{{1}}} }}`.


## Changelog

### 0.4.0
This is re-write to make it compatible with more modern MediaWiki practices and make sure it
works nicely with MediaWiki 1.27+:
- Extension Registration (extension.json)
- json i18n files
- An actual README file! :-)
- Switching to semantic versioning.

### 0.3
Fix i18n to work with v1.16+, sanitize output using htmlspecialchars().

### 0.2
Change syntax to <metadesc>some content</metadesc> to support template variable substitution.

### 0.1
Initial release.
 
