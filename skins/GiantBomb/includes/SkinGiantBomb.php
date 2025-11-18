<?php
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
        $out->addModules( [ 'skins.giantbomb', 'skins.giantbomb.js', 'skins.giantbomb.externalheader' ] );
    }
}
