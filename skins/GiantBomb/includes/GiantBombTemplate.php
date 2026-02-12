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

        // Check if we're on a game page (in Games/ namespace but not a sub-page)
        $title = $this->getSkin()->getTitle();
        $pageTitle = $title->getText();
        
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

        $action = $request->getText('action', 'view');
        $isViewAction = $action === 'view' || $action === 'purge' || $action === '';
        $isAdEligible = $isViewAction
            && !$title->isSpecialPage()
            && $title->getNamespace() >= 0
            && $title->getNamespace() % 2 === 0;

        if ($isAdEligible) {
            $contentClasses[] = 'gb-wiki-content';
        }

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
                    <?php if ($isAdEligible) { ?><div id="gb-wiki-content"><?php } ?>
                    <?php $this->html( 'bodytext' ) ?>
                    <?php if ($isAdEligible) { ?></div><?php } ?>
                    <?php $this->html( 'catlinks' ) ?>
                    <?php $this->html( 'dataAfterContent' ) ?>
                </div>
            </div>
        </div>
<?php        
    }
}