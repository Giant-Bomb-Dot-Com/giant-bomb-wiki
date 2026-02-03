<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\CommentStore\CommentStoreComment;
use Wikimedia\Rdbms\DBQueryError;

require_once __DIR__ . '/Maintenance.php';

class UpdateTemplateImages extends Maintenance {
	private const LEGACY_UPLOAD_HOST = 'https://www.giantbomb.com';
	private const PREFERRED_SIZES = [ 'scale_super', 'screen_kubrick', 'scale_large', 'scale_medium' ];

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Updates a specified template with Giant Bomb image URLs for a specific category.' );
		// Add CLI options: --category and --template
		$this->addOption( 'category', 'The category to process', true, true );
		$this->addOption( 'template', 'The template to update (e.g. Concept)', true, true );
	}

	public function execute() {
		$category = $this->getOption( 'category' );
		$template = $this->getOption( 'template' );

		$services = MediaWikiServices::getInstance();
		$wikiPageFactory = $services->getWikiPageFactory();
		$revisionLookup = $services->getRevisionLookup();
		$user = $services->getUserFactory()->newFromName( 'Maintenance script' );

		$dbr = $this->getDB( DB_REPLICA );
		$res = $dbr->select(
			[ 'page', 'categorylinks' ],
			[ 'page_id', 'page_namespace', 'page_title' ],
			[ 'cl_to' => $category, 'page_namespace' => NS_MAIN ],
			__METHOD__,
			[],
			[ 'categorylinks' => [ 'JOIN', 'page_id=cl_from' ] ]
		);

		$this->output( "Found " . $res->numRows() . " pages in Category:$category.\n" );

		foreach ( $res as $row ) {
			$title = Title::newFromRow( $row );
			$wikiPage = $wikiPageFactory->newFromTitle( $title );
			$rev = $revisionLookup->getRevisionByTitle( $title );
			
			if ( !$rev ) continue;
			$content = $rev->getContent( SlotRecord::MAIN );
			if ( !$content instanceof TextContent ) continue;

			$text = $content->getText();
			$entries = $this->parseLegacyImageDataFromText( $text );

			$imageEntry = $entries['infobox'] ?? $entries['background'] ?? null;
			$imageUrl = $imageEntry ? $this->buildLegacyImageUrl( $imageEntry ) : null;

			if ( $imageUrl ) {
				$newText = $this->updateTemplate( $text, $imageUrl, $template );
				$this->saveWithRetry( $wikiPage, $user, $newText );
			}
		}
		$this->output( "Done!\n" );
	}

	private function buildLegacyImageUrl( array $entry ): ?string {
		$file = trim( $entry['file'] ?? '' );
		$path = trim( $entry['path'] ?? '', '/' );
		$sizes = array_map( 'trim', explode( ',', ( $entry['sizes'] ?? '' ) ) );

		if ( !$file || !$path || !$sizes ) return null;

		$useSize = $sizes[0]; 
		foreach ( self::PREFERRED_SIZES as $candidate ) {
			if ( in_array( $candidate, $sizes ) ) {
				$useSize = $candidate;
				break;
			}
		}

		return self::LEGACY_UPLOAD_HOST . "/a/uploads/$useSize/$path/$file";
	}

	private function saveWithRetry( $wikiPage, $user, $newText, $attempts = 3 ) {
		$title = $wikiPage->getTitle();
		
		for ( $i = 0; $i < $attempts; $i++ ) {
			try {
				$updater = $wikiPage->newPageUpdater( $user );
				
				// 1. Create content specifically as Wikitext
				$newContent = ContentHandler::makeContent( $newText, $title, CONTENT_MODEL_WIKITEXT );
				
				// 2. Explicitly set the slot to use this content
				$updater->setContent( SlotRecord::MAIN, $newContent );
				
				// 3. Force the content model for the main slot to be wikitext
				// This is the "secret sauce" to fix pages stuck in 'text' mode
				if ( $wikiPage->getContentModel() !== CONTENT_MODEL_WIKITEXT ) {
					$this->output( "Forcing content model change to wikitext for: " . $title->getPrefixedText() . "\n" );
				}
	
				$comment = CommentStoreComment::newUnsavedComment( 
					"Batch update: Fixed Image URL and forced wikitext content model" 
				);
	
				// 4. Save the revision
				$updater->saveRevision( $comment, EDIT_UPDATE | EDIT_SUPPRESS_RC );
				
				// 5. Clear caches immediately
				$title->invalidateCache();
				MediaWikiServices::getInstance()->getParserCache()->deleteOptionsKey( $wikiPage );
	
				$this->output( "Processed: " . $title->getPrefixedText() . "\n" );
				return; 
			} catch ( DBQueryError $e ) {
				if ( $i === $attempts - 1 ) throw $e;
				$this->output( "Database busy, retrying...\n" );
				usleep( 500000 );
			}
		}
	}

	private function updateTemplate( $text, $url, $templateName ) {
		// Escape template name for regex
		$t = preg_quote( $templateName, '/' );
		if ( preg_match( "/({{$t}\b[^}]*)/is", $text, $matches ) ) {
			$templateBody = $matches[1];
			
			if ( preg_match( '/\|\s*Image\s*=[^|}]*/i', $templateBody ) ) {
				$newBody = preg_replace( '/(\|\s*Image\s*=)[^|}]*/i', "$1$url", $templateBody );
			} else {
				// Clean trim and ensure newline before final closing }}
				$newBody = rtrim( $templateBody ) . "\n| Image=" . $url . "\n";
			}
			return str_replace( $templateBody, $newBody, $text );
		}
		return $text;
	}

	public static function parseLegacyImageDataFromText( string $text ): ?array {
		if ( !preg_match( '/<div[^>]*id=(["\'])imageData\\1[^>]*data-json=(["\'])(.*?)\\2/si', $text, $matches ) ) {
			return null;
		}
		$raw = html_entity_decode( $matches[3], ENT_QUOTES | ENT_HTML5 );
		$data = json_decode( trim( $raw ), true );
		return ( is_array( $data ) && json_last_error() === JSON_ERROR_NONE ) ? $data : null;
	}
}

$maintClass = "UpdateTemplateImages";
require_once RUN_MAINTENANCE_IF_MAIN;