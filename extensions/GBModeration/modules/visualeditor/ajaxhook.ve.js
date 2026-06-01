/*
	Ajaxhook callback for api.php?action=visualeditoredit.
	See [ext.moderation.ajaxhook.js] for details.
*/

(function () {
  "use strict";

  /**
   * Make an API response for action=visualeditoredit
   *
   * @return {Object}
   */
  mw.moderation.ajaxhook.visualeditoredit = function () {
    var ret = {};
    ret.visualeditoredit = {
      result: "success" /* Lowercase */,

      /* newrevid is "undefined" on purpose:
				in this case, ve.init.mw.DesktopArticleTarget.js doesn't do much,
				most importantly - doesn't fire 'postEdit' hook
				(which is good, because we need to show another text there).
				We invoke postEdit ourselves in [ext.moderation.notify.js].
			*/
      newrevid: undefined,

      /* Provide things we already know */
      isRedirect: mw.config.get("wgIsRedirect"),

      /* Fields which are ok to leave empty
				(because VisualEditor doesn't use them if they are empty)
			*/
      displayTitleHtml: "",
      lastModified: "",
      contentSub: "",
      modules: "",
      jsconfigvars: "",

      /* We don't really care about VisualEditor receiving this HTML.
				It simply displays it on the page without reloading it.

				Certainly not worth doing a synchronous XHR request
				(which is long deprecated and may be ignored by modern browsers)

				We can do this later in notifyQueued().
			*/
      content: "<div id='moderation-ajaxhook'></div>",
      categorieshtml: "<div id='catlinks'></div>",
    };

    /*
			VisualEditor may choose not to reload the page,
			but instead to display content/categorieshtml without reload.

			We must detect the appearance of #moderation-ajaxhook,
			and then call mw.moderation.notifyQueued().
		*/
    mw.hook("wikipage.content").add(function ($content) {
      if ($content.find("#moderation-ajaxhook").length !== 0) {
        mw.moderation.notifyQueued({
          /* Force re-rendering of #mw-content-text */
          showParsed: true,
        });
      }
    });

    /* Clear autosave storage of VisualEditor */
    if (mw.storage) {
      mw.storage.session.remove("ve-docstate");
    }

    return ret;
  };
})();
