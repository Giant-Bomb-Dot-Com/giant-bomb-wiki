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
        
        $gtmId = getenv( 'GTM_CONTAINER_ID' );
        if ( $gtmId ) {
            $out->addHeadItem( 'gtm-head', 
                "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':" .
                "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0]," .
                "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=" .
                "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);" .
                "})(window,document,'script','dataLayer','" . htmlspecialchars( $gtmId ) . "');</script>"
            );
        }
    }

    public static function onOutputPageBodyAttributes( OutputPage $out, Skin $skin, array &$bodyAttrs ) {
        $user = $skin->getUser();
        if ( $user->isRegistered() && $user->isAllowed( 'gb-premium' ) ) {
            $bodyAttrs['class'] .= ' _rx7q';
        }
    }

    public static function onParserAfterTidy( Parser &$parser, &$text ) {
        $text = preg_replace(
            '/(<div\s+id=["\']imageData["\'][^>]*>)/',
            '$1</div>',
            $text
        );

        $text = preg_replace(
            '/\bclass="((?:[^"]*\s)?gb-\w+-sidebar(?:\s[^"]*)?)"/',
            'class="$1 gb-sidebar"',
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
        
        // Process game, character, or franchise pages rendered via templates
        $isGamePage = strpos( $pageTitle, 'Games/' ) === 0 && 
                      substr_count( $pageTitle, '/' ) === 1;
        $isCharacterPage = strpos( $pageTitle, 'Characters/' ) === 0 && 
                           substr_count( $pageTitle, '/' ) === 1;
        $isFranchisePage = strpos( $pageTitle, 'Franchises/' ) === 0 && 
                           substr_count( $pageTitle, '/' ) === 1;
        
        if ( !$isGamePage && !$isCharacterPage && !$isFranchisePage ) {
            return;
        }
        
        if ( $isCharacterPage ) {
            self::addCharacterSeoTags( $out, $title, $pageTitle );
            return;
        }
        
        if ( $isFranchisePage ) {
            self::addFranchiseSeoTags( $out, $title, $pageTitle );
            return;
        }

        // Get SMW properties for this page
        $store = \SMW\StoreFactory::getStore();
        $subject = \SMW\DIWikiPage::newFromTitle( $title );
        
        $gameName = self::getSMWPropertyValue( $store, $subject, 'Has name' ) 
                    ?: str_replace( 'Games/', '', $pageTitle );
        $deck = self::getSMWPropertyValue( $store, $subject, 'Has deck' ) ?: '';
        
        // Set HTML title
        $out->setHTMLTitle( $gameName . ' (Game) - ' . $GLOBALS['wgSitename'] );
        
        // Build meta description
        $metaDescription = $deck;
        if ( $metaDescription === '' ) {
            $metaDescription = $gameName . ' - Game info, reviews, and more on Giant Bomb Wiki.';
        }
        
        // Add meta description
        $out->addMeta( 'description', PageHelper::sanitizeMetaText( $metaDescription ) );
        
        // Build canonical URL
        $canonicalUrl = $title->getFullURL();
        
        // Get cover image (SMW property first, then legacy imageData fallback)
        $metaImage = self::getPageImage( $title, $store, $subject );
        
        // Add OpenGraph tags
        PageHelper::addOpenGraphTags( $out, [
            'og:title' => $gameName . ' (Game) - ' . $GLOBALS['wgSitename'],
            'og:description' => PageHelper::sanitizeMetaText( $metaDescription ),
            'og:url' => $canonicalUrl,
            'og:site_name' => $GLOBALS['wgSitename'],
            'og:type' => 'video.game',
            'og:locale' => 'en_US',
        ], $metaImage );
        
        // Add Twitter Card tags
        PageHelper::addTwitterTags( $out, [
            'twitter:card' => $metaImage ? 'summary_large_image' : 'summary',
            'twitter:title' => $gameName . ' (Game) - ' . $GLOBALS['wgSitename'],
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
     * Get the cover/profile image for a page.
     * 
     * Checks in order:
     * 1. SMW "Has image" property (preferred - structured data)
     * 2. Legacy imageData div in page content (fallback for old imports)
     * 
     * @param \Title $title The page title
     * @param mixed $store Optional SMW store (will be created if not provided)
     * @param mixed $subject Optional SMW subject (will be created if not provided)
     * @return string|null The image URL or null if not found
     */
    private static function getPageImage( \Title $title, $store = null, $subject = null ): ?string {
        // Try SMW "Has image" property first
        if ( !$store ) {
            $store = \SMW\StoreFactory::getStore();
        }
        if ( !$subject ) {
            $subject = \SMW\DIWikiPage::newFromTitle( $title );
        }
        
        $smwImage = self::getSMWPropertyValue( $store, $subject, 'Has image' );
        if ( $smwImage ) {
            // Has image stores the GB image path (e.g., "scale_large/xxx/filename.jpg")
            // or could be a full URL - check and build appropriately
            if ( strpos( $smwImage, 'http' ) === 0 ) {
                return $smwImage;
            }
            // Assume it's a GB image path - build full URL
            return 'https://www.giantbomb.com/a/uploads/' . $smwImage;
        }
        
        // Fall back to parsing legacy imageData from page content
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
    
    /**
     * @deprecated Use getPageImage() instead
     */
    private static function getGameCoverImage( \Title $title ): ?string {
        return self::getPageImage( $title );
    }

    /**
     * Add SEO meta tags for character pages.
     */
    private static function addCharacterSeoTags( OutputPage &$out, \Title $title, string $pageTitle ): void {
        $store = \SMW\StoreFactory::getStore();
        $subject = \SMW\DIWikiPage::newFromTitle( $title );
        
        $characterName = self::getSMWPropertyValue( $store, $subject, 'Has name' ) 
                    ?: str_replace( 'Characters/', '', $pageTitle );
        $deck = self::getSMWPropertyValue( $store, $subject, 'Has deck' ) ?: '';
        
        // Set HTML title
        $out->setHTMLTitle( $characterName . ' (Character) - ' . $GLOBALS['wgSitename'] );
        
        $metaDescription = $deck;
        if ( $metaDescription === '' ) {
            $metaDescription = $characterName . ' - Character info and appearances on Giant Bomb Wiki.';
        }
        
        $out->addMeta( 'description', PageHelper::sanitizeMetaText( $metaDescription ) );
        
        $canonicalUrl = $title->getFullURL();
        $metaImage = self::getPageImage( $title, $store, $subject );
        
        PageHelper::addOpenGraphTags( $out, [
            'og:title' => $characterName . ' (Character) - ' . $GLOBALS['wgSitename'],
            'og:description' => PageHelper::sanitizeMetaText( $metaDescription ),
            'og:url' => $canonicalUrl,
            'og:site_name' => $GLOBALS['wgSitename'],
            'og:type' => 'profile',
            'og:locale' => 'en_US',
        ], $metaImage );
        
        PageHelper::addTwitterTags( $out, [
            'twitter:card' => $metaImage ? 'summary_large_image' : 'summary',
            'twitter:title' => $characterName . ' (Character) - ' . $GLOBALS['wgSitename'],
            'twitter:description' => PageHelper::sanitizeMetaText( $metaDescription ),
            'twitter:site' => '@giantbomb',
        ], $metaImage, $characterName );
        
        // FictionalCharacter is the correct Schema.org type for video game characters
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'FictionalCharacter',
            'name' => $characterName,
            'description' => PageHelper::sanitizeMetaText( $metaDescription ),
            'url' => $canonicalUrl,
        ];
        
        if ( $metaImage ) {
            $jsonLd['image'] = $metaImage;
        }
        
        $out->addHeadItem(
            'jsonld-character',
            '<script type="application/ld+json">' . json_encode( $jsonLd, JSON_UNESCAPED_SLASHES ) . '</script>'
        );
    }

    /**
     * Add SEO meta tags for franchise pages.
     */
    private static function addFranchiseSeoTags( OutputPage &$out, \Title $title, string $pageTitle ): void {
        $store = \SMW\StoreFactory::getStore();
        $subject = \SMW\DIWikiPage::newFromTitle( $title );
        
        $franchiseName = self::getSMWPropertyValue( $store, $subject, 'Has name' ) 
                    ?: str_replace( 'Franchises/', '', $pageTitle );
        $deck = self::getSMWPropertyValue( $store, $subject, 'Has deck' ) ?: '';
        
        // Set HTML title
        $out->setHTMLTitle( $franchiseName . ' (Franchise) - ' . $GLOBALS['wgSitename'] );
        
        $metaDescription = $deck;
        if ( $metaDescription === '' ) {
            $metaDescription = $franchiseName . ' - Franchise info, games, and characters on Giant Bomb Wiki.';
        }
        
        $out->addMeta( 'description', PageHelper::sanitizeMetaText( $metaDescription ) );
        
        $canonicalUrl = $title->getFullURL();
        $metaImage = self::getPageImage( $title, $store, $subject );
        
        PageHelper::addOpenGraphTags( $out, [
            'og:title' => $franchiseName . ' (Franchise) - ' . $GLOBALS['wgSitename'],
            'og:description' => PageHelper::sanitizeMetaText( $metaDescription ),
            'og:url' => $canonicalUrl,
            'og:site_name' => $GLOBALS['wgSitename'],
            'og:type' => 'website',
            'og:locale' => 'en_US',
        ], $metaImage );
        
        PageHelper::addTwitterTags( $out, [
            'twitter:card' => $metaImage ? 'summary_large_image' : 'summary',
            'twitter:title' => $franchiseName . ' (Franchise) - ' . $GLOBALS['wgSitename'],
            'twitter:description' => PageHelper::sanitizeMetaText( $metaDescription ),
            'twitter:site' => '@giantbomb',
        ], $metaImage, $franchiseName );
        
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'CreativeWorkSeries',
            'name' => $franchiseName,
            'description' => PageHelper::sanitizeMetaText( $metaDescription ),
            'url' => $canonicalUrl,
        ];
        
        if ( $metaImage ) {
            $jsonLd['image'] = $metaImage;
        }
        
        $out->addHeadItem(
            'jsonld-franchise',
            '<script type="application/ld+json">' . json_encode( $jsonLd, JSON_UNESCAPED_SLASHES ) . '</script>'
        );
    }
}
