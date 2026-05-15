/*
	This code handles VisualEditor and other API-based JavaScript editors.

	A. Why is this needed?

	When Moderation aborts MultiContentSave hook, api.php?action=edit returns error,
	so VisualEditor panics "The edit wasn't saved! Unknown error!".

	B. Why such an unusual implementation?

	It turns out, neither API (in MediaWiki core) nor VisualEditor provide
	any means of altering the API response via a PHP hook.

	C. How does this solution work?

	We intercept all AJAX responses (sic!), and if we determine that this
	response is "Unknown error: moderation-edit-queued" (see above),
	we FAKE THE AJAX RESPONSE by replacing it with "edit saved successfully!"

	NOTE: JavaScript editors also need "preloading" support.
	See [visualeditor/preload.ve.js], [mobilefrontend/preload.mf.js] for details.
*/

(function () {
  "use strict";

  mw.moderation = mw.moderation || {};
  mw.moderation.ajaxhook = mw.moderation.ajaxhook || {};

  var rewriteAjaxResponse; // Defined below

  /**
   * Intercept all API calls made via mw.Api(), rewrite the response if needed.
   *
   * @param {mw.Api} apiObj
   */
  mw.moderation.trackAjax = function (apiObj) {
    var oldFunc = apiObj.prototype.ajax;
    apiObj.prototype.ajax = function (parameters, ajaxOptions) {
      var lastQuery = parameters;

      ajaxOptions.beforeSend = function (jqXHR, settings) {
        mw.hook("ajaxhook.beforeSend").fire(jqXHR, settings);
      };

      ajaxOptions.dataFilter = function (rawData, dataType) {
        if (dataType !== "json") {
          return rawData;
        }

        var newRet = rewriteAjaxResponse(lastQuery, JSON.parse(rawData));
        if (!newRet) {
          return rawData;
        }

        return JSON.stringify(newRet);
      };

      return oldFunc.apply(this, arguments);
    };
  };

  mw.loader.using("mediawiki.api", function () {
    mw.moderation.trackAjax(mw.Api);
  });

  /**
   * Make an API response for action=edit.
   * This affects most API-based JavaScript editors, including MobileFrontend.
   *
   * @return {Object}
   */
  mw.moderation.ajaxhook.edit = function () {
    var ret = {},
      timestamp = "2016-12-08T12:33:23Z"; /* TODO: recalculate */

    ret.edit = {
      result: "Success" /* Uppercase */,
      pageid: mw.config.get("wgArticleId"),
      title: mw.config.get("wgTitle"),
      contentmodel: mw.config.get("wgPageContentModel"),
      oldrevid: mw.config.get("wgRevisionId"),
      newrevid: 0 /* NOTE: change if this causes problems in any API-based editors */,
      newtimestamp: timestamp,
    };

    if (ret.edit.pageid) {
      ret.edit.new = "";
    }

    mw.hook("moderation.ajaxhook.edit").fire();
    return ret;
  };

  /**
   * Main logic of AJAX response rewriting.
   *
   * @param {Object} query API request, e.g. { action: "edit", "title": "Testpage1", ... }.
   * @param {Object} ret API response, e.g. { edit: { result: "success", ... } }.
   * @return {Object|false} New API response (if overwrite is needed) or false
   * (if no need to overwrite).
   */
  rewriteAjaxResponse = function (query, ret) {
    // Allow the hook to modify the response (used by [preload33.mf.js])
    mw.hook("ajaxhook.rewriteAjaxResponse").fire(query, ret);
    if (ret.modified) {
      // If the hook sets this field, then "ret" is the new response.
      delete ret.modified;
      return ret;
    }

    /* Check whether we need to overwrite this AJAX response or not */
    var errorCode;
    if (ret.errors) {
      errorCode = ret.errors[0].code;
    } else {
      return false; /* Nothing to overwrite */
    }

    if (errorCode === "moderation-edit-queued") {
      /* Set cookie for [ext.moderation.notify.js].
				It means "edit was just queued for moderation".
			*/
      mw.cookie.set("modqueued", "1", { path: "/" });

      /*
				Error from api.php?action=edit: edit was queued for moderation.
				We must replace this response with "Edit saved successfully!".
			*/
      var func = mw.moderation.ajaxhook[query.action];
      if (!func) {
        /* Nothing to overwrite */
      }

      return func(); /* Fake a successful API response */
    }

    return false; /* Nothing to overwrite */
  };
})();
