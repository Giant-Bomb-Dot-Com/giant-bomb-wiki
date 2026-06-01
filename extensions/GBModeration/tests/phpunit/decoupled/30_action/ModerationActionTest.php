<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2018-2025 Edward Chernenko.

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
 * Checks consequences of the moderation actions on Special:Moderation.
 */

namespace MediaWiki\Moderation\Tests;

use DatabaseLogEntry;
use MediaWiki\Moderation\ModerationActionShowImage;
use MediaWiki\Moderation\ModerationCompatTools;
use MediaWiki\Moderation\ModerationUploadStorage;
use MediaWiki\Title\Title;
use MWException;
use SpecialPage;
use User;

require_once __DIR__ . "/../../framework/ModerationTestsuite.php";

/**
 * @group Database
 * @covers MediaWiki\Moderation\ModerationAction
 */
class ModerationActionTest extends ModerationTestCase
{
    /**
     * @dataProvider dataProvider
     */
    public function testAction(array $options)
    {
        $this->runSet($options);
    }

    /**
     * Provide datasets for testAction() runs.
     */
    public function dataProvider()
    {
        global $wgModerationTimeToOverrideRejection;

        $sets = [
            "successful Reject" => [
                [
                    "modaction" => "reject",
                    "expectedOutput" => "(moderation-rejected-ok: 1)",
                    "expectRejected" => true,
                ],
            ],

            "successful RejectAll" => [
                [
                    "modaction" => "rejectall",
                    "expectedOutput" => "(moderation-rejected-ok: 1)",
                    "expectRejected" => true,
                    "expectedFields" => [
                        "mod_rejected_batch" => 1,
                    ],
                    "expectedLogTargetIsAuthor" => true,
                ],
            ],

            "successful Approve (newly created page)" => [
                [
                    "modaction" => "approve",
                    "expectedOutput" => "(moderation-approved-ok: 1)",
                    "expectApproved" => true,
                    "expectReturnLinks" => ["Test page 1"],
                ],
            ],
            "successful Approve (edit on the existing page)" => [
                [
                    "modaction" => "approve",
                    "existing" => true, // edit in existing page
                    "expectedOutput" => "(moderation-approved-ok: 1)",
                    "expectApproved" => true,
                    "expectReturnLinks" => ["Test page 1"],
                ],
            ],
            "successful Approve (minor edit on the existing page)" => [
                [
                    "modaction" => "approve",
                    "mod_minor" => 1,
                    "existing" => true, // edit in existing page
                    "expectedOutput" => "(moderation-approved-ok: 1)",
                    "expectApproved" => true,
                    "expectReturnLinks" => ["Test page 1"],
                ],
            ],
            "successful Approve (previously rejected change)" => [
                [
                    "modaction" => "approve",
                    "mod_rejected" => 1,
                    "expectedOutput" => "(moderation-approved-ok: 1)",
                    "expectApproved" => true,
                    "expectReturnLinks" => ["Test page 1"],
                ],
            ],
            "successful Approve should preserve the timestamp of original edit" => [
                [
                    "modaction" => "approve",
                    "mod_timestamp" => "-6 hours",
                    "expectedOutput" => "(moderation-approved-ok: 1)",
                    "expectApproved" => true,
                    "expectReturnLinks" => ["Test page 1"],
                ],
            ],
            "successful Approve (upload)" => [
                [
                    "modaction" => "approve",
                    "filename" => "image100x100.png",
                    "expectedOutput" => "(moderation-approved-ok: 1)",
                    "expectApproved" => true,
                    "expectReturnLinks" => ["File:Image100x100.png"],
                ],
            ],
            "successful Approve (reupload)" => [
                [
                    "modaction" => "approve",
                    "existing" => true, // reupload
                    "filename" => "image100x100.png",
                    "expectedOutput" => "(moderation-approved-ok: 1)",
                    "expectApproved" => true,
                    "expectReturnLinks" => ["File:Image100x100.png"],
                ],
            ],
            "successful Approve (move)" => [
                [
                    "modaction" => "approve",
                    "mod_type" => "move",
                    "expectedOutput" => "(moderation-approved-ok: 1)",
                    "expectApproved" => true,
                    "expectedLogAction" => "approve-move",
                ],
            ],

            "successful Approve (tagged edit)" => [
                [
                    "modaction" => "approve",
                    "expectedOutput" => "(moderation-approved-ok: 1)",
                    "expectApproved" => true,
                    "mod_tags" => "Cat-related tag\nTag about dogs",
                    "expectReturnLinks" => ["Test page 1"],
                ],
            ],
            "successful Approve (tagged upload)" => [
                [
                    "modaction" => "approve",
                    "filename" => "image100x100.png",
                    "expectedOutput" => "(moderation-approved-ok: 1)",
                    "expectApproved" => true,
                    "mod_tags" => "Cat-related tag\nTag about dogs",
                    "expectReturnLinks" => ["File:Image100x100.png"],
                ],
            ],
            "successful Approve (tagged move)" => [
                [
                    "modaction" => "approve",
                    "mod_type" => "move",
                    "expectedOutput" => "(moderation-approved-ok: 1)",
                    "expectApproved" => true,
                    "expectedLogAction" => "approve-move",
                    "mod_tags" => "Cat-related tag\nTag about dogs",
                ],
            ],

            "successful ApproveAll" => [
                [
                    "modaction" => "approveall",
                    "expectedOutput" => "(moderation-approved-ok: 1)",
                    "expectApproved" => true,
                    "expectedLogTargetIsAuthor" => true,
                ],
            ],

            "successful Reject (edit with the conflict)" => [
                [
                    "modaction" => "reject",
                    "mod_conflict" => 1,
                    "expectedOutput" => "(moderation-rejected-ok: 1)",
                    "expectRejected" => true,
                ],
            ],

            // Actions show/preview/merge/block/unblock/editchange shouldn't change the row
            "Show (null edit)" => [
                [
                    "modaction" => "show",
                    "nullEdit" => true,
                    "expectedOutput" => "(moderation-diff-no-changes)",
                    "expectActionLinks" => [
                        "approve" => false,
                        "reject" => true,
                    ],
                ],
            ],
            "ShowImage (100x100 PNG image)" => [
                [
                    "modaction" => "showimg",
                    "filename" => "image100x100.png",
                    "expectedContentType" => "image/png",
                    "expectedContentDisposition" =>
                        "inline;filename*=UTF-8''Image100x100.png",
                    "expectOutputToEqualUploadedFile" => true,
                ],
            ],
            "EditChange" => [
                [
                    "modaction" => "editchange",
                    "enableEditChange" => true,
                ],
            ],
            "Merge" => [["mod_conflict" => 1, "modaction" => "merge"]],

            // Check block/unblock
            "successful Block" => [
                [
                    "modaction" => "block",
                    "expectModblocked" => true,
                    "expectedOutput" => "moderation-block-ok",
                    "expectedLogTargetIsAuthor" => true,
                ],
            ],
            "successful Unblock" => [
                [
                    "modaction" => "unblock",
                    "modblocked" => true,
                    "expectModblocked" => false,
                    "expectedOutput" => "moderation-unblock-ok",
                    "expectedLogTargetIsAuthor" => true,
                ],
            ],
            "duplicate attempt to Block (already blocked)" => [
                [
                    // Can happen if moderator clicked twice on "Mark as spammer" link.
                    // Should report success, but shouldn't create a new LogEntry.
                    "modaction" => "block",
                    "modblocked" => true,
                    "expectedOutput" => "moderation-block-ok",
                    "expectLogEntry" => false,
                ],
            ],
            "duplicate attempt to Unblock (already unblocked)" => [
                [
                    // Should report success, but shouldn't create a new LogEntry.
                    "modaction" => "unblock",
                    "expectedOutput" => "moderation-unblock-ok",
                    "expectLogEntry" => false,
                ],
            ],

            "Show (normal edit)" => [
                [
                    "modaction" => "show",
                    "mod_title" => "Test_page_1",
                    "mod_text" =>
                        "This text is '''very bold''' and ''most italic''.\n",
                    "mod_new_len" => 49,
                    "expectActionLinks" => [
                        "approve" => true,
                        "reject" => true,
                    ],
                    "expectedHtmlTitle" => "(difference-title: Test page 1)",

                    // Shouldn't render any HTML: modaction=show displays wikitext
                    "expectedOutputHtml" =>
                        "This text is '''very bold''' and ''most italic''.",
                ],
            ],
            "Show (already rejected edit)" => [
                [
                    "modaction" => "show",
                    "mod_rejected" => 1,
                    "mod_rejected_by_user" => 12345,
                    "mod_rejected_by_user_text" => "Some moderator",
                    "expectActionLinks" => [
                        "approve" => true,
                        "reject" => false,
                    ],
                    "expectedOutput" =>
                        "(moderation-rejected-by: Some moderator, Some moderator)",
                ],
            ],

            // modaction=preview
            "Preview (normal edit)" => [
                [
                    "modaction" => "preview",
                    "mod_title" => "Test_page_1",
                    "mod_text" =>
                        "This text is '''very bold''' and ''most italic''.\n",
                    "mod_new_len" => 49,
                    "expectedOutputHtml" =>
                        "This text is <b>very bold</b> and <i>most italic</i>.",
                    "expectedHtmlTitle" =>
                        "(moderation-preview-title: Test page 1)",
                ],
            ],

            // Check modaction=show for uploads/moves
            "Show (100x100 PNG image, no description)" => [
                [
                    "modaction" => "show",
                    "filename" => "image100x100.png",
                    "nullEdit" => true,
                    "expectedOutput" => "(moderation-diff-upload-notext)",
                    "expectShowImageLink" => true,
                    "expectShowImageThumbnail" => true,
                ],
            ],
            "Show (100x100 PNG image, with description)" => [
                [
                    "modaction" => "show",
                    "filename" => "image100x100.png",
                    "mod_text" => "Funny description",
                    "mod_new_len" => 17,
                    "expectedOutput" => "Funny description",
                    "expectShowImageLink" => true,
                    "expectShowImageThumbnail" => true,
                ],
            ],
            "Show (non-image upload: OGG audio file, no description)" => [
                [
                    "modaction" => "show",
                    "filename" => "sound.ogg",
                    "nullEdit" => true,
                    "expectedOutput" => "(moderation-diff-upload-notext)",
                    "expectShowImageLink" => true,
                ],
            ],
            "Show (move)" => [
                [
                    "modaction" => "show",
                    "mod_type" => "move",
                    "mod_namespace" => NS_TEMPLATE,
                    "mod_title" => "OrigTitle",
                    "mod_page2_namespace" => NS_CATEGORY,
                    "mod_page2_title" => "NewTitle",
                    "expectedOutput" =>
                        "(movepage-page-moved: Template:OrigTitle, Category:NewTitle)",
                ],
            ],

            // Errors printed by actions:
            "Error: unknown modaction" => [
                [
                    "modaction" => "makesandwich",
                    "expectedError" => "(moderation-unknown-modaction)",
                ],
            ],
            "Error: attempt to Reject already rejected edit" => [
                [
                    "modaction" => "reject",
                    "mod_rejected" => 1,
                    "expectedError" => "(moderation-already-rejected)",
                ],
            ],
            "Error: attempt to Approve a null edit (same text as current text of the page)" => [
                [
                    "modaction" => "approve",
                    "existing" => true,
                    "nullEdit" => true,
                    "expectedError" => "(edit-no-change)",
                    "expectRejected" => true,
                    "expectedLogAction" => "reject",
                ],
            ],
            "Error: attempt to Approve edit which was rejected long ago" => [
                [
                    "modaction" => "approve",
                    "mod_rejected" => 1,
                    "mod_timestamp" =>
                        "-" .
                        ($wgModerationTimeToOverrideRejection + 1) .
                        " seconds",
                    "expectedError" => "(moderation-rejected-long-ago)",
                ],
            ],
            "Error: attempt to Approve edit which should cause an error in doEditContent" => [
                [
                    // approve: handing of situation when doEditContent() results in an error
                    "modaction" => "approve",
                    "simulateInvalidJsonContent" => true,
                    "expectedError" =>
                        "(invalid-json-data: (json-error-syntax))",
                ],
            ],
            "Error: attempt to Approve upload when the file was deleted from stash" => [
                [
                    "modaction" => "approve",
                    "filename" => "image100x100.png",
                    "simulateMissingStashedImage" => true,
                    "expectedError" => "(moderation-missing-stashed-image)",
                ],
            ],
            "Error: attempt to ApproveAll when there is nothing to approve" => [
                [
                    "modaction" => "approveall",
                    "mod_rejected" => 1,
                    "expectedError" => "(moderation-nothing-to-approveall)",
                ],
            ],
            "Error: attempt to RejectAll when there is nothing to reject" => [
                [
                    "modaction" => "rejectall",
                    "mod_rejected" => 1,
                    "expectedError" => "(moderation-nothing-to-rejectall)",
                ],
            ],
            "Error: attempt to Merge when there is no edit conflict" => [
                [
                    "modaction" => "merge",
                    "expectedError" => "(moderation-merge-not-needed)",
                ],
            ],
            "Error: attempt to Merge by non-automoderated moderator" => [
                [
                    "modaction" => "merge",
                    "mod_conflict" => 1,
                    "notAutomoderated" => true,
                    "expectedError" => "(moderation-merge-not-automoderated)",
                ],
            ],

            // editchange{,submit} shouldn't be available without $wgModerationEnableEditChange
            'Error: attempt to EditChange without $wgModerationEnableEditChange' => [
                [
                    "modaction" => "editchange",
                    "expectedError" => "(moderation-unknown-modaction)",
                ],
            ],
            'Error: attempt to EditChangeSubmit without $wgModerationEnableEditChange' => [
                [
                    "modaction" => "editchangesubmit",
                    "expectedError" => "(moderation-unknown-modaction)",
                ],
            ],
            "Error: attempt to use EditChange on non-text change (page move)" => [
                [
                    "modaction" => "editchange",
                    "enableEditChange" => true,
                    "mod_type" => "move",
                    "expectedError" => "(moderation-editchange-not-edit)",
                ],
            ],
            "Error: attempt to use EditChangeSubmit on non-text change (page move)" => [
                [
                    "modaction" => "editchangesubmit",
                    "enableEditChange" => true,
                    "mod_type" => "move",
                    "expectedError" => "(moderation-edit-not-found)",
                ],
            ],

            // Actions that don't modify anything shouldn't throw ReadOnlyError
            "Show (when wiki is readonly)" => [
                ["modaction" => "show", "readonly" => true],
            ],
            "ShowImage (when wiki is readonly)" => [
                [
                    "modaction" => "showimg",
                    "filename" => "image100x100.png",
                    "readonly" => true,
                    "expectedContentType" => "image/png",
                    "expectedContentDisposition" =>
                        "inline;filename*=UTF-8''Image100x100.png",
                    "expectOutputToEqualUploadedFile" => true,
                ],
            ],
            "Preview (when wiki is readonly)" => [
                [
                    "modaction" => "preview",
                    "readonly" => true,
                ],
            ],

            "successful EditChangeSubmit" => [
                [
                    "modaction" => "editchangesubmit",
                    "enableEditChange" => true,
                    "mod_user" => 0,
                    "mod_user_text" => "127.1.2.3",
                    "postData" => [
                        "wpTextbox1" => "Modified text ~~~", // "~~~" is to test PreSaveTransform
                        "wpSummary" => "Modified edit summary",
                    ],
                    "expectedOutput" => "(moderation-editchange-ok)",
                    "expectedFields" => [
                        "mod_text" =>
                            "Modified text (signature-anon: 127.1.2.3, 127.1.2.3)",
                        "mod_new_len" => 52,
                        "mod_comment" => "Modified edit summary",
                    ],
                    "expectedLogAction" => "editchange",
                ],
            ],
            'no-op EditChangeSubmit (the original text hasnt\'t been changed)' => [
                [
                    "modaction" => "editchangesubmit",
                    "enableEditChange" => true,
                    "mod_text" => "Original Text 1",
                    "mod_comment" => "Original Summary 1",
                    "mod_new_len" => 15,
                    "postData" => [
                        "wpTextbox1" => "Original Text 1",
                        "wpSummary" => "Original Summary 1",
                    ],
                    "expectedOutput" => "(moderation-editchange-ok)",
                    "expectLogEntry" => false,
                ],
            ],

            /*
				modaction=showimg.
				NOTE: when testing thumbnails, we check two images:
				one smaller than thumbnail's width, one larger,
				because they are handled differently.
				First test is on image640x50.png (large image),
				second on image100x100.png (smaller image).
			*/
            "ShowImage (650x50 PNG image)" => [
                [
                    "modaction" => "showimg",
                    "filename" => "image640x50.png",
                    "mod_title" => "Image_name_with_spaces.png",
                    "expectedContentType" => "image/png",
                    "expectedContentDisposition" =>
                        "inline;filename*=UTF-8''Image_name_with_spaces.png",
                    "expectOutputToEqualUploadedFile" => true,
                ],
            ],
            "ShowImage: thumbnail of 650x50 PNG image (larger than thumbnail width)" => [
                [
                    "modaction" => "showimg",
                    "filename" => "image640x50.png",
                    "expectedContentType" => "image/png",
                    "expectedContentDisposition" =>
                        "inline;filename*=UTF-8''" .
                        ModerationActionShowImage::THUMB_WIDTH .
                        "px-Image640x50.png",
                    "showThumb" => true,
                    "expectOutputToEqualUploadedFile" => false, // Thumbnail, not original image
                    "expectedImageWidth" =>
                        ModerationActionShowImage::THUMB_WIDTH,
                ],
            ],
            "ShowImage: thumbnail of 100x100 PNG image (smaller than thumbnail width)" => [
                [
                    "modaction" => "showimg",
                    "filename" => "image100x100.png",
                    "expectedContentType" => "image/png",
                    "expectedContentDisposition" =>
                        "inline;filename*=UTF-8''Image100x100.png",
                    "showThumb" => true,

                    // This image is not wide enough,
                    // its thumbnail will be the same as the original image.
                    "expectOutputToEqualUploadedFile" => true,
                ],
            ],
            "ShowImage (non-image upload: OGG audio file)" => [
                [
                    "modaction" => "showimg",
                    "filename" => "sound.ogg",
                    "expectedContentType" => "application/ogg",
                    "expectedContentDisposition" =>
                        "inline;filename*=UTF-8''Sound.ogg",
                    "expectOutputToEqualUploadedFile" => true,
                ],
            ],
            "ShowImage: thumbnail mode for non-image (OGG audio file)" => [
                [
                    "modaction" => "showimg",
                    "filename" => "sound.ogg",
                    "expectedContentType" => "application/ogg",
                    "expectedContentDisposition" =>
                        "inline;filename*=UTF-8''Sound.ogg",
                    "showThumb" => true,

                    // OGG is not an image, thumbnail will be the same as the original file.
                    "expectOutputToEqualUploadedFile" => true,
                ],
            ],
            "ShowImage: missing stash image" => [
                [
                    "modaction" => "showimg",
                    "filename" => "image100x100.png",
                    "simulateMissingStashedImage" => true,
                    "expectedHttpStatus" => 404,
                ],
            ],
        ];

        // "Already merged" error
        foreach (["approve", "reject", "merge"] as $action) {
            $sets[
                "Error: modaction=$action used on edit which is already merged"
            ] = [
                [
                    "modaction" => $action,
                    "mod_conflict" => 1,
                    "mod_merged_revid" => 12345,
                    "expectedError" => "(moderation-already-merged)",
                ],
            ];
        }

        // ReadOnlyError exception from non-readonly actions
        $nonReadOnlyActions = [
            "approve",
            "approveall",
            "reject",
            "rejectall",
            "block",
            "unblock",
            "merge",
            "editchange",
            "editchangesubmit",
        ];
        foreach ($nonReadOnlyActions as $action) {
            $sets[
                "Error: ReadOnlyError exception from non-readonly modaction=$action"
            ] = [
                [
                    "modaction" => $action,
                    "readonly" => true,
                    "expectReadOnlyError" => true,
                ],
            ];
        }

        // 'moderation-edit-not-found' from everything
        $allActions = array_merge($nonReadOnlyActions, [
            "show",
            "showimg",
            "preview",
        ]);
        foreach ($allActions as $action) {
            $options = [
                "modaction" => $action,
                "simulateNoSuchEntry" => true,
                "expectedError" => "(moderation-edit-not-found)",
            ];
            if ($action == "editchange" || $action == "editchangesubmit") {
                $options["enableEditChange"] = true;
            }

            $sets["Error: \"edit not found\" from modaction=$action"] = [
                $options,
            ];
        }

        return $sets;
    }

