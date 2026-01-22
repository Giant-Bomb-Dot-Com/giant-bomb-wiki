<?php
/**
 * Import wiki templates from wikitext files
 * 
 * Imports all content type templates into MediaWiki.
 * 
 * Usage:
 *   php maintenance/run.php import_templates/import_all_templates.php
 *   php maintenance/run.php import_templates/import_all_templates.php --type=game
 *   php maintenance/run.php import_templates/import_all_templates.php --type=character
 */

// Support both local dev (import_templates/) and Docker (maintenance/import_templates/)
if ( file_exists( __DIR__ . '/../Maintenance.php' ) ) {
    require_once __DIR__ . '/../Maintenance.php';
} else {
    require_once __DIR__ . '/../maintenance/Maintenance.php';
}

class ImportWikiTemplates extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->addDescription( 'Import wiki templates from wikitext files' );
        $this->addOption( 'type', 'Template type to import (default: all)', false, true );
    }

    public function execute() {
        global $IP;
        $moduleDir = "$IP/skins/GiantBomb/modules/wiki";
        $pagesDir = "$IP/skins/GiantBomb/pages/wiki";
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
        
        // Character page templates
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
        
        // Company page templates
        $companyTemplates = [
            'Template:Company' => "$templateDir/Template_Company.wikitext",
            'Template:CompanyEnd' => "$templateDir/Template_CompanyEnd.wikitext",
            'Template:CompanySidebar' => "$templateDir/Template_CompanySidebar.wikitext",
        ];
        
        // Concept page templates
        $conceptTemplates = [
            'Template:Concept' => "$templateDir/Template_Concept.wikitext",
            'Template:ConceptEnd' => "$templateDir/Template_ConceptEnd.wikitext",
            'Template:ConceptSidebar' => "$templateDir/Template_ConceptSidebar.wikitext",
        ];
        
        // Location page templates
        $locationTemplates = [
            'Template:Location' => "$templateDir/Template_Location.wikitext",
            'Template:LocationEnd' => "$templateDir/Template_LocationEnd.wikitext",
            'Template:LocationSidebar' => "$templateDir/Template_LocationSidebar.wikitext",
        ];
        
        // Person page templates
        $personTemplates = [
            'Template:Person' => "$templateDir/Template_Person.wikitext",
            'Template:PersonEnd' => "$templateDir/Template_PersonEnd.wikitext",
            'Template:PersonSidebar' => "$templateDir/Template_PersonSidebar.wikitext",
        ];
        
        // Platform page templates
        $platformTemplates = [
            'Template:Platform' => "$templateDir/Template_Platform.wikitext",
            'Template:PlatformEnd' => "$templateDir/Template_PlatformEnd.wikitext",
            'Template:PlatformSidebar' => "$templateDir/Template_PlatformSidebar.wikitext",
        ];
        
        // Object page templates
        $objectTemplates = [
            'Template:Object' => "$templateDir/Template_Object.wikitext",
            'Template:ObjectEnd' => "$templateDir/Template_ObjectEnd.wikitext",
            'Template:ObjectSidebar' => "$templateDir/Template_ObjectSidebar.wikitext",
        ];
        
        // Genre page templates
        $genreTemplates = [
            'Template:Genre' => "$templateDir/Template_Genre.wikitext",
            'Template:GenreEnd' => "$templateDir/Template_GenreEnd.wikitext",
            'Template:GenreSidebar' => "$templateDir/Template_GenreSidebar.wikitext",
        ];
        
        // Theme page templates
        $themeTemplates = [
            'Template:Theme' => "$templateDir/Template_Theme.wikitext",
            'Template:ThemeEnd' => "$templateDir/Template_ThemeEnd.wikitext",
            'Template:ThemeSidebar' => "$templateDir/Template_ThemeSidebar.wikitext",
        ];
        
        // Accessory page templates
        $accessoryTemplates = [
            'Template:Accessory' => "$templateDir/Template_Accessory.wikitext",
            'Template:AccessoryEnd' => "$templateDir/Template_AccessoryEnd.wikitext",
            'Template:AccessorySidebar' => "$templateDir/Template_AccessorySidebar.wikitext",
        ];
        
        // DLC page templates
        $dlcTemplates = [
            'Template:DLC' => "$templateDir/Template_DLC.wikitext",
            'Template:DLCEnd' => "$templateDir/Template_DLCEnd.wikitext",
            'Template:DLCSidebar' => "$templateDir/Template_DLCSidebar.wikitext",
        ];
        
        // Release page templates
        $releaseTemplates = [
            'Template:Release' => "$templateDir/Template_Release.wikitext",
            'Template:ReleaseEnd' => "$templateDir/Template_ReleaseEnd.wikitext",
            'Template:ReleaseSidebar' => "$templateDir/Template_ReleaseSidebar.wikitext",
        ];
        
        // Rating Board page templates
        $ratingBoardTemplates = [
            'Template:RatingBoard' => "$templateDir/Template_RatingBoard.wikitext",
            'Template:RatingBoardEnd' => "$templateDir/Template_RatingBoardEnd.wikitext",
            'Template:RatingBoardSidebar' => "$templateDir/Template_RatingBoardSidebar.wikitext",
        ];
        
        // Region page templates
        $regionTemplates = [
            'Template:Region' => "$templateDir/Template_Region.wikitext",
            'Template:RegionEnd' => "$templateDir/Template_RegionEnd.wikitext",
            'Template:RegionSidebar' => "$templateDir/Template_RegionSidebar.wikitext",
        ];
        
        // Game Rating page templates
        $gameRatingTemplates = [
            'Template:GameRating' => "$templateDir/Template_GameRating.wikitext",
            'Template:GameRatingEnd' => "$templateDir/Template_GameRatingEnd.wikitext",
            'Template:GameRatingSidebar' => "$templateDir/Template_GameRatingSidebar.wikitext",
        ];

        $rootGamePageTemplates = [
            'Main_Page' => "$pagesDir/Main_Page.wikitext",
            'Games' => "$pagesDir/Page_Games.wikitext",

            'Module:BadgeList' => "$moduleDir/Module_BadgeList.wikitext",
            'Module:GameFilters' => "$moduleDir/Module_GameFilters.wikitext",
            'Module:GameQuery' => "$moduleDir/Module_GameQuery.wikitext",

            'Template:GameCard' => "$templateDir/Template_GameCard.wikitext",
            'Template:GameQuery' => "$templateDir/Template_GameQuery.wikitext",
        ];
        
        // All template groups
        $allGroups = [
            'shared' => $sharedTemplates,
            'game' => $gameTemplates,
            'character' => $characterTemplates,
            'franchise' => $franchiseTemplates,
            'company' => $companyTemplates,
            'concept' => $conceptTemplates,
            'location' => $locationTemplates,
            'person' => $personTemplates,
            'platform' => $platformTemplates,
            'object' => $objectTemplates,
            'genre' => $genreTemplates,
            'theme' => $themeTemplates,
            'accessory' => $accessoryTemplates,
            'dlc' => $dlcTemplates,
            'release' => $releaseTemplates,
            'ratingboard' => $ratingBoardTemplates,
            'region' => $regionTemplates,
            'gamerating' => $gameRatingTemplates,
            'rootgames' => $rootGamePageTemplates,
        ];
        
        // Build template list based on type
        $templates = [];
        if ( $type === 'all' ) {
            foreach ( $allGroups as $group ) {
                $templates = array_merge( $templates, $group );
            }
        } elseif ( isset( $allGroups[$type] ) ) {
            $templates = $allGroups[$type];
        } else {
            $this->output( "Unknown type: $type\n" );
            $this->output( "Available types: all, " . implode( ', ', array_keys( $allGroups ) ) . "\n" );
            return;
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
