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
 * Unit test of ActionLinkRenderer.
 */

namespace MediaWiki\Moderation\Tests;

use IContextSource;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Moderation\ActionLinkRenderer;
use MediaWiki\Title\Title;
use RawMessage;
use User;

require_once __DIR__ . "/autoload.php";

class ActionLinkRendererTest extends ModerationUnitTestCase
{
    /**
     * Test that ActionLinkRenderer::makeLink() generates HTML of the link as expected.
     * @param string $action Name of moderation action, e.g. "reject" or "show".
     * @param bool $isTokenNeeded Whether the URL of resulting link should have token= parameter.
     * @dataProvider dataProviderMakeLink
     *
     * @covers MediaWiki\Moderation\ActionLinkRenderer
     */
    public function testMakeLink($action, $isTokenNeeded)
    {
        $id = 12345;
        $editToken = "Edit token " . rand(0, 100000);
        $expectedLinkText = "Text of link " . rand(0, 100000);
        $expectedTooltip = "Link tooltip " . rand(0, 100000);
        $expectedResult = "Returned HTML " . rand(0, 100000);
        $expectedQueryParameters = [
            "modaction" => $action,
            "modid" => $id,
        ];

        // Mock all parameters of ActionLinkRenderer::__construct.
        $specialTitle = $this->createMock(Title::class);
        $context = $this->createMock(IContextSource::class);
        $linkRenderer = $this->createMock(LinkRenderer::class);

        if ($isTokenNeeded) {
            $expectedQueryParameters["token"] = $editToken;
            $context
                ->expects($this->once())
                ->method("getUser")
                ->will(
                    $this->returnCallback(function () use ($editToken) {
                        $user = $this->createMock(User::class);
                        $user->method("getEditToken")->willReturn($editToken);

                        return $user;
                    }),
                );
        } else {
            $context->expects($this->never())->method("getUser");
        }

        $context
            ->expects($this->exactly(2))
            ->method("msg")
            ->withConsecutive(
                ["moderation-$action"],
                ["tooltip-moderation-$action"],
            )
            ->willReturnOnConsecutiveCalls(
                new RawMessage($expectedLinkText),
                new RawMessage($expectedTooltip),
            );

        $linkRenderer
            ->expects($this->once())
            ->method("makePreloadedLink")
            ->with(
                $this->identicalTo($specialTitle),
                $this->identicalTo($expectedLinkText),
                $this->identicalTo(""),
                $this->identicalTo([
                    "title" => $expectedTooltip,
                ]),
                $this->identicalTo($expectedQueryParameters),
            )
            ->willReturn($expectedResult);

        '@phan-var IContextSource $context';
        '@phan-var LinkRenderer $linkRenderer';
        '@phan-var Title $specialTitle';

        // Now run ActionLinkRenderer::makeLink().
        $renderer = new ActionLinkRenderer(
            $context,
            $linkRenderer,
            $specialTitle,
        );
        $result = $renderer->makeLink($action, $id);

        $this->assertSame($expectedResult, $result);
    }

    /**
     * Provide datasets for testMakeLink() runs.
     * @return array
     */
    public function dataProviderMakeLink()
    {
        return [
            "show (token NOT needed)" => ["show", false],
            "preview (token NOT needed)" => ["preview", false],
            "reject (token needed)" => ["reject", true],
            "showimg (token needed)" => ["showimg", true],
        ];
    }
}
