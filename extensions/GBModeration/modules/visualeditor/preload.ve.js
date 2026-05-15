/*
	This code handles Preload for VisualEditor.

	When JavaScript-based editors load the main <textarea>
	(their analogue of #wpTextbox1 in index.php?action=edit),
	they ignore Preload hooks like onEditFormInitialText, so if this user
	has a pending change, it won't be preloaded at all.
*/

(function () {
  "use strict";

  var preloadedSummary = "";

  /* Supply VisualEditor with preloaded pending edit, if there is one */
  mw.loader.using("ext.visualEditor.targetLoader", function () {
    var api = new mw.Api();

    var funcName = "requestParsoidData";
    var oldFunc = mw.libs.ve.targetLoader[funcName];

    /* Override requestParsoidData() method */
    mw.libs.ve.targetLoader[funcName] = function (pageName, options) {
      /*
				useDefault() - call the original (unmodified) method from mw.libs.ve.
				Example:
				return useDefault( "no change is awaiting moderation, so nothing to preload!" );
			*/
      var self = this,
        params = arguments;

      function useDefault(reason) {
        console.log("Moderation: not preloading: " + reason);

        return oldFunc.apply(self, params);
      }

      if (options.oldId !== undefined && isNaN(options.oldId)) {
        // Imperfect workaround for MediaWiki 1.40+
        options.oldId = 0;
        return useDefault(
          "can't preload creation of new pages in VE for MW 1.40+, but local storage of VE should handle this",
        );
      }

      /* If user is editing some older revision,
				then preloading is not needed here */
      if (
        options.oldId !== undefined &&
        options.oldId !== mw.config.get("wgCurRevisionId")
      ) {
        return useDefault("user is editing an older revision");
      }

      if (options.wikitext !== undefined) {
        return useDefault(
          "requestParsoidData() is parsing custom wikitext, not the current revision",
        );
      }

      /* We need to get the following information:
				1) response from action=query&prop=moderationpreload
				(i.e. wikitext of pending change, if it exists)

				2) response from ?action=visualeditor&paction=metadata
				(i.e. everything except parsed HTML)

				3) response from ?action=visualeditor&paction=parsefragment,
				which will transform (1) into the parsed HTML.

				After that, we insert this parsed HTML into (2),
				and return it as the result of requestPageData().
			*/
      var qPreload = {
        action: "query",
        prop: "moderationpreload",
        mptitle: pageName,
        mpmode: "wikitext",
      };
      return api
        .post(qPreload)
        .then(function (data) {
          var wikitext = data.query.moderationpreload.wikitext;
          if (!wikitext) {
            /* Nothing to preload.
						Call the original requestPageData() from VisualEditor. */
            return useDefault("no pending change found");
          }

          /* Preload summary.
					Used in 've.saveDialog.stateChanged' hook, see below */
          preloadedSummary = data.query.moderationpreload.comment;

          /* (2) Get metadata */
          var qMetadata = {
            action: "visualeditor",
            paction: "metadata", // Ask for everything except parsed HTML
            page: pageName,
            uselang: mw.config.get("wgUserLanguage"),
          };
          var promiseMetadata = api.get(qMetadata).then();

          /* (3) Get HTML */
          var qParseFragment = {
            action: "visualeditor",
            paction: "parsefragment", // Convert wikitext into HTML
            page: pageName,
            wikitext: wikitext, // Just preloaded from moderationpreload API
          };
          var promiseParseFragment = api.post(qParseFragment).then();

          /* When both (2) and (3) are completed, combine their results and return */
          return $.when(promiseMetadata, promiseParseFragment)
            .then(function (metadata, parsefragment) {
              var ret = metadata[0];
              var ret2 = parsefragment[0];

              if (ret.visualeditor && ret2.visualeditor) {
                var content = ret2.visualeditor.content;
                var about = new mw.Title(
                  "Special:Redirect/revision/" +
                    mw.config.get("wgCurRevisionId"),
                ).getUrl();

                ret.visualeditor.content =
                  '<html about="' +
                  about +
                  '"><body>' +
                  content +
                  "</body></html>";
              }

              ret.visualeditor.canEdit = true;

              // Return metadata + HTML (like api.php?action=visualeditor&paction=parse)
              return ret;
            })
            .promise();
        })
        .promise();
    };
  });

  /* Supply VisualEditor with preloaded summary.
		NOTE: we can't simply create #wpSummary (which causes VE to use
		its contents as initialEditSummary), because initialEditSummary
		is lost when editing a section (in restoreEditSection()).
	*/
  mw.hook("ve.saveDialog.stateChanged").add(function () {
    var $input = $(".ve-ui-mwSaveDialog-summary").find("textarea"),
      oldSummary = $input.val();

    if (oldSummary.replace(/\s*\/\*.*\*\/\s*/, "") === "") {
      // Either this summary is empty, or this is an
      // automatic edit summary like this: /* SectionName */
      // We can safely replace it with preloaded summary.

      $input.val(preloadedSummary);
    }
  });
})();
