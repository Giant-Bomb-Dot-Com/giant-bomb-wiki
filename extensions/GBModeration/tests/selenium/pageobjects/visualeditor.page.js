"use strict";
const Page = require("./page.js");

/**
	@brief Represents the editing form of VisualEditor.
*/

class VisualEditor extends Page {
  /** @brief Editable element in the editor */
  get content() {
    /*
			VisualEditor is a huge nuisance to emulate.

			Its "VisualEditor surface" object (wrapper of contenteditable)
			is programmed to be in two modes: "focused" and "not focused".
			It starts in "not focused" mode.
			It won't accept new input correctly unless it becomes "focused" first.

			For that to happen, "focusin" event must be fired.
			The confusing part is, VisualEditor ignores this event unless
			something within contenteditable is currently selected.

			So doing $('.ve-ce-documentNode').addValue() isn't enough,
			as it may click outside of any objects within contenteditable.

			To be sure we select something, we click on <p> tag first
			(this tag always exists, even if the article hasn't been created yet).
		*/
    $(".ve-active").waitForExist();

    var parSelector = ".ve-ce-documentNode p";
    $(parSelector).waitForExist();

    /* Try click() several times, because VisualEditor needs to install onclick() handler first */
    var self = this;
    browser.waitUntil(function () {
      if (self.closeNoticeButton.isDisplayed()) {
        /* Close "Notice" popup, it may prevent us from clicking on parSelector. */
        self.closeNoticeButton.click();
        return false;
      }

      // Trigger (1) selection of this <p>, (2) focusin event.
      browser.execute(function (selector) {
        $(selector).click();
      }, parSelector);

      //$( parSelector ).click();
      return $(".ve-ce-surface-focused").isExisting();
    });

    return $(".ve-ce-documentNode");
  }

  /* Button to close "Notice" popup */
  get closeNoticeButton() {
    return $(".oo-ui-tool-name-notices .oo-ui-icon-close");
  }

  /** @brief "Save page" button in the editor */
  get saveButton() {
    var $submit = this.getWhenVisible(
      ".ve-ui-toolbar-saveButton a, a.ve-ui-toolbar-saveButton",
    );

    browser.waitUntil(function () {
      return $submit.getAttribute("aria-disabled") === "false";
    });

    return $submit;
  }

  /** @brief "Save page" button in "Describe what you changed" dialog */
  get confirmButton() {
    return this.getWhenVisible(
      '//*[@class="oo-ui-processDialog-navigation"]//a[contains(.,"Save")]',
    );
  }

  /** @brief "Summary" field in "Describe what you changed" dialog */
  get summary() {
    return this.getWhenVisible(".ve-ui-mwSaveDialog-summary textarea");
  }

  get welcomeStartButton() {
    return this.getWhenVisible("a=Start editing");
  }

  get welcomeDialog() {
    return $(".oo-ui-dialog");
  }

  get editTab() {
    return this.getWhenVisible("#ca-ve-edit a");
  }

  /**
		@brief Text in "Something went wrong" dialog.
	*/
  get errMsg() {
    return $(".oo-ui-processDialog-error");
  }

  /**
		@returns Displayed error (if any).
		@retval null No error.
	*/
  get error() {
    return this.errMsg.isDisplayed() ? this.errMsg.getText() : null;
  }

  /**
		@brief Open VisualEditor for article "name".
	*/
  open(name) {
    super.open(name + "?veaction=edit&vehidebetadialog=true");
  }

  /**
		@brief Open VisualEditor for the already opened article via the UI.
	*/
  openSwitch() {
    this.editTab.click();
    this.closeWelcomeDialog();
  }

  /**
		@brief Close the welcome dialog.
		This is needed when we can't pass vehidebetadialog=true in the URL,
		e.g. when testing openSwitch().
	*/
  closeWelcomeDialog() {
    this.welcomeStartButton.click();

    /* Wait for dialog to disappear */
    this.welcomeDialog.waitForDisplayed(3000, true);
  }

  /**
		@brief Edit the page in VisualEditor.
		@param name Page title, e.g. "List of Linux distributions".
		@param content Page content (arbitrary text).
		@param summary Edit comment (e.g. "fixed typo").
	*/
  edit(name, content, summary = "") {
    this.open(name);

    this.content.addValue(content);
    this.saveButton.click();
    this.summary.addValue(summary);
    this.submitAndWait(this.confirmButton);
  }
}

module.exports = new VisualEditor();
