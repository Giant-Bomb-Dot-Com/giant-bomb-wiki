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
 * Trait that helps to mock the result of LinkRenderer::makeLink()
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkRendererFactory;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\Title;

/**
 * @method static \PHPUnit\Framework\MockObject\Rule\AnyInvokedCount any()
 * @method static \PHPUnit\Framework\MockObject\Rule\InvokedCount once()
 * @method static \PHPUnit\Framework\Constraint\IsIdentical identicalTo($a)
 * @method static void assertTrue($a, string $message='')
 */
trait MockLinkRendererTrait
{
    /**
     * Mocks LinkRendererFactory service to return $linkText instead of link to $title.
     * @param array $linkTextToTitle
     * @phan-param array<string,LinkTarget> $linkTextToTitle
     */
    public function mockLinkRenderer(array $linkTextToTitle)
    {
        // Mock LinkRendererFactory service to ensure that OutputPage::addReturnTo() added expected link.
        $callback = function (LinkTarget $title) use ($linkTextToTitle) {
            foreach ($linkTextToTitle as $mockedText => $title2) {
                if ($title2 && $title->isSameLinkAs($title2)) {
                    return $mockedText;
                }
            }

            $this->assertTrue(
                false,
                "mockLinkRenderer: makeLink() was called for non-configured title: " .
                    Title::newFromLinkTarget($title)->getFullText(),
            );
        };

        $linkRenderer = $this->createMock(LinkRenderer::class);
        foreach (["makeLink", "makeKnownLink"] as $methodName) {
            $linkRenderer
                ->expects($this->any())
                ->method($methodName)
                ->willReturnCallback($callback);
        }
        $linkRenderer
            ->expects($this->any())
            ->method("getLinkClasses")
            ->willReturn("");

        $lrFactory = $this->createMock(LinkRendererFactory::class);
        $lrFactory
            ->expects($this->any())
            ->method("create")
            ->willReturn($linkRenderer);
        $lrFactory
            ->expects($this->any())
            ->method("createFromLegacyOptions")
            ->willReturn($linkRenderer);
        $this->setService("LinkRendererFactory", $lrFactory);
    }

    // These methods are in MediaWikiIntegrationTestCase (this trait is used by its subclasses).

    /** @inheritDoc */
    abstract protected function setService(string $name, $service);

    /** @inheritDoc */
    abstract protected function createMock(string $originalClassName);
}
