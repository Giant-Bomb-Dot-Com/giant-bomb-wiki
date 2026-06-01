/*
	Fire "postEdit" hook to show "moderation-edit-queued" to the user.
*/

(function () {
  "use strict";

  var isMobile = mw.config.get("skin") === "minerva",
    containerSel = ".postedit-container, .mw-notification-tag-modqueued";

  mw.moderation = mw.moderation || {};

  /**
   * Display mobile/desktop version of postedit notification.
   *
   * @param {jQuery} $div
   * @return {Deferred}
   */
  function show($div) {
    var $d = $.Deferred(),
      module = isMobile
        ? "ext.moderation.mf.notify"
        : "ext.moderation.notify.desktop";

    mw.loader.using(module, function () {
      mw.moderation.notifyCb($div, function () {
        $d.resolve();
      });
    });

    return $d;
  }

  /**
   * Get edit URL of the current page.
   *
   * @return {string}
   */
  function getEditUrl() {
    var returnto = new mw.Uri().query.returnto;
    if (returnto) {
      /* Custom edit form requested, e.g. Special:FormEdit
				or ?action=formedit from Extension:PageForms */
      return mw.util.getUrl.apply(null, JSON.parse(returnto));
    }

    var q = {};
    if (!isMobile && mw.cookie.get("VEE", "") === "visualeditor") {
      q.veaction = "edit";
    } else {
      q.action = "edit";
    }
    return mw.util.getUrl(null, q);
  }

  /**
   * Show "your edit was queued for moderation" to user.
   * May be called from [ext.moderation.ajaxhook.js].
   *
   * @param {Object|undefined} options
   */
  mw.moderation.notifyQueued = function (options) {
    if (!options) {
      options = {};
    }

    if ($(containerSel).length) {
      /* User quickly clicked Submit several times in VisualEditor, etc.
				Don't show the dialog twice.
			*/
      return;
    }

    var $div = $("<div>").attr("id", "postedit-modqueued");

    /* "Pending review" icon */
    $div.append(
      $("<div>")
        .attr("id", "pending-review")
        .append(mw.msg("moderation-pending-review")),
    );

    /* "Success: your edit has been sent to moderation" */
    $div.append(
      $("<p>").append(
        mw.message("moderation-edit-queued", getEditUrl()).plain(),
      ),
    );

    /* "To skip moderation in the future, please sign up" */
    if (mw.user.getId() === 0) {
      $div.append(
        $("<p>").append(mw.message("moderation-suggest-signup").parse()),
      );
    }

    show($div).done(function () {
      /* Remove the cookie from [ext.moderation.ajaxhook.js] */
      mw.cookie.set("modqueued", null, { path: "/" });

      /* If requested, display HTML of this queued edit */
      if (options.showParsed) {
        var api = new mw.Api();
        api
          .get({
            action: "query",
            prop: "moderationpreload",
            mptitle: mw.config.get("wgPageName"),
            mpmode: "parsed",
          })
          .done(function (ret) {
            var parsed = ret.query.moderationpreload.parsed;
            if (parsed) {
              var $div2 = $("<div>").html(parsed.text);
              mw.hook("wikipage.content").fire(
                $("#mw-content-text").empty().append($div2),
              );

              $("#catlinks").html(parsed.categorieshtml);
            }
          });
      }
    });
  };

  var justQueued =
    /* 1. From the normal edit form: redirect contains ?modqueued=1 */
    mw.util.getParamValue("modqueued") === "1" ||
    /* 2. From [ext.moderation.ajaxhook.js]: page was edited via API */
    mw.cookie.get("modqueued") === "1";

  if (justQueued && mw.config.get("wgAction") === "view") {
    mw.moderation.notifyQueued();
  }
})();