    /*-------------------------------------------------------------------*/
    /* TestSet of this test                                              */
    /*-------------------------------------------------------------------*/

    use ModerationTestsuitePendingChangeTestSet {
        applyOptions as parentApplyOptions;
    }

    private const READONLY_REASON = "Simulated ReadOnly mode";

    private const USER_AGENT_OF_AUTHOR =
        ModerationTestsuite::DEFAULT_USER_AGENT . " (AuthorOfEdit)";

    private const USER_AGENT_OF_MODERATOR =
        ModerationTestsuite::DEFAULT_USER_AGENT . " (Moderator)";

    /**
     * @var string Name of action, e.g. 'approve' or 'rejectall'.
     */
    protected $modaction = null;

    /**
     * @var bool If true, $wgModerationEnableEditChange is enabled.
     */
    protected $enableEditChange = false;

    /**
     * @var array Expected field values after the action.
     * Field that are NOT in this array are expected to be unmodified.
     */
    protected $expectedFields = [];

    /**
     * @var string|null Error that should be printed by this action, e.g. "(sessionfailure)".
     */
    protected $expectedError = null;

    /**
     * @var string|null Expected subtype of LogEntry. If null, assumed to be same as $modaction.
     * Example of non-default value: 'approve-move' for modaction=approve.
     */
    protected $expectedLogAction = null;

