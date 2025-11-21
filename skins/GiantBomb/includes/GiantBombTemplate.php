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
        
        // Check if we're on the main page
        $isMainPage = $this->getSkin()->getTitle()->isMainPage();

        // Check if we're on a game page (in Games/ namespace but not a sub-page)
        $title = $this->getSkin()->getTitle();
        $pageTitle = $title->getText();
        $isGamePage = strpos($pageTitle, 'Games/') === 0 &&
                      substr_count($pageTitle, '/') === 1;
        $isNewReleasesPage = $pageTitle === 'New Releases' || $pageTitle === 'New Releases/';
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
        } elseif ($isNewReleasesPage) {
            // Show new releases page
?>
        <?php include __DIR__ . '/views/new-releases-page.php'; ?>
<?php
        } else {
            // Show normal wiki content for other pages
?>
        <div id="content" class="mw-body" role="main">
            <a id="top"></a>
            <div id="siteNotice"><?php $this->html( 'sitenotice' ) ?></div>
            <h1 id="firstHeading" class="firstHeading"><?php $this->html( 'title' ) ?></h1>
            <div id="bodyContent" class="mw-body-content">
                <div id="siteSub"><?php $this->msg( 'tagline' ) ?></div>
                <div id="contentSub"><?php $this->html( 'subtitle' ) ?></div>
                <?php if ( $this->data['undelete'] ) { ?>
                    <div id="contentSub2"><?php $this->html( 'undelete' ) ?></div>
                <?php } ?>
                <?php if ( $this->data['newtalk'] ) { ?>
                    <div class="usermessage"><?php $this->html( 'newtalk' ) ?></div>
                <?php } ?>
                <div id="jump-to-nav" class="mw-jump">
                    <?php $this->msg( 'jumpto' ) ?>
                    <a href="#mw-navigation"><?php $this->msg( 'jumptonavigation' ) ?></a>,
                    <a href="#p-search"><?php $this->msg( 'jumptosearch' ) ?></a>
                </div>
                <?php $this->html( 'bodytext' ) ?>
                <?php $this->html( 'catlinks' ) ?>
                <?php $this->html( 'dataAfterContent' ) ?>
            </div>
        </div>
<?php
        }
    }
}
