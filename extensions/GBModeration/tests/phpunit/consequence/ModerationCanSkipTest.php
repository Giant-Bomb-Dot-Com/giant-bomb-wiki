<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2021 Edward Chernenko.

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
 * Unit test of ModerationCanSkip.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Moderation\ModerationApproveHook;
use MediaWiki\Moderation\ModerationCanSkip;
use User;

require_once __DIR__ . "/autoload.php";

class ModerationCanSkipTest extends ModerationUnitTestCase
{
    /**
     * Check the return value of methods like canEditSkip().
     * @param bool $expectedResult Value that should be returned by tested method.
     * @param string $method Name of tested method, e.g. "canMoveSkip"
     * @param array $args Additional arguments of $method, e.g. array of namespaces for canEditSkip().
     * @param array $isAllowed Mocked return values of $user->isAllowed(), e.g. [ 'rollback' => true ].
     * @param array $configVars Configuration settings (if any), e.g. [ 'ModerationEnable' => false ].
     * @dataProvider dataProviderCanSkip
     *
     * @covers MediaWiki\Moderation\ModerationCanSkip
     */
    public function testCanSkip(
        $expectedResult,
        $method,
        array $args,
        array $isAllowed,
        array $configVars,
    ) {
        $approveHook = $this->createMock(ModerationApproveHook::class);
        $approveHook
            ->expects($this->any())
            ->method("isApprovingNow")
            ->willReturn($configVars["__inApprove"] ?? false);

        '@phan-var ModerationApproveHook $approveHook';

        $config = new ServiceOptions(ModerationCanSkip::CONSTRUCTOR_OPTIONS, [
            "ModerationEnable" => $configVars["ModerationEnable"] ?? true,
            "ModerationIgnoredInNamespaces" =>
                $configVars["ModerationIgnoredInNamespaces"] ?? [],
            "ModerationOnlyInNamespaces" =>
                $configVars["ModerationOnlyInNamespaces"] ?? [],
        ]);
        $canSkip = new ModerationCanSkip($config, $approveHook);

        // Mock User::isAllowed() to return values from $isAllowed array.
        $user = $this->createMock(User::class);
        $user
            ->expects($this->any())
            ->method("isAllowed")
            ->will(
                $this->returnCallback(static function ($right) use (
                    $isAllowed,
                ) {
                    return $isAllowed[$right] ?? false;
                }),
            );

        '@phan-var User $user';

        $result = $canSkip->$method($user, ...$args);
        $this->assertSame(
            $expectedResult,
            $result,
            "Result on $method() doesn't match expected.",
        );
    }

