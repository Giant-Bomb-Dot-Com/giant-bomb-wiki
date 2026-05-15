<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2018-2021 Edward Chernenko.

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
 * Verifies that moderators see "New changes await moderation" notice.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Title\Title;

require_once __DIR__ . "/../framework/ModerationTestsuite.php";

/**
 * @group Database
 * @covers MediaWiki\Moderation\ModerationNotifyModerator
 */
class ModerationNotifyModeratorIntegrationTest extends ModerationTestCase
{
    /**
     * Ensure that moderator is notified about new pending changes.
     */
    public function testModeratorIsNotified(ModerationTestsuite $t)
    {
        $t->loginAs($t->unprivilegedUser);
        $t->doTestEdit();

        /* Notification "New changes await!" is shown to moderator on all pages... */
        $t->loginAs($t->moderator);
        $this->assertSame(
            "\n(moderation-new-changes-appeared)",
            $this->getNotice($t),
            "Notification not shown to moderator",
        );
    }

    /**
     * Ensure that notification is not shown to non-moderators.
     */
    public function testNonModeratorIsNotNotified(ModerationTestsuite $t)
    {
        $t->loginAs($t->unprivilegedUser);
        $t->doTestEdit();

        $t->loginAs($t->unprivilegedUser);
        $this->assertNull(
            $this->getNotice($t),
            "Notification shown to non-moderator",
        );
    }

    /**
     * Ensure that notification isn't shown on Special:Moderation itself.
     */
    public function testNoNotificationOnSpecialModeration(
        ModerationTestsuite $t,
    ) {
        $t->loginAs($t->unprivilegedUser);
        $t->doTestEdit();

        $t->loginAs($t->moderator);
        $t->fetchSpecial();

        $this->assertNull(
            $t->html->getNewMessagesNotice() /* Look on the current page, which is Special:Moderation */,
            "Notification shown when already on Special:Moderation",
        );
    }

    /**
     * Ensure that notification isn't shown to moderator who already was on Special:Moderation.
     */
    public function testNoNotificationAfterSpecialModeration(
        ModerationTestsuite $t,
    ) {
        $t->loginAs($t->unprivilegedUser);
        $t->doTestEdit();

        $t->loginAs($t->moderator);
        $t->fetchSpecial(); // Open Special:Moderation

        $this->assertNull(
            $this->getNotice($t),
            "Notification still shown after Special:Moderation has already been visited",
        );
    }

    /**
     * Ensure that visiting Special:Moderation doesn't hide the notice for other moderators.
     */
    public function testNotificationHiddenOnlyForThisModerator(
        ModerationTestsuite $t,
    ) {
        $t->loginAs($t->unprivilegedUser);
        $t->doTestEdit();

        $t->loginAs($t->moderator);
        $t->fetchSpecial(); // Moderator #1 opened Special:Moderation

        /* ... notification should still be shown to another moderator #2 */
        $t->loginAs($t->moderatorButNotAutomoderated);
        $this->assertSame(
            "\n(moderation-new-changes-appeared)",
            $this->getNotice($t),
            "Notification not shown to the second moderator",
        );
    }

    /**
     * Ensure that other moderators aren't notified if this new change has already been rejected.
     */
    public function testNoNotificationIfRejected(ModerationTestsuite $t)
    {
        $t->loginAs($t->unprivilegedUser);
        $t->doTestEdit();

        $t->loginAs($t->moderator);
        $t->fetchSpecial();
        $t->httpGet($t->new_entries[0]->rejectLink);

        $t->loginAs($t->moderatorButNotAutomoderated);
        $this->assertNull(
            $this->getNotice($t),
            "Notification still shown after all changes were rejected",
        );
    }

    /**
     * Ensure that other moderators aren't notified if this new change has already been approved.
     */
    public function testNoNotificationIfApproved(ModerationTestsuite $t)
    {
        $t->loginAs($t->unprivilegedUser);
        $t->doTestEdit();

        $t->loginAs($t->moderator);
        $t->fetchSpecial();
        $t->httpGet($t->new_entries[0]->approveLink);

        $t->loginAs($t->moderatorButNotAutomoderated);
        $this->assertNull(
            $this->getNotice($t),
            "Notification still shown after all changes were approved",
        );
    }

    /**
     * Ensure that moderator is NOT notified about new changes in the Spam folder.
     */
    public function testNoNotificationIfSpam(ModerationTestsuite $t)
    {
        $t->modblock($t->unprivilegedUser);

        $t->loginAs($t->unprivilegedUser);
        $t->doTestEdit(); // This edit is rejected automatically

        /* Notification "New changes await!" is not shown */
        $t->loginAs($t->moderator);
        $this->assertNull(
            $this->getNotice($t),
            "Notification was shown for change in Spam folder",
        );
    }

    /**
     * Find NewMessages notice in HTML of some randomly chosen page.
     * @param ModerationTestsuite $t
     * @return string|null
     */
    protected function getNotice(ModerationTestsuite $t)
    {
        $randomPageUrl = Title::newFromText("Can_Be_Any_Page")->getFullURL();
        return $t->html->loadUrl($randomPageUrl)->getNewMessagesNotice();
    }
}
