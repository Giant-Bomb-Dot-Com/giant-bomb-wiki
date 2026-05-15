/**
	@brief Test of the testsuite itself: checks wrapper around browser.url().
	See before() in wdio.conf.js for details.

	This file doesn't test the Moderation.
		See [specs/*.js] for Moderation-related tests.
*/

"use strict";

const expect = require("chai").expect,
  MobileFrontend = require("../../pageobjects/mobilefrontend.page.js"),
  BlankPage = require("wdio-mediawiki/BlankPage");

var PageName = "Test" + Math.random();

describe("browser.url()", function () {
  this.timeout(120000);

  for (var i = 1; i <= 10; i++) {
    it(
      'should ignore "Do you really want to leave" alert: attempt ' + i,
      function () {
        MobileFrontend.open(PageName, 0);
        MobileFrontend.content.addValue("+");

        BlankPage.open();

        /* There shouldn't be an alert "Do you really want to leave this page" */
        expect(function () {
          browser.getAlertText(); /* Throws exception when there is no alert */
        }).to.throw();
      },
    );
  }
});
