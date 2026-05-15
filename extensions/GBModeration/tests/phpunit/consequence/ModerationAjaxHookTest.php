<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2024 Edward Chernenko.

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
*/

/**
 * @file
 * Unit test of ModerationAjaxHook
 */

namespace MediaWiki\Moderation\Tests;

use ExtensionRegistry;
use MediaWiki\Moderation\ModerationAjaxHook;
use MediaWiki\Moderation\ModerationPreload;
use MediaWiki\Moderation\PendingEdit;
use MediaWiki\Title\Title;
use MobileContext;
use OutputPage;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class ModerationAjaxHookTest extends ModerationUnitTestCase
{
    /**
     * Verify that ModerationAjaxHook::add() adds the necessary modules to OutputPage.
     * Note: because return value of things like class_exists() can't really be mocked,
     * we can only test the current configuration. (depending on whether MobileFrontend
     * and VisualEditor are installed during the test)
     * @param array $opt
     * @dataProvider dataProviderAjaxHook
     * @covers MediaWiki\Moderation\ModerationAjaxHook
     */
    public function testAjaxHook(array $opt)
    {
        $installedExtensions = $opt["installedExtensions"] ?? [];

        $extensionRegistry = ExtensionRegistry::getInstance();
        foreach ($installedExtensions as $name => $shouldBeLoaded) {
            if ($shouldBeLoaded !== $extensionRegistry->isLoaded($name)) {
                $this->markTestSkipped(
                    "Test skipped: Extension:$name: shouldBeLoaded (" .
                        (int) $shouldBeLoaded .
                        ") != isLoaded",
                );
            }
        }

        $title = Title::newFromText("UTPage-" . rand(0, 100000));
        if ($opt["pageExists"] ?? false) {
            $title->resetArticleId(12345);
        }

        // Mock findPendingEdit() in the Moderation.Preload service.
        $preload = $this->createMock(ModerationPreload::class);
        $preload
            ->expects($this->any())
            ->method("findPendingEdit")
            ->with($this->identicalTo($title))
            ->willReturn(
                empty($opt["hasPendingEdit"])
                    ? false
                    : $this->createMock(PendingEdit::class),
            );
        $this->setService("Moderation.Preload", $preload);

        // Mock shouldDisplayMobileView() in the MobileContext (from Extension:MobileFrontend).
        if ($installedExtensions["MobileFrontend"] ?? false) {
            $mobileContext = $this->createMock(MobileContext::class);
            $mobileContext
                ->expects($this->any())
                ->method("shouldDisplayMobileView")
                ->willReturn(!empty($opt["isMobileView"]));
            $this->setService("MobileFrontend.Context", $mobileContext);
        }

        // Mock OutputPage to expect correct modules to be added.
        $out = $this->createMock(OutputPage::class);
        $out->expects($this->any())->method("getTitle")->willReturn($title);

        if (isset($opt["expectedModules"])) {
            $out->expects($this->once())
                ->method("addModules")
                ->with($this->identicalTo($opt["expectedModules"]));
        }

        if ($opt["expectFakeArticleId"] ?? false) {
            $out->expects($this->once())
                ->method("addJsConfigVars")
                ->with(
                    $this->identicalTo("wgArticleId"),
                    $this->identicalTo(-1),
                );
        } else {
            $out->expects($this->never())->method("addJsConfigVars");
        }

        '@phan-var OutputPage $out';

        ModerationAjaxHook::add($out);
    }

    /**
     * Provide datasets for testAjaxHook() runs.
     * @return array
     */
    public function dataProviderAjaxHook()
    {
        return [
            "only VE is installed" => [
                [
                    "installedExtensions" => [
                        "VisualEditor" => true,
                        "MobileFrontend" => false,
                    ],
                    "expectedModules" => [
                        "ext.moderation.ve",
                        "ext.moderation.ajaxhook",
                    ],
                ],
            ],
            "both VE and MF are installed, NOT in mobile view" => [
                [
                    "installedExtensions" => [
                        "VisualEditor" => true,
                        "MobileFrontend" => true,
                    ],
                    "isMobileView" => false,
                    "expectedModules" => [
                        "ext.moderation.ve",
                        "ext.moderation.ajaxhook",
                    ],
                ],
            ],
            "both VE and MF are installed, using Mobile view" => [
                [
                    "installedExtensions" => [
                        "VisualEditor" => true,
                        "MobileFrontend" => true,
                    ],
                    "isMobileView" => true,
                    "expectedModules" => [
                        "ext.moderation.mf.notify",
                        "ext.moderation.mf.preload33",
                        "ext.moderation.ve",
                        "ext.moderation.ajaxhook",
                    ],
                ],
            ],
            "only MF is installed, using Mobile view" => [
                [
                    "installedExtensions" => [
                        "VisualEditor" => false,
                        "MobileFrontend" => true,
                    ],
                    "isMobileView" => true,
                    "expectedModules" => [
                        "ext.moderation.mf.notify",
                        "ext.moderation.mf.preload33",
                        "ext.moderation.ajaxhook",
                    ],
                ],
            ],
            'MF is installed, page doesn\'t exist, no pending edit' => [
                [
                    "installedExtensions" => [
                        "MobileFrontend" => true,
                    ],
                    "isMobileView" => true,
                    "pageExists" => false,
                    "hasPendingEdit" => false,
                    "expectFakeArticleId" => false,
                ],
            ],
            "MF is installed, page exists, no pending edit" => [
                [
                    "installedExtensions" => [
                        "MobileFrontend" => true,
                    ],
                    "isMobileView" => true,
                    "pageExists" => true,
                    "hasPendingEdit" => false,
                    "expectFakeArticleId" => false,
                ],
            ],
            "MF is installed, page exists, have pending edit" => [
                [
                    "installedExtensions" => [
                        "MobileFrontend" => true,
                    ],
                    "isMobileView" => true,
                    "pageExists" => true,
                    "hasPendingEdit" => true,
                    "expectFakeArticleId" => false,
                ],
            ],
            'Need wgArticleId=-1: MF is installed, page doesn\'t exist, have pending edit' => [
                [
                    "installedExtensions" => [
                        "MobileFrontend" => true,
                    ],
                    "isMobileView" => true,
                    "pageExists" => false,
                    "hasPendingEdit" => true,
                    // Only in this case should wgArticleId be set to -1.
                    // Otherwise MobileFrontend won't preload the pending text.
                    "expectFakeArticleId" => true,
                ],
            ],
        ];
    }
}
