<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2018-2024 Edward Chernenko.

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
 * Checks that automoderated users can bypass moderation and others can't.
 */

namespace MediaWiki\Moderation\Tests;

use MediaWiki\MediaWikiServices;
use MediaWiki\Moderation\ModerationCompatTools;
use MediaWiki\Title\Title;
use MWException;
use UnexpectedValueException;
use User;

require_once __DIR__ . "/../../framework/ModerationTestsuite.php";

/**
 * @group Database
 * @covers MediaWiki\Moderation\ModerationCanSkip
 */
class ModerationInterceptTest extends ModerationTestCase
{
    /**
     * @dataProvider dataProvider
     */
    public function testIntercept(array $options)
    {
        $this->runSet($options);
    }

    /**
     * Provide datasets for testIntercept() runs.
     */
    public function dataProvider()
    {
        $sets = [
            "anonymous edit should be intercepted" => [["anonymously" => true]],
            "edit by unprivileged user should be intercepted" => [
                ["groups" => []],
            ],
            "edit by automoderated user should bypass moderation" => [
                [
                    "intercept" => false,
                    "groups" => ["automoderated"],
                ],
            ],
            "edit in main namespace should be intercepted" => [
                ["namespace" => NS_MAIN],
            ],
            "edit in Project namespace should be intercepted" => [
                ["namespace" => NS_PROJECT],
            ],

            // Users with "rollback" right always bypass moderation of edits (but not moves),
            // see ModerationCanSkip::canSkip() for explanation.
            'non-automoderated user with "rollback" right can bypass moderation of edits' => [
                [
                    "intercept" => false,
                    "groups" => ["rollback"],
                ],
            ],
            'moves by non-automoderated user with "rollback" right are intercepted' => [
                ["action" => "move", "groups" => ["rollback"]],
            ],

            "edits by moderator-but-not-automoderated should be intercepted" => [
                ["groups" => ["moderator"]],
            ],

            // $wgModerationEnable
            'edits shouldn\'t be intercepted when $wgModerationEnable=false' => [
                [
                    "intercept" => false,
                    "anonymously" => true,
                    "ModerationEnable" => false,
                ],
            ],

            // $wgModerationOnlyInNamespaces
            "edit in main namespace should be intercepted " .
            'when $wgModerationOnlyInNamespaces=[NS_MAIN, NS_PROJECT]' => [
                [
                    "namespace" => NS_MAIN,
                    "ModerationOnlyInNamespaces" => [NS_MAIN, NS_PROJECT],
                ],
            ],
            "edit in Project namespace should be intercepted " .
            'when $wgModerationOnlyInNamespaces=[NS_MAIN, NS_PROJECT]' => [
                [
                    "namespace" => NS_PROJECT,
                    "ModerationOnlyInNamespaces" => [NS_MAIN, NS_PROJECT],
                ],
            ],
            "edit in User namespace should bypass moderation " .
            'when $wgModerationOnlyInNamespaces=[NS_MAIN, NS_PROJECT]' => [
                [
                    "intercept" => false,
                    "namespace" => NS_USER,
                    "ModerationOnlyInNamespaces" => [NS_MAIN, NS_PROJECT],
                ],
            ],

            // $wgModerationIgnoredInNamespaces
            "edit in main namespace should bypass moderation " .
            'when $wgModerationIgnoredInNamespaces=[NS_MAIN, NS_PROJECT]' => [
                [
                    "intercept" => false,
                    "namespace" => NS_MAIN,
                    "ModerationIgnoredInNamespaces" => [NS_MAIN, NS_PROJECT],
                ],
            ],
            "edit in Project namespace should bypass moderation " .
            'when $wgModerationIgnoredInNamespaces=[NS_MAIN, NS_PROJECT]' => [
                [
                    "intercept" => false,
                    "namespace" => NS_PROJECT,
                    "ModerationIgnoredInNamespaces" => [NS_MAIN, NS_PROJECT],
                ],
            ],
            "edit in User namespace should by intercepted " .
            'when $wgModerationIgnoredInNamespaces=[NS_MAIN, NS_PROJECT]' => [
                [
                    "namespace" => NS_USER,
                    "ModerationIgnoredInNamespaces" => [NS_MAIN, NS_PROJECT],
                ],
            ],

            // Uploads
            "upload should be intercepted" => [["action" => "upload"]],
            "upload by automoderated user should bypass moderation" => [
                [
                    "intercept" => false,
                    "action" => "upload",
                    "groups" => ["automoderated"],
                ],
            ],
            'upload should bypass moderation when $wgModerationIgnoredInNamespaces=[NS_FILE]' => [
                [
                    "intercept" => false,
                    "action" => "upload",
                    "ModerationIgnoredInNamespaces" => [NS_FILE],
                ],
            ],
            'upload should bypass moderation when $wgModerationOnlyInNamespaces=[NS_MAIN]' => [
                [
                    "intercept" => false,
                    "action" => "upload",
                    "ModerationOnlyInNamespaces" => [NS_MAIN],
                ],
            ],

            // Moves
            "move should be intercepted" => [["action" => "move"]],
            "move by automoderated user should bypass moderation" => [
                [
                    "intercept" => false,
                    "action" => "move",
                    "groups" => ["automoderated"],
                ],
            ],

            "source namespace being excluded is not enough for move to bypass moderation " .
            "(both source and target must be excluded from moderation)" => [
                [
                    "intercept" => true,
                    "action" => "move",
                    "ModerationIgnoredInNamespaces" => [self::DEFAULT_NS1],
                ],
            ],
            "target namespace being excluded is not enough for move to bypass moderation " .
            "(both source and target must be excluded from moderation)" => [
                [
                    "intercept" => true,
                    "action" => "move",
                    "ModerationIgnoredInNamespaces" => [self::DEFAULT_NS2],
                ],
            ],
            "move should bypass moderation if both source and target namespaces are excluded" => [
                [
                    "intercept" => false,
                    "action" => "move",
                    "ModerationIgnoredInNamespaces" => [
                        self::DEFAULT_NS1,
                        self::DEFAULT_NS2,
                    ],
                ],
            ],
        ];

        // Run each set via ApiBot and NonApiBot.
        $newSets = [];
        foreach ($sets as $description => $set) {
            $newSets["$description (API)"] = [$set[0] + ["viaApi" => true]];
            $newSets["$description (not API)"] = [
                $set[0] + ["viaApi" => false],
            ];
        }
        return $newSets;
    }

