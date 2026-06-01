<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2018-2020 Edward Chernenko.

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
 * Performs editing methods of the testsuite (edit/upload/move) via API.
 */

namespace MediaWiki\Moderation\Tests;

class ModerationTestsuiteApiBot extends ModerationTestsuiteBot
{
    /**
     * Make an edit via API.
     * @param ModerationTestsuite $t
     * @param string $title
     * @param string $text
     * @param string $summary
     * @param string|int $section
     * @param array $extraParams
     * @return ModerationTestsuiteApiBotResponse
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
            $extraParams["section"] = $section;
        }

        $ret = $t->query(
            [
                "action" => "edit",
                "title" => $title,
                "text" => $text,
                "summary" => $summary,
                "token" => null,
            ] + $extraParams,
        );

        return $this->makeResponse($ret, "moderation-edit-queued");
    }

    /**
     * Make a move via API.
     * @param ModerationTestsuite $t
     * @param string $oldTitle
     * @param string $newTitle
     * @param string $reason
     * @param array $extraParams
     * @return ModerationTestsuiteApiBotResponse
     */
    public function doMove(
        ModerationTestsuite $t,
        $oldTitle,
        $newTitle,
        $reason,
        array $extraParams,
    ) {
        $ret = $t->query(
            [
                "action" => "move",
                "from" => $oldTitle,
                "to" => $newTitle,
                "reason" => $reason,
                "token" => null,
            ] + $extraParams,
        );

        return $this->makeResponse($ret, "moderation-move-queued");
    }

    /**
     * Perform an upload via API.
     * @param ModerationTestsuite $t
     * @param string $title
     * @param string $srcPath
     * @param string $text
     * @param array $extraParams Bot-specific parameters.
     * @return ModerationTestsuiteApiBotResponse
     */
    public function doUpload(
        ModerationTestsuite $t,
        $title,
        $srcPath,
        $text,
        array $extraParams,
    ) {
        $ret = $t->query(
            [
                "action" => "upload",
                "filename" => $title,
                "text" => $text,
                "token" => null,
                "file" => curl_file_create($srcPath),
                "ignorewarnings" => 1,
            ] + $extraParams,
        );

        return $this->makeResponse($ret, "moderation-image-queued");
    }

    /**
     * Convenience method to create ModerationTestsuiteApiBotResponse object.
     * @param array $ret Raw API result (value returned by $t->query()).
     * @param string $interceptCode "Action was intercepted" error, e.g. 'moderation-edit-queued'.
     * @return ModerationTestsuiteApiBotResponse
     */
    protected function makeResponse(array $ret, $interceptCode)
    {
        $isIntercepted = false;
        $isBypassed = true;
        $error = null;

        if (isset($ret["error"])) {
            $isBypassed = false;

            $code = $ret["error"]["code"];

            $isIntercepted = $code == $interceptCode;
            if (!$isIntercepted) {
                // We wrap the error code in braces, e.g. "(emptyfile)",
                // so that the return value of getError() would be the same
                // for ApiBot and NonApiBot.
                $error = "($code)";
            }
        }

        return ModerationTestsuiteApiBotResponse::factory(
            $ret,
            $isIntercepted,
            $isBypassed,
            $error,
        );
    }
}
