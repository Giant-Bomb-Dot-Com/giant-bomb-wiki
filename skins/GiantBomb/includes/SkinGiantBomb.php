<?php

use GiantBomb\Skin\Helpers\PageHelper;
use MediaWiki\MediaWikiServices;

class SkinGiantBomb extends SkinTemplate {
    public $skinname = 'giantbomb';
    public $stylename = 'GiantBomb';
    public $template = 'GiantBombTemplate';
    public $useHeadElement = true;

    public function initPage( OutputPage $out ) {
        parent::initPage( $out );
        
        $out->addMeta( 'viewport', 'width=device-width, initial-scale=1.0' );

        // Pass header asset URL to JavaScript
        $headerAssetsUrl = getenv('GB_SITE_SERVER');
        $out->addJsConfigVars( 'wgHeaderAssetsUrl', $headerAssetsUrl );

        $out->addModuleStyles( 'skins.giantbomb.styles' );
        $out->addModules( [ 'skins.giantbomb', 'skins.giantbomb.js', 'skins.giantbomb.wikijs' ] );
    }

    /**
     * Fix the imageData div which is self-closing in imported content.
     * MediaWiki strips the / from <div ... /> making it unclosed.
     * This adds a closing tag immediately after the opening tag.
     */
    public static function onParserAfterTidy( Parser &$parser, &$text ) {
        // Find <div id="imageData" ...> (or id='imageData') and close it immediately
        $text = preg_replace(
            '/(<div\s+id=["\']imageData["\'][^>]*>)/',
            '$1</div>',
            $text
        );
        return true;
    }

    /**
     * Add SEO meta tags for template-rendered game pages.
     * Reads SMW properties to populate OpenGraph, Twitter cards, and meta description.
     */
    public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
        $title = $out->getTitle();
        if ( !$title ) {
            return;
        }

        $pageTitle = $title->getText();
        
        // Only process game pages rendered via templates
        $isGamePage = strpos( $pageTitle, 'Games/' ) === 0 && 
                      substr_count( $pageTitle, '/' ) === 1;
        
        if ( !$isGamePage ) {
            return;
        }

        // Get SMW properties for this page
        $store = \SMW\StoreFactory::getStore();
        $subject = \SMW\DIWikiPage::newFromTitle( $title );
        
        $gameName = self::getSMWPropertyValue( $store, $subject, 'Has name' ) 
                    ?: str_replace( 'Games/', '', $pageTitle );
        $deck = self::getSMWPropertyValue( $store, $subject, 'Has deck' ) ?: '';
        
        // Build meta description
        $metaDescription = $deck;
        if ( $metaDescription === '' ) {
            $metaDescription = $gameName . ' - Game info, reviews, and more on Giant Bomb Wiki.';
        }
        
        // Add meta description
        $out->addMeta( 'description', PageHelper::sanitizeMetaText( $metaDescription ) );
        
        // Build canonical URL
        $canonicalUrl = $title->getFullURL();
        
        // Get cover image from page content for OG image
        $metaImage = self::getGameCoverImage( $title );
        
        // Add OpenGraph tags
        PageHelper::addOpenGraphTags( $out, [
            'og:title' => $gameName . ' - Giant Bomb Wiki',
            'og:description' => PageHelper::sanitizeMetaText( $metaDescription ),
            'og:url' => $canonicalUrl,
            'og:site_name' => 'Giant Bomb Wiki',
            'og:type' => 'video.game',
            'og:locale' => 'en_US',
        ], $metaImage );
        
        // Add Twitter Card tags
        PageHelper::addTwitterTags( $out, [
            'twitter:card' => $metaImage ? 'summary_large_image' : 'summary',
            'twitter:title' => $gameName . ' - Giant Bomb Wiki',
            'twitter:description' => PageHelper::sanitizeMetaText( $metaDescription ),
            'twitter:site' => '@giantbomb',
        ], $metaImage, $gameName );
        
        // Add JSON-LD structured data for VideoGame
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'VideoGame',
            'name' => $gameName,
            'description' => PageHelper::sanitizeMetaText( $metaDescription ),
            'url' => $canonicalUrl,
        ];
        
        if ( $metaImage ) {
            $jsonLd['image'] = $metaImage;
        }
        
        // Get release date
        $releaseDate = self::getSMWPropertyValue( $store, $subject, 'Has release date' );
        if ( $releaseDate ) {
            $jsonLd['datePublished'] = $releaseDate;
        }
        
        // Get genres
        $genres = self::getSMWPropertyValues( $store, $subject, 'Has genres' );
        if ( !empty( $genres ) ) {
            $jsonLd['genre'] = array_map( function( $g ) {
                return str_replace( 'Genres/', '', $g );
            }, $genres );
        }
        
        $out->addHeadItem(
            'jsonld-videogame',
            '<script type="application/ld+json">' . json_encode( $jsonLd, JSON_UNESCAPED_SLASHES ) . '</script>'
        );
    }

    /**
     * Get a single SMW property value for a subject.
     */
    private static function getSMWPropertyValue( $store, $subject, string $propertyName ): ?string {
        try {
            $property = new \SMW\DIProperty( $propertyName );
            $values = $store->getPropertyValues( $subject, $property );
            if ( !empty( $values ) ) {
                $value = reset( $values );
                if ( $value instanceof \SMWDIBlob ) {
                    return $value->getString();
                } elseif ( $value instanceof \SMW\DIWikiPage ) {
                    return $value->getTitle()->getText();
                } elseif ( $value instanceof \SMWDITime ) {
                    return $value->getMwTimestamp();
                }
            }
        } catch ( \Exception $e ) {
            // Property doesn't exist or SMW error
        }
        return null;
    }

    /**
     * Get multiple SMW property values for a subject.
     */
    private static function getSMWPropertyValues( $store, $subject, string $propertyName ): array {
        $result = [];
        try {
            $property = new \SMW\DIProperty( $propertyName );
            $values = $store->getPropertyValues( $subject, $property );
            foreach ( $values as $value ) {
                if ( $value instanceof \SMWDIBlob ) {
                    $result[] = $value->getString();
                } elseif ( $value instanceof \SMW\DIWikiPage ) {
                    $result[] = $value->getTitle()->getText();
                }
            }
        } catch ( \Exception $e ) {
            // Property doesn't exist or SMW error
        }
        return $result;
    }

    /**
     * Try to extract the game cover image from page content.
     */
    private static function getGameCoverImage( \Title $title ): ?string {
        try {
            $wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
            $content = $wikiPage->getContent();
            
            if ( !$content ) {
                return null;
            }
            
            $text = $content->getText();
            $imageData = PageHelper::parseLegacyImageData( $text );
            
            if ( $imageData && isset( $imageData['infobox'] ) ) {
                return PageHelper::buildLegacyImageUrl(
                    $imageData['infobox'],
                    [ 'scale_super', 'scale_large', 'scale_medium', 'screen_kubrick' ]
                );
            }
        } catch ( \Exception $e ) {
            // Page doesn't exist or content inaccessible
        }
        
        return null;
    }
}
