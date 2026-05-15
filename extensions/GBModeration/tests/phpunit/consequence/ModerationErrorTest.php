<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2024 Edward Chernenko.

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
 * Unit test of ModerationError.
 */

namespace MediaWiki\Moderation\Tests;

use ErrorPageError;
use MediaWiki\Moderation\ModerationError;
use MediaWiki\Title\Title;
use RequestContext;
use Status;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class ModerationErrorTest extends ModerationUnitTestCase
{
    use MockLinkRendererTrait;

    /**
     * Test that ModerationError exception can be constructed from string (name of i18n message).
     * @covers MediaWiki\Moderation\ModerationError
     */
    public function testNewExceptionFromString()
    {
        $messageName = "name-of-some-message";

        $e = new ModerationError($messageName);

        $this->assertInstanceOf(ErrorPageError::class, $e);
        $this->assertInstanceOf(
            Status::class,
            $e->status,
            'ModerationError::$status',
        );
        $this->assertFalse(
            $e->status->isOK(),
            "Status of ModerationError shouldn't be successful.",
        );
        $this->assertSame(
            $messageName,
            $e->status->getMessage()->getKey(),
            "Message name of ModerationError::\$status doesn't match expected.",
        );

        // Fields used by ErrorPageError class.
        $this->assertSame("moderation", $e->title, 'ErrorPageError::$title');
        $this->assertSame(
            "($messageName)",
            $e->getMessageObject()->inLanguage("qqx")->plain(),
            "ErrorPageError::getMessageObject()",
        );
    }

    /**
     * Test that ModerationError exception can be constructed from Status object.
     * @covers MediaWiki\Moderation\ModerationError
     */
    public function testNewExceptionFromStatus()
    {
        $status = Status::newGood();
        $status->fatal(
            "name-of-some-message",
            "some-parameter",
            "another-parameter",
        );

        $e = new ModerationError($status);
        $this->assertInstanceOf(ErrorPageError::class, $e);
        $this->assertSame($status, $e->status, 'ModerationError::$status');
        $this->assertEquals(
            $status->getMessage(),
            $e->msg,
            'ErrorPageError::$msg',
        );
    }

    /**
     * Test that ModerationError::report() correctly prints Status into the global OutputPage object.
     * @param bool $isMadeFromString
     * @dataProvider dataProviderReport
     * @covers MediaWiki\Moderation\ModerationError
     */
    public function testReport($isMadeFromString)
    {
        $title = Title::newFromText("UTPage-" . rand(0, 100000));

        // Mock LinkRendererFactory service to ensure that OutputPage::addReturnTo() added expected link.
        $this->mockLinkRenderer(["{MockedReturnToLink}" => $title]);

        // ErrorPageError class prints to $wgOut (global OutputPage), ModerationError does the same.
        global $wgOut;
        $context = new RequestContext(); // To obtain clean OutputPage.
        $context->setLanguage("qqx");
        $context->setTitle($title);
        $out = $wgOut = $context->getOutput();

        $this->overrideConfigValue("LanguageCode", "qqx");

        // Create and report() an exception.
        if ($isMadeFromString) {
            $e = new ModerationError("name-of-some-message");
            $expectedText = "(name-of-some-message)";
        } else {
            $e = new ModerationError(
                Status::newFatal("some-message", "param1", "param2"),
            );
            $expectedText = "(some-message: param1, param2)";
        }

        ob_start();
        $e->report();
        $printedText = ob_get_clean();

        $this->assertNotEmpty(
            $printedText,
            "Nothing was printed by ModerationError::report()",
        );

        // Analyze what the exception did to the OutputPage object and what HTML was printed.
        $this->assertTrue(
            $out->isDisabled(),
            "OutputPage wasn't disabled after ModerationError::report()",
        );

        $html = new ModerationTestHTML();
        $html->loadString($printedText);

        $elem = $html->getElementById("mw-mod-error");
        $this->assertNotNull(
            $elem,
            'HTML element with id="mw-mod-error" not found on the page.',
        );

        $this->assertSame(
            "error",
            $elem->getAttribute("class"),
            "Incorrect class= attribute.",
        );
        $this->assertSame(
            $expectedText,
            $elem->textContent,
            "Incorrect text of the error.",
        );

        $returnto = $html->getElementById("mw-returnto");
        $this->assertNotNull(
            $returnto,
            'HTML element with id="mw-returnto" not found on the page.',
        );
        $this->assertSame(
            "(returnto: {MockedReturnToLink})",
            $returnto->textContent,
            'Incorrect "Return to" link.',
        );
    }

    /**
     * Provide datasets for testReport() runs.
     * @return array
     */
    public function dataProviderReport()
    {
        return [
            "from string" => [true],
            "from Status" => [false],
        ];
    }
}
