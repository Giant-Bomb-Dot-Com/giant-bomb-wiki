<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2015-2021 Edward Chernenko.

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
 * Ensures that uploads are intercepted by Extension:Moderation.
 */

namespace MediaWiki\Moderation\Tests;

require_once __DIR__ . "/../framework/ModerationTestsuite.php";

/**
 * @group Database
 * @covers MediaWiki\Moderation\ModerationUploadHooks
 */
class ModerationUploadTest extends ModerationTestCase
{
    public function testUpload(ModerationTestsuite $t)
    {
        $t->loginAs($t->unprivilegedUser);
        $result = $t->doTestUpload();
        $t->fetchSpecial();

        # Was the upload queued for moderation?
        $this->assertTrue(
            $result->isIntercepted(),
            "testUpload(): Special:Upload didn't say that upload was queued for moderation.",
        );

        # Is the data on Special:Moderation correct?
        $entry = $t->new_entries[0];
        $this->assertCount(
            1,
            $t->new_entries,
            "testUpload(): One upload was queued for moderation, but number of " .
                "added entries in Pending folder isn't 1",
        );
        $this->assertCount(
            0,
            $t->deleted_entries,
            "testUpload(): Something was deleted from Pending folder during the queueing",
        );
        $this->assertSame($t->lastEdit["User"], $entry->user);
        $this->assertSame($t->lastEdit["Title"], $entry->title);

        # Can we approve this upload?
        $this->assertNotNull(
            $entry->approveLink,
            "testUpload(): Approve link not found",
        );

        $t->html->loadUrl($entry->approveLink);
        $this->assertMatchesRegularExpression(
            "/\(moderation-approved-ok: 1\)/",
            $t->html->getMainText(),
            "testUpload(): Result page doesn't contain (moderation-approved-ok: 1)",
        );

        # Has the file been uploaded after the approval?
        $ret = $t->query([
            "action" => "query",
            "prop" => "imageinfo",
            "iilimit" => 1,
            "iiprop" => "user|timestamp|comment|size|url|sha1",
            "titles" => $entry->title,
        ]);
        $retPage = array_shift($ret["query"]["pages"]);

        $this->assertNotNull($retPage);
        '@phan-var array $retPage';

        $ii = $retPage["imageinfo"][0];

        $this->assertSame($t->lastEdit["User"], $ii["user"]);
        $this->assertSame($t->lastEdit["Text"], $ii["comment"]);
        $this->assertSame($t->lastEdit["SHA1"], $ii["sha1"]);
    }

    public function testUploadHookVerifies(ModerationTestsuite $t)
    {
        # Does upload hook call getVerificationErrorCode() to check
        # the image before queueing the upload?

        $path = tempnam(sys_get_temp_dir(), "modtest");
        file_put_contents($path, ""); # Empty
        $t->loginAs($t->unprivilegedUser);
        $result = $t->getBot("nonApi")->upload("1.png", $path);
        unlink($path);

        $this->assertSame(
            "(emptyfile)",
            $result->getError(),
            "testUploadHookVerifies(): no error was printed when trying to upload empty file.",
        );
    }

    /**
     * @covers MediaWiki\Moderation\ModerationApproveHook::onRevisionFromEditComplete
     */
    public function testReupload(ModerationTestsuite $t)
    {
        $title = "Test image " . $t->uniqueSuffix() . ".png";

        # Upload the image first
        $t->loginAs($t->automoderated);
        $t->doTestUpload($title, "image640x50.png", "Text 1");

        # Now queue reupload for moderation
        $t->loginAs($t->unprivilegedUser);
        $result = $t->doTestUpload($title, "image100x100.png", "Text 2");
        $t->fetchSpecial();

        # Was the reupload queued for moderation?
        $this->assertTrue(
            $result->isIntercepted(),
            "testMove(): Special:MovePage didn't say that reupload was queued for moderation.",
        );

        # Is the data on Special:Moderation correct?
        $entry = $t->new_entries[0];
        $this->assertCount(
            1,
            $t->new_entries,
            "testReupload(): One upload was queued for moderation, but number of " .
                "added entries in Pending folder isn't 1",
        );
        $this->assertCount(
            0,
            $t->deleted_entries,
            "testReupload(): Something was deleted from Pending folder during the queueing",
        );
        $this->assertSame($t->lastEdit["User"], $entry->user);
        $this->assertSame($t->lastEdit["Title"], $entry->title);

        # Does modaction=show display (moderation-diff-reupload) message?
        $this->assertMatchesRegularExpression(
            "/\(moderation-diff-reupload\)/",
            $t->html->loadUrl($entry->showLink)->getMainText(),
            "testReupload(): (moderation-diff-reupload) not found in the output of modaction=show",
        );

        # Can we approve this reupload?
        $this->assertNotNull(
            $entry->approveLink,
            "testReupload(): Approve link not found",
        );

        /* Wait up to 1 second to avoid archived name collision */
        $t->sleepUntilNextSecond();

        $t->html->loadUrl($entry->approveLink);
        $this->assertMatchesRegularExpression(
            "/\(moderation-approved-ok: 1\)/",
            $t->html->getMainText(),
            "testReupload(): Result page doesn't contain (moderation-approved-ok: 1)",
        );

        # Has the file been reuploaded after the approval?
        $ret = $t->query([
            "action" => "query",
            "prop" => "imageinfo",
            "iilimit" => 1,
            "iiprop" => "user|timestamp|comment|size|url|sha1",
            "titles" => $entry->title,
        ]);
        $retPage = array_shift($ret["query"]["pages"]);

        $this->assertNotNull($retPage);
        '@phan-var array $retPage';

        $ii = $retPage["imageinfo"][0];

        $this->assertSame($t->lastEdit["User"], $ii["user"]);
        $this->assertSame($t->lastEdit["Text"], $ii["comment"]);
        $this->assertSame($t->lastEdit["SHA1"], $ii["sha1"]);
    }
}