    /**
     * @var bool If true, new LogEntry is expected to appear after the action.
     * If false, LogEntry is expected to NOT appear. If null, auto-detect.
     */
    protected $expectLogEntry = null;

    /**
     * @var bool If true, userpage of author of the change is the expected target of LogEntry.
     */
    protected $expectedLogTargetIsAuthor = false;

    /**
     * @var string|null Expected value of Content-Disposition header (if any) or null.
     */
    protected $expectedContentDisposition = null;

    /**
     * @var string|null If not null, we expect response to have this Content-Type (e.g. image/png).
     */
    protected $expectedContentType = null;

    /**
     * @var int Expected HTTP status code of the response (e.g. 404 for "Not Found").
     */
    protected $expectedHttpStatus = 200;

    /**
     * @var int|null If not null, we expect response to be an image with this width.
     */
    protected $expectedImageWidth = null;

    /**
     * @var string|null If not null, we expect <h1> tag to contain this string.
     */
    protected $expectedHtmlTitle = null;

    /**
     * @var bool|null If true/false, author of change is expected to become (not) modblocked.
     * If null, blocked status is expected to remain the same.
     */
    protected $expectModblocked = null;

    /**
     * @var array Action links to expect and NOT expect on the result page.
     * Example: [ 'approve' => true, 'reject' => false ].
     * Not listed actions are not checked for existence/nonexistence.
     */
    protected $expectActionLinks = [];

