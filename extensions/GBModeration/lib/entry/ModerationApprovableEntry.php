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
 * Parent class for all entry types (edit, upload, move, etc.).
 */

namespace MediaWiki\Moderation;

use Status;
use stdClass;
use User;

abstract class ModerationApprovableEntry extends ModerationEntry
{
    /** @var IConsequenceManager */
    protected $consequenceManager;

    /** @var ModerationApproveHook */
    protected $approveHook;

    /** @var TimestampTools */
    protected $timestampTools;

    /**
     * @param stdClass $row
     * @param IConsequenceManager $consequenceManager
     * @param ModerationApproveHook $approveHook
     * @param TimestampTools $timestampTools
     */
    public function __construct(
        $row,
        IConsequenceManager $consequenceManager,
        ModerationApproveHook $approveHook,
        TimestampTools $timestampTools,
    ) {
        parent::__construct($row);

        $this->consequenceManager = $consequenceManager;
        $this->approveHook = $approveHook;
        $this->timestampTools = $timestampTools;
    }

    /**
     * Get the list of fields needed for selecting $row from database.
     * @return array
     */
    public static function getFields()
    {
        $fields = array_merge(parent::getFields(), [
            "mod_id AS id",
            "mod_timestamp AS timestamp",
            "mod_cur_id AS cur_id",
            "mod_comment AS comment",
            "mod_minor AS minor",
            "mod_bot AS bot",
            "mod_last_oldid AS last_oldid",
            "mod_ip AS ip",
            "mod_header_xff AS header_xff",
            "mod_header_ua AS header_ua",
            "mod_text AS text",
            "mod_merged_revid AS merged_revid",
            "mod_rejected AS rejected",
            "mod_stash_key AS stash_key",
            "mod_tags AS tags",
        ]);

        return $fields;
    }

    /**
     * Returns mod_id of this ApprovableEntry.
     * @return int
     */
    public function getId()
    {
        $row = $this->getRow();
        return (int) $row->id;
    }

    /**
     * @inheritDoc
     * @param int $flags @phan-unused-param
     */
    protected function getUser($flags = 0)
    {
        /* User could have been recently renamed or deleted.
         Make sure we have the correct data when approving. */
        return parent::getUser(User::READ_LATEST);
    }

    /**
     * Install hooks which affect postedit behavior of doEditContent().
     */
    protected function installApproveHook()
    {
        $row = $this->getRow();

        $this->approveHook->addTask(
            $this->getTitle(),
            $this->getUser(),
            $row->type,
            [
                # For CheckUser extension to work properly, IP, XFF and UA
                # should be set to the correct values for the original user
                # (not from the moderator)
                "ip" => $row->ip,
                "xff" => $row->header_xff,
                "ua" => $row->header_ua,
                "tags" => $row->tags,

                # Here we set the timestamp of this edit to $row->timestamp
                # (this is needed because doEditContent() always uses current timestamp).
                #
                # NOTE: timestamp in recentchanges table is not updated on purpose:
                # users would want to see new edits as they appear,
                # without the edits surprisingly appearing somewhere in the past.
                "timestamp" => $row->timestamp,
            ],
        );
    }

    /**
     * Approve this change.
     * @param User $moderator
     * @throws ModerationError
     */
    public function approve(User $moderator)
    {
        $row = $this->getRow();

        /* Can this change be approved? */
        if ($row->merged_revid) {
            throw new ModerationError("moderation-already-merged");
        }
        if (
            $row->rejected &&
            !$this->timestampTools->canReapproveRejected($row->timestamp)
        ) {
            throw new ModerationError("moderation-rejected-long-ago");
        }

        # Install hooks to modify CheckUser database after approval, etc.
        $this->installApproveHook();

        # Do the actual approval.
        $status = $this->doApprove($moderator);
        if (!$status->isGood()) {
            /* Uniform handling of errors from doEditContent(), etc.:
             throw the ModerationError exception */
            throw new ModerationError($status->getMessage());
        }

        # Create post-approval log entry ("successfully approved").
        $this->consequenceManager->add(
            new AddLogEntryConsequence(
                $this->getApproveLogSubtype(),
                $moderator,
                $this->getTitle(),
                $this->getApproveLogParameters(),
                true, // Run ApproveHook on newly created log entry
            ),
        );

        # Approved edits are removed from "moderation" table,
        # because they already exist in page history, recentchanges etc.
        $this->consequenceManager->add(
            new DeleteRowFromModerationTableConsequence((int) $row->id),
        );
    }

    /**
     * Post-approval log subtype. May be overridden in subclass.
     * @return string (e.g. "approve" for "moderation/approve" log).
     */
    protected function getApproveLogSubtype()
    {
        return "approve";
    }

    /**
     * Parameters for post-approval log.
     * @return array
     */
    protected function getApproveLogParameters()
    {
        return ["revid" => $this->approveHook->getLastRevId()];
    }

    /**
     * Approve this change.
     * @param User $moderator
     * @return Status
     */
    abstract public function doApprove(User $moderator);
}
