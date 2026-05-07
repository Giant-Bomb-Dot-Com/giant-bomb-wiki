<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2014-2025 Edward Chernenko.

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
 * Affects doEditContent() during modaction=approve(all).
 * Corrects rev_timestamp, rc_ip and checkuser logs when edit is approved.
 */

namespace MediaWiki\Moderation;

use File;
use FormatJson;
use ManualLogEntry;
use MediaWiki\ChangeTags\ChangeTagsStore;
use MediaWiki\Hook\FileUploadHook;
use MediaWiki\Hook\PageMoveCompletingHook;
use MediaWiki\Hook\RecentChange_saveHook;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\RevisionFromEditCompleteHook;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use RecentChange;
use User;
use Wikimedia\IPUtils;
use WikiPage;

class ModerationApproveHook implements
    FileUploadHook,
    PageMoveCompletingHook,
    RecentChange_saveHook,
    RevisionFromEditCompleteHook
{
    /** @var LoggerInterface */
    private $logger;

    /** @var ChangeTagsStore */
    private $changeTagsStore;

    /** @var array List of _id fields in tables that are supported by updateWithoutQueue(). */
    protected $idFieldNames = [
        "recentchanges" => "rc_id",
        "revision" => "rev_id",
    ];

    /**
     * @var array Tasks which must be performed by postapprove hooks.
     * Format: [ key1 => [ 'ip' => ..., 'xff' => ..., 'ua' => ... ], key2 => ... ]
     */
    protected $tasks = [];

    /**
     * @var array Log entries to modify in FileUpload hook.
     * Format: [ log_id1 => ManualLogEntry, log_id2 => ... ]
     *
     * @phan-var array<int,ManualLogEntry>
     */
    protected $logEntriesToFix = [];

    /**
     * @param LoggerInterface $logger
     * @param ChangeTagsStore $changeTagsStore
     */
    public function __construct(
        LoggerInterface $logger,
        ChangeTagsStore $changeTagsStore,
    ) {
        $this->logger = $logger;
        $this->changeTagsStore = $changeTagsStore;
    }

    /**
     * Used in extension.json to obtain this service as HookHandler.
     * @return ModerationApproveHook
     */
    public static function hookHandlerFactory()
    {
        return MediaWikiServices::getInstance()->getService(
            "Moderation.ApproveHook",
        );
    }

    /**
     * PageMoveCompleting hook.
     * Here we modify rev_timestamp of a newly created redirect after the page move.
     * @param LinkTarget $oldTitle
     * @param LinkTarget $newTitle @phan-unused-param
     * @param UserIdentity $user
     * @param int $pageid @phan-unused-param
     * @param int $redirid @phan-unused-param
     * @param string $reason @phan-unused-param
     * @param RevisionRecord $revision @phan-unused-param
     * @return bool|void
     */
    public function onPageMoveCompleting(
        $oldTitle,
        $newTitle,
        $user,
        $pageid,
        $redirid,
        $reason,
        $revision,
    ) {
        $task = $this->getTask(
            $oldTitle,
            $user->getName(),
            ModerationNewChange::MOD_TYPE_MOVE,
        );
        if (!$task) {
            return;
        }

        $revid = Title::newFromLinkTarget($oldTitle)->getLatestRevID();
        if ($revid) {
            // Redirect was created. Its timestamp should also be modified.
            $dbr = ModerationCompatTools::getDB(DB_REPLICA);
            $timestamp = $dbr->timestamp($task["timestamp"]); // Possibly in PostgreSQL format

            $this->queueUpdate(
                "revision",
                [$revid],
                ["rev_timestamp" => $timestamp],
            );
        }
    }

    /**
     * Add revid parameter to ManualLogEntry (if missing). See onFileUpload() for details.
     * @param int $logid
     * @param ManualLogEntry $logEntry
     */
    public function checkLogEntry($logid, ManualLogEntry $logEntry)
    {
        $params = $logEntry->getParameters();
        if (array_key_exists("revid", $params) && $params["revid"] === null) {
            $this->logEntriesToFix[$logid] = $logEntry;
        }
    }

    /** @var int|null Revid of the last edit, populated in onRevisionFromEditComplete */
    protected $lastRevId = null;

    /**
     * Returns revid of the last edit.
     * @return int|null
     */
    public function getLastRevId()
    {
        return $this->lastRevId;
    }

    /**
     * RevisionFromEditComplete hook.
     * Here we determine $lastRevId.
     * @param WikiPage $wikiPage @phan-unused-param
     * @param RevisionRecord $rev
     * @param int|bool $originalRevId @phan-unused-param
     * @param UserIdentity $user @phan-unused-param
     * @param string[] &$tags
     * @return bool|void
     */
    public function onRevisionFromEditComplete(
        $wikiPage,
        $rev,
        $originalRevId,
        $user,
        &$tags,
    ) {
        /* Remember ID of this revision for getLastRevId() */
        $this->lastRevId = $rev->getId();
    }

    /**
     * Calculate key in $tasks array for $title/$username/$type triplet.
     * @param LinkTarget $title
     * @param string $username
     * @param string $type mod_type of this change.
     * @return string
     */
    protected function getTaskKey(LinkTarget $title, $username, $type)
    {
        return implode(
            "[" /* Symbol "[" is not allowed in both titles and usernames */,
            [$username, $title->getNamespace(), $title->getDBKey(), $type],
        );
    }

    /**
     * Find the task regarding edit by $username on $title.
     * @param LinkTarget $title
     * @param string $username
     * @param string $type One of ModerationNewChange::MOD_TYPE_* values.
     * @return array|false [ 'ip' => ..., 'xff' => ..., 'ua' => ..., ... ]
     */
    protected function getTask(LinkTarget $title, $username, $type)
    {
        $key = $this->getTaskKey($title, $username, $type);
        return $this->tasks[$key] ?? false;
    }

    /**
     * Add a new task. Called before doEditContent().
     * @param LinkTarget $title
     * @param User $user
     * @param string $type
     * @param array $task
     *
     * @phan-param array{ip:?string,xff:?string,ua:?string,tags:?string,timestamp:?string} $task
     */
    public function addTask(LinkTarget $title, User $user, $type, array $task)
    {
        $key = $this->getTaskKey($title, $user->getName(), $type);
        $this->tasks[$key] = $task;
    }

    /**
     * Returns true if any ApproveHook tasks were installed, false otherwise.
     * This is used by CanSkip service to allow Moderation to be bypassed during modaction=approve.
     * @return bool
     */
    public function isApprovingNow()
    {
        return count($this->tasks) > 0;
    }

    /**
     * Find the entry in $tasks about change $rc.
     * @param ?RecentChange $rc
     * @return array|false
     */
    protected function getTaskByRC(?RecentChange $rc)
    {
        if (!$rc) {
            return false;
        }

        $title = Title::castFromPageReference($rc->getPage());
        if (!$title) {
            return false;
        }

        $logAction = $rc->mAttribs["rc_log_action"] ?? "";

        $type = ModerationNewChange::MOD_TYPE_EDIT;
        if ($logAction == "move" || $logAction == "move_redir") {
            $type = ModerationNewChange::MOD_TYPE_MOVE;
        }

        return $this->getTask($title, $rc->mAttribs["rc_user_text"], $type);
    }

    /**
     * onCheckUserInsertChangesRow()
     * Update IP, user-agent and XFF of newly approved edit in cu_changes table.
     *
     * @param string &$ip
     * @param string|false &$xff
     * @param array &$row
     * @param UserIdentity $user @phan-unused-param
     * @param ?RecentChange $rc
     */
    public function onCheckUserInsertChangesRow(
        string &$ip,
        &$xff,
        array &$row,
        UserIdentity $user,
        ?RecentChange $rc,
    ) {
        $task = $this->getTaskByRC($rc);
        if (!$task) {
            return;
        }

        $ip = IPUtils::sanitizeIP($task["ip"]);
        $xff = $task["xff"] ?? "";
        $row["cuc_agent"] = $task["ua"];
    }

    /**
     * onCheckUserInsertLogEventRow()
     * Update IP, user-agent and XFF of newly approved edit in cu_log_event table.
     *
     * @param string &$ip
     * @param string|false &$xff
     * @param array &$row
     * @param UserIdentity $user @phan-unused-param
     * @param int $id @phan-unused-param
     * @param ?RecentChange $rc
     */
    public function onCheckUserInsertLogEventRow(
        string &$ip,
        &$xff,
        array &$row,
        UserIdentity $user,
        int $id,
        ?RecentChange $rc,
    ) {
        $task = $this->getTaskByRC($rc);
        if (!$task) {
            return;
        }

        $ip = IPUtils::sanitizeIP($task["ip"]);
        $xff = $task["xff"] ?? "";
        $row["cule_agent"] = $task["ua"];
    }

    /**
     * onCheckUserInsertPrivateEventRow()
     * Update IP, user-agent and XFF of newly approved edit in cu_private_event table.
     *
     * @param string &$ip
     * @param string|false &$xff
     * @param array &$row
     * @param UserIdentity $user @phan-unused-param
     * @param ?RecentChange $rc
     */
    public function onCheckUserInsertPrivateEventRow(
        string &$ip,
        &$xff,
        array &$row,
        UserIdentity $user,
        ?RecentChange $rc,
    ) {
        $task = $this->getTaskByRC($rc);
        if (!$task) {
            return;
        }

        $ip = IPUtils::sanitizeIP($task["ip"]);
        $xff = $task["xff"] ?? "";
        $row["cupe_agent"] = $task["ua"];
    }

    /**
     * Fix approve LogEntry not having "revid" parameter (because it wasn't known before).
     * This happens when approving uploads (but NOT reuploads),
     * because creation of description page of newly uploaded images is delayed via DeferredUpdate,
     * so it happens AFTER the LogEntry has been added to the database.
     *
     * This is called from FileUpload hook (temporarily installed when approving the edit).
     *
     * @param File $file
     * @param bool $reupload
     * @param bool $hasDescription @phan-unused-param
     * @return bool|void
     */
    public function onFileUpload($file, $reupload, $hasDescription)
    {
        if ($reupload) {
            return; // rev_id is not missing for reuploads
        }

        $title = $file->getTitle();

        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);
        foreach ($this->logEntriesToFix as $logid => $logEntry) {
            if ($logEntry->getTarget()->equals($title)) {
                $params = $logEntry->getParameters();
                $params["revid"] = $title->getLatestRevID();

                $dbw->update(
                    "logging",
                    [
                        "log_params" => $logEntry->makeParamBlob($params),
                    ],
                    ["log_id" => $logid],
                    __METHOD__,
                );
            }
        }
    }

    /**
     * Schedule post-approval UPDATE SQL query.
     * @param string $table Name of table, e.g. 'revision'.
     * @param int|array $ids One or several IDs (e.g. rev_id or rc_id).
     * @param array $values New values, as expected by $db->update,
     * e.g. [ 'rc_ip' => '1.2.3.4', 'rc_something' => '...' ].
     *
     * @phan-param array<string,string> $values
     */
    protected function queueUpdate($table, $ids, array $values)
    {
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $this->logger->debug(
            "[ApproveHook] queueUpdate(): table={table}; ids={ids}; values={values}",
            [
                "table" => $table,
                "ids" => implode("|", $ids),
                "values" => FormatJson::encode($values),
            ],
        );

        // RecentChange_save hook is deferred in a manner that doesn't allow us to know for sure
        // if all RecentChanges were already created BEFORE doUpdate(),
        // so we must apply the necessary change immediately, not queue it.
        $this->updateWithoutQueue($table, $ids, $values);
    }

    /**
     * Perform post-approval UPDATE SQL query immediately.
     * @param string $table Name of table, e.g. 'revision'.
     * @param array $ids One or several IDs (e.g. rev_id or rc_id).
     * @param array $values New values, as expected by $db->update,
     * e.g. [ 'rc_ip' => '1.2.3.4', 'rc_something' => '...' ].
     *
     * @phan-param array<string,string> $values
     */
    protected function updateWithoutQueue($table, array $ids, array $values)
    {
        $idFieldName = $this->idFieldNames[$table]; /* e.g. "rev_id" */
        $newTimestamp = $values["rev_timestamp"] ?? null;

        $dbw = ModerationCompatTools::getDB(DB_PRIMARY);

        if ($table === "revision" && $newTimestamp) {
            // Double-check that $newTimestamp is not more ancient
            // than the rev_timestamp of the previous revision.
            $ids = array_filter($ids, function ($revisionId) use (
                $dbw,
                $newTimestamp,
            ) {
                $prevTimestamp = $dbw->selectField(
                    [
                        "a" => "revision" /* This revision, one of $ids */,
                        "b" => "revision" /* Previous revision */,
                    ],
                    "b.rev_timestamp",
                    ["a.rev_id" => $revisionId],
                    "ModerationApproveHook::updateWithoutQueue",
                    [],
                    [
                        "b" => ["INNER JOIN", ["b.rev_id=a.rev_parent_id"]],
                    ],
                );
                if ($prevTimestamp > $newTimestamp) {
                    // Using $newTimestamp would result in incorrect order of history,
                    // so we are ignoring this update.
                    $this->logger->info(
                        "[ApproveHook] Decided not to set rev_timestamp={timestamp} for revision #{revid}, " .
                            "because previous revision has {prev_timestamp} (which is newer).",
                        [
                            "revid" => $revisionId,
                            "timestamp" => $newTimestamp,
                            "prev_timestamp" => $prevTimestamp,
                        ],
                    );
                    return false;
                }

                return true;
            });
            if (!$ids) {
                // Nothing to do: we decided to skip rev_timestamp update for all rows.
                return;
            }
        }

        $dbw->update($table, $values, [$idFieldName => $ids], __METHOD__);
    }

    /**
     * onRecentChange_save()
     * This hook is temporarily installed when approving the edit.
     * It modifies the IP in the recentchanges table,
     * so that it matches the user who made the edit, not the moderator.
     *
     * @param RecentChange $rc
     * @return bool|void
     */
    // phpcs:ignore MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName, MediaWiki.Commenting.FunctionComment.MissingDocumentationPublic
    public function onRecentChange_save($rc)
    {
        global $wgPutIPinRC;

        $task = $this->getTaskByRC($rc);
        if (!$task) {
            return;
        }

        if ($wgPutIPinRC) {
            $this->queueUpdate("recentchanges", $rc->mAttribs["rc_id"], [
                "rc_ip" => IPUtils::sanitizeIP($task["ip"]),
            ]);
        }

        $dbr = ModerationCompatTools::getDB(DB_REPLICA);
        $timestamp = $dbr->timestamp($task["timestamp"]); // Possibly in PostgreSQL format

        /* Fix rev_timestamp to be equal to mod_timestamp
			(time when edit was queued, i.e. made by the user)
			instead of current time (time of approval). */
        $this->queueUpdate(
            "revision",
            [$rc->mAttribs["rc_this_oldid"]],
            ["rev_timestamp" => $timestamp],
        );

        if ($task["tags"]) {
            /* Add tags assigned by AbuseFilter, etc. */
            $this->changeTagsStore->addTags(
                explode("\n", $task["tags"]),
                $rc->mAttribs["rc_id"],
                $rc->mAttribs["rc_this_oldid"],
                $rc->mAttribs["rc_logid"],
                null,
                $rc,
            );
        }
    }
}
