<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2021 Edward Chernenko.

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
 * Unit test of ModerationPageForms
 */

namespace MediaWiki\Moderation\Tests;

use ExtensionRegistry;
use MediaWiki\Moderation\ModerationPageForms;
use MediaWiki\Moderation\ModerationPreload;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use RequestContext;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class ModerationPageFormsTest extends ModerationUnitTestCase
{
    /**
     * Ensure that returnto= link after the edit points back to Special:FormEdit or action=formedit.
     * @param array $requestParams
     * @param string $expectedReturnTo
     * @param array $expectedReturnToQuery
     * @dataProvider dataProviderContinueEditingLinkHook
     * @covers MediaWiki\Moderation\ModerationPageForms
     */
    public function testContinueEditingLinkHook(
        array $requestParams,
        $expectedReturnTo,
        array $expectedReturnToQuery,
    ) {
        $this->skipIfNoPageForms();

        $context = new RequestContext();
        $context->setRequest(new FauxRequest($requestParams, true));

        $title = Title::newFromText("Doesn't matter");
        $context->setTitle($title);

        $returnto = "";
        $returntoquery = [];

        $preload = $this->createMock(ModerationPreload::class);
        '@phan-var ModerationPreload $preload';

        $plugin = new ModerationPageForms($preload);

        $hookResult = $plugin->onModerationContinueEditingLink(
            $returnto,
            $returntoquery,
            $title,
            $context,
        );
        $this->assertNotFalse(
            $hookResult,
            'Handler of ModerationContinueEditingLink hook shouldn\'t return false.',
        );

        $this->assertSame($expectedReturnTo, $returnto, "returnto");
        $this->assertSame(
            $expectedReturnToQuery,
            $returntoquery,
            "returntoquery",
        );
    }

    /**
     * Provide datasets for testContinueEditingLinkHook() runs.
     * @return array
     */
    public function dataProviderContinueEditingLinkHook()
    {
        return [
            "editing URL had action=formedit, so returntoquery should have it too" => [
                ["action" => "formedit"],
                "",
                ["action" => "formedit"],
            ],
            "editing URL pointed to Special:FormEdit, so returnto should point to it too" => [
                ["title" => "Special:FormEdit"],
                "Special:FormEdit",
                [],
            ],
            "editing URL points to neither action=formedit nor Special:FormEdit" => [
                ["title" => "Some article", "action" => "edit"],
                "",
                [],
            ],
            "some editing URL without even the title= parameter" => [
                ["unusual" => "parameters"],
                "",
                [],
            ],
        ];
    }

    /**
     * Ensure that preloadText() does nothing when visiting Special:FormEdit without target page.
     * @covers MediaWiki\Moderation\ModerationPageForms
     */
    public function testPreloadNoTargetTitle()
    {
        $this->skipIfNoPageForms();

        $text = $oldText = "Unmodified text";

        // Mock ModerationPreload service to ensure that its preload hook is NOT called.
        $preload = $this->createMock(ModerationPreload::class);
        $preload->expects($this->never())->method("onEditFormPreloadText");

        '@phan-var ModerationPreload $preload';

        $plugin = new ModerationPageForms($preload);
        $plugin->preloadText($text, null);

        $this->assertSame(
            $oldText,
            $text,
            "Text shouldn't be modified when targetTitle is null.",
        );
    }

    /**
     * Ensure that preloadText() provides preloaded text.
     * @covers MediaWiki\Moderation\ModerationPageForms
     */
    public function testPreload()
    {
        $this->skipIfNoPageForms();

        $targetTitle = Title::newFromText("UTPage-" . rand(0, 100000));
        $text = "Old text";

        // Mock ModerationPreload service to ensure that its preload hook is called.
        $preload = $this->createMock(ModerationPreload::class);
        $preload
            ->expects($this->once())
            ->method("onEditFormPreloadText")
            ->with($this->identicalTo($text), $this->identicalTo($targetTitle));

        '@phan-var ModerationPreload $preload';

        $plugin = new ModerationPageForms($preload);
        $plugin->preloadText($text, $targetTitle);
    }

    protected function skipIfNoPageForms()
    {
        if (!ExtensionRegistry::getInstance()->isLoaded("PageForms")) {
            $this->markTestSkipped(
                "Test skipped: PageForms extension must be installed to run it.",
            );
        }
    }
}