    /**
     * @var array Page names of expected "Return to" links (not counting Special:Moderation).
     */
    protected $expectReturnLinks = [];

    /**
     * @var bool If true, output must have a link to modaction=showimg.
     */
    protected $expectShowImageLink = false;

    /**
     * @var bool If true, output must have a thumbnail image.
     */
    protected $expectShowImageThumbnail = false;

    /**
     * @var bool If true, binary output of this modaction must be the same as content of $filename.
     */
    protected $expectOutputToEqualUploadedFile = false;

    /**
     * @var bool If true, we expect ReadOnlyError exception to be thrown.
     */
    protected $expectReadOnlyError = false;

    /**
     * @var bool If true, rejection fields will be added to $expectedFields.
     */
    protected $expectRejected = false;

    /**
     * @var bool If true, post-approve consequences are tested (e.g. that database row is deleted).
     * ($expectedFields are ignored).
     */
    protected $expectApproved = false;

    /**
     * @var string Plaintext that should be present in the output of modaction.
     */
    protected $expectedOutput = "";

    /**
     * @var string Raw HTML that should be present in the output of modaction.
     * @see $expectedOutput
     */
    protected $expectedOutputHtml = "";

    /**
     * @var array Request body to send with POST request.
     */
    protected $postData = [];

    /**
     * @var bool If true, the wiki will be in readonly mode.
     */
    protected $readonly = false;

