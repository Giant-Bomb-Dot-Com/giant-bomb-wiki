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
 * Verifies that renaming a page has consequences.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\MediaWikiServices;
use MediaWiki\Moderation\ModerationCanSkip;
use MediaWiki\Moderation\QueueMoveConsequence;
use MediaWiki\Title\Title;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class MovesHaveConsequencesTest extends ModerationUnitTestCase
{
    use MakeEditTestTrait;

    /** @var Title */
    protected $title;

    /** @var string */
    protected $reason;

    /**
     * Test consequences when a move is queued for moderation.
     * @covers MediaWiki\Moderation\ModerationMoveHooks::onTitleMove
     */
    public function testMove()
    {
        $this->precreatePage();

        $user = self::getTestUser()->getUser();
        $newTitle = Title::newFromText("UTPage-new-" . rand(0, 100000));
        $reason = "Some reason for renaming the page";

        $manager = $this->mockConsequenceManager();

        // Mock the result of canMoveSkip()
        $canSkip = $this->createMock(ModerationCanSkip::class);
        $canSkip
            ->expects($this->once())
            ->method("canMoveSkip")
            ->with(
                $user,
                $this->title->getNamespace(),
                $newTitle->getNamespace(),
            )
            ->willReturn(false); // Can't bypass moderation
        $this->setService("Moderation.CanSkip", $canSkip);

        $mp = MediaWikiServices::getInstance()
            ->getMovePageFactory()
            ->newMovePage($this->title, $newTitle);
        $status = $mp->move($user, $reason, true);

        $this->assertTrue(
            $status->hasMessage("moderation-move-queued"),
            "Status returned by MovePage doesn't include \"moderation-move-queued\" message.",
        );

        $this->assertConsequencesEqual(
            [new QueueMoveConsequence($this->title, $newTitle, $user, $reason)],
            $manager->getConsequences(),
        );
    }

    /**
     * Test consequences of move when User is automoderated (can bypass moderation of moves).
     * @covers MediaWiki\Moderation\ModerationMoveHooks::onTitleMove
     */
    public function testAutomoderatedMove()
    {
        $this->precreatePage();

        $user = self::getTestUser()->getUser();
        $newTitle = Title::newFromText("UTPage-new-" . rand(0, 100000));
        $reason = "Some reason for renaming the page";

        $manager = $this->mockConsequenceManager();

        // Mock the result of canMoveSkip()
        $canSkip = $this->createMock(ModerationCanSkip::class);
        $canSkip
            ->expects($this->once())
            ->method("canMoveSkip")
            ->with(
                $user,
                $this->title->getNamespace(),
                $newTitle->getNamespace(),
            )
            ->willReturn(true); // Can bypass moderation when moving the page

        // Can bypass moderation when creating a redirect page
        $canSkip
            ->expects($this->any())
            ->method("canEditSkip")
            ->with($user, $this->title->getNamespace())
            ->willReturn(true);

        $this->setService("Moderation.CanSkip", $canSkip);

        $mp = MediaWikiServices::getInstance()
            ->getMovePageFactory()
            ->newMovePage($this->title, $newTitle);
        $status = $mp->move($user, $reason, true);

        $this->assertTrue(
            $status->isGood(),
            "User can bypass moderation, but move() still failed: " .
                $status->getMessage()->plain(),
        );

        // The moderation was skipped, so should be no consequences.
        $this->assertNoConsequences($manager);
    }

    /**
     * Create a page as automoderated user. (this edit will bypass moderation)
     */
    private function precreatePage()
    {
        $this->title = Title::newFromText("UTPage-" . rand(0, 100000));
        $this->makeEdit(
            $this->title,
            self::getTestUser(["automoderated"])->getUser(),
            "Some text",
        );
    }
}
