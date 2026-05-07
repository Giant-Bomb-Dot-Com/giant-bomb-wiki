/*
	Implements notifyQueued() for MobileFrontend.
	See [ext.moderation.notify.js] for non-mobile-specific code.
*/

(function () {
  "use strict";

  mw.moderation = mw.moderation || {};

  /**
   * Returns false if this popup should be suppressed, true otherwise.
   *
   * @param {string} msg
   * @return {boolean}
   */
  function shouldAllowMessage(msg) {
    switch (msg) {
      case mw.msg("mobile-frontend-editor-success-new-page"):
      case mw.msg("mobile-frontend-editor-success-landmark-1"):
      case mw.msg("mobile-frontend-editor-success"):
        return false;
    }

    return true;
  }

  /* Override notifyOnPageReload() to suppress default notification ("edit saved"),
		because notifyQueued() already shows "edit queued for moderation" */
  mw.loader.using("mobile.startup", function () {
    var M = require("mobile.startup"),
      oldReload = M.notifyOnPageReload;

    var newReload = function (msg, cssClass) {
      if (shouldAllowMessage(msg)) {
        oldReload(msg, cssClass);
      }
    };

    M.notifyOnPageReload = newReload;
  });

  /**
   * This callback is used by notifyQueued().
   * It displays $div as mw.notification.
   *
   * @param {jQuery} $div
   * @param {Function} readyCallback
   */
  mw.moderation.notifyCb = function ($div, readyCallback) {
    mw.notify($div, {
      tag: "modqueued",
      autoHide: false,
      type: "info",
    }).done(function () {
      var $notif = $(".mw-notification-tag-modqueued");

      /* Remove on click */
      $notif.on("click", function () {
        this.remove();
      });

      /* Remove when moving to another page */
      var onhashchange = function ($ev) {
        var ev = $ev.originalEvent;
        if (!ev.oldURL.match("#/editor/") && !ev.newURL.match(/#$/)) {
          $notif.remove();
          $(window).off("hashchange", onhashchange);
        }
      };
      $(window).on("hashchange", onhashchange);

      readyCallback();
    });
  };

  /* Workaround for Google Chrome issue.
		In Chrome, onSaveComplete() sometimes doesn't reload the page
		when changing window.location from /wiki/Something#/editor/0 to /wiki/Something
		(expected correct behavior: it should ALWAYS reload the page).
		The reason is unclear.

		As a workaround, we modify window.location explicitly to be sure.
		Note: we can't use window.location.reload() in onhashchange
		(it was causing flaky SauceLabs results in IE11 and Firefox).
	*/
  mw.hook("moderation.ajaxhook.edit").add(function () {
    $(window).one("hashchange", function () {
      window.location.search = "?modqueued=1";
    });
  });
})();
