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
 * Consequence that sends an email "new edit was queued for moderation" to $wgModerationEmail.
 */

namespace MediaWiki\Moderation;

use DeferredUpdates;
use MailAddress;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use SpecialPage;
use User;

class SendNotificationEmailConsequence implements IConsequence
{
    /** @var Title */
    protected $title;

    /** @var User */
    protected $user;

    /** @var int */
    protected $modid;

    /**
     * @param Title $title
     * @param User $user
     * @param int $modid
     */
    public function __construct(Title $title, User $user, $modid)
    {
        $this->title = $title;
        $this->user = $user;
        $this->modid = $modid;
    }

    /**
     * Execute the consequence.
     */
    public function run()
    {
        /* Sending may be slow, defer it
         until the user receives HTTP response */
        DeferredUpdates::addCallableUpdate([$this, "sendNotificationEmailNow"]);
    }

    /**
     * Deliver the deferred letter from run().
     */
    public function sendNotificationEmailNow()
    {
        global $wgModerationEmail, $wgPasswordSender;

        $emailer = MediaWikiServices::getInstance()->getEmailer();
        $to = new MailAddress($wgModerationEmail);
        $from = new MailAddress($wgPasswordSender);
        $subject = wfMessage("moderation-notification-subject")
            ->inContentLanguage()
            ->text();
        $content = wfMessage(
            "moderation-notification-content",
            $this->title->getPrefixedText(),
            $this->user->getName(),
            SpecialPage::getTitleFor("Moderation")->getCanonicalURL([
                "modaction" => "show",
                "modid" => $this->modid,
            ]),
        )
            ->inContentLanguage()
            ->text();

        $emailer->send([$to], $from, $subject, $content);
    }
}