    /*-------------------------------------------------------------------*/
    /* TestSet of this test                                              */
    /*-------------------------------------------------------------------*/

    use ModerationTestsuiteTestSet;

    /**
     * @const Namespace which is used when not selected by the test.
     */
    private const DEFAULT_NS1 = NS_USER_TALK;

    /**
     * @const Second namespace which is used when not selected by the test.
     * Only needed for moves.
     */
    private const DEFAULT_NS2 = NS_HELP;

    /** @var bool If true, this change is expected to be intercepted. */
    protected $intercept = true;

    /** @var int Namespace of the test page */
    protected $namespace = self::DEFAULT_NS1;

    /** @var int Namespace of the new title (for moves) */
    protected $namespace2 = self::DEFAULT_NS2;

    /** @var array|null Extra configuration, e.g. [ 'ModerationIgnoredInNamespaces' => [] ] */
    protected $configVars = [];

    /** @var array Groups of the test user, e.g. [ 'sysop', 'bureaucrat' ] */
    protected $groups = [];

    /** @var bool If true, the edit will be anonymous. ($groups will be ignored) */
    protected $anonymously = false;

    /** @var bool If true, edits are made via API, if false, via the user interface. */
    protected $viaApi = false;

    /** @var string Operation to test, one of the following: 'edit', 'upload', 'move' */
    protected $action = "edit";

    /**
     * @var ModerationTestsuiteApiBotResponse|ModerationTestsuiteNonApiBotResponse
     * Result of edit(), move() or upload().
     */
    protected $result = null;

