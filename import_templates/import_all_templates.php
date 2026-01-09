<?php
/**
 * Import Game templates into MediaWiki
 */

require_once __DIR__ . '/../maintenance/Maintenance.php';

class ImportGameTemplates extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->addDescription( 'Import Game templates from wikitext files' );
    }

    public function execute() {
        global $IP;
        $templateDir = "$IP/skins/GiantBomb/templates/wiki";
        
        $templates = [
            'Template:Game' => "$templateDir/Template_Game.wikitext",
            'Template:GameEnd' => "$templateDir/Template_GameEnd.wikitext",
            'Template:GameSidebar' => "$templateDir/Template_GameSidebar.wikitext",
            'Template:StripPrefix' => "$templateDir/Template_StripPrefix.wikitext",
            'Template:SidebarListItem' => "$templateDir/Template_SidebarListItem.wikitext",
            'Template:SidebarRelatedItem' => "$templateDir/Template_SidebarRelatedItem.wikitext",
        ];

        $services = \MediaWiki\MediaWikiServices::getInstance();
        $wikiPageFactory = $services->getWikiPageFactory();

        foreach ( $templates as $titleStr => $filePath ) {
            if ( !file_exists( $filePath ) ) {
                $this->output( "File not found: $filePath\n" );
                continue;
            }

            $content = file_get_contents( $filePath );
            if ( $content === false ) {
                $this->output( "Could not read: $filePath\n" );
                continue;
            }

            $title = \Title::newFromText( $titleStr );
            if ( !$title ) {
                $this->output( "Invalid title: $titleStr\n" );
                continue;
            }

            $wikiPage = $wikiPageFactory->newFromTitle( $title );
            $contentObj = \ContentHandler::makeContent( $content, $title );
            
            $updater = $wikiPage->newPageUpdater( \User::newSystemUser( 'Maintenance script', ['steal' => true] ) );
            $updater->setContent( \MediaWiki\Revision\SlotRecord::MAIN, $contentObj );
            $updater->saveRevision(
                \MediaWiki\CommentStore\CommentStoreComment::newUnsavedComment( 'Import game template' ),
                EDIT_FORCE_BOT | EDIT_SUPPRESS_RC
            );

            $this->output( "Imported: $titleStr\n" );
        }

        $this->output( "Done!\n" );
    }
}

$maintClass = ImportGameTemplates::class;
require_once RUN_MAINTENANCE_IF_MAIN;
