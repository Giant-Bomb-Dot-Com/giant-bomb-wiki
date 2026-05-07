<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2014-2025 Edward Chernenko.

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
 * Handles exceptions thrown by actions on Special:Moderation.
 */

namespace MediaWiki\Moderation;

use ErrorPageError;
use Message;
use Status;
use Xml;

class ModerationError extends ErrorPageError
{
    /** @var Status Error details */
    public $status;

    /**
     * @inheritDoc
     */
    public function __construct($message)
    {
        if ($message instanceof Status) {
            $this->status = $message;
            $message = $this->status->getMessage();
        } else {
            $this->status = Status::newFatal($message);
        }

        parent::__construct("moderation", $message);
    }

    /**
     * Completely override report() from ErrorPageError in order to wrap the message
     * in <div id='mw-mod-error'></div>
     * @param mixed $action @phan-unused-param
     */
    public function report($action = 0)
    {
        global $wgOut;

        $msg =
            $this->msg instanceof Message
                ? $this->msg
                : $wgOut->msg($this->msg);

        $wgOut->prepareErrorPage();
        $wgOut->setPageTitleMsg($msg);

        $wgOut->addHTML(
            Xml::tags(
                "div",
                [
                    "id" => "mw-mod-error",
                    "class" => "error",
                ],
                $msg->parse(),
            ),
        );
        $wgOut->addReturnTo($wgOut->getTitle());
        $wgOut->output();

        $wgOut->disable();
    }
}
