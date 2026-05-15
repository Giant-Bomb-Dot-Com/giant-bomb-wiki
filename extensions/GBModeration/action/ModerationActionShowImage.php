<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2014-2024 Edward Chernenko.

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
 * Implements modaction=showimg on [[Special:Moderation]].
 */

namespace MediaWiki\Moderation;

use File;
use FileBackend;
use HTTPFileStreamer;
use OutputPage;
use UnregisteredLocalFile;
use UploadStashException;

class ModerationActionShowImage extends ModerationAction
{
    public const THUMB_WIDTH = 320;

    public function requiresEditToken()
    {
        return false;
    }

    public function requiresWrite()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function outputResult(array $result, OutputPage $out)
    {
        $out->disable(); # No HTML output (image only)
        if (isset($result["missing"])) {
            HTTPFileStreamer::send404Message(""); // Send 404 Not Found
            return;
        }

        $headers = [];
        $headers[] =
            "Content-Disposition: " .
            FileBackend::makeContentDisposition(
                "inline",
                $result["thumb-filename"],
            );

        $repo = $this->repoGroup->getLocalRepo();
        $repo->streamFileWithStatus($result["thumb-path"], $headers);
    }

    public function execute()
    {
        $row = $this->entryFactory->loadRowOrThrow(
            $this->id,
            ["mod_title AS title", "mod_stash_key AS stash_key"],
            DB_REPLICA,
        );

        $stash = ModerationUploadStorage::getStash();

        try {
            $file = $stash->getFile($row->stash_key);
        } catch (UploadStashException $_) {
            return ["missing" => ""];
        }

        $isThumb = $this->getRequest()->getVal("thumb");
        if ($isThumb) {
            $thumb = $file->transform(
                ["width" => self::THUMB_WIDTH],
                File::RENDER_NOW,
            );
            if ($thumb) {
                $storagePath = $thumb->getStoragePath();
                if ($thumb->fileIsSource() || !$storagePath) {
                    $isThumb = false;
                } else {
                    $file = new UnregisteredLocalFile(
                        false,
                        $stash->repo,
                        $storagePath,
                        false,
                    );
                }
            }
        }

        $thumbFilename = "";
        if ($isThumb) {
            $thumbFilename .= $file->getWidth() . "px-";
        }
        $thumbFilename .= $row->title;

        return [
            "thumb-path" => $file->getPath(),
            "thumb-filename" => $thumbFilename,
        ];
    }
}
