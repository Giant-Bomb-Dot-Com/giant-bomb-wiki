<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2023 Edward Chernenko.

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
 * Trait that helps to create ModerationAction object with all mocked dependencies.
 */

namespace MediaWiki\Moderation\Tests;

use Language;
use MediaWiki\Moderation\ActionLinkRenderer;
use MediaWiki\Moderation\EditFormOptions;
use MediaWiki\Moderation\EntryFactory;
use MediaWiki\Moderation\IConsequenceManager;
use MediaWiki\Moderation\ModerationAction;
use MediaWiki\Moderation\ModerationCanSkip;
use MediaWiki\Revision\RevisionRenderer;
use ReadOnlyMode;
use RepoGroup;
use RequestContext;

/**
 * @method static \PHPUnit\Framework\MockObject\Rule\InvokedCount never()
 */
trait ActionTestTrait
{
    /**
     * Create ModerationAction using $setupMocks() callback, which receives all mocked dependencies.
     * @param string $class Name of the ModerationAction subclass.
     * @param callable|null $setupMocks Callback that can configure MockObject dependencies.
     * @return ModerationAction
     *
     * @phan-param class-string $class
     */
    private function makeActionForTesting($class, callable $setupMocks = null)
    {
        $context = new RequestContext();
        $entryFactory = $this->createMock(EntryFactory::class);
        $manager = $this->createMock(IConsequenceManager::class);
        $canSkip = $this->createMock(ModerationCanSkip::class);
        $editFormOptions = $this->createMock(EditFormOptions::class);
        $actionLinkRenderer = $this->createMock(ActionLinkRenderer::class);
        $repoGroup = $this->createMock(RepoGroup::class);
        $contentLanguage = $this->createMock(Language::class);
        $revisionRenderer = $this->createMock(RevisionRenderer::class);
        $readOnlyMode = $this->createMock(ReadOnlyMode::class);

        $arguments = [
            $context,
            $entryFactory,
            $manager,
            $canSkip,
            $editFormOptions,
            $actionLinkRenderer,
            $repoGroup,
            $contentLanguage,
            $revisionRenderer,
            $readOnlyMode,
        ];

        $context->setLanguage("qqx");

        if ($setupMocks) {
            $setupMocks(...$arguments);
        } else {
            // Since we are not configuring a mock of ConsequenceManager,
            // it means that we expect no consequences to be added.
            $manager->expects($this->never())->method("add");
        }

        return new $class(...$arguments);
    }

    // These methods are in MediaWikiIntegrationTestCase (this trait is used by its subclasses).

    /** @inheritDoc */
    abstract protected function createMock(string $originalClassName);
}
