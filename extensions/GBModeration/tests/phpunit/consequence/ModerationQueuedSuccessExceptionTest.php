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
 * Unit test of ModerationQueuedSuccessException.
 */

namespace MediaWiki\Moderation\Tests;

use ErrorPageError;
use MediaWiki\Moderation\ModerationQueuedSuccessException;
use MediaWiki\Title\Title;
use RequestContext;

require_once __DIR__ . "/autoload.php";

class ModerationQueuedSuccessExceptionTest extends ModerationUnitTestCase
{
    /**
     * Test that ModerationQueuedSuccessException overrides some methods of ErrorPageError class.
     * @covers MediaWiki\Moderation\ModerationQueuedSuccessException
     */
    public function testExceptionSubclass()
    {
        $e = new ModerationQueuedSuccessException("whatever", []);

        $this->assertInstanceOf(ErrorPageError::class, $e);
        $this->assertFalse($e->isLoggable(), "isLoggable");
    }

    /**
     * Verify that throwIfNeeded() throws an exception only on Special:Upload and Special:Movepage.
     * @param bool $expectException If true, exception is expected to be thrown.
     * @param string|null $specialPageName Name of special page (if any), e.g. "Upload".
     * @dataProvider dataProviderThrowIfNeeded
     * @covers MediaWiki\Moderation\ModerationQueuedSuccessException
     */
    public function testThrowIfNeeded($expectException, $specialPageName = null)
    {
        $msg = "some-msg";
        $params = ["key1" => "val1", "anotherkey" => "anotherval"];

        if ($specialPageName) {
            $title = Title::makeTitle(NS_SPECIAL, $specialPageName);
            RequestContext::getMain()->setTitle($title);
        } else {
            $this->setMwGlobals("wgTitle", null);
            RequestContext::getMain()->setTitle(null);
        }

        if ($expectException) {
            $this->expectExceptionObject(
                new ModerationQueuedSuccessException($msg, $params),
            );
        }

        ModerationQueuedSuccessException::throwIfNeeded($msg, $params);

        // If we are here, test has already succeeded ($exceptException is false and no exception was
        // thrown by tested code). But PHPUnit won't generate code coverage for test without assertions.
        $this->assertTrue(true);
    }

    /**
     * Provide datasets for testThrowIfNeeded() runs.
     * @return array
     */
    public function dataProviderThrowIfNeeded()
    {
        return [
            "Special:MovePage" => [true, "Movepage"],
            "Special:Upload" => [true, "Upload"],
            "Special:BlankPage" => [false, "Blankpage"],
            "No global Title" => [false, null],
        ];
    }
}
