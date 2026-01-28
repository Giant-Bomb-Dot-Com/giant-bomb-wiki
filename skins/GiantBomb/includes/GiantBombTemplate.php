<?php
class GiantBombTemplate extends BaseTemplate {
    public function execute() {
        // Google Tag Manager noscript fallback
        $gtmId = getenv( 'GTM_CONTAINER_ID' );
        if ( $gtmId ) {
            echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . 
                 htmlspecialchars( $gtmId ) . '" height="0" width="0" ' .
                 'style="display:none;visibility:hidden"></iframe></noscript>';
        }
        
        // Handle API requests first
        $request = RequestContext::getMain()->getRequest();
        $action = $request->getText('action', '');

        if ($action === 'get-releases') {
            require_once __DIR__ . '/api/releases-api.php';
            return;
        }

        if ($action === 'get-games') {
            require_once __DIR__ . '/api/games-api.php';
            return;
        }
        
        if ($action === 'get-platforms') {
            require_once __DIR__ . '/api/platforms-api.php';
            return;
        }
        
        if ($action === 'get-concepts') {
            require_once __DIR__ . '/api/concepts-api.php';
            return;
        }

        if ($action === 'get-people') {
            require_once __DIR__ . '/api/peoples-api.php';
            return;
        }
        
        // Check if we're on the main page
        $isMainPage = $this->getSkin()->getTitle()->isMainPage();

        //Set main to false to force mediawiki as it's been replaced
        $isMainPage = false;

        // Check if we're on a game page (in Games/ namespace but not a sub-page)
        $title = $this->getSkin()->getTitle();
        $pageTitle = $title->getText();
        
        // Game pages now render via MediaWiki templates (the "MediaWiki way")
        // Set $useCustomGamePage = true to revert to custom PHP rendering
        $useCustomGamePage = false;
        $isGamePage = $useCustomGamePage && 
                      strpos($pageTitle, 'Games/') === 0 &&
                      substr_count($pageTitle, '/') === 1;
        // Platform pages now render via MediaWiki templates (the "MediaWiki way")
        // Set $useCustomPlatformPage = true to revert to custom PHP rendering
        $useCustomPlatformPage = false;
        $isPlatformPage = $useCustomPlatformPage &&
                          strpos($pageTitle, 'Platforms/') === 0 &&
                          substr_count($pageTitle, '/') === 1;
        // Character pages now render via MediaWiki templates (the "MediaWiki way")
        // Set $useCustomCharacterPage = true to revert to custom PHP rendering
        $useCustomCharacterPage = false;
        $isCharacterPage = $useCustomCharacterPage &&
                           strpos($pageTitle, 'Characters/') === 0 &&
                           substr_count($pageTitle, '/') === 1;
        // Concept pages now render via MediaWiki templates (the "MediaWiki way")
        // Set $useCustomConceptPage = true to revert to custom PHP rendering
        $useCustomConceptPage = false;
        $isConceptPage = $useCustomConceptPage &&
                         strpos($pageTitle, 'Concepts/') === 0 &&
                         substr_count($pageTitle, '/') === 1;
        // Company pages now render via MediaWiki templates (the "MediaWiki way")
        // Set $useCustomCompanyPage = true to revert to custom PHP rendering
        $useCustomCompanyPage = false;
        $isCompanyPage = $useCustomCompanyPage &&
                         strpos($pageTitle, 'Companies/') === 0 &&
                         substr_count($pageTitle, '/') === 1;
        // Franchise pages now render via MediaWiki templates (the "MediaWiki way")
        // Set $useCustomFranchisePage = true to revert to custom PHP rendering
        $useCustomFranchisePage = false;
        $isFranchisePage = $useCustomFranchisePage &&
                           strpos($pageTitle, 'Franchises/') === 0 &&
                           substr_count($pageTitle, '/') === 1;
        // Person pages now render via MediaWiki templates (the "MediaWiki way")
        // Set $useCustomPersonPage = true to revert to custom PHP rendering
        $useCustomPersonPage = false;
        $isPersonPage = $useCustomPersonPage &&
                        strpos($pageTitle, 'People/') === 0 &&
                        substr_count($pageTitle, '/') === 1;
        // Object pages now render via MediaWiki templates (the "MediaWiki way")
        // Set $useCustomObjectPage = true to revert to custom PHP rendering
        $useCustomObjectPage = false;
        $isObjectPage = $useCustomObjectPage &&
                        strpos($pageTitle, 'Objects/') === 0 &&
                        substr_count($pageTitle, '/') === 1;
        // Location pages now render via MediaWiki templates (the "MediaWiki way")
        // Set $useCustomLocationPage = true to revert to custom PHP rendering
        $useCustomLocationPage = false;
        $isLocationPage = $useCustomLocationPage &&
                          strpos($pageTitle, 'Locations/') === 0 &&
                          substr_count($pageTitle, '/') === 1;
        // Accessory pages now render via MediaWiki templates (the "MediaWiki way")
        // Set $useCustomAccessoryPage = true to revert to custom PHP rendering
        $useCustomAccessoryPage = false;
        $isAccessoryPage = $useCustomAccessoryPage &&
                           strpos($pageTitle, 'Accessories/') === 0 &&
                           substr_count($pageTitle, '/') === 1;
        $isNewReleasesPage = $pageTitle === 'New Releases' || $pageTitle === 'New Releases/';
        $isPlatformsPage = $pageTitle === 'Platforms' || $pageTitle === 'Platforms/';
        $isConceptsPage = false; //$pageTitle === 'Concepts' || $pageTitle === 'Concepts/';
        $isPeoplePage = $pageTitle === 'People' || $pageTitle === 'People/';
        error_log("Current page title: " . $pageTitle);
        

        if ($isMainPage) {
            // Show landing page for main page
?>
        <!--

        Commenting this out but leaving it in for now as an
        Example for using Vue Components in our current setup

        <div
             data-vue-component="VueExampleComponent"
             data-label="An example vue component with props">
        </div>
        <div
             data-vue-component="VueSingleFileComponentExample"
             data-title="My First SFC">
        </div> -->
        <?php include __DIR__ . '/views/landing-page.php'; ?>
<?php
        } elseif ($isGamePage) {
            // Show custom game page for game pages
?>
        <?php include __DIR__ . '/views/game-page.php'; ?>
<?php
        } elseif ($isPlatformPage) {
            // Show custom platform page for platform pages
?>
        <?php include __DIR__ . '/views/platform-page.php'; ?>
<?php
        } elseif ($isCharacterPage) {
            // Show custom character page for character pages
?>
        <?php include __DIR__ . '/views/character-page.php'; ?>
<?php
        } elseif ($isConceptPage) {
            // Show custom concept page for concept pages
?>
        <?php include __DIR__ . '/views/concept-page.php'; ?>
<?php
        } elseif ($isCompanyPage) {
            // Show custom company page
?>
        <?php include __DIR__ . '/views/company-page.php'; ?>
<?php
        } elseif ($isFranchisePage) {
            // Show custom franchise page
?>
        <?php include __DIR__ . '/views/franchise-page.php'; ?>
<?php
        } elseif ($isPersonPage) {
?>
        <?php include __DIR__ . '/views/person-page.php'; ?>
<?php
        } elseif ($isObjectPage) {
?>
        <?php include __DIR__ . '/views/object-page.php'; ?>
<?php
        } elseif ($isLocationPage) {
?>
        <?php include __DIR__ . '/views/location-page.php'; ?>
<?php
        } elseif ($isAccessoryPage) {
?>
        <?php include __DIR__ . '/views/accessory-page.php'; ?>
<?php
        } elseif ($isNewReleasesPage) {
            // Show new releases page
?>
        <?php include __DIR__ . '/views/new-releases-page.php'; ?>
<?php
        } elseif ($isPlatformsPage) {
            // Show platforms page
?>
        <?php include __DIR__ . '/views/platforms-page.php'; ?>
<?php
        } elseif ($isConceptsPage) {
            // Show concepts page
?>
        <?php include __DIR__ . '/views/concepts-page.php'; ?>
<?php
        } elseif ($isPeoplePage) {
            // Show people page
?>
        <?php include __DIR__ . '/views/peoples-page.php'; ?>
<?php
        } else {
            // Show normal wiki content for other pages
            // This includes game pages when rendered via templates (MediaWiki way)
            
            // Check if this is a template-rendered content page (the "MediaWiki way")
            $isTemplateGamePage = strpos($pageTitle, 'Games/') === 0 &&
                                  substr_count($pageTitle, '/') === 1;
            $isTemplateCharacterPage = strpos($pageTitle, 'Characters/') === 0 &&
                                       substr_count($pageTitle, '/') === 1;
            $isTemplateFranchisePage = strpos($pageTitle, 'Franchises/') === 0 &&
                                       substr_count($pageTitle, '/') === 1;
            $isTemplatePlatformPage = strpos($pageTitle, 'Platforms/') === 0 &&
                                      substr_count($pageTitle, '/') === 1;
            $isTemplateConceptPage = strpos($pageTitle, 'Concepts/') === 0 &&
                                     substr_count($pageTitle, '/') === 1;
            $isTemplateCompanyPage = strpos($pageTitle, 'Companies/') === 0 &&
                                     substr_count($pageTitle, '/') === 1;
            $isTemplatePersonPage = strpos($pageTitle, 'People/') === 0 &&
                                    substr_count($pageTitle, '/') === 1;
            $isTemplateObjectPage = strpos($pageTitle, 'Objects/') === 0 &&
                                    substr_count($pageTitle, '/') === 1;
            $isTemplateLocationPage = strpos($pageTitle, 'Locations/') === 0 &&
                                      substr_count($pageTitle, '/') === 1;
            $isTemplateAccessoryPage = strpos($pageTitle, 'Accessories/') === 0 &&
                                       substr_count($pageTitle, '/') === 1;
            $isTemplateContentPage = $isTemplateGamePage || $isTemplateCharacterPage || $isTemplateFranchisePage ||
                                     $isTemplatePlatformPage || $isTemplateConceptPage || $isTemplateCompanyPage ||
                                     $isTemplatePersonPage || $isTemplateObjectPage || $isTemplateLocationPage ||
                                     $isTemplateAccessoryPage;
            
            $contentClasses = ['mw-body'];
            if ($isTemplateContentPage) $contentClasses[] = 'wiki-template-page';
            if ($isTemplateGamePage) $contentClasses[] = 'wiki-game-page';
            if ($isTemplateCharacterPage) $contentClasses[] = 'wiki-character-page';
            if ($isTemplateFranchisePage) $contentClasses[] = 'wiki-franchise-page';
            if ($isTemplatePlatformPage) $contentClasses[] = 'wiki-platform-page';
            if ($isTemplateConceptPage) $contentClasses[] = 'wiki-concept-page';
            if ($isTemplateCompanyPage) $contentClasses[] = 'wiki-company-page';
            if ($isTemplatePersonPage) $contentClasses[] = 'wiki-person-page';
            if ($isTemplateObjectPage) $contentClasses[] = 'wiki-object-page';
            if ($isTemplateLocationPage) $contentClasses[] = 'wiki-location-page';
            if ($isTemplateAccessoryPage) $contentClasses[] = 'wiki-accessory-page';
?>
        <div class="page-wrapper">
            <?php include __DIR__ . '/partials/header.php'; ?>
            
            <div id="content" class="<?php echo implode(' ', $contentClasses); ?>" role="main">
                <a id="top"></a>
                <div id="siteNotice"><?php $this->html( 'sitenotice' ) ?></div>
                <?php if (!$isTemplateContentPage) { ?>
                <h1 id="firstHeading" class="firstHeading"><?php $this->html( 'title' ) ?></h1>
                <?php } ?>
                <div id="bodyContent" class="mw-body-content">
                    <?php if (!$isTemplateContentPage) { ?>
                    <div id="siteSub"><?php $this->msg( 'tagline' ) ?></div>
                    <div id="contentSub"><?php $this->html( 'subtitle' ) ?></div>
                    <?php } ?>
                    <?php if ( $this->data['undelete'] ) { ?>
                        <div id="contentSub2"><?php $this->html( 'undelete' ) ?></div>
                    <?php } ?>
                    <?php if ( $this->data['newtalk'] ) { ?>
                        <div class="usermessage"><?php $this->html( 'newtalk' ) ?></div>
                    <?php } ?>
                    <?php if (!$isTemplateContentPage) { ?>
                    <div id="jump-to-nav" class="mw-jump">
                        <?php $this->msg( 'jumpto' ) ?>
                        <a href="#mw-navigation"><?php $this->msg( 'jumptonavigation' ) ?></a>,
                        <a href="#p-search"><?php $this->msg( 'jumptosearch' ) ?></a>
                    </div>
                    <?php } ?>
                    <?php $this->html( 'bodytext' ) ?>
                    <?php $this->html( 'catlinks' ) ?>
                    <?php $this->html( 'dataAfterContent' ) ?>
                </div>
            </div>
        </div>
<?php
        }
    }
}
