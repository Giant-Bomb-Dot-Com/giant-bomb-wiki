<?php

namespace MediaWiki\Extension\GBRelated;

use MediaWiki\Deferred\DeferredUpdates;

class Hooks
{
    // recompute on save so related lists track edits; postsend keeps the
    // editor's request fast
    public static function onPageSaveComplete(
        $wikiPage,
        $user,
        $summary,
        $flags,
        $revisionRecord,
        $editResult
    ) {
        $title = $wikiPage->getTitle();
        if (!$title) {
            return;
        }
        if (RelatedStore::handles($title)) {
            DeferredUpdates::addCallableUpdate(static function () use (
                $title,
            ) {
                RelatedStore::rebuild($title);
                $title->invalidateCache(time() + 1);
            }, DeferredUpdates::POSTSEND);
        }
        if (ScoreStore::handles($title)) {
            DeferredUpdates::addCallableUpdate(static function () use (
                $title,
            ) {
                ScoreStore::updateOnSave($title);
            }, DeferredUpdates::POSTSEND);
        }
    }

    public static function onLoadExtensionSchemaUpdates($updater)
    {
        $updater->addExtensionTable(
            "gb_related",
            __DIR__ . "/../sql/tables.sql",
        );
        $updater->addExtensionTable(
            "gb_page_score",
            __DIR__ . "/../sql/page_score.sql",
        );
    }
}
