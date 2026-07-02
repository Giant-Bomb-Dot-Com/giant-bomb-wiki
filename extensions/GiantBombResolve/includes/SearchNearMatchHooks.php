<?php

namespace MediaWiki\Extension\GiantBombResolve;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use SMW\DIProperty;
use SMW\Query\Language\Disjunction;
use SMW\Query\Language\SomeProperty;
use SMW\Query\Language\ValueDescription;
use SMW\StoreFactory;
use SMWDIBlob;
use SMWQuery;
use Title;

/**
 * Lets "go" searches land on an entry by its clean Name or an Alias. Entry
 * titles are prefixed ("Games/Star Fox") so direct title lookup misses them;
 * after it fails we exact-match the SMW "Has name" / "Has aliases" properties
 * instead. No search-index changes needed.
 */
class SearchNearMatchHooks
{
    /**
     * Hook: SearchAfterNoDirectMatch
     * Sets $title and returns false when we resolve a match.
     */
    public static function onSearchAfterNoDirectMatch($term, &$title)
    {
        $resolved = self::resolveByNameOrAlias((string) $term);
        if ($resolved !== null) {
            $title = $resolved;
            return false;
        }
        return true;
    }

    /**
     * Exact-match against "Has name" / "Has aliases". Aliases are one
     * comma-separated blob, so only single-alias values match exactly;
     * multi-alias entries still turn up in regular fulltext.
     */
    private static function resolveByNameOrAlias(string $term): ?Title
    {
        $term = trim($term);
        // names are short; 255 (db title bound) keeps junk away from SMW
        if (
            $term === "" ||
            mb_strlen($term) > 255 ||
            strpos($term, "\n") !== false
        ) {
            return null;
        }

        // cache hits AND misses — this fires on every failed "Go" search and
        // the SMW lookup isn't free; new entities take up to the TTL to appear
        $cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
        $prefixed = $cache->getWithSetCallback(
            $cache->makeKey("gb-search-nearmatch", sha1($term)),
            900, // 15 minutes
            static function () use ($term) {
                $title = self::querySmwForNameOrAlias($term);
                return $title ? $title->getPrefixedDBkey() : "";
            },
        );

        if (!is_string($prefixed) || $prefixed === "") {
            return null;
        }

        // re-check; the page may have been deleted within the TTL
        $title = Title::newFromText($prefixed);
        return $title instanceof Title && $title->exists() ? $title : null;
    }

    /**
     * The uncached SMW lookup behind resolveByNameOrAlias().
     */
    private static function querySmwForNameOrAlias(string $term): ?Title
    {
        try {
            $description = new Disjunction([
                new SomeProperty(
                    DIProperty::newFromUserLabel("Has name"),
                    new ValueDescription(new SMWDIBlob($term)),
                ),
                new SomeProperty(
                    DIProperty::newFromUserLabel("Has aliases"),
                    new ValueDescription(new SMWDIBlob($term)),
                ),
            ]);

            $query = new SMWQuery($description);
            $query->setLimit(1);

            $results = StoreFactory::getStore()
                ->getQueryResult($query)
                ->getResults();
        } catch (\Throwable $e) {
            LoggerFactory::getInstance("GiantBombResolve")->warning(
                'Search near-match SMW lookup failed for "{term}": {message}',
                ["term" => $term, "message" => $e->getMessage()],
            );
            return null;
        }

        if (!$results) {
            return null;
        }

        $title = $results[0]->getTitle();
        return $title instanceof Title && $title->exists() ? $title : null;
    }
}
