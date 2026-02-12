<?php

namespace MediaWiki\Extension\AlgoliaSearch;

use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\UserIdentity;
use Title;
use WikiPage;

class AlgoliaHooks {

	/**
	 * Hook: PageSaveComplete
	 * Sync page to Algolia after save
	 */
	public static function onPageSaveComplete(
		WikiPage $wikiPage,
		UserIdentity $user,
		string $summary,
		int $flags,
		RevisionRecord $revisionRecord,
		EditResult $editResult
	): void {
		$config = MediaWikiServices::getInstance()->getMainConfig();

		if ( !(bool)$config->get( 'AlgoliaSearchEnabled' ) ) {
			return;
		}

		$title = $wikiPage->getTitle();
		if ( !$title || $title->getNamespace() !== NS_MAIN ) {
			return;
		}

		// Skip redirects
		if ( $wikiPage->isRedirect() ) {
			return;
		}

		$type = self::getTypeFromTitle( $title, $config );
		if ( $type === null ) {
			return;
		}

		try {
			$index = AlgoliaClientFactory::getIndexFromConfig( $config );
			if ( !$index ) {
				return;
			}

			$record = RecordMapper::mapRecord( $type, $title );
			if ( $record === null ) {
				return;
			}

			$index->saveObjects( [ $record ] );
		} catch ( \Throwable $e ) {
			wfLogWarning( 'AlgoliaSearch: Failed to sync page ' . $title->getPrefixedText() . ': ' . $e->getMessage() );
		}
	}

	/**
	 * Hook: PageDeleteComplete
	 * Remove page from Algolia after deletion
	 */
	public static function onPageDeleteComplete(
		PageIdentity $page,
		\MediaWiki\Permissions\Authority $deleter,
		string $reason,
		int $pageID,
		RevisionRecord $deletedRev,
		\ManualLogEntry $logEntry,
		int $archivedRevisionCount
	): void {
		$config = MediaWikiServices::getInstance()->getMainConfig();

		if ( !(bool)$config->get( 'AlgoliaSearchEnabled' ) ) {
			return;
		}

		if ( $page->getNamespace() !== NS_MAIN ) {
			return;
		}

		try {
			$index = AlgoliaClientFactory::getIndexFromConfig( $config );
			if ( !$index ) {
				return;
			}

			$objectId = 'wiki:' . $pageID;
			$index->deleteObjects( [ $objectId ] );
		} catch ( \Throwable $e ) {
			wfLogWarning( 'AlgoliaSearch: Failed to delete object wiki:' . $pageID . ': ' . $e->getMessage() );
		}
	}

	public static function getTypeFromTitle( Title $title, $config ): ?string {
		$prefixMap = (array)$config->get( 'AlgoliaTypePrefixMap' );
		$titleText = $title->getText();

		foreach ( $prefixMap as $type => $prefix ) {
			$prefixWithSlash = $prefix . '/';
			if ( strpos( $titleText, $prefixWithSlash ) === 0 ) {
				// Check it's a direct child (no further slashes)
				$remainder = substr( $titleText, strlen( $prefixWithSlash ) );
				if ( strpos( $remainder, '/' ) === false ) {
					return $type;
				}
			}
		}

		return null;
	}
}
