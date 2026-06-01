"use strict";
const Page = require("./page.js");

class EditPage extends Page {
  get content() {
    return $("#wpTextbox1");
  }
  get displayedContent() {
    return $("#mw-content-text");
  }
  get heading() {
    return $("#firstHeading");
  }
  get save() {
    return $('[name="wpSave"]');
  }

  open(name) {
    super.open(name + "?action=edit&hidewelcomedialog=true");
    this.content.waitForDisplayed(); /* In Edge, browser.url() may return before DOM is ready */
  }

  edit(name, content) {
    this.open(name);
    this.content.setValue(content);
    this.submitAndWait(this.save);
  }
}
module.exports = new EditPage();
