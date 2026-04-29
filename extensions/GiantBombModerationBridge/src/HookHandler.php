<?php

namespace MediaWiki\Extension\GiantBombModerationBridge;

use MediaWiki\Hook\BeforePageDisplayHook;

class HookHandler implements BeforePageDisplayHook
{
    public function onBeforePageDisplay($out, $skin): void
    {
        // Load the script on FormEdit and FormSpecial pages where the OOUI dialog appears
        $action = $out->getContext()->getRequest()->getVal("action");
        if ($action === "formedit" || $out->getTitle()->isSpecial("FormEdit")) {
            $out->addModules("ext.GiantBombModerationBridge.js");
        }
    }
}
