# MediaWiki-Phan-Config release history #

## 0.14.0 / 2024-02-03
* Add plugin to disallow use of `new Exception` (Daimona Eaytoy)
* Do not emit MediaWikiNoEmptyIfDefined for properties of classes with the AllowDynamicProperties attribute (Daimona Eaytoy)
* Emit MediaWikiNoEmptyIfDefined for all node types except array element access (Daimona Eaytoy)
* Upgrade phan to 5.4.3 and mediawiki/phan-taint-check-plugin to 6.0.0 (James D. Forrester)

## 0.13.0 / 2023-09-08
* Add plugin to forbid `empty()` on defined variables and properties (Daimona Eaytoy)
* Bump phan to 5.4.2 and taint-check to 5.0.0 (Michael Große)
* "Auto discovery" of namespaces of wgConf and wgRequest (Amir Sarabadani)

## 0.12.1 / 2023-04-17
* Enable PhanCompatibleSerializeInterfaceDeprecated (Umherirrender)
* Create a separate config file for libraries (Daimona Eaytoy)

## 0.12.0 / 2022-10-06
* Avoid PhanRedefinedInheritedInterface by excluding symfony/polyfill-php80 (Umherirrender)
* Bump minimum PHP version to 7.4 (C. Scott Ananian)
* Disable PhanPluginDuplicateExpressionAssignmentOperation (Reedy)
* Bump phan to 5.4.1 and taint-check to 4.0.0 (Daimona Eaytoy)

## 0.11.1 / 2021-11-08
* Set minimum and target PHP version (Daimona Eaytoy)

## 0.11.0 / 2021-09-03
* Enable plugin UseReturnValuePlugin (Umherirrender)
* Exclude stubs from .phan configurations in libraries (C. Scott Ananian)
* Suppress PhanDeprecatedCaseInsensitiveDefine (Umherirrender)
* Suppress phan issues about use statements (Umherirrender)
* Add plugins: LoopVariableReusePlugin, RedundantAssignmentPlugin, UnreachableCodePlugin, SimplifyExpressionPlugin, DuplicateArrayKeyPlugin (Daimona Eaytoy)
* Remove hack that allows disabling taint-check (Daimona Eaytoy)
* Bump phan to 5.2.0, taint-check to 3.3.2 (Daimona Eaytoy)

## 0.10.6 / 2020-12-14
* Add a ConfigBuilder class to configure phan (Daimona Eaytoy)
* Remove option for filtering the list of directories (Daimona Eaytoy)
* Bump taint-check to 3.2.1 and phan to 3.2.6 (Daimona Eaytoy)

## 0.10.5 / 2020-12-05

Intermediate maintenance release without ConfigBuilder.

* Bump taint-check to 3.1.1 (Daimona Eaytoy)
* Expand globals_type_map (Daimona Eaytoy)

* build: Updating mediawiki/mediawiki-codesniffer to 34.0.0 (James D. Forrester)
* build: Updating ockcyp/covers-validator to 1.3.1 (libraryupgrader)
* build: Updating ockcyp/covers-validator to 1.3.0 (libraryupgrader)
* build: Updating mediawiki/mediawiki-codesniffer to 33.0.0 (libraryupgrader)

## 0.10.4 / 2020-11-17

Intermediate maintenance release without ConfigBuilder.

* Bump taint-check to 3.0.4 (Daimona Eaytoy)

## 0.10.3 / 2020-09-22

Intermediate maintenance release without ConfigBuilder.

* Fix exclude of stubs from this repo (Umherirrender)
* Require taint-check 3.0.3, up from 3.0.2 (James D. Forrester)

* build: update mediawiki/mediawiki-codesniffer to 31.0.0 (libraryupgrader)
* build: update php-parallel-lint/php-console-highlighter to 0.5.0 (libraryupgrader)
* build: update php-parallel-lint/php-parallel-lint to 1.2.0 (libraryupgrader)
* build: update ockcyp/covers-validator to 1.2.0 (libraryupgrader)
* build: update .gitreview to point to mediawiki/tools/phan (Antoine Musso)

## 0.10.2 / 2020-04-15 ##
* Adjust taint-check settings, require new version (Daimona Eaytoy)
* build: Upgrade mediawiki-codesniffer from v29.0.0 to v30.0.0 (James D. Forrester)

