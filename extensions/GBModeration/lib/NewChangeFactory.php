<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2021 Edward Chernenko.

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
 * Factory that can construct ModerationNewChange objects.
 */

namespace MediaWiki\Moderation;

use Language;
use MediaWiki\Moderation\Hook\HookRunner;
use MediaWiki\Title\Title;
use User;

class NewChangeFactory
{
    /** @var IConsequenceManager */
    protected $consequenceManager;

    /** @var ModerationPreload */
    protected $preload;

    /** @var HookRunner */
    protected $hookRunner;

    /** @var ModerationNotifyModerator */
    protected $notifyModerator;

    /** @var Language */
    protected $contentLanguage;

    /**
     * @param IConsequenceManager $consequenceManager
     * @param ModerationPreload $preload
     * @param HookRunner $hookRunner
     * @param ModerationNotifyModerator $notifyModerator
     * @param Language $contentLanguage
     */
    public function __construct(
        IConsequenceManager $consequenceManager,
        ModerationPreload $preload,
        HookRunner $hookRunner,
        ModerationNotifyModerator $notifyModerator,
        Language $contentLanguage,
    ) {
        $this->consequenceManager = $consequenceManager;
        $this->preload = $preload;
        $this->hookRunner = $hookRunner;
        $this->notifyModerator = $notifyModerator;
        $this->contentLanguage = $contentLanguage;
    }

    /**
     * Construct new ModerationNewChange.
     * @param Title $title
     * @param User $user
     * @return ModerationNewChange
     */
    public function makeNewChange(Title $title, User $user)
    {
        return new ModerationNewChange(
            $title,
            $user,
            $this->consequenceManager,
            $this->preload,
            $this->hookRunner,
            $this->notifyModerator,
            new ModerationBlockCheck(),
            $this->contentLanguage,
        );
    }
}