    /**
     * @var bool If true, page will be of JSON model and content of this edit will be invalid JSON.
     * This is used for test "does modaction=approve detect errors from doEditContent?"
     */
    protected $simulateInvalidJsonContent = false;

    /**
     * @var bool If true, uploaded image would be deleted from stash.
     */
    protected $simulateMissingStashedImage = false;

    /**
     * @var bool If true, incorrect modid will be used in the action URL.
     */
    protected $simulateNoSuchEntry = false;

    /**
     * @var bool If true, thumb=1 will be added to action URL (for modaction=showimg).
     */
    protected $showThumb = false;

    /**
     * Initialize this TestSet from the input of dataProvider.
     * @param array $options
     */
    protected function applyOptions(array $options)
    {
        $options["mod_header_ua"] = self::USER_AGENT_OF_AUTHOR;

        foreach ($options as $key => $value) {
            switch ($key) {
                case "enableEditChange":
                case "expectedContentType":
                case "expectedContentDisposition":
                case "expectedHttpStatus":
                case "expectedFields":
                case "expectedError":
                case "expectedImageWidth":
                case "expectedHtmlTitle":
                case "expectedLogAction":
                case "expectLogEntry":
                case "expectedLogTargetIsAuthor":
                case "expectModblocked":
                case "expectedOutput":
                case "expectedOutputHtml":
                case "expectActionLinks":
                case "expectReturnLinks":
                case "expectShowImageLink":
                case "expectShowImageThumbnail":
                case "expectOutputToEqualUploadedFile":
                case "expectReadOnlyError":
                case "expectRejected":
                case "expectApproved":
                case "modaction":
                case "postData":
                case "readonly":
                case "simulateInvalidJsonContent":
                case "simulateMissingStashedImage":
                case "simulateNoSuchEntry":
                case "showThumb":
                    $this->$key = $value;
                    unset($options[$key]);
            }
        }

        if (!$this->modaction) {
            throw new MWException(
                __CLASS__ . ": parameter 'modaction' is required",
            );
        }

        if ($this->expectLogEntry === null) {
            // Auto-detect whether the log entry is needed.
            $this->expectLogEntry = false;
            if (
                $this->expectedFields ||
                $this->expectRejected ||
                $this->expectApproved ||
                $this->expectModblocked !== null
            ) {
                $this->expectLogEntry = true;
            }
        }

        $this->parentApplyOptions($options);

        if ($this->simulateInvalidJsonContent) {
            $this->fields["mod_text"] = "NOT A VALID JSON";
            $this->fields["mod_new_len"] = 16;

            $this->getTestsuite()->setMwConfig("NamespaceContentModels", [
                $this->fields["mod_namespace"] => CONTENT_MODEL_JSON,
            ]);
        }

        if ($this->simulateMissingStashedImage) {
            $stash = ModerationUploadStorage::getStash();
            $stash->removeFile($this->fields["mod_stash_key"]);
        }

        $this->expectedFields += $this->fields;

        if (!$this->expectedLogAction) {
            // Default: $expectedLogAction is the same as $modaction
            // (e.g. modaction=reject creates 'moderation/reject' log entries).
            $this->expectedLogAction = $this->modaction;
        }

        if ($this->expectedContentType == "application/ogg") {
            // Allow OGG files (music, i.e. not images) to be uploaded.
            global $wgFileExtensions;
            $this->getTestsuite()->setMwConfig(
                "FileExtensions",
                array_merge($wgFileExtensions, ["ogg"]),
            );
        }
    }

    /**
     * Returns the expected target of LogEntry created by this modaction.
     * @return Title
     */
    protected function getExpectedLogTarget()
    {
        if ($this->expectedLogTargetIsAuthor) {
            // For actions like 'block' or 'rejectall':
            // target is the author's userpage.
            return Title::makeTitle(NS_USER, $this->fields["mod_user_text"]);
        }

        // Default (for actions like 'approve' or 'reject'):
        // target is the page affected by this change.
        return $this->getExpectedTitleObj();
    }

    /**
     * Returns the User who will perform this modaction.
     * @return User
     */
    protected function getModerator()
    {
        $t = $this->getTestsuite();
        return $this->notAutomoderated
            ? $t->moderatorButNotAutomoderated
            : $t->moderator;
    }