    /**
     * Initialize this TestSet from the input of dataProvider.
     * @param array $options
     */
    protected function applyOptions(array $options)
    {
        foreach ($options as $key => $value) {
            switch ($key) {
                case "ModerationEnable":
                case "ModerationIgnoredInNamespaces":
                case "ModerationOnlyInNamespaces":
                    $this->configVars[$key] = $value;
                    break;

                case "action":
                case "anonymously":
                case "intercept":
                case "groups":
                case "namespace":
                case "namespace2":
                case "viaApi":
                    $this->$key = $value;
                    break;

                default:
                    throw new UnexpectedValueException(
                        __CLASS__ . ": unknown key {$key} in options",
                    );
            }
        }
    }

    /**
     * Assert the state of the database after the edit.
     */
    protected function assertResults()
    {
        $this->assertSame(
            ["edit was intercepted" => $this->intercept],
            [
                "edit was intercepted" => $this->result->isIntercepted(),
            ],
        );

        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $row = $dbw->selectRow("moderation", "*", "", __METHOD__);

        $this->assertSame(
            ["edit was queued" => $this->intercept],
            ["edit was queued" => (bool) $row],
        );

        // Ensure that the target page is unmodified if the edit was supposed to be intercepted.
        // This page didn't exist before the test, so we can just check whether it exists now.
        $targetTitle =
            $this->action == "move"
                ? $this->getPage2Title()
                : $this->getTitle();
        $targetPageExists = (bool) $this->getTestsuite()->getLastRevision(
            $targetTitle->getFullText(),
        );
        $this->assertSame(
            ["target page was unchanged" => $this->intercept],
            ["target page was unchanged" => !$targetPageExists],
        );
    }

    /**
     * Get title of the affected page.
     * @return Title
     */
    protected function getTitle()
    {
        if ($this->action == "upload") {
            return Title::makeTitle(NS_FILE, "Test file 1.png");
        }

        return Title::makeTitle($this->namespace, "Test page 1");
    }

    /**
     * Get new title of the moved page.
     * @return Title
     */
    protected function getPage2Title()
    {
        return Title::makeTitle($this->namespace2, "Test page 2");
    }

    /**
     * Execute the TestSet, making an edit/upload/move with requested parameters.
     */
    protected function makeChanges()
    {
        $t = $this->getTestsuite();

        foreach ($this->configVars as $name => $value) {
            $t->setMwConfig($name, $value);
        }

        $title = $this->getTitle();
        $page2Title = $this->getPage2Title();

        if ($this->anonymously) {
            $this->disableAutoCreateTempUser();
            $user = User::newFromName("127.0.0.1", false);
        } else {
            $user = $t->unprivilegedUser;
            $userGroupManager = MediaWikiServices::getInstance()->getUserGroupManager();

            // Make sure that the test user doesn't have any groups
            foreach ($userGroupManager->getUserGroups($user) as $oldGroup) {
                $userGroupManager->removeUserFromGroup($user, $oldGroup);
            }

            // Add the user to groups requested by this test (if any)
            foreach ($this->groups as $group) {
                $userGroupManager->addUserToGroup($user, $group);

                if ($group == "rollback") {
                    // Rollback group (users with 'rollback' right) is not defined
                    // by default, so we need to configure it explicitly.
                    global $wgGroupPermissions;
                    $t->setMwConfig(
                        "GroupPermissions",
                        [
                            "rollback" => [
                                "rollback" => true,
                            ],
                        ] + $wgGroupPermissions,
                    );
                }
            }
        }

        if ($this->action == "move") {
            ModerationTestUtil::fastEdit($title);
        }

        if ($this->action == "upload") {
            // Workaround for archive name collisions when test image with the same name
            // is uploaded by 2 consecutive tests during the same second.
            $t->sleepUntilNextSecond();
        }

        $t->loginAs($user);
        $bot = $t->getBot($this->viaApi ? "api" : "nonApi");

        switch ($this->action) {
            case "edit":
                $this->result = $bot->edit(
                    $title->getFullText(),
                    "New text",
                    "Summary",
                );
                break;

            case "move":
                $this->result = $bot->move(
                    $title->getFullText(),
                    $page2Title->getFullText(),
                );
                break;

            case "upload":
                $this->result = $bot->upload($title->getText(), "", "");
                break;

            default:
                throw new MWException(
                    'Unknown "action" requested in TestSet of ' . __CLASS__,
                );
        }
    }
}
