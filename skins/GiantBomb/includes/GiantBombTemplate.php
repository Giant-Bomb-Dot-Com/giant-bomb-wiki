<?php
class GiantBombTemplate extends BaseTemplate {
    public function execute() {
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
    }
}
