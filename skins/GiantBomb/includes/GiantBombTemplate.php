<?php
class GiantBombTemplate extends BaseTemplate {
    public function execute() {
?>
        <h1>Giant Bomb Wiki</h1>
        <div
             data-vue-component="VueExampleComponent"
             data-label="An example vue component with props">
        </div>
        <div
             data-vue-component="VueSingleFileComponentExample"
             data-title="My First SFC">
        </div>
<?php
    }
}
