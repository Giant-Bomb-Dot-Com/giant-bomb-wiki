<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020 Edward Chernenko.

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
 * Consequence that creates a new LogEntry in Special:Log/moderation.
 */

namespace MediaWiki\Moderation;

use ManualLogEntry;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use User;

class AddLogEntryConsequence implements IConsequence
{
    /** @var string */
    protected $subtype;

    /** @var User */
    protected $user;

    /** @var Title */
    protected $title;

    /** @var array */
    protected $params;

    /** @var bool */
    protected $runApproveHook;

    /**
     * @param string $subtype
     * @param User $user
     * @param Title $title
     * @param array $params
     * @param bool $runApproveHook
     */
    public function __construct(
        $subtype,
        User $user,
        Title $title,
        array $params = [],
        $runApproveHook = false,
    ) {
        $this->subtype = $subtype;
        $this->user = $user;
        $this->title = $title;
        $this->params = $params;
        $this->runApproveHook = $runApproveHook;
    }

    /**
     * Execute the consequence.
     */
    public function run()
    {
        $logEntry = new ManualLogEntry("moderation", $this->subtype);
        $logEntry->setPerformer($this->user);
        $logEntry->setTarget($this->title);
        $logEntry->setParameters($this->params);

        $logid = $logEntry->insert();
        $logEntry->publish($logid);

        if ($this->runApproveHook) {
            $approveHook = MediaWikiServices::getInstance()->getService(
                "Moderation.ApproveHook",
            );
            $approveHook->checkLogEntry($logid, $logEntry);
        }
    }
}
