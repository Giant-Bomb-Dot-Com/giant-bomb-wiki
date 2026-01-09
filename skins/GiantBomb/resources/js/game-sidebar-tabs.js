/**
 * Game Page JavaScript
 * 
 * Features:
 * - Hero images: Loads cover and background from embedded JSON
 * - Sidebar tabs: Interactive tabs for Characters/Locations/Concepts/Objects
 * - Prefix stripping: Removes namespace prefixes from link text for cleaner display
 * 
 * Why client-side prefix stripping?
 * ---------------------------------
 * SMW stores page names with namespace prefixes (e.g., "Companies/id Software").
 * When rendered via #show with link=all, the link text includes the full path.
 * Server-side alternatives would require:
 *   - Changing data storage (breaking existing imports)
 *   - Complex SMW result templates
 *   - Lua modules (which we're avoiding for simplicity)
 * 
 * The JS solution is a simple, fast progressive enhancement that improves
 * display without affecting the underlying data model.
 */
( function () {
    'use strict';

    var GB_IMAGE_BASE = 'https://www.giantbomb.com/a/uploads/';

    /**
     * Initialize hero images from embedded JSON data
     */
    function initHeroImages() {
        var imageDataEl = document.getElementById( 'imageData' );
        if ( !imageDataEl ) return;

        // Handle both div (data-json attr) and script (textContent) formats
        var jsonStr = imageDataEl.getAttribute( 'data-json' ) || imageDataEl.textContent;
        if ( !jsonStr ) return;

        try {
            var imageData = JSON.parse( jsonStr );
            
            // Set cover image (use scale_super size)
            if ( imageData.infobox && imageData.infobox.file && imageData.infobox.path ) {
                var coverUrl = GB_IMAGE_BASE + 'scale_super/' + imageData.infobox.path + imageData.infobox.file;
                var coverContainer = document.querySelector( '.gb-game-hero-cover' );
                
                if ( coverContainer ) {
                    // Create or update the cover image
                    var coverImg = coverContainer.querySelector( 'img' );
                    if ( !coverImg ) {
                        coverImg = document.createElement( 'img' );
                        coverContainer.appendChild( coverImg );
                    }
                    coverImg.src = coverUrl;
                    coverImg.alt = document.querySelector( '.gb-game-hero-title' )?.textContent || 'Game cover';
                }
            }
            
            // Set background image on hero section (use screen_kubrick_wide size)
            if ( imageData.background && imageData.background.file && imageData.background.path ) {
                var bgUrl = GB_IMAGE_BASE + 'screen_kubrick_wide/' + imageData.background.path + imageData.background.file;
                var heroSection = document.querySelector( '.gb-game-hero' );
                
                if ( heroSection ) {
                    heroSection.style.backgroundImage = 'url(' + bgUrl + ')';
                }
            }
        } catch ( e ) {
            console.error( 'Failed to parse image data:', e );
        }
    }

    /**
     * Strip wiki prefixes (Companies/, Platforms/, etc.) from link text
     */
    function stripPrefixesFromLinks() {
        var prefixes = [
            'Companies/', 'Platforms/', 'Genres/', 'Themes/', 
            'Franchises/', 'Characters/', 'Concepts/', 'Locations/', 'Objects/', 'Games/'
        ];
        
        // Target links in sidebar AND hero platforms
        var targetLinks = document.querySelectorAll( 
            '.gb-game-details a, .gb-sidebar-related-content a, .gb-game-hero-platforms a, .gb-game-hero-platform a'
        );
        
        targetLinks.forEach( function ( link ) {
            var text = link.textContent;
            prefixes.forEach( function ( prefix ) {
                if ( text.indexOf( prefix ) === 0 ) {
                    link.textContent = text.replace( prefix, '' );
                }
            });
            // Also handle underscores to spaces
            link.textContent = link.textContent.replace( /_/g, ' ' );
        });
        
        // Also strip from span elements in hero platforms (for non-linked text)
        var platformSpans = document.querySelectorAll( '.gb-game-hero-platform' );
        platformSpans.forEach( function ( span ) {
            var text = span.textContent;
            prefixes.forEach( function ( prefix ) {
                if ( text.indexOf( prefix ) === 0 ) {
                    span.textContent = text.replace( prefix, '' );
                }
            });
            span.textContent = span.textContent.replace( /_/g, ' ' );
        });
    }

    function initSidebarTabs() {
        var tabContainers = document.querySelectorAll( '.gb-sidebar-related-tabs' );
        
        tabContainers.forEach( function ( container ) {
            var tabs = container.querySelectorAll( '.gb-sidebar-related-tab' );
            var section = container.closest( '.gb-sidebar-section' );
            
            if ( !section ) return;
            
            var contentContainer = section.querySelector( '.gb-sidebar-related-content' );
            if ( !contentContainer ) return;
            
            var panels = contentContainer.querySelectorAll( '.gb-sidebar-related-list' );
            
            tabs.forEach( function ( tab ) {
                tab.addEventListener( 'click', function () {
                    var targetId = this.getAttribute( 'data-target' );
                    
                    tabs.forEach( function ( t ) {
                        t.classList.remove( 'gb-sidebar-related-tab--active' );
                    } );
                    this.classList.add( 'gb-sidebar-related-tab--active' );
                    
                    panels.forEach( function ( panel ) {
                        if ( panel.id === targetId ) {
                            panel.classList.add( 'gb-sidebar-related-list--active' );
                        } else {
                            panel.classList.remove( 'gb-sidebar-related-list--active' );
                        }
                    } );
                } );
            } );
        } );
    }

    function init() {
        initHeroImages();
        stripPrefixesFromLinks();
        initSidebarTabs();
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
}() );

