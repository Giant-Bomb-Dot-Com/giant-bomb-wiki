<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2014-2023 Edward Chernenko.

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
 * Hooks related to file uploads.
 */

namespace MediaWiki\Moderation;

use MediaWiki\Hook\UploadVerifyUploadHook;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;
use MediaWiki\Title\Title;
use RequestContext;
use UploadBase;
use User;

class ModerationUploadHooks implements
    GetUserPermissionsErrorsHook,
    UploadVerifyUploadHook
{
    /** @var IConsequenceManager */
    protected $consequenceManager;

    /** @var ModerationCanSkip */
    protected $canSkip;

    /** @var EditFormOptions */
    protected $editFormOptions;

    /**
     * @param IConsequenceManager $consequenceManager
     * @param ModerationCanSkip $canSkip
     * @param EditFormOptions $editFormOptions
     */
    public function __construct(
        IConsequenceManager $consequenceManager,
        ModerationCanSkip $canSkip,
        EditFormOptions $editFormOptions,
    ) {
        $this->consequenceManager = $consequenceManager;
        $this->canSkip = $canSkip;
        $this->editFormOptions = $editFormOptions;
    }

    /**
     * Intercept image uploads and queue them for moderation.
     * @param UploadBase $upload
     * @param User $user
     * @param mixed $props @phan-unused-param
     * @param string $comment
     * @param string $pageText
     * @param array &$error
     * @return bool|void
     */
    public function onUploadVerifyUpload(
        $upload,
        $user,
        $props,
        $comment,
        $pageText,
        &$error,
    ) {
        if ($this->canSkip->canUploadSkip($user)) {
            return;
        }

        // FIXME: ModerationIntercept hook was previously called here (via doEditContent).
        // Now that doEditContent is no longer used, an upload-specific hook should be added here.
        // (calling ModerationIntercept here is impractical, as it should receive many parameters
        // that would be synthetic/irrelevant here).
        // Note: skipping moderation for uploads via ModerationIntercept hook didn't work even
        // before its invocation here was removed. It only worked for normal edits (non-uploads).

        return; //for GB we don't want to queue file uploads for moderation as tmp files, so just return here.

        $error = $this->consequenceManager->add(
            new QueueUploadConsequence($upload, $user, $comment, $pageText),
        );
        if ($error) {
            // Failed. Reason has been placed into &$error.
            return;
        }

        /* Watch/Unwatch this file immediately:
         watchlist is the user's own business, no reason to wait for approval of the upload */
        $this->editFormOptions->watchIfNeeded($user, [$upload->getTitle()]);

        /* Display user-friendly results page if the upload was caused
         by Special:Upload (not API, other extension, etc.) */
        $errorMsg = "moderation-image-queued";
        ModerationQueuedSuccessException::throwIfNeeded($errorMsg);

        // Return machine-readable error if this is NOT Special:Upload.
        $error = [$errorMsg];
    }

    /**
     * Prevent non-automoderated users from using action=revert.
     * @param Title $title @phan-unused-param
     * @param User $user
     * @param string $action @phan-unused-param
     * @param string &$result
     * @return bool|void
     */
    public function onGetUserPermissionsErrors($title, $user, $action, &$result)
    {
        /*
			action=revert bypasses doUpload(), so it is not intercepted
			and is applied without moderation.
			Therefore we don't allow it.
		*/
        $context = RequestContext::getMain();
        $exactAction = ModerationCompatTools::getActionName($context);

        if ($exactAction == "revert") {
            if (!$this->canSkip->canUploadSkip($user)) {
                $result = "moderation-revert-not-allowed";
                return false;
            }
        }
    }
}
