<?php
/**
 * Script to append {{GameEnd}} to game pages that don't have it
 */

require_once '/var/www/html/maintenance/Maintenance.php';

use MediaWiki\MediaWikiServices;

class AddGameEnd extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->addDescription('Add {{GameEnd}} to game pages');
    }

    public function execute() {
        $dbr = $this->getDB(DB_REPLICA);
        $services = MediaWikiServices::getInstance();
        $wikiPageFactory = $services->getWikiPageFactory();
        $userFactory = $services->getUserFactory();
        
        // Find all game pages
        $res = $dbr->select(
            'page',
            ['page_id', 'page_title'],
            [
                'page_namespace' => 0,
                'page_title ' . $dbr->buildLike('Games/', $dbr->anyString())
            ],
            __METHOD__
        );
        
        foreach ($res as $row) {
            $title = Title::newFromID($row->page_id);
            if (!$title) continue;
            
            $page = $wikiPageFactory->newFromTitle($title);
            $content = $page->getContent();
            if (!$content) continue;
            
            $text = $content->getText();
            
            // Check if {{GameEnd}} already exists
            if (strpos($text, '{{GameEnd}}') !== false) {
                $this->output("Skipping {$row->page_title} - already has GameEnd\n");
                continue;
            }
            
            // Append {{GameEnd}}
            $newText = $text . "\n{{GameEnd}}";
            
            $newContent = new WikitextContent($newText);
            $user = $userFactory->newFromName('Maintenance script');
            if (!$user || $user->getId() === 0) {
                $user = User::newSystemUser('Maintenance script', ['steal' => true]);
            }
            
            $updater = $page->newPageUpdater($user);
            $updater->setContent('main', $newContent);
            $updater->saveRevision(
                CommentStoreComment::newUnsavedComment('Adding {{GameEnd}} template for proper page layout'),
                EDIT_MINOR
            );
            
            $this->output("Updated {$row->page_title}\n");
        }
        
        $this->output("Done!\n");
    }
}

$maintClass = AddGameEnd::class;
require_once RUN_MAINTENANCE_IF_MAIN;