    /**
     * Assert the consequences of the action.
     */
    protected function assertResults()
    {
        // Add rejection-related fields to $this->expectedFields.
        // It was too early to do in applyOptions(), because $this->fields['mod_id'] was unknown.
        if ($this->expectRejected) {
            $this->expectedFields = array_merge($this->expectedFields, [
                "mod_rejected" => 1,
                "mod_rejected_by_user" => $this->getModerator()->getId(),
                "mod_rejected_by_user_text" => $this->getModerator()->getName(),
                "mod_preloadable" => $this->fields["mod_id"],
            ]);
        }

        $t = $this->getTestsuite();

        if ($this->readonly) {
            // We can't use CACHE_DB for SessionCache in ReadOnly mode,
            // because the database won't be writable.
            $t->setMwConfig("SessionCacheType", CACHE_MEMCACHED);

            if (!$t->doesMemcachedWork()) {
                // No way to login when both Memcached and CACHE_DB are unavailable.
                $this->markTestSkipped(
                    "Test skipped: Memcached is unavailable (ReadOnly tests need it for login to work)",
                );
            }
        }

        $t->loginAs($this->getModerator());
        $t->setUserAgent(self::USER_AGENT_OF_MODERATOR);

        if ($this->readonly) {
            $t->setMwConfig("ReadOnly", self::READONLY_REASON);
        }

        if ($this->enableEditChange) {
            $t->setMwConfig("ModerationEnableEditChange", true);
        }

        if ($this->existing && $this->filename && $this->expectApproved) {
            /* Wait up to 1 second to avoid archived name collision */
            $t->sleepUntilNextSecond();
        }

        // Execute the action, check HTML printed by the action
        $url = $this->getActionURL();
        $req =
            $this->modaction == "editchangesubmit"
                ? $t->httpPost($url, $this->postData)
                : $t->httpGet($url);

        // Clear the link cache, so that methods like $title->getLatestRevID()
        // wouldn't return stale data after modaction=approve.
        Title::clearCaches();

        $this->assertSame(
            $this->expectedHttpStatus,
            $req->getStatus(),
            "modaction={$this->modaction}: wrong HTTP status code.",
        );

        if ($this->expectedContentType) {
            $this->assertBinaryOutput($req);
        } else {
            $this->assertHtmlOutput($t->html->loadReq($req));
        }

        // Check the mod_* fields in the database after the action.
        $this->assertDatabaseChanges();
        $this->assertApproved();
        $this->assertBlockedStatus();
        $this->assertLogEntry();
    }

    /**
     * Check HTML output printed by the action URL.
     * @param ModerationTestsuiteHTML $html
     * @see assertBinaryOutput
     */
    protected function assertHtmlOutput(ModerationTestsuiteHTML $html)
    {
        if ($this->expectedHtmlTitle) {
            $this->assertSame(
                "(pagetitle: " . $this->expectedHtmlTitle . ")",
                $html->getTitle(),
                "modaction={$this->modaction}: unexpected HTML title.",
            );
        }

        if ($this->expectedOutputHtml) {
            $this->assertStringContainsString(
                $this->expectedOutputHtml,
                $html->saveHTML($html->getMainContent()),
                "modaction={$this->modaction}: unexpected HTML output.",
            );
        }

        $output = $html->getMainText() ?? "";
        if ($this->expectedOutput) {
            $this->assertStringContainsString(
                $this->expectedOutput,
                $output,
                "modaction={$this->modaction}: unexpected output.",
            );
        }

        $expectedReadOnlyText = "(readonlytext: " . self::READONLY_REASON . ")";
        if ($this->expectReadOnlyError) {
            $this->assertStringContainsString(
                $expectedReadOnlyText,
                $output,
                "modaction={$this->modaction}: no ReadOnlyError exception in ReadOnly mode.",
            );
        } else {
            $this->assertStringNotContainsString(
                $expectedReadOnlyText,
                $output,
                "modaction={$this->modaction}: unexpected ReadOnlyError exception.",
            );
        }

        $error = $html->getModerationError();
        if ($this->expectedError) {
            $this->assertSame(
                $this->expectedError,
                $error,
                "modaction={$this->modaction}: expected error not shown.",
            );
        } else {
            $this->assertNull(
                $this->expectedError,
                "modaction={$this->modaction}: unexpected error.",
            );
        }

        foreach ($this->expectActionLinks as $action => $isExpected) {
            $link = $html->getElementByXPath(
                '//a[contains(@href,"modaction=' . $action . '")]',
            );
            $this->assertSame(
                ["action link [$action] exists" => $isExpected],
                [
                    "action link [$action] exists" => (bool) $link,
                ],
            );
        }

        $link = $html->getElementByXPath(
            '//*[@id="mw-content-text"]//a[contains(@href,"modaction=showimg")]',
        );
        if ($this->expectShowImageLink) {
            $this->assertNotNull($link, "Missing show image link");

            $expectedUrl = SpecialPage::getTitleFor("Moderation")->getLocalURL([
                "modaction" => "showimg",
                "modid" => $this->fields["mod_id"],
            ]);
            $this->assertSame($expectedUrl, $link->getAttribute("href"));
        } else {
            $this->assertNull($link, "Unexpected show image link");
        }

        $thumb = $html->getElementByXPath(
            '//img[contains(@src,"modaction=showimg")]',
            $link,
        );
        if ($this->expectShowImageThumbnail) {
            $this->assertNotNull($thumb, "Missing show image thumbnail");

            $expectedUrl = SpecialPage::getTitleFor("Moderation")->getLocalURL([
                "modaction" => "showimg",
                "modid" => $this->fields["mod_id"],
                "thumb" => 1,
            ]);
            $this->assertSame($expectedUrl, $thumb->getAttribute("src"));
        } else {
            $this->assertNull($thumb, "Unexpected show image thumbnail");
        }

        $this->assertReturnLinks($html);
    }

    /**
     * Check "Return to" links in output printed by the action URL.
     * @param ModerationTestsuiteHTML $html
     * @see assertBinaryOutput
     */
    protected function assertReturnLinks(ModerationTestsuiteHTML $html)
    {
        $expectedReturnTo = [];
        if ($this->expectedHttpStatus !== 404) {
            if ($this->expectReadOnlyError) {
                $expectedReturnTo[] = ["(mainpage)", null];
            } else {
                $expectedReturnTo[] = [
                    "Special:Moderation",
                    SpecialPage::getTitleFor("Moderation"),
                ];
                foreach ($this->expectReturnLinks as $pageName) {
                    $expectedReturnTo[] = [
                        $pageName,
                        Title::newFromText($pageName),
                    ];
                }
            }
        }

        $returnLinks = $html->getElementsByXPath(
            '//*[@id="mw-returnto"]/a' .
                "|" .
                '//*[@class="mw-returnto-extra"]/a',
        );
        $this->assertCount(
            count($expectedReturnTo),
            $returnLinks,
            'Unexpected number of "Return to" links.',
        );

        foreach ($returnLinks as $idx => $link) {
            [$expectedPageName, $expectedTitle] = $expectedReturnTo[$idx];

            $this->assertSame(
                "(returnto: $expectedPageName)",
                $link->parentNode->textContent,
            );
            if ($expectedTitle) {
                $this->assertSame(
                    $expectedTitle->getLocalURL(),
                    $link->getAttribute("href"),
                );
            }
        }
    }

