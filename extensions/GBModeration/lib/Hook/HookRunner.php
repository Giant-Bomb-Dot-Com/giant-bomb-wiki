<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2021 Edward Chernenko.

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
 * Methods to run hooks that are provided by Extension:Moderation itself.
 */

namespace MediaWiki\Moderation\Hook;

use Content;
use IContextSource;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Linker\LinkTarget;
use Status;
use User;
use WikiPage;

class HookRunner implements
    ModerationContinueEditingLinkHook,
    ModerationInterceptHook,
    ModerationPendingHook
{
    /** @var HookContainer */
    protected $container;

    /**
     * @param HookContainer $container
     */
    public function __construct(HookContainer $container)
    {
        $this->container = $container;
    }

    /**
     * @inheritDoc
     */
    public function onModerationContinueEditingLink(
        string &$returnto,
        array &$returntoquery,
        LinkTarget $title,
        IContextSource $context,
    ) {
        $this->container->run("ModerationContinueEditingLink", [
            &$returnto,
            &$returntoquery,
            $title,
            $context,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function onModerationIntercept(
        WikiPage $page,
        User $user,
        Content $content,
        string $summary,
        $is_minor,
        $is_watch,
        $section,
        $flags,
        Status $status,
    ) {
        return $this->container->run("ModerationIntercept", [
            $page,
            $user,
            $content,
            $summary,
            $is_minor,
            $is_watch,
            $section,
            $flags,
            $status,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function onModerationPending(array $fields, $modid)
    {
        return $this->container->run("ModerationPending", [$fields, $modid]);
    }
}
