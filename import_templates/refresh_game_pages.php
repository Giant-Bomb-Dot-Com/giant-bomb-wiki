<?php
/**
 * Force re-parse of game pages by doing a null edit
 */

require_once '/var/www/html/maintenance/Maintenance.php';

class RefreshGamePages extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->addDescription( 'Force re-parse game pages' );
    }

    public function execute() {
        $pages = [
            'Games/Doom',
            'Games/Desert_Strike:_Return_to_the_Gulf',
            'Games/Super_Mario_Bros.',
            'Games/Icewind_Dale',
            'Games/Baldur\'s_Gate',
            'Games/Baldur\'s_Gate_3',
        ];

        $services = \MediaWiki\MediaWikiServices::getInstance();
        $wikiPageFactory = $services->getWikiPageFactory();
        $user = \User::newSystemUser( 'Maintenance script', ['steal' => true] );

        foreach ( $pages as $titleStr ) {
            $title = \Title::newFromText( $titleStr );
            if ( !$title || !$title->exists() ) {
                $this->output( "Page not found: $titleStr\n" );
                continue;
            }

            $wikiPage = $wikiPageFactory->newFromTitle( $title );
            
            // Get current content
            $content = $wikiPage->getContent();
            if ( !$content ) {
                $this->output( "No content: $titleStr\n" );
                continue;
            }

            // Do a null edit (save same content)
            $updater = $wikiPage->newPageUpdater( $user );
            $updater->setContent( \MediaWiki\Revision\SlotRecord::MAIN, $content );
            $updater->saveRevision(
                \MediaWiki\CommentStore\CommentStoreComment::newUnsavedComment( 'Refresh template rendering' ),
                EDIT_UPDATE | EDIT_FORCE_BOT | EDIT_SUPPRESS_RC
            );

            $this->output( "Refreshed: $titleStr\n" );
        }

        $this->output( "Done!\n" );
    }
}

$maintClass = RefreshGamePages::class;
require_once RUN_MAINTENANCE_IF_MAIN;