    /**
     * Check non-HTML output printed by the action URL.
     * @param IModerationTestsuiteResponse $req
     * @see assertHtmlOutput
     */
    protected function assertBinaryOutput(IModerationTestsuiteResponse $req)
    {
        $this->assertSame(
            $this->expectedContentType,
            $req->getResponseHeader("Content-Type"),
            "modaction={$this->modaction}: wrong Content-Type header.",
        );

        if ($this->filename) {
            $origFile = file_get_contents($this->findSourceFilename());
            $downloadedFile = $req->getContent();

            $testedMetric = "output matches contents of [{$this->filename}]";
            $this->assertSame(
                [
                    $testedMetric => $this->expectOutputToEqualUploadedFile,
                ],
                [$testedMetric => $origFile == $downloadedFile],
            );

            if (isset($this->expectedImageWidth)) {
                // Determine width/height of image in $req->getContent().
                [$width, $height] = $this->getImageSize($downloadedFile);

                $this->assertSame(
                    $this->expectedImageWidth,
                    $width,
                    "modaction={$this->modaction}: thumbnail's width doesn't match expected",
                );

                // Has the ratio been preserved?
                [$origWidth, $origHeight] = $this->getImageSize($origFile);

                $this->assertSame(
                    round($origWidth / $origHeight, 2),
                    round($width / $height, 2),
                    "modaction={$this->modaction}: thumbnail's ratio doesn't match original",
                );
            }
        }

        if ($this->expectedContentDisposition) {
            $this->assertSame(
                $this->expectedContentDisposition,
                $req->getResponseHeader("Content-Disposition"),
                "modaction={$this->modaction}: wrong Content-Disposition header.",
            );
        }
    }

    /**
     * Determine width/height of downloaded image.
     * @param string $contents
     * @return array Array of two integers (width and height).
     */
    private function getImageSize($contents)
    {
        $path = tempnam(sys_get_temp_dir(), "modtest_thumb");
        file_put_contents($path, $contents);
        $size = getimagesize($path);
        unlink($path);

        return $size;
    }

    /**
     * Check whether/how was the database row modified by this action.
     */
    protected function assertDatabaseChanges()
    {
        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        if ($this->expectApproved) {
            $row = $dbw->selectRow(
                "moderation",
                "*",
                ["mod_id" => $this->fields["mod_id"]],
                __METHOD__,
            );
            $this->assertFalse(
                $row,
                "modaction={$this->modaction}: database row wasn't deleted",
            );
        } else {
            $this->assertRowEquals($this->expectedFields);
        }
    }

    /**
     * Check the necessary consequences of modaction=approve(all).
     */
    protected function assertApproved()
    {
        if (!$this->expectApproved) {
            return; // Not an Approve operation.
        }

        if ($this->fields["mod_type"] == "move") {
            $this->assertMoveApproved();
        } else {
            // Test consequences of edit or upload
            $this->assertEditApproved();
        }

        // Check that recentchanges (unlike page history) contain the timestamp of approval,
        // not timestamp of the original edit.
        $rcQueryFields = ["rc_timestamp"];

        $changeTagsStore = $this->getServiceContainer()->getChangeTagsStore();
        $rcQueryFields["ts_tags"] = $changeTagsStore->makeTagSummarySubquery(
            "recentchanges",
        );

        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $rcRow = $dbw->selectRow(
            "recentchanges",
            $rcQueryFields,
            [],
            __METHOD__,
            ["ORDER BY" => "rc_timestamp DESC"],
        );
        $this->assertTimestampIsRecent($rcRow->rc_timestamp);

        // Check that change tags were preserved on approval.
        $expectedTags =
            $this->fields["mod_tags"] === null
                ? null
                : str_replace("\n", ",", $this->fields["mod_tags"]);
        $this->assertSame($expectedTags, $rcRow->ts_tags);

        // Check that UserAgent was preserved on approval.
        $agents =
            $this->filename || $this->fields["mod_type"] == "move"
                ? $this->getTestsuite()->getCULEAgents(1)
                : $this->getTestsuite()->getCUCAgents(1);

        $this->assertNotSame(
            self::USER_AGENT_OF_MODERATOR,
            $agents[0],
            'UserAgent in checkuser tables matches moderator\'s UserAgent',
        );
        $this->assertSame(
            self::USER_AGENT_OF_AUTHOR,
            $agents[0],
            'UserAgent in checkuser tables doesn\'t match UserAgent of user who made the edit',
        );
    }

    /**
     * Check the necessary consequences of approving an edit or upload.
     */
    protected function assertEditApproved()
    {
        $rev = $this->getTestsuite()->getLastRevision(
            $this->getExpectedTitle(),
        );

        $this->assertSame($this->fields["mod_user_text"], $rev["user"]);
        $this->assertSame($this->fields["mod_text"], $rev["*"]);

        $this->assertSame(
            ["is minor edit" => (bool) $this->fields["mod_minor"]],
            ["is minor edit" => array_key_exists("minor", $rev)],
        );

        $isReupload = $this->existing && $this->filename;
        if (!$isReupload) {
            // Reuploads don't place edit comment into rev_comment.
            $this->assertSame($this->fields["mod_comment"], $rev["comment"]);

            // Changing rev_timestamp in ApproveHook is not yet implemented for reuploads.
            $expectedTimestamp = wfTimestamp(
                TS_ISO_8601,
                $this->fields["mod_timestamp"],
            );
            $this->assertSame($expectedTimestamp, $rev["timestamp"]);
        }

        if ($this->filename) {
            $repoGroup = $this->getServiceContainer()->getRepoGroup();
            $file = $repoGroup->findFile($this->getExpectedTitleObj());

            $this->assertTrue(
                $file->exists(),
                "Approved file doesn't exist in FileRepo",
            );

            $srcPath = ModerationTestsuite::findSourceFilename($this->filename);
            $expectedContents = file_get_contents($srcPath);

            $contents = file_get_contents($file->getLocalRefPath());

            $this->assertSame(
                $expectedContents,
                $contents,
                "Approved file is different from uploaded file",
            );
        }
    }