## 0.10.1 / 2020-03-26 ##
* Fix path for taint-check (Daimona Eaytoy)

## 0.10.0 / 2020-03-26 ##
* Require taint-check (Daimona Eaytoy)
* Upgrade phan to 2.6.1 (Daimona Eaytoy)
* Update PHPUnit to 8.5 (Umherirrender)
* Upgrade phan to 2.5.0 (Daimona Eaytoy)

## 0.9.2 / 2020-02-13 ##
* Upgrade phan to 2.4.9 (Daimona Eaytoy)
* build: Updating composer dependencies (libraryupgrader)

## 0.9.1 / 2020-01-24 ##
* Upgrade phan to 2.4.7 (Daimona Eaytoy)
* Add more dev dependencies to the list of excluded files (Daimona Eaytoy)
* Set `exclude_file_regex` to exclude tests and dep devs from vendor folder (Umherirrender)
* Drop Travis testing, no extra advantage over Wikimedia CI and runs post-merge anyway (James D. Forrester)
* build: Updating mediawiki/mediawiki-codesniffer to 29.0.0 (libraryupgrader)
* Update phan/phan to 2.4.6 (Umherirrender)
* Ignore composer.json on export (Umherirrender)

## 0.9.0 / 2019-12-07 ##
* Update phan/phan to 2.4.4 (Umherirrender)
* Disable implicit scalar and null casts (Daimona Eaytoy)
* Restore a line removed incidentally (Daimona Eaytoy)
* Add `MW_VENDOR_PATH` to set up path of mediawiki/vendor clone (Umherirrender)
* Disable `PhanAccess*Internal` (Aryeh Gregor)
* build: Upgrade mediawiki-codesniffer to v28.0.0 (James D. Forrester)
* Add `MSG_EOR` under windows as stub (Umherirrender)

## 0.8.0 / 2019-10-09 ##
* Move phan/phan to composer require and upgrade it (Daimona Eaytoy)
* Really require PHP 7.2+ (Daimona Eaytoy)

## 0.7.1 / 2019-09-01 ##
* Restore PHP5.6 requirement (Daimona Eaytoy)

## 0.7.0 / 2019-09-01 ##
* Upgrade phan to 2.2.11 (Daimona Eaytoy)
* Upgrade phan, remove old config settings (Daimona Eaytoy)
* build: Updating mediawiki/mediawiki-codesniffer to 26.0.0 (libraryupgrader)
* Suppress warnings about unknown dirs from 'directory_list' (Umherirrender)
* Removed old tests/phan/stubs for core from directory list (Umherirrender)

## 0.6.1 / 2019-06-01 ##
* Enable enable_class_alias_support (Max Semenik)

## 0.6.0 / 2019-05-13 ##
* Rename tests/phan/stubs in dir list to new location (Umherirrender)
* Upgrade phan to 1.2.7 (Kunal Mehta)
* Upgrade phan to 1.3.2 (Daimona Eaytoy)
* Upgrade phan to 1.3.4 (Umherirrender)

## 0.5.0 / 2019-03-10 ##
* Add RegexChecker, UnusedSuppression and DuplicateExpression plugins
  (Daimona Eaytoy)
* Upgrade Phan to 1.2.6 (Kunal Mehta & Daimona Eaytoy)

## 0.4.0 / 2019-02-23 ##
* Add phan version to composer.json (Kunal Mehta)
* build: Updating mediawiki/minus-x to 0.3.1 (Umherirrender)
* Don't start paths with "./" (Kunal Mehta)
* Drop PHP support pre 7.0 (Reedy)

## 0.3.0 / 2018-06-08 ##
* Include MediaWiki core's `tests/phan/stubs` by default (Kunal Mehta)
* Support MW_INSTALL_PATH (Umherirrender)
* Suppress PhanDeprecated* by default (Kunal Mehta)
* Suppress PhanUnreferencedUseNormal (Umherirrender)

## 0.2.0 / 2018-04-05 ##
* phan should also check an extension's maintenance scripts by default (Kunal Mehta)
* Suppress PhanDeprecatedFunction by default (Kunal Mehta)

## 0.1.0 / 2018-02-03 ##
* Initial release (Kunal Mehta)
