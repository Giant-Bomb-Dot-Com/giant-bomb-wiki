<?php
class GiantBombTemplate extends BaseTemplate {
    public function execute() {
        ?>
        <!DOCTYPE html>
        <html>
            <head>
                <?php echo $this->get('headelement'); ?>
            </head>
            <body>
                <h1>Giant Bomb Wiki</h1>

                 <div
                    data-vue-component="VueExampleComponent"
                    data-label="An example vue component with props">
                 </div>

                <div
                    data-vue-component="VueSingleFileComponentExample"
                    data-title="My First SFC">
                </div>

                <?php echo $this->get('trailelement'); ?>
            </body>
        </html>
        <?php
    }
}
