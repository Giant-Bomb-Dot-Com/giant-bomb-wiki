<?php
class GiantBombTemplate extends BaseTemplate {
    public function execute() {
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

        // Check if we're on a game page (in Games/ namespace but not a sub-page)
        $title = $this->getSkin()->getTitle();
        $pageTitle = $title->getText();
        
        // Game pages now render via MediaWiki templates (the "MediaWiki way")
        // Set $useCustomGamePage = true to revert to custom PHP rendering
        $useCustomGamePage = false;
        $isGamePage = $useCustomGamePage && 
                      strpos($pageTitle, 'Games/') === 0 &&
                      substr_count($pageTitle, '/') === 1;
        $isPlatformPage = strpos($pageTitle, 'Platforms/') === 0 &&
                          substr_count($pageTitle, '/') === 1;
        // Character pages now render via MediaWiki templates (the "MediaWiki way")
        // Set $useCustomCharacterPage = true to revert to custom PHP rendering
        $useCustomCharacterPage = false;
        $isCharacterPage = $useCustomCharacterPage &&
                           strpos($pageTitle, 'Characters/') === 0 &&
                           substr_count($pageTitle, '/') === 1;
        $isConceptPage = strpos($pageTitle, 'Concepts/') === 0 &&
                         substr_count($pageTitle, '/') === 1;
        $isCompanyPage = strpos($pageTitle, 'Companies/') === 0 &&
                         substr_count($pageTitle, '/') === 1;
        $isFranchisePage = strpos($pageTitle, 'Franchises/') === 0 &&
                           substr_count($pageTitle, '/') === 1;
        $isPersonPage = strpos($pageTitle, 'People/') === 0 &&
                        substr_count($pageTitle, '/') === 1;
        $isObjectPage = strpos($pageTitle, 'Objects/') === 0 &&
                        substr_count($pageTitle, '/') === 1;
        $isLocationPage = strpos($pageTitle, 'Locations/') === 0 &&
                          substr_count($pageTitle, '/') === 1;
        $isAccessoryPage = strpos($pageTitle, 'Accessories/') === 0 &&
                           substr_count($pageTitle, '/') === 1;
        $isNewReleasesPage = $pageTitle === 'New Releases' || $pageTitle === 'New Releases/';
        $isPlatformsPage = $pageTitle === 'Platforms' || $pageTitle === 'Platforms/';
        $isConceptsPage = $pageTitle === 'Concepts' || $pageTitle === 'Concepts/';
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
            
            // Check if this is a template-rendered game or character page
            $isTemplateGamePage = strpos($pageTitle, 'Games/') === 0 &&
                                  substr_count($pageTitle, '/') === 1;
            $isTemplateCharacterPage = strpos($pageTitle, 'Characters/') === 0 &&
                                       substr_count($pageTitle, '/') === 1;
            $isTemplateContentPage = $isTemplateGamePage || $isTemplateCharacterPage;
            
            $contentClasses = ['mw-body'];
            if ($isTemplateContentPage) $contentClasses[] = 'wiki-template-page';
            if ($isTemplateGamePage) $contentClasses[] = 'wiki-game-page';
            if ($isTemplateCharacterPage) $contentClasses[] = 'wiki-character-page';
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
