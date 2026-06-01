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
 * Unit test of ApiModeration.
 */

namespace MediaWiki\Moderation\Tests;

use ApiMain;
use ApiTestCase;
use ApiUsageException;
use MediaWiki\Moderation\ActionFactory;
use MediaWiki\Moderation\ApiModeration;
use MediaWiki\Moderation\ModerationError;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\TestingAccessWrapper;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 * @group medium
 */
class ApiModerationTest extends ApiTestCase
{
    use MockModerationActionTrait;

    /**
     * Verify that api.php?action=moderation will throw ApiUsageException for non-moderators.
     * @covers MediaWiki\Moderation\ApiModeration
     */
    public function testNotPermitted()
    {
        $notModerator = self::getTestUser()->getUser();

        $mock = $this->addMockedAction("reject");
        $mock->expects($this->never())->method("execute");

        $exceptionThrown = false;
        try {
            $this->doApiRequestWithToken(
                [
                    "action" => "moderation",
                    "modaction" => "reject",
                    "modid" => 12345,
                ],
                null,
                $notModerator,
            );
        } catch (ApiUsageException $e) {
            $exceptionThrown = true;
            $this->assertSame(
                "(apierror-permissiondenied: (action-moderation))",
                $e->getMessageObject()->inLanguage("qqx")->plain(),
            );
        }

        $this->assertTrue(
            $exceptionThrown,
            "ApiUsageException wasn't thrown for non-moderator.",
        );
    }

    /**
     * Verify that api.php?action=moderation will throw ApiUsageException if token= is invalid.
     * @covers MediaWiki\Moderation\ApiModeration
     */
    public function testNoToken()
    {
        $mock = $this->addMockedAction("reject");
        $mock->expects($this->never())->method("execute");

        $exceptionThrown = false;
        try {
            $this->doApiRequest(
                [
                    "action" => "moderation",
                    "modaction" => "reject",
                    "modid" => 12345,
                    "token" => "INVALID TOKEN",
                ],
                null,
                false,
                self::getTestUser(["moderator"])->getUser(),
            );
        } catch (ApiUsageException $e) {
            $exceptionThrown = true;
            $this->assertSame(
                "(apierror-badtoken)",
                $e->getMessageObject()->inLanguage("qqx")->plain(),
            );
        }

        $this->assertTrue(
            $exceptionThrown,
            "ApiUsageException wasn't thrown for invalid token.",
        );
    }

    /**
     * Verify that api.php?action=moderation catches ModerationError from execute()
     * and throws a properly formatted ApiUsageException instead.
     * @covers MediaWiki\Moderation\ApiModeration
     */
    public function testThrownModerationError()
    {
        $mock = $this->addMockedAction("reject");
        $mock
            ->expects($this->once())
            ->method("execute")
            ->will(
                $this->returnCallback(
                    /** @return never */
                    static function () {
                        throw new ModerationError(
                            "error-thrown-by-tested-action",
                        );
                    },
                ),
            );

        $exceptionThrown = false;
        try {
            $this->doApiRequestWithToken(
                [
                    "action" => "moderation",
                    "modaction" => "reject",
                    "modid" => 12345,
                ],
                null,
                self::getTestUser(["moderator"])->getUser(),
            );
        } catch (ApiUsageException $e) {
            $exceptionThrown = true;
            $this->assertSame(
                "(error-thrown-by-tested-action)",
                $e->getMessageObject()->inLanguage("qqx")->plain(),
            );
        }

        $this->assertTrue(
            $exceptionThrown,
            "ApiUsageException wasn't thrown when execute() resulted in a ModerationError.",
        );
    }

    /**
     * Verify that api.php?action=moderation runs execute() and adds its result into API response.
     * @covers MediaWiki\Moderation\ApiModeration
     */
    public function testSuccessfulAction()
    {
        $mockedResult = ["cat" => "feline", "fennec fox" => "canine"];

        $mock = $this->addMockedAction("reject");
        $mock
            ->expects($this->once())
            ->method("execute")
            ->willReturn($mockedResult);

        [$result] = $this->doApiRequestWithToken(
            [
                "action" => "moderation",
                "modaction" => "reject",
                "modid" => 12345,
            ],
            null,
            self::getTestUser(["moderator"])->getUser(),
        );

        $this->assertSame(["moderation" => $mockedResult], $result);
    }

    /**
     * Test that ApiModeration subclass overrides some methods of ApiBase class.
     * @covers MediaWiki\Moderation\ApiModeration
     */
    public function testApiBaseSubclass()
    {
        $actionFactory = $this->createMock(ActionFactory::class);
        '@phan-var ActionFactory $actionFactory';

        $api = new ApiModeration(new ApiMain(), "moderation", $actionFactory);

        $this->assertTrue($api->isWriteMode(), "isWriteMode");
        $this->assertTrue($api->mustBePosted(), "mustBePosted");
        $this->assertSame("csrf", $api->needsToken(), "needsToken");

        $allowedParams = $api->getAllowedParams();
        $this->assertArrayHasKey("modaction", $allowedParams);
        $this->assertArrayHasKey("modid", $allowedParams);

        $this->assertFalse(
            empty($allowedParams["modaction"][ParamValidator::PARAM_REQUIRED]),
            "Parameter modaction= must be required.",
        );
        $this->assertFalse(
            empty($allowedParams["modid"][ParamValidator::PARAM_REQUIRED]),
            "Parameter modid= must be required.",
        );

        $wrapper = TestingAccessWrapper::newFromObject($api);
        $this->assertNotEmpty(
            $wrapper->getExamplesMessages(),
            "getExamplesMessages",
        );
    }
}
