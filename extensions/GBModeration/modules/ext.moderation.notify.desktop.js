/*
	Implements notifyQueued() for desktop view.
	See [ext.moderation.notify.js] for non-desktop-specific code.
*/

(function () {
  "use strict";

  mw.moderation = mw.moderation || {};

  /**
   * This callback is used by notifyQueued().
   * Displays $div as postEdit notification.
   *
   * @param {jQuery} $div
   * @param {Function} readyCallback
   */
  mw.moderation.notifyCb = function ($div, readyCallback) {
    /* Don't remove $div when clicking on links */
    $div.find("a").on("click", function (e) {
      e.stopPropagation();
    });

    mw.loader.using("mediawiki.action.view.postEdit", function () {
      /* Desktop version */
      // Usual postedit notification is barely noticeable, so we need additional styles.
      mw.notify($div, {
        autoHide: false,
      }).then(function () {
        $("#mw-notification-area").addClass("mw-notification-area-modqueued");
      });

      readyCallback();
    });
  };
})();
