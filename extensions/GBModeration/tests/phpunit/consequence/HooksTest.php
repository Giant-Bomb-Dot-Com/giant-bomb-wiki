<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2025 Edward Chernenko.

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
 * Unit test of various hooks like onwgQueryPages that are not covered by other tests.
 */

namespace MediaWiki\Moderation\Tests;

use ApiMain;
use ApiUsageException;
use Article;
use ChangeTags;
use MediaWiki\ChangeTags\ChangeTags as ChangeTags44;
use MediaWiki\EditPage\EditPage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Moderation\EditFormOptions;
use MediaWiki\Moderation\ModerationCanSkip;
use MediaWiki\Moderation\SpecialModeration;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use QueryPage;
use RequestContext;
use Status;
use Wikimedia\TestingAccessWrapper;

if (!class_exists("ChangeTags")) {
    // MediaWiki 1.44+
    // @phan-suppress-next-line PhanUndeclaredClassAliasOriginal, PhanUndeclaredClassReference
    class_alias(ChangeTags44::class, "ChangeTags");
}

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class HooksTest extends ModerationUnitTestCase
{
    /**
     * @covers MediaWiki\Moderation\ModerationApiHooks::onwgQueryPages
     */
    public function testQueryPageListed()
    {
        $this->assertContains(
            [SpecialModeration::class, "Moderation"],
            QueryPage::getPages(),
            "Special:Moderation isn't listed in the list of query pages.",
        );
    }

    /**
     * @covers MediaWiki\Moderation\ModerationEditHooks::onListDefinedTags
     */
    public function testDefinedTagListed()
    {
        $definedTags = $this->getServiceContainer()
            ->getChangeTagsStore()
            ->listDefinedTags();
        $this->assertContains(
            "moderation-merged",
            $definedTags,
            "Tag 'moderation-merged' isn't listed in the list of defined change tags.",
        );
        $this->assertContains(
            "moderation-spam",
            $definedTags,
            "Tag 'moderation-spam' isn't listed in the list of defined change tags.",
        );
    }

    /**
     * @covers MediaWiki\Moderation\ModerationEditHooks::onChangeTagsAllowedAdd
     */
    public function testCanAddSpamTag()
    {
        $this->assertTrue(
            ChangeTags::canAddTagsAccompanyingChange([
                "moderation-spam",
            ])->isOK(),
            "Tag 'moderation-spam' wasn't allowed by canAddTagsAccompanyingChange().",
        );
    }

    /**
     * Ensure that action=revert is not allowed to non-automoderated users.
     * @see testRevertImageRestrictedViaApi - same, but via API.
     * @param bool $isAutomoderated
     * @param string $mwActionName Name of MediaWiki action (NOT modaction!), e.g. "revert".
     * @dataProvider dataProviderRevertImageRestrictedViaUI
     * @covers MediaWiki\Moderation\ModerationUploadHooks::ongetUserPermissionsErrors
     */
    public function testRevertImageRestrictedViaUI(
        $isAutomoderated,
        $mwActionName,
    ) {
        $title = Title::newFromText("File:Something.png");
        $user = self::getTestUser()->getUser();

        // Mock the result of canUploadSkip()
        $canSkip = $this->createMock(ModerationCanSkip::class);
        $canSkip
            ->expects(
                $mwActionName == "revert" ? $this->once() : $this->never(),
            )
            ->method("canUploadSkip")
            ->with($this->identicalTo($user))
            ->willReturn($isAutomoderated);
        $this->setService("Moderation.CanSkip", $canSkip);

        $globalContext = RequestContext::getMain();
        $globalContext->getRequest()->setVal("action", $mwActionName);
        $globalContext->setTitle($title);

        $permManager = MediaWikiServices::getInstance()->getPermissionManager();
        $permissionErrors = $permManager->getPermissionErrors(
            "upload",
            $user,
            $title,
        );

        if (!$isAutomoderated && $mwActionName == "revert") {
            $this->assertSame(
                [["moderation-revert-not-allowed"]],
                $permissionErrors,
                "User who can't bypass moderation of uploads was allowed to use action=revert.",
            );
        } else {
            $this->assertEmpty(
                $permissionErrors,
                "Using action=$mwActionName wasn't allowed when it shouldn't have been restricted.",
            );
        }
    }

    /**
     * Provide datasets for testRevertImageRestrictedViaUI() runs.
     * @return array
     */
    public function dataProviderRevertImageRestrictedViaUI()
    {
        return [
            "action=revert, automoderated (should be allowed)" => [
                true,
                "revert",
            ],
            "action=revert, NOT automoderated (should be disallowed)" => [
                false,
                "revert",
            ],
            "action=view, automoderated (should be allowed)" => [true, "view"],
            "action=view, NOT automoderated (should be allowed)" => [
                false,
                "view",
            ],
        ];
    }

    /**
     * Ensure that api.php?action=filerevert is not allowed to non-automoderated users.
     * @see testRevertImageRestrictedViaUI - same, but via UI
     * @see testRotateImageRestricted
     * @param bool $isAutomoderated
     * @dataProvider dataProviderIsAutomoderated
     * @covers MediaWiki\Moderation\ModerationApiHooks::onApiCheckCanExecute
     */
    public function testRevertImageRestrictedViaApi($isAutomoderated)
    {
        $context = new RequestContext();
        $context->setRequest(
            new FauxRequest([
                "action" => "filerevert",
                "filename" => "irrelevant",
                "archivename" => "irrelevant",
                "token" => $context->getUser()->getEditToken(),
            ]),
        );

        // Mock the result of canUploadSkip()
        $canSkip = $this->createMock(ModerationCanSkip::class);
        $canSkip
            ->expects($this->once())
            ->method("canUploadSkip")
            ->with($this->identicalTo($context->getUser()))
            ->willReturn($isAutomoderated);
        $this->setService("Moderation.CanSkip", $canSkip);

        $apiMain = new ApiMain($context, true);

        $wrapper = TestingAccessWrapper::newFromObject($apiMain);
        $wrapper->setupExecuteAction();

        $status = Status::newGood();
        try {
            $wrapper->checkExecutePermissions($wrapper->setupModule());
        } catch (ApiUsageException $e) {
            $status = Status::wrap($e->getStatusValue());
        }

        if ($isAutomoderated) {
            $this->assertTrue(
                $status->isOK(),
                "Using action=filerevert wasn't allowed when it shouldn't have been restricted.",
            );
        } else {
            $this->assertFalse(
                $status->isOK(),
                "Non-automoderated user wasn't disallowed to revert image to previous revision.",
            );
            $this->assertSame(
                "moderation-revert-not-allowed",
                $status->getMessage()->getKey(),
            );
        }
    }

    /**
     * Ensure that api.php?action=imagerotate is not allowed to non-automoderated users.
     * @see testRevertImageRestrictedViaApi
     * @param bool $isAutomoderated
     * @dataProvider dataProviderIsAutomoderated
     * @covers MediaWiki\Moderation\ModerationApiHooks::onApiCheckCanExecute
     */
    public function testRotateImageRestricted($isAutomoderated)
    {
        $context = new RequestContext();
        $context->setRequest(
            new FauxRequest([
                "action" => "imagerotate",
                "rotation" => "90",
                "token" => $context->getUser()->getEditToken(),
            ]),
        );

        // Mock the result of canUploadSkip()
        $canSkip = $this->createMock(ModerationCanSkip::class);
        $canSkip
            ->expects($this->once())
            ->method("canUploadSkip")
            ->with($this->identicalTo($context->getUser()))
            ->willReturn($isAutomoderated);
        $this->setService("Moderation.CanSkip", $canSkip);

        $apiMain = new ApiMain($context, true);

        $wrapper = TestingAccessWrapper::newFromObject($apiMain);
        $wrapper->setupExecuteAction();

        $status = Status::newGood();
        try {
            $wrapper->checkExecutePermissions($wrapper->setupModule());
        } catch (ApiUsageException $e) {
            $status = Status::wrap($e->getStatusValue());
        }

        if ($isAutomoderated) {
            $this->assertTrue(
                $status->isOK(),
                "Using action=imagerotate wasn't allowed when it shouldn't have been restricted.",
            );
        } else {
            $this->assertFalse(
                $status->isOK(),
                "Non-automoderated user wasn't disallowed to rotate image.",
            );
            $this->assertSame(
                "moderation-imagerotate-not-allowed",
                $status->getMessage()->getKey(),
            );
        }
    }

    /**
     * Provide datasets for testRevertImageRestrictedViaApi() and testRotateImageRestricted() runs.
     * @return array
     */
    public function dataProviderIsAutomoderated()
    {
        return [
            "automoderated (should be allowed)" => [true],
            "NOT automoderated (should be disallowed)" => [false],
        ];
    }

    /**
     * Ensure that API actions other than filerevert/imagerotate are not restricted to automoderated users.
     * @see testRevertImageRestrictedViaApi
     * @see testRotateImageRestricted
     * @covers MediaWiki\Moderation\ModerationApiHooks::onApiCheckCanExecute
     */
    public function testActionsOtherThanRevertOrRotateAreNotRestrictedViaApi()
    {
        $context = new RequestContext();
        $context->setRequest(new FauxRequest(["action" => "query"]));

        // Make request on behalf of non-automoderated user.
        $canSkip = $this->createMock(ModerationCanSkip::class);
        $canSkip
            ->expects($this->once())
            ->method("canUploadSkip")
            ->willReturn(false);
        $this->setService("Moderation.CanSkip", $canSkip);

        $apiMain = new ApiMain($context, true);

        $status = Status::newGood();
        try {
            $apiMain->execute();
        } catch (ApiUsageException $e) {
            $status = Status::wrap($e->getStatusValue());
        }

        $this->assertTrue(
            $status->isOK(),
            "Using action=query wasn't allowed when it shouldn't have been restricted: " .
                $status->getMessage()->getKey(),
        );
    }

    /**
     * Ensure that EditPage::showEditForm:fields hook adds merge-related fields to the EditPage form.
     * @param int $mergeID
     * @dataProvider dataProviderMergeFieldsInEditForm
     * @covers MediaWiki\Moderation\ModerationEditHooks::onEditPage__showEditForm_fields
     */
    public function testMergeFieldsInEditForm($mergeID)
    {
        // Mock the EditFormOptions service.
        $editFormOptions = $this->createMock(EditFormOptions::class);
        $editFormOptions
            ->expects($this->once())
            ->method("getMergeID")
            ->willReturn($mergeID);
        $this->setService("Moderation.EditFormOptions", $editFormOptions);

        // Show the EditPage form.
        $title = Title::newFromText("UTPage-" . rand(0, 100000));
        $context = new RequestContext();
        $context->setTitle($title);

        $editPage = new EditPage(Article::newFromTitle($title, $context));
        $editPage->setContextTitle($title);
        $editPage->showEditForm();

        $html = new ModerationTestHTML();
        $html->loadString($editPage->getContext()->getOutput()->getHTML());

        $mergeInput = $html->getElementByXPath('//input[@name="wpMergeID"]');
        $blankSummaryInput = $html->getElementByXPath(
            '//input[@name="wpIgnoreBlankSummary"]',
        );

        if ($mergeID) {
            $this->assertNotNull($mergeInput, "wpMergeID field not found.");
            $this->assertSame(
                (string) $mergeID,
                $mergeInput->getAttribute("value"),
                "wpMergeID.value",
            );

            $this->assertNotNull(
                $blankSummaryInput,
                "wpIgnoreBlankSummary field not found.",
            );
            $this->assertSame(
                "1",
                $blankSummaryInput->getAttribute("value"),
                "wpIgnoreBlankSummary.value",
            );
        } else {
            $this->assertNull(
                $mergeInput,
                "wpMergeID field found where it is not needed.",
            );
            $this->assertNull(
                $blankSummaryInput,
                "wpIgnoreBlankSummary field found where it is not needed.",
            );
        }
    }

    /**
     * Provide datasets for testMergeFieldsInEditForm() runs.
     * @return array
     */
    public function dataProviderMergeFieldsInEditForm()
    {
        return [
            "getMergeID() returned 12345" => [12345],
            "Not in the process of merging: getMergeID() returned 0" => [0],
        ];
    }

    /**
     * Ensure that BeforePageDisplay hook adds CSS/JS modules of postedit notifications, etc.
     * @param bool $isAutomoderated
     * @dataProvider dataProviderBeforePageDisplayHook
     * @covers MediaWiki\Moderation\ModerationEditHooks::onBeforePageDisplay
     */
    public function testBeforePageDisplayHook($isAutomoderated)
    {
        $title = Title::newFromText("Project:UTPage-" . rand(0, 100000));
        $user = self::getTestUser()->getUser();

        // Mock the result of canEditSkip()
        $canSkip = $this->createMock(ModerationCanSkip::class);
        $canSkip
            ->expects($this->once())
            ->method("canEditSkip")
            ->with(
                $this->identicalTo($user),
                $this->identicalTo($title->getNamespace()),
            )
            ->willReturn($isAutomoderated);
        $this->setService("Moderation.CanSkip", $canSkip);

        $context = new RequestContext();
        $context->setUser($user);
        $context->setTitle($title);

        $out = $context->getOutput();

        // Call OutputPage::output(), which is what triggers BeforePageDisplay hook.
        ob_start();
        $out->output();
        ob_end_clean();

        // Check if ResourceLoader modules were added to $out, but only for non-automoderated users.
        $modules = $out->getModules();
        if ($isAutomoderated) {
            $this->assertNotContains(
                "ext.moderation.notify",
                $modules,
                "getModules()",
            );
            $this->assertNotContains(
                "ext.moderation.notify.desktop",
                $modules,
                "getModules()",
            );
        } else {
            $this->assertContains(
                "ext.moderation.notify",
                $modules,
                "getModules()",
            );
            $this->assertContains(
                "ext.moderation.notify.desktop",
                $modules,
                "getModules()",
            );
        }

        // TODO: double-check that ModerationAjaxHook::add() was called.
        // Should it be made into a service just to simplify the test?
    }

    /**
     * Provide datasets for testBeforePageDisplayHook() runs.
     * @return array
     */
    public function dataProviderBeforePageDisplayHook()
    {
        return [
            "is automoderated" => [true],
            "is NOT automoderated" => [false],
        ];
    }
}
