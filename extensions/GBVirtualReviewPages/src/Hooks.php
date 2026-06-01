<?php

namespace MediaWiki\Extension\GBVirtualReviewPages;

use Title;
use Article;
use OutputPage;
use User;
use WebRequest;
use MediaWiki;

class Hooks {

    /**
     * Intercepts the request to inject the template content dynamically.
     */
    public static function onBeforeInitialize( Title &$title, &$article, &$output, &$user, $request, $mediaWiki ) {
        $pageName = $title->getPrefixedText();

        if ( substr( $pageName, -8 ) === '/Reviews' && !$title->exists() ) {
            $parentPage = substr( $pageName, 0, -8 ); 
            
            $output->setPageTitle( "Reviews for " . basename( $parentPage ) );
            
            // Added #siteSub and .mw-site-sub to the hidden elements list
            $output->addInlineStyle( '
                #firstHeading, 
                .mw-page-title-main, 
                .page-heading, 
                #siteSub, 
                .mw-site-sub,
                .noarticletext { 
                    display: none !important; 
                }
            ' );
            
            $output->addWikiTextAsContent( "{{Template:ReviewLayout|parent=" . $parentPage . "}}" );
            
            return false; 
        }
        return true;
    }

    /**
     * Forces links to /Reviews pages to turn blue and removes redlink URL attributes.
     */
    public static function onTitleIsAlwaysKnown( Title $title, &$isKnown ) {
        if ( substr( $title->getPrefixedText(), -8 ) === '/Reviews' ) {
            $isKnown = true;
        }
        return true;
    }

    /**
     * Overrides the default edit behavior when clicking a virtual link, forcing a standard view.
     */
    public static function onMediaWikiPerformAction( $output, $article, $action, $user, $request, $wiki ) {
        $title = $article->getTitle();
        
        if ( substr( $title->getPrefixedText(), -8 ) === '/Reviews' && !$title->exists() ) {
            if ( $action === 'edit' || $action === 'submit' ) {
                $article->view();
                return false; 
            }
        }
        return true;
    }

    /**
     * Blocks physical page creation requests targeting any /Reviews subpage path.
     */
    public static function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
        // If someone is trying to create or edit a non-existent /Reviews path
        if ( substr( $title->getPrefixedText(), -8 ) === '/Reviews' && !$title->exists() ) {
            if ( $action === 'create' || $action === 'edit' ) {
                // Uses a core MediaWiki error key to halt execution cleanly
                $result = [ 'protectedinterface' ]; 
                return false; 
            }
        }
        return true;
    }
}
