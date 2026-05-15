/*
	This code handles Preload for MobileFrontend for MediaWiki 1.33+.

	We detect situation when MobileFrontend queries [api.php?action=query&prop=revisions],
	add prop=moderationpreload into this API query, and then modify the response,
	thus making MobileFrontend think that there is a +1 additional revision
	(which contains the same text/summary as this user's change that still awaits Moderation)
*/

(function () {
  "use strict";

  // This hook is called by [ext.moderation.ajaxhook.js] (in beforeSend() callback of $.ajax)
  mw.hook("ajaxhook.beforeSend").add(function (jqXHR, settings) {
    var requestUri = new mw.Uri(settings.url),
      q = requestUri.query;

    // Is this a "load latest revision" query?
    if (
      q.action !== "query" ||
      q.format !== "json" ||
      q.formatversion !== "2" ||
      !q.prop
    ) {
      return; // Unrelated API query
    }

    if (q.prop.split("|").indexOf("revisions") === -1) {
      return; // Not a "load latest revision" query
    }

    // Tell API to look for a pending change (if any)
    q.prop += "|moderationpreload";
    q.mptitle = q.titles;

    // eslint-disable-next-line no-jquery/no-is-numeric
    if ($.isNumeric(q.rvsection)) {
      // Only one section is needed
      q.mpsection = q.rvsection;
    }

    // Modify the URL of this Ajax request
    requestUri.query = q;
    settings.url = requestUri.toString();
  });

  // This hook is called by [ext.moderation.ajaxhook.js] (in dataFilter() callback of $.ajax)
  mw.hook("ajaxhook.rewriteAjaxResponse").add(function (query, ret) {
    if (!ret.query || !ret.query.pages || !ret.query.moderationpreload) {
      return; // Unrelated API query
    }

    if ("missing" in ret.query.moderationpreload) {
      return; // There is no pending revision (nothing to preload)
    }

    if (!ret.query.pages[0].revisions) {
      // Page doesn't exist yet (non-automoderated user is creating it)
      ret.query.pages[0].revisions = [
        {
          timestamp: new Date().toISOString(), // Fake timestamp (irrelevant)
          contentformat: "text/x-wiki",
          contentmodel: "wikitext",
        },
      ];
      delete ret.query.pages[0].missing;
    }

    ret.query.pages[0].revisions[0].content =
      ret.query.moderationpreload.wikitext;
    ret.modified = true; // Notify rewriteAjaxResponse() that rewrite is needed

    // Preload the summary.
    // NOTE: MobileFrontend always adds /* SectionName */ when
    // saving an edit. This can result in ugly summaries,
    // e.g. "/* Section 1 */ /* Section 3 */ /* Section 6 */ fix typo".
    // To avoid that, we simply remove /* SectionName */
    // from the preloaded edit comment.
    mw.hook("mobileFrontend.editorOpened").add(function () {
      var summary = ret.query.moderationpreload.comment;
      summary = summary.replace(/\s*\/\*.*\*\/\s*/g, "");
      $(".summary").val(summary);
    });
  });
})();
