"use strict";
const Page = require("./page.js");

/**
	@brief Represents the editing form of MobileFrontend.
*/

class MobileFrontend extends Page {
  /** @brief Editable element in the editor */
  get content() {
    return $("#wikitext-editor,.wikitext-editor");
  }

  /** @brief Button to close "You are not logged in" dialog */
  get editAnonymouslyButton() {
    return $("a=Edit without logging in");
  }

  /** "Next" button (navigates from textarea screen to "Enter edit summary" screen) */
  get nextButton() {
    return $(".continue");
  }

  /** "Save" button on the "Enter edit summary" screen */
  get saveButton() {
    return $(".submit");
  }

  /** @brief "Summary" field in "Describe what you changed" dialog */
  get summary() {
    return this.getWhenVisible(".summary");
  }

  /**
		@brief Text in "Something went wrong" dialog.
	*/
  get errMsg() {
    return $(".mw-notification-type-error");
  }

  /**
		@returns Displayed error (if any).
		@retval null No error.
	*/
  get error() {
    return this.errMsg.isDisplayed() ? this.errMsg.getText() : null;
  }

  /**
		@brief Open MobileFrontend editor for article "name".
	*/
  open(name, section = 0) {
    browser.switchToMobileSkin();

    /* Make sure that post-edit redirect of MobileFrontend will take us to the article.
			Also a workaround against https://github.com/mozilla/geckodriver/issues/790 */
    super.open(name);

    super.open(name + "#/editor/" + section);

    /* FIXME: in Edge, simply navigating to #/editor/0 sometimes doesn't open the editor.
			Possible reason: hashchange event was called before MobileFrontend scripts
			were completely initialized (so #/editor/ URL wasn't handled).
		*/

    /* When re-rendering the page without reload,
			MobileFrontend will sometimes create 2 editor-overlays
			(one recently added and one stale/invisible).
			This makes tests very flaky,
			so we delete all overlays except the last one.
		*/
    $(".editor-overlay").waitForDisplayed();
    browser.execute(function () {
      $(".editor-overlay").slice(0, -1).remove();
    });

    var self = this;
    browser.waitUntil(function () {
      if (self.editAnonymouslyButton.isDisplayed()) {
        self.editAnonymouslyButton.click();
        return false;
      }

      return self.content.isDisplayed();
    });
  }

  /**
		@brief Edit the page in MobileFrontend.
		@param name Page title, e.g. "List of Linux distributions".
		@param section Section number, e.g. 0.
		@param content Page content (arbitrary text).
		@param summary Edit comment (e.g. "fixed typo").
	*/
  edit(name, section, content, summary = "") {
    this.open(name, section);
    this.content.setValue(content);
    this.nextButton.click();

    if (summary !== false) {
      this.summary.setValue(summary);
    }

    /* Suppress "Are you sure you want to create a new page?" dialog.
			Overwriting window.confirm is not supported in IE11,
			catching alert with acceptAlert() - not supported in Safari.
		*/
    browser.execute(function () {
      window.confirm = function () {
        return true;
      };
      return true;
    });

    this.submitAndWait(this.saveButton);
  }
}

module.exports = new MobileFrontend();
