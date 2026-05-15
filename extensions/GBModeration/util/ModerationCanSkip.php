<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2014-2021 Edward Chernenko.

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
 * Checks if the user is allowed to skip moderation.
 */

namespace MediaWiki\Moderation;

use MediaWiki\Config\ServiceOptions;
use User;

class ModerationCanSkip
{
    /** @var ServiceOptions */
    protected $options;

    /** @var ModerationApproveHook */
    protected $approveHook;

    public const CONSTRUCTOR_OPTIONS = [
        "ModerationEnable",
        "ModerationIgnoredInNamespaces",
        "ModerationOnlyInNamespaces",
    ];

    /**
     * @param ServiceOptions $options
     * @param ModerationApproveHook $approveHook
     */
    public function __construct(
        ServiceOptions $options,
        ModerationApproveHook $approveHook,
    ) {
        $this->options = $options;
        $this->approveHook = $approveHook;
    }

    /**
     * Check if edits by $user can bypass moderation in namespace $namespaceNumber.
     * @param User $user
     * @param int $namespaceNumber
     * @return bool
     */
    public function canEditSkip(User $user, $namespaceNumber)
    {
        return $this->canSkip($user, "skip-moderation", [$namespaceNumber]);
    }

    /**
     * Check if uploads by $user can bypass moderation.
     * @param User $user
     * @return bool
     */
    public function canUploadSkip(User $user)
    {
        return $this->canEditSkip($user, NS_FILE);
    }

    /**
     * Check if moves by $user can bypass moderation.
     * @param User $user
     * @param int $fromNamespace Namespace of the old title.
     * @param int $toNamespace Namespace of the new title.
     * @return bool
     */
    public function canMoveSkip(User $user, $fromNamespace, $toNamespace)
    {
        return $this->canSkip($user, "skip-move-moderation", [
            $fromNamespace,
            $toNamespace,
        ]);
    }

    /*-------------------------------------------------------------------*/

    /**
     * Returns true if $user can skip moderation, false otherwise.
     * @param User $user
     * @param string $permission Name of the user's right that allows to bypass moderation.
     * @param int[] $affectedNamespaces Array of namespace numbers of all affected pages.
     * @return bool
     */
    protected function canSkip(
        User $user,
        $permission,
        array $affectedNamespaces,
    ) {
        if (
            !$this->options->get("ModerationEnable") ||
            $this->approveHook->isApprovingNow()
        ) {
            return true; /* Moderation is disabled */
        }

        if ($user->isAllowed($permission)) {
            return true; /* $user is allowed to bypass moderation */
        }

        if ($permission == "skip-moderation" && $user->isAllowed("rollback")) {
            /*
				It makes little sense for some user to have 'rollback'
				and not have 'skip-moderation', and there is no perfect
				implementation for this case.
				Therefore we allow such users to skip moderation
				of edits (but not moves).
			*/
            return true;
        }

        /* Is moderation disabled in ALL affected namespace(s)? */
        return $this->canSkipInAllNamespaces($affectedNamespaces);
    }

    /**
     * Check if moderation can be skipped in all $namespaces.
     * @param int[] $namespaces Array of namespace numbers.
     * @return bool
     * True if all $namespaces are non-moderated,
     * false if at least one of $namespaces is moderated.
     */
    protected function canSkipInAllNamespaces(array $namespaces)
    {
        foreach (array_unique($namespaces) as $ns) {
            if (!$this->canSkipInNamespace($ns)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if moderation can be skipped in namespace $namespaceNumber.
     * @param int $namespaceNumber
     * @return bool
     */
    protected function canSkipInNamespace($namespaceNumber)
    {
        if (
            in_array(
                $namespaceNumber,
                $this->options->get("ModerationIgnoredInNamespaces"),
            )
        ) {
            return true; /* This namespace is NOT moderated, e.g. Sandbox:Something */
        }

        $onlyInNamespaces = $this->options->get("ModerationOnlyInNamespaces");
        if (
            $onlyInNamespaces &&
            !in_array($namespaceNumber, $onlyInNamespaces)
        ) {
            return true; /* This namespace is NOT moderated */
        }

        return false;
    }
}
