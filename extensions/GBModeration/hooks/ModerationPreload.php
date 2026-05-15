<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2014-2021 Edward Chernenko.

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
 * Hooks/methods to preload edits which are pending moderation.
 */

namespace MediaWiki\Moderation;

use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Hook\AlternateEditHook;
use MediaWiki\Hook\EditFormInitialTextHook;
use MediaWiki\Hook\EditFormPreloadTextHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use RequestContext;
use User;
use WebRequest;
use Xml;

/*
	Calculating 'mod_preload_id':
	1) For anonymous user: ']' + hex string in the session.
	2) For registered user: '[' + username.

	Note: ']' and '[' are used because they aren't allowed in usernames.
*/

class ModerationPreload implements
    AlternateEditHook,
    EditFormInitialTextHook,
    EditFormPreloadTextHook,
    LocalUserCreatedHook
{
    /** @var EditPage|null Editor object passed from onAlternateEdit() to onEditFormPreloadText() */
    protected $editPage = null;

    /** @var User|null Current user. If not set, $wgUser will be used. */
    private $user = null;

    /** @var EntryFactory */
    protected $entryFactory;

    /** @var IConsequenceManager */
    protected $consequenceManager;

    /**
     * @param EntryFactory $entryFactory
     * @param IConsequenceManager $consequenceManager
     */
    public function __construct(
        EntryFactory $entryFactory,
        IConsequenceManager $consequenceManager,
    ) {
        $this->entryFactory = $entryFactory;
        $this->consequenceManager = $consequenceManager;
    }

    /**
     * Used in extension.json to obtain this service as HookHandler.
     * @return ModerationPreload
     */
    public static function hookHandlerFactory()
    {
        return MediaWikiServices::getInstance()->getService(
            "Moderation.Preload",
        );
    }

    /**
     * Get the request.
     * @return WebRequest
     */
    protected function getRequest()
    {
        return RequestContext::getMain()->getRequest();
    }

    /**
     * Get the user.
     * @return User
     */
    protected function getUser()
    {
        if ($this->user) {
            return $this->user;
        }

        return RequestContext::getMain()->getUser();
    }

    /**
     * Override the current user: preload for $user instead.
     * @param User $user
     */
    public function setUser(User $user)
    {
        $this->user = $user;
    }

    /**
     * Calculate value of mod_preload_id for the current user.
     * @param bool $create If true, new preload ID will be generated for first-time anonymous editors.
     * @return string|false Preload ID (string).
     * Returns false if current user is anonymous AND hasn't edited before AND $create is false.
     */
    public function getId($create = false)
    {
        $user = $this->getUser();
        if ($user->isRegistered()) {
            return "[" . $user->getName();
        }

        return $this->getAnonId($create);
    }

    /**
     * Calculate mod_preload_id for anonymous user.
     * @param bool $create If true, new preload ID will be generated for first-time anonymous editors.
     * @return string|false Preload ID (string), if already existed or just created.
     */
    protected function getAnonId($create)
    {
        $anonToken = $this->getRequest()->getSessionData("anon_id");
        if (!$anonToken) {
            if (!$create) {
                return false;
            }

            $anonToken = $this->consequenceManager->add(
                new RememberAnonIdConsequence(),
            );
        }

        return "]" . $anonToken;
    }

    /**
     * LocalUserCreated hook handler - called when user creates an account.
     * If the user did some anonymous edits before registering,
     * this hook makes them non-anonymous, so that they could be preloaded.
     * @param User $user
     * @param bool $autocreated @phan-unused-param
     * @return bool|void
     */
    public function onLocalUserCreated($user, $autocreated)
    {
        $this->setUser($user);

        $anonId = $this->getAnonId(false);
        if (!$anonId) {
            # This visitor never saved any edits
            return;
        }

        $this->consequenceManager->add(
            new GiveAnonChangesToNewUserConsequence(
                $user,
                $anonId,
                $this->getId(),
            ),
        );

        // Forget the fact that this user edited anonymously:
        // this user is now registered and no longer needs anonymous preload.
        $this->consequenceManager->add(new ForgetAnonIdConsequence());
    }

    /**
     * Check if there is a pending-moderation edit of this user to this page,
     * and if such edit exists, then load its text and edit comment.
     * @param Title $title
     * @return PendingEdit|false
     */
    public function findPendingEdit(Title $title)
    {
        $id = $this->getId();
        if (!$id) {
            # This visitor never saved any edits
            return false;
        }

        return $this->entryFactory->findPendingEdit($id, $title);
    }

    /**
     * If there is an edit (currently pending moderation) made by the
     * current user, inform EditPage object of its Text and Summary,
     * so that the user can continue editing its own revision.
     * @param string &$text @phan-output-reference
     * @param Title $title
     * @param EditPage|null $editPage
     * @return bool|void
     */
    protected function showPendingEdit(&$text, $title, $editPage)
    {
        $section = $this->getRequest()->getVal("section", "");
        if ($section == "new") {
            # Nothing to preload if new section is being created
            return;
        }

        $pendingEdit = $this->findPendingEdit($title);
        if (!$pendingEdit) {
            return;
        }

        $out = RequestContext::getMain()->getOutput();
        $out->addModules("ext.moderation.edit");
        $out->addHTML(
            Xml::tags(
                "div",
                ["id" => "mw-editing-your-version"],
                $out->msg("moderation-editing-your-version")->parse(),
            ),
        );

        $text = $pendingEdit->getSectionText($section);
        if ($editPage) {
            $editPage->summary = $pendingEdit->getComment();
        }
    }

    /**
     * AlternateEdit hook handler.
     * Remember EditPage object, which will then be used in onEditFormPreloadText.
     * @param EditPage $editPage
     * @return bool|void
     */
    public function onAlternateEdit($editPage)
    {
        $this->editPage = $editPage;
    }

    /**
     * EditFormPreloadText hook handler.
     * Preloads text/summary when the article doesn't exist yet.
     * @param string &$text
     * @param Title $title
     * @return bool|void
     */
    public function onEditFormPreloadText(&$text, $title)
    {
        $this->showPendingEdit($text, $title, $this->editPage);
    }

    /**
     * EditFormInitialText hook handler.
     * Preloads text/summary when the article already exists.
     * @param EditPage $editPage
     * @return bool|void
     */
    public function onEditFormInitialText($editPage)
    {
        $this->showPendingEdit(
            $editPage->textbox1,
            $editPage->getTitle(),
            $editPage,
        );
    }
}
