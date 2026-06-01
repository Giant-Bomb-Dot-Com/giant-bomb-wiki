/**
	@brief Test of the testsuite itself: checks [pageobjects/visualeditor.page.js].
	Makes sure that VisualEditor.edit() works reliably (correctly waits for needed UI elements, etc.).

	This file doesn't test the Moderation.
	See [specs/*.js] for Moderation-related tests.
*/

"use strict";

const VisualEditor = require("../../pageobjects/visualeditor.page.js");

describe("VisualEditor PageObject", function () {
  for (var i = 1; i <= 10; i++) {
    it("should edit without errors: attempt " + i, function () {
      this.timeout(10000);

      var PageName = "Test" + Math.random(),
        Content = Date.now() + " " + Math.random();

      VisualEditor.edit(PageName, Content);

      browser.pause(500);
      browser.refresh();
    });
  }
});
