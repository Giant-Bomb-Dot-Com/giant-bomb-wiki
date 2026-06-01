<?php

// This file helps Phan to avoid false positives in "detect unused code" mode.
// For example, MediaWiki hook handlers (listed in extension.json) shouldn't be considered unused.

namespace MediaWiki\Moderation;

/** @phan-file-suppress PhanParamTooFew, PhanStaticCallToNonStatic, PhanAccessMethodProtected */

ModerationApproveHook::hookHandlerFactory();
ModerationApproveHook::onCheckUserInsertChangesRow();
ModerationApproveHook::onCheckUserInsertLogEventRow();
ModerationApproveHook::onCheckUserInsertPrivateEventRow();
ModerationEditHooks::onMultiContentSave();
ModerationNotifyModerator::hookHandlerFactory();
ModerationNotifyModerator::onEchoCanAbortNewMessagesAlert();
ModerationPageForms::onPageForms__EditFormPreloadText();
ModerationPageForms::onPageForms__EditFormInitialText();
ModerationPageForms::onModerationContinueEditingLink();
ModerationPreload::hookHandlerFactory();
EditFormOptions::hookHandlerFactory();
EditFormOptions::onEditFilter();
EditFormOptions::onSpecialPageBeforeExecute();
ModerationVersionCheck::invalidateCache();

// wasDbUpdatedAfter() is expected to be unused.
// We are not removing it, as it may be useful if/when there will be some changes to DB schema.
ModerationVersionCheck::wasDbUpdatedAfter();