    /**
     * Provide datasets for testCanSkip() runs.
     * @return array
     */
    public function dataProviderCanSkip()
    {
        return [
            // Default: moderation is enabled.
            "canEditSkip()=false (no factors that would allow to bypass Moderation), NS_MAIN" => [
                false,
                "canEditSkip",
                [NS_MAIN],
                [],
                [],
            ],
            "canEditSkip()=false (no factors that would allow to bypass Moderation), NS_PROJECT" => [
                false,
                "canEditSkip",
                [NS_PROJECT],
                [],
                [],
            ],
            "canMoveSkip()=false (no factors that would allow to bypass Moderation)" => [
                false,
                "canMoveSkip",
                [NS_MAIN, NS_PROJECT],
                [],
                [],
            ],
            "canUploadSkip()=false (no factors that would allow to bypass Moderation)" => [
                false,
                "canUploadSkip",
                [],
                [],
                [],
            ],

            // $wgModerationEnable
            'canEditSkip()=true, because $wgModerationEnable=false' => [
                true,
                "canEditSkip",
                [NS_MAIN],
                [],
                ["ModerationEnable" => false],
            ],
            'canMoveSkip()=true, because $wgModerationEnable=false' => [
                true,
                "canMoveSkip",
                [NS_MAIN, NS_PROJECT],
                [],
                ["ModerationEnable" => false],
            ],
            'canUploadSkip()=true, because $wgModerationEnable=false' => [
                true,
                "canUploadSkip",
                [],
                [],
                ["ModerationEnable" => false],
            ],

            // During modaction=approve, when ApproveHook::isApprovingNow() returns true.
            "canEditSkip()=true, because ApproveHook is installed" => [
                true,
                "canEditSkip",
                [NS_MAIN],
                [],
                ["__inApprove" => true],
            ],
            "canMoveSkip()=true, because ApproveHook is installed" => [
                true,
                "canMoveSkip",
                [NS_MAIN, NS_PROJECT],
                [],
                ["__inApprove" => true],
            ],
            "canUploadSkip()=true, because ApproveHook is installed" => [
                true,
                "canUploadSkip",
                [],
                [],
                ["__inApprove" => true],
            ],

            // "skip-moderation" right
            "canEditSkip()=true, becauser has skip-moderation right" => [
                true,
                "canEditSkip",
                [NS_MAIN],
                ["skip-moderation" => true],
                [],
            ],
            "canUploadSkip()=true, becauser has skip-moderation right" => [
                true,
                "canUploadSkip",
                [],
                ["skip-moderation" => true],
                [],
            ],
            "canMoveSkip()=false: user without skip-move-moderation right (skip-moderation is not enough)" => [
                false,
                "canMoveSkip",
                [NS_MAIN, NS_PROJECT],
                ["skip-moderation" => true],
                [],
            ],

            // Users with "rollback" right are intentionally treated as if they had "skip-moderation".
            "canEditSkip()=true, user has rollback right (intentionally treated as skip-moderation)" => [
                true,
                "canEditSkip",
                [NS_MAIN],
                ["rollback" => true],
                [],
            ],
            "canUploadSkip()=true, user has rollback right (intentionally treated as skip-moderation)" => [
                true,
                "canUploadSkip",
                [],
                ["rollback" => true],
                [],
            ],
            "canMoveSkip()=false: user without skip-move-moderation right (rollback is not enough)" => [
                false,
                "canMoveSkip",
                [NS_MAIN, NS_PROJECT],
                ["rollback" => true],
                [],
            ],

            // "skip-move-moderation" right
            "canMoveSkip()=true, becauser has skip-move-moderation right" => [
                true,
                "canMoveSkip",
                [NS_MAIN, NS_PROJECT],
                ["skip-move-moderation" => true],
                [],
            ],
            "canEditSkip()=false: user without skip-moderation right (skip-move-moderation is not enough)" => [
                false,
                "canEditSkip",
                [NS_MAIN],
                ["skip-move-moderation" => true],
                [],
            ],
            "canUploadSkip()=false: user without skip-moderation right " .
            "(skip-move-moderation is not enough)" => [
                false,
                "canUploadSkip",
                [],
                ["skip-move-moderation" => true],
                [],
            ],

            // $wgModerationOnlyInNamespaces + canEditSkip()
            "canEditSkip()=true, namespace NS_TALK is excluded " .
            'due to $wgModerationOnlyInNamespaces=[NS_MAIN, NS_PROJECT]' => [
                true,
                "canEditSkip",
                [NS_TALK],
                [],
                [
                    "ModerationOnlyInNamespaces" => [NS_MAIN, NS_PROJECT],
                ],
            ],
            "canEditSkip()=false, namespace NS_MAIN is not excluded " .
            'when $wgModerationOnlyInNamespaces=[NS_MAIN, NS_PROJECT]' => [
                false,
                "canEditSkip",
                [NS_MAIN],
                [],
                [
                    "ModerationOnlyInNamespaces" => [NS_MAIN, NS_PROJECT],
                ],
            ],
            "canEditSkip()=false, namespace NS_PROJECT is not excluded " .
            'when $wgModerationOnlyInNamespaces=[NS_MAIN, NS_PROJECT]' => [
                false,
                "canEditSkip",
                [NS_PROJECT],
                [],
                [
                    "ModerationOnlyInNamespaces" => [NS_MAIN, NS_PROJECT],
                ],
            ],

            // $wgModerationOnlyInNamespaces + canUploadSkip()
            'canUploadSkip()=true, NS_FILE is not in $wgModerationOnlyInNamespaces=[NS_MAIN, NS_PROJECT]' => [
                true,
                "canUploadSkip",
                [],
                [],
                [
                    "ModerationOnlyInNamespaces" => [NS_MAIN, NS_PROJECT],
                ],
            ],
            'canUploadSkip()=false, NS_FILE is listed in $wgModerationOnlyInNamespaces' => [
                false,
                "canUploadSkip",
                [],
                [],
                [
                    "ModerationOnlyInNamespaces" => [
                        NS_MAIN,
                        NS_FILE,
                        NS_PROJECT,
                    ],
                ],
            ],

            // $wgModerationIgnoredInNamespaces + canEditSkip()
            "canEditSkip()=true, namespace NS_MAIN is excluded " .
            'due to $wgModerationIgnoredInNamespaces=[NS_MAIN, NS_PROJECT]' => [
                true,
                "canEditSkip",
                [NS_MAIN],
                [],
                [
                    "ModerationIgnoredInNamespaces" => [NS_MAIN, NS_PROJECT],
                ],
            ],
            "canEditSkip()=true, namespace NS_PROJECT is excluded " .
            'due to $wgModerationIgnoredInNamespaces=[NS_MAIN, NS_PROJECT]' => [
                true,
                "canEditSkip",
                [NS_PROJECT],
                [],
                [
                    "ModerationIgnoredInNamespaces" => [NS_MAIN, NS_PROJECT],
                ],
            ],
            "canEditSkip()=false, namespace NS_TALK is not excluded " .
            'when $wgModerationIgnoredInNamespaces=[NS_MAIN, NS_PROJECT]' => [
                false,
                "canEditSkip",
                [NS_TALK],
                [],
                [
                    "ModerationIgnoredInNamespaces" => [NS_MAIN, NS_PROJECT],
                ],
            ],

            // $wgModerationIgnoredInNamespaces + canUploadSkip()
            'canUploadSkip()=true, NS_FILE is listed in $wgModerationIgnoredInNamespaces ' => [
                true,
                "canUploadSkip",
                [],
                [],
                [
                    "ModerationIgnoredInNamespaces" => [NS_FILE],
                ],
            ],
            'canUploadSkip()=false, NS_FILE not in $wgModerationIgnoredInNamespaces=[NS_MAIN, NS_PROJECT]' => [
                false,
                "canUploadSkip",
                [],
                [],
                [
                    "ModerationIgnoredInNamespaces" => [NS_MAIN, NS_PROJECT],
                ],
            ],

            // $wgModerationIgnoredInNamespaces + canMoveSkip()
            // Note: both source AND target namespaces must ignored for canMoveSkip() to return true.
            "canMoveSkip()=false, moving NS_MAIN to NS_TALK requires both NS_MAIN and NS_TALK to be " .
            'listed in $wgModerationIgnoredInNamespaces=[NS_MAIN, NS_PROJECT]' => [
                false,
                "canMoveSkip",
                [NS_MAIN, NS_TALK],
                [],
                [
                    "ModerationIgnoredInNamespaces" => [NS_MAIN, NS_PROJECT],
                ],
            ],
            "canMoveSkip()=false, moving NS_MAIN to NS_TALK requires both NS_MAIN and NS_TALK to be " .
            'listed in $wgModerationIgnoredInNamespaces=[NS_PROJECT, NS_TALK]' => [
                false,
                "canMoveSkip",
                [NS_MAIN, NS_TALK],
                [],
                [
                    "ModerationIgnoredInNamespaces" => [NS_PROJECT, NS_TALK],
                ],
            ],
            "canMoveSkip()=false, because both source and target namespaces (NS_MAIN, NS_TALK) were " .
            'listed in $wgModerationIgnoredInNamespaces=[NS_TALK, NS_MAIN]' => [
                true,
                "canMoveSkip",
                [NS_MAIN, NS_TALK],
                [],
                [
                    "ModerationIgnoredInNamespaces" => [NS_TALK, NS_MAIN],
                ],
            ],

            // $wgModerationOnlyInNamespaces + canMoveSkip()
            // Note: canMoveSkip() won't return true if even one of source/target namespaces is moderated.
            "canMoveSkip()=true, because neither source not target namespaces (NS_MAIN and NS_TALK)" .
            'were listed in $wgModerationOnlyInNamespaces=[NS_PROJECT, NS_FILE]' => [
                true,
                "canMoveSkip",
                [NS_MAIN, NS_TALK],
                [],
                [
                    "ModerationOnlyInNamespaces" => [NS_PROJECT, NS_FILE],
                ],
            ],
            "canMoveSkip()=false, moving NS_MAIN to NS_PROJECT, but moderation is enabled for NS_PROJECT, " .
            'which is listed in $wgModerationOnlyInNamespaces=[NS_PROJECT, NS_FILE]' => [
                false,
                "canMoveSkip",
                [NS_MAIN, NS_PROJECT],
                [],
                [
                    "ModerationOnlyInNamespaces" => [NS_PROJECT, NS_FILE],
                ],
            ],
            "canMoveSkip()=false, moving NS_FILE to NS_MAIN, but moderation is enabled for NS_FILE, " .
            'which is listed in $wgModerationOnlyInNamespaces=[NS_PROJECT, NS_FILE]' => [
                false,
                "canMoveSkip",
                [NS_FILE, NS_MAIN],
                [],
                [
                    "ModerationOnlyInNamespaces" => [NS_PROJECT, NS_FILE],
                ],
            ],
        ];
    }
}
