<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2022 Edward Chernenko.

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
 * Unit test of ActionFactory.
 */

namespace MediaWiki\Moderation\Tests;

use DerivativeContext;
use Language;
use MediaWiki\Moderation\ActionFactory;
use MediaWiki\Moderation\ActionLinkRenderer;
use MediaWiki\Moderation\EditFormOptions;
use MediaWiki\Moderation\EntryFactory;
use MediaWiki\Moderation\ModerationActionApprove;
use MediaWiki\Moderation\ModerationActionBlock;
use MediaWiki\Moderation\ModerationActionEditChange;
use MediaWiki\Moderation\ModerationActionEditChangeSubmit;
use MediaWiki\Moderation\ModerationActionMerge;
use MediaWiki\Moderation\ModerationActionPreview;
use MediaWiki\Moderation\ModerationActionReject;
use MediaWiki\Moderation\ModerationActionShow;
use MediaWiki\Moderation\ModerationActionShowImage;
use MediaWiki\Moderation\ModerationCanSkip;
use MediaWiki\Moderation\ModerationError;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\RevisionRenderer;
use ReadOnlyMode;
use RepoGroup;
use RequestContext;
use User;
use Wikimedia\TestingAccessWrapper;

require_once __DIR__ . "/autoload.php";

class ActionFactoryTest extends ModerationUnitTestCase
{
    /**
     * Test ActionFactory::makeAction() on all known actions,
     * as well as requiresWrite() and requiresEditToken() of these actions.
     * @param string $modaction
     * @param string $expectedClass
     * @param bool $requiresWrite
     * @param bool $requiresEditToken
     * @dataProvider dataProviderFactory
     *
     * @phan-param class-string $expectedClass
     *
     * @covers MediaWiki\Moderation\ActionFactory
     * @covers MediaWiki\Moderation\ModerationAction
     * @covers MediaWiki\Moderation\ModerationActionEditChange::requiresEditToken
     * @covers MediaWiki\Moderation\ModerationActionPreview::requiresEditToken
     * @covers MediaWiki\Moderation\ModerationActionPreview::requiresWrite
     * @covers MediaWiki\Moderation\ModerationActionShowImage::requiresEditToken
     * @covers MediaWiki\Moderation\ModerationActionShowImage::requiresWrite
     * @covers MediaWiki\Moderation\ModerationActionShow::requiresEditToken
     * @covers MediaWiki\Moderation\ModerationActionShow::requiresWrite
     */
    public function testFactory(
        $modaction,
        $expectedClass,
        $requiresWrite,
        $requiresEditToken,
    ) {
        $user = User::newFromName("10.11.12.13", false);
        $modid = 12345;

        $context = new DerivativeContext(RequestContext::getMain());
        $context->setRequest(
            new FauxRequest([
                "modaction" => $modaction,
                "modid" => $modid,
            ]),
        );
        $context->setUser($user);

        $actionFactory = $this->makeFactory();
        $action = $actionFactory->makeAction($context);

        $this->assertInstanceof($expectedClass, $action);

        $this->assertSame(
            $requiresWrite,
            $action->requiresWrite(),
            "Incorrect return value of requiresWrite()",
        );
        $this->assertSame(
            $requiresEditToken,
            $action->requiresEditToken(),
            "Incorrect return value of requiresEditToken()",
        );

        $this->assertSame(
            $modaction,
            $action->actionName,
            'Incorrect value of $action->actionName',
        );
        $this->assertSame(
            $user,
            $action->moderator,
            'Incorrect return value of $action->moderator',
        );

        $actionWrapper = TestingAccessWrapper::newFromObject($action);
        $this->assertSame(
            $modid,
            $actionWrapper->id,
            "Incorrect return value of requiresEditToken()",
        );
    }

    /**
     * Test ActionFactory::makeAction() on unknown action.
     * @covers MediaWiki\Moderation\ActionFactory
     */
    public function testFactoryUnknownAction()
    {
        $context = new DerivativeContext(RequestContext::getMain());
        $context->setRequest(new FauxRequest(["modaction" => "makesandwich"]));

        $this->expectExceptionObject(
            new ModerationError("moderation-unknown-modaction"),
        );

        $actionFactory = $this->makeFactory();
        $actionFactory->makeAction($context);
    }

    /**
     * Provide datasets for testFactory() runs.
     * @return array
     */
    public function dataProviderFactory()
    {
        return [
            "approveall" => [
                "approveall",
                ModerationActionApprove::class,
                true,
                true,
            ],
            "approve" => [
                "approve",
                ModerationActionApprove::class,
                true,
                true,
            ],
            "block" => ["block", ModerationActionBlock::class, true, true],
            "editchange" => [
                "editchange",
                ModerationActionEditChange::class,
                true,
                false,
            ],
            "editchangesubmit" => [
                "editchangesubmit",
                ModerationActionEditChangeSubmit::class,
                true,
                true,
            ],
            "merge" => ["merge", ModerationActionMerge::class, true, true],
            "preview" => [
                "preview",
                ModerationActionPreview::class,
                false,
                false,
            ],
            "rejectall" => [
                "rejectall",
                ModerationActionReject::class,
                true,
                true,
            ],
            "reject" => ["reject", ModerationActionReject::class, true, true],
            "show" => ["show", ModerationActionShow::class, false, false],
            "showimg" => [
                "showimg",
                ModerationActionShowImage::class,
                false,
                false,
            ],
            "unblock" => ["unblock", ModerationActionBlock::class, true, true],
        ];
    }

    /**
     * Make a clean ActionFactory object with mocked parameters.
     * @return ActionFactory
     * @suppress PhanTypeMismatchArgument
     */
    private function makeFactory()
    {
        return new ActionFactory(
            $this->createMock(EntryFactory::class),
            new MockConsequenceManager(),
            $this->createMock(ModerationCanSkip::class),
            $this->createMock(EditFormOptions::class),
            $this->createMock(ActionLinkRenderer::class),
            $this->createMock(RepoGroup::class),
            $this->createMock(Language::class),
            $this->createMock(RevisionRenderer::class),
            $this->createMock(ReadOnlyMode::class),
        );
    }
}
