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
( () => {
    'use strict';

    const GB_IMAGE_BASE = 'https://www.giantbomb.com/a/uploads/';

    const initHeroImages = () => {
        const imageDataEl = document.getElementById( 'imageData' );
        if ( !imageDataEl ) return;

        const jsonStr = imageDataEl.getAttribute( 'data-json' ) || imageDataEl.textContent;
        if ( !jsonStr ) return;

        try {
            const imageData = JSON.parse( jsonStr );
            
            if ( imageData.infobox?.file && imageData.infobox?.path ) {
                const coverUrl = `${GB_IMAGE_BASE}scale_super/${imageData.infobox.path}${imageData.infobox.file}`;
                const coverContainer = document.querySelector( '.gb-game-hero-cover' );
                
                if ( coverContainer ) {
                    let coverImg = coverContainer.querySelector( 'img' );
                    if ( !coverImg ) {
                        coverImg = document.createElement( 'img' );
                        coverContainer.appendChild( coverImg );
                    }
                    coverImg.src = coverUrl;
                    coverImg.alt = document.querySelector( '.gb-game-hero-title' )?.textContent || 'Game cover';
                }
            }
            
            if ( imageData.background?.file && imageData.background?.path ) {
                const bgUrl = `${GB_IMAGE_BASE}screen_kubrick_wide/${imageData.background.path}${imageData.background.file}`;
                const heroSection = document.querySelector( '.gb-game-hero' );
                
                if ( heroSection ) {
                    heroSection.style.backgroundImage = `url(${bgUrl})`;
                }
            }
        } catch ( e ) {
            console.error( 'Failed to parse image data:', e );
        }
    };

    const stripPrefixesFromLinks = () => {
        const prefixes = [
            'Companies/', 'Platforms/', 'Genres/', 'Themes/', 
            'Franchises/', 'Characters/', 'Concepts/', 'Locations/', 'Objects/', 'Games/'
        ];
        
        const targetLinks = document.querySelectorAll( 
            '.gb-game-details a, .gb-sidebar-related-content a, .gb-game-hero-platforms a, .gb-game-hero-platform a'
        );
        
        targetLinks.forEach( link => {
            let text = link.textContent;
            prefixes.forEach( prefix => {
                if ( text.startsWith( prefix ) ) {
                    text = text.replace( prefix, '' );
                }
            });
            link.textContent = text.replace( /_/g, ' ' );
        });
        
        document.querySelectorAll( '.gb-game-hero-platform' ).forEach( span => {
            let text = span.textContent;
            prefixes.forEach( prefix => {
                if ( text.startsWith( prefix ) ) {
                    text = text.replace( prefix, '' );
                }
            });
            span.textContent = text.replace( /_/g, ' ' );
        });
    };

    const initSidebarTabs = () => {
        document.querySelectorAll( '.gb-sidebar-related-tabs' ).forEach( container => {
            const tabs = container.querySelectorAll( '.gb-sidebar-related-tab' );
            const section = container.closest( '.gb-sidebar-section' );
            if ( !section ) return;
            
            const panels = section.querySelector( '.gb-sidebar-related-content' )
                ?.querySelectorAll( '.gb-sidebar-related-list' );
            if ( !panels ) return;
            
            tabs.forEach( tab => {
                tab.addEventListener( 'click', () => {
                    const targetId = tab.getAttribute( 'data-target' );
                    
                    tabs.forEach( t => t.classList.remove( 'gb-sidebar-related-tab--active' ) );
                    tab.classList.add( 'gb-sidebar-related-tab--active' );
                    
                    panels.forEach( panel => {
                        panel.classList.toggle( 'gb-sidebar-related-list--active', panel.id === targetId );
                    });
                });
            });
        });
    };

    const init = () => {
        initHeroImages();
        stripPrefixesFromLinks();
        initSidebarTabs();
    };

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
})();
