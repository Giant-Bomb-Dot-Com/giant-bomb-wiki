<?php
/**
 * Import wiki templates from wikitext files
 * 
 * Imports Game, Character, Franchise, and shared templates into MediaWiki.
 * 
 * Usage:
 *   php maintenance/run.php import_templates/import_all_templates.php
 *   php maintenance/run.php import_templates/import_all_templates.php --type=game
 *   php maintenance/run.php import_templates/import_all_templates.php --type=character
 *   php maintenance/run.php import_templates/import_all_templates.php --type=franchise
 */

require_once __DIR__ . '/../maintenance/Maintenance.php';

class ImportWikiTemplates extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->addDescription( 'Import wiki templates from wikitext files' );
        $this->addOption( 'type', 'Template type to import: all, game, character, franchise, shared (default: all)', false, true );
    }

    public function execute() {
        global $IP;
        $templateDir = "$IP/skins/GiantBomb/templates/wiki";
        $type = $this->getOption( 'type', 'all' );
        
        // Shared templates used by multiple page types
        $sharedTemplates = [
            'Template:StripPrefix' => "$templateDir/Template_StripPrefix.wikitext",
            'Template:SidebarListItem' => "$templateDir/Template_SidebarListItem.wikitext",
            'Template:SidebarRelatedItem' => "$templateDir/Template_SidebarRelatedItem.wikitext",
        ];
        
        // Game page templates
        $gameTemplates = [
            'Template:Game' => "$templateDir/Template_Game.wikitext",
            'Template:GameEnd' => "$templateDir/Template_GameEnd.wikitext",
            'Template:GameSidebar' => "$templateDir/Template_GameSidebar.wikitext",
        ];
        
        $characterTemplates = [
            'Template:Character' => "$templateDir/Template_Character.wikitext",
            'Template:CharacterEnd' => "$templateDir/Template_CharacterEnd.wikitext",
            'Template:CharacterSidebar' => "$templateDir/Template_CharacterSidebar.wikitext",
        ];
        
        // Franchise page templates
        $franchiseTemplates = [
            'Template:Franchise' => "$templateDir/Template_Franchise.wikitext",
            'Template:FranchiseEnd' => "$templateDir/Template_FranchiseEnd.wikitext",
            'Template:FranchiseSidebar' => "$templateDir/Template_FranchiseSidebar.wikitext",
            'Template:FranchiseGameItem' => "$templateDir/Template_FranchiseGameItem.wikitext",
            'Template:FranchiseFirstGame' => "$templateDir/Template_FranchiseFirstGame.wikitext",
        ];
        
        // Build template list based on type
        $templates = [];
        if ( $type === 'all' || $type === 'shared' ) {
            $templates = array_merge( $templates, $sharedTemplates );
        }
        if ( $type === 'all' || $type === 'game' ) {
            $templates = array_merge( $templates, $gameTemplates );
        }
        if ( $type === 'all' || $type === 'character' ) {
            $templates = array_merge( $templates, $characterTemplates );
        }
        if ( $type === 'all' || $type === 'franchise' ) {
            $templates = array_merge( $templates, $franchiseTemplates );
        }

        $services = \MediaWiki\MediaWikiServices::getInstance();
        $wikiPageFactory = $services->getWikiPageFactory();

        $imported = 0;
        $skipped = 0;
        
        foreach ( $templates as $titleStr => $filePath ) {
            if ( !file_exists( $filePath ) ) {
                $this->output( "Skipping (file not found): $titleStr\n" );
                $skipped++;
                continue;
            }

            $content = file_get_contents( $filePath );
            if ( $content === false ) {
                $this->output( "Could not read: $filePath\n" );
                $skipped++;
                continue;
            }

            $title = \Title::newFromText( $titleStr );
            if ( !$title ) {
                $this->output( "Invalid title: $titleStr\n" );
                $skipped++;
                continue;
            }

            $wikiPage = $wikiPageFactory->newFromTitle( $title );
            $contentObj = \ContentHandler::makeContent( $content, $title );
            
            $updater = $wikiPage->newPageUpdater( \User::newSystemUser( 'Maintenance script', ['steal' => true] ) );
            $updater->setContent( \MediaWiki\Revision\SlotRecord::MAIN, $contentObj );
            $updater->saveRevision(
                \MediaWiki\CommentStore\CommentStoreComment::newUnsavedComment( 'Import template from wikitext file' ),
                EDIT_FORCE_BOT | EDIT_SUPPRESS_RC
            );

            $this->output( "Imported: $titleStr\n" );
            $imported++;
        }

        $this->output( "\nDone! Imported: $imported, Skipped: $skipped\n" );
    }
}

$maintClass = ImportWikiTemplates::class;
require_once RUN_MAINTENANCE_IF_MAIN;