    /**
     * Check the necessary consequences of approving a move.
     */
    protected function assertMoveApproved()
    {
        $t = $this->getTestsuite();
        $newTitle = $this->getExpectedPage2Title();

        // New title: should contain the text of this page.
        $rev = $t->getLastRevision($newTitle);
        $this->assertSame($this->fields["mod_user_text"], $rev["user"]);
        $this->assertSame($this->textOfPrecreatedPage, $rev["*"]);

        // Old title: should contain a redirect.
        $rev = $t->getLastRevision($this->getExpectedTitle());
        $this->assertSame($this->fields["mod_user_text"], $rev["user"]);
        $this->assertNotSame($this->fields["mod_text"], $rev["*"]);
        $this->assertMatchesRegularExpression(
            "/^#[^ ]+ \[\[" .
                preg_quote($newTitle) .
                '\]\]\n\(move-redirect-text\)$/',
            $rev["*"],
        );
    }

    /**
     * Check whether the moderation block was added/deleted.
     */
    protected function assertBlockedStatus()
    {
        $expectedBlocker = $this->getModerator();
        if ($this->expectModblocked === null) {
            // Default: block status shouldn't change.
            $this->expectModblocked = $this->modblocked;

            // If the user was already blocked via [ 'modblocked' => true ],
            // we should expect getModeratorWhoBlocked() to tell who did it.
            $expectedBlocker = $this->getModeratorWhoBlocked();
        }

        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $row = $dbw->selectRow(
            "moderation_block",
            ["mb_user", "mb_by", "mb_by_text", "mb_timestamp"],
            ["mb_address" => $this->fields["mod_user_text"]],
            __METHOD__,
        );

        if ($this->expectModblocked) {
            $fields = get_object_vars($row);

            // Not-strict check that 'mb_timestamp' is not too far from "now"
            $this->assertTimestampIsRecent($fields["mb_timestamp"]);
            unset($fields["mb_timestamp"]);

            $expectedFields = [
                "mb_user" => (string) $this->fields["mod_user"],
                "mb_by" => (string) $expectedBlocker->getId(),
                "mb_by_text" => $expectedBlocker->getName(),
            ];
            $this->assertSame($expectedFields, $fields);
        } else {
            $this->assertFalse(
                $row,
                "modaction={$this->modaction}: Author is unexpectedly blacklisted as spammer.",
            );
        }
    }

    /**
     * Check the log entry created by this action (if any).
     */
    protected function assertLogEntry()
    {
        // Check the LogEntry, if any
        $queryInfo = DatabaseLogEntry::getSelectQueryData();
        $queryInfo["conds"]["log_type"] = "moderation";
        $queryInfo["options"]["ORDER BY"] = "log_id DESC";

        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        $row = $dbw->selectRow(
            $queryInfo["tables"],
            $queryInfo["fields"],
            $queryInfo["conds"],
            __METHOD__,
            $queryInfo["options"],
            $queryInfo["join_conds"],
        );

        if (!$this->expectLogEntry) {
            $this->assertFalse(
                $row,
                "modaction={$this->modaction}: unexpected LogEntry appeared after readonly action",
            );
        } else {
            $this->assertNotFalse(
                $row,
                "modaction={$this->modaction}: logging table is empty after the action",
            );

            $logEntry = DatabaseLogEntry::newFromRow($row);

            $this->assertSame(
                "moderation",
                $logEntry->getType(),
                "modaction={$this->modaction}: incorrect LogEntry type",
            );
            $this->assertSame(
                $this->expectedLogAction,
                $logEntry->getSubtype(),
                "modaction={$this->modaction}: incorrect LogEntry subtype",
            );
            $this->assertSame(
                $this->getModerator()->getName(),
                $logEntry->getPerformerIdentity()->getName(),
                "modaction={$this->modaction}: incorrect name of moderator in LogEntry",
            );
            $this->assertSame(
                $this->getExpectedLogTarget()->getFullText(),
                $logEntry->getTarget()->getFullText(),
                "modaction={$this->modaction}: incorrect LogEntry target",
            );

            $this->assertTimestampIsRecent($logEntry->getTimestamp());

            $expectedParams = [];
            switch ($this->expectedLogAction) {
                case "reject":
                    $expectedParams = [
                        "modid" => $this->fields["mod_id"],
                        "user" => $this->fields["mod_user"],
                        "user_text" => $this->fields["mod_user_text"],
                    ];
                    break;

                case "rejectall":
                case "approveall":
                    $expectedParams = [
                        "4::count" => 1,
                    ];
                    break;

                case "approve":
                    $expectedParams = [
                        "revid" => $this->getExpectedTitleObj()->getLatestRevID(),
                    ];
                    break;

                case "approve-move":
                    $expectedParams = [
                        "4::target" => $this->getExpectedPage2Title(),
                        "user" => $this->fields["mod_user"],
                        "user_text" => $this->fields["mod_user_text"],
                    ];
                    break;

                case "editchange":
                    $expectedParams = [
                        "modid" => $this->fields["mod_id"],
                    ];
            }

            $this->assertSame(
                $expectedParams,
                $logEntry->getParameters(),
                "modaction={$this->modaction}: incorrect LogEntry parameters",
            );
        }
    }

    /**
     * Calculates the URL of modaction requested by this TestSet.
     * @return string
     */
    protected function getActionURL()
    {
        $q = [
            "modid" => $this->fields["mod_id"],
            "modaction" => $this->modaction,
        ];
        if (
            !in_array($this->modaction, [
                "show",
                "showimg",
                "preview",
                "editchange",
            ])
        ) {
            $q["token"] = $this->getTestsuite()->getEditToken();
        }

        if ($this->showThumb) {
            // modaction=showimg&thumb=1
            $q["thumb"] = 1;
        }

        if ($this->simulateNoSuchEntry) {
            $q["modid"] = 0; // Entry with ID=0 never exists
        }

        return SpecialPage::getTitleFor("Moderation")->getLocalURL($q);
    }
}
