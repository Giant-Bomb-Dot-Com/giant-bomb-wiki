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
 * Unit test of SendNotificationEmailConsequence.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Mail\IEmailer;
use MediaWiki\Moderation\SendNotificationEmailConsequence;
use MediaWiki\Title\Title;
use SpecialPage;
use User;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class SendNotificationEmailConsequenceTest extends ModerationUnitTestCase
{
    /**
     * Verify that SendNotificationEmailConsequence sends an outgoing email.
     * @covers MediaWiki\Moderation\SendNotificationEmailConsequence
     */
    public function testSendNotificationEmailConsequence()
    {
        $title = Title::newFromText("Project:Some page");
        $user = User::newFromName("10.11.12.13", false);
        $modid = 12345;
        $expectedRecipient = "some.recipient@localhost";
        $expectedSender = "some.sender@localhost";

        $expectedContent =
            "(moderation-notification-content: " .
            $title->getFullText() .
            ", " .
            $user->getName() .
            ", " .
            SpecialPage::getTitleFor("Moderation")->getCanonicalURL([
                "modaction" => "show",
                "modid" => $modid,
            ]) .
            ")";

        $this->setMwGlobals("wgModerationEmail", $expectedRecipient);
        $this->setMwGlobals("wgPasswordSender", $expectedSender);
        $this->overrideConfigValue("LanguageCode", "qqx");

        $emailer = $this->createMock(IEmailer::class);
        $emailer
            ->expects($this->once())
            ->method("send")
            ->with(
                $this->equalTo([$expectedRecipient]),
                $this->equalTo($expectedSender),
                $this->identicalTo("(moderation-notification-subject)"),
                $this->identicalTo($expectedContent),
            );
        $this->setService("Emailer", $emailer);

        // Create and run the Consequence.
        $consequence = new SendNotificationEmailConsequence(
            $title,
            $user,
            $modid,
        );
        $consequence->run();
    }
}
