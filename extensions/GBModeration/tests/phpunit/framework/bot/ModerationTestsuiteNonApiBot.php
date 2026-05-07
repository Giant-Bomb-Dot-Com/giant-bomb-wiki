<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2018-2024 Edward Chernenko.

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
 * Performs editing methods of the testsuite (edit/upload/move) NOT via API.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\EditPage\EditPage;
use MediaWiki\Title\Title;
use MWException;

class ModerationTestsuiteNonApiBot extends ModerationTestsuiteBot
{
    /**
     * Make an edit via the usual interface, as real users do.
     * @param ModerationTestsuite $t
     * @param string $title
     * @param string $text
     * @param string $summary
     * @param string|int $section
     * @param array $extraParams
     * @return ModerationTestsuiteNonApiBotResponse
     */
    public function doEdit(
        ModerationTestsuite $t,
        $title,
        $text,
        $summary,
        $section,
        array $extraParams,
    ) {
        if ($section !== "") {
            $extraParams["wpSection"] = $section;
        }

        $params = $extraParams + [
            "action" => "submit",
            "title" => $title,
            "wpTextbox1" => $text,
            "wpSummary" => $summary,
            "wpEditToken" => $t->getEditToken(),
            "wpSave" => "Save",
            "wpIgnoreBlankSummary" => "",
            "wpRecreate" => "",
            "wpUnicodeCheck" => EditPage::UNICODE_CHECK,
            "wpUltimateParam" => 1,
        ];

        /* Determine wpEdittime (timestamp of the current revision of $title),
         otherwise edit conflict will occur. */
        $rev = $t->getLastRevision($title);
        $params["wpEdittime"] = $rev
            ? wfTimestamp(TS_MW, $rev["timestamp"])
            : "";

        $req = $t->httpPost(wfScript("index"), $params);
        $location = $req->getResponseHeader("Location");

        return ModerationTestsuiteNonApiBotResponse::factory(
            $req,
            $location && strpos($location, "modqueued=1") !== false,
            $location && strpos($location, "modqueued=1") === false,
            !$location ? "unknown error" : null,
        );
    }

    /**
     * Make a move via the usual interface, as real users do.
     * @param ModerationTestsuite $t
     * @param string $oldTitle
     * @param string $newTitle
     * @param string $reason
     * @param array $extraParams
     * @return ModerationTestsuiteNonApiBotResponse
     */
    public function doMove(
        ModerationTestsuite $t,
        $oldTitle,
        $newTitle,
        $reason,
        array $extraParams,
    ) {
        $newTitleObj = Title::newFromText($newTitle);
        $req = $t->httpPost(
            wfScript("index"),
            $extraParams + [
                "action" => "submit",
                "title" => "Special:MovePage",
                "wpOldTitle" => $oldTitle,
                "wpNewTitleMain" => $newTitleObj->getText(),
                "wpNewTitleNs" => $newTitleObj->getNamespace(),
                "wpMove" => "Move",
                "wpEditToken" => $t->getEditToken(),
                "wpReason" => $reason,
            ],
        );

        $submitResult = $this->getSubmitResult(
            $req,
            "moderation-move-queued",
            $failedReason,
        );
        return ModerationTestsuiteNonApiBotResponse::factory(
            $req,
            $submitResult == "intercepted",
            $submitResult == "bypassed",
            $failedReason,
        );
    }

    /**
     * Perform an upload via the usual interface, as real users do.
     * @param ModerationTestsuite $t
     * @param string $title
     * @param string $srcPath
     * @param string $text
     * @param array $extraParams Bot-specific parameters.
     * @return ModerationTestsuiteNonApiBotResponse
     */
    public function doUpload(
        ModerationTestsuite $t,
        $title,
        $srcPath,
        $text,
        array $extraParams,
    ) {
        $req = $t->httpPost(
            wfScript("index"),
            $extraParams + [
                "title" => "Special:Upload",
                "wpUploadFile" => curl_file_create($srcPath),
                "wpDestFile" => $title,
                "wpIgnoreWarning" => "1",
                "wpEditToken" => $t->getEditToken(),
                "wpUpload" => "Upload",
                "wpUploadDescription" => $text,
            ],
        );

        $submitResult = $this->getSubmitResult(
            $req,
            "moderation-image-queued",
            $failedReason,
        );
        return ModerationTestsuiteNonApiBotResponse::factory(
            $req,
            $submitResult == "intercepted",
            $submitResult == "bypassed",
            $failedReason,
        );
    }

    /**
     * Interpret the HTML page printed by submitted Special:Upload or Special:Movepage.
     * @param IModerationTestsuiteResponse $req Value returned by httpPost().
     * @param string $interceptMsg One of "moderation-move-queued", "moderation-image-queued".
     * @param string|null &$failedReason Error code (if any) will be written into this variable.
     * @return string One of "intercepted", "bypassed", "failed".
     */
    protected function getSubmitResult(
        IModerationTestsuiteResponse $req,
        $interceptMsg,
        &$failedReason = null,
    ) {
        if ($req->getResponseHeader("Location")) {
            if ($interceptMsg == "moderation-image-queued") {
                return "bypassed"; // HTTP redirect from Special:Upload
            }

            // Sanity check: Special:Movepage doesn't print a HTTP redirect.
            throw new MWException(
                __METHOD__ .
                    ": unexpected HTTP redirect, " .
                    "expected either success text or ($interceptMsg) message.",
            );
        }

        $html = new ModerationTestsuiteHTML();
        $html->loadReq($req);

        $div = $html->getElementByXPath(
            '//div[contains(@class, "mw-message-box-error")]',
        );
        if (!$div) {
            // MediaWiki 1.43+
            $div = $html->getElementByXPath(
                '//div[contains(@class, "cdx-message--error")]',
            );
        }

        if ($div) {
            // Error found
            $error = trim($div->textContent);
            if ($error == $interceptMsg) {
                // The change was indeed intercepted, but a customized
                // "success!" message (ModerationQueuedSuccessException)
                // wasn't used for some reason. This would be a bug,
                // so let's throw an exception to make the test fail.
                throw new MWException(
                    __METHOD__ .
                        ": message $interceptMsg was printed " .
                        'as an error, not via "successfully intercepted" exception.',
                );
            }

            // Genuine error (likely unrelated to Moderation), e.g. 'readonly'.
            $failedReason = $error;
            return "failed";
        }

        $successText = $html->getMainText();
        if (strpos($successText, "($interceptMsg)") !== false) {
            return "intercepted";
        }

        if (
            $interceptMsg == "moderation-move-queued" &&
            strpos($successText, "(movepage-moved:") !== false
        ) {
            // Successfully submitted Special:Movepage (move wasn't queued for moderation)
            return "bypassed";
        }

        // No errors were found, however but a customized "success!" message
        // (ModerationQueuedSuccessException) wasn't printed either.
        // This would be a bug, so let's throw an exception to make the test fail.
        throw new MWException(
            __METHOD__ .
                ": no errors were found, but the output " .
                "doesn't contain \"successfully intercepted\" message.",
        );
    }
}
