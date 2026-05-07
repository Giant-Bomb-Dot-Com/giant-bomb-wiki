"use strict";

const expect = require("chai").expect,
  EditPage = require("../../pageobjects/edit.page.js");

describe("Utility function browser.loginIntoNewAccount()", function () {
  before(function () {
    browser.loginIntoNewAccount();
  });

  it("should add login cookies to the Selenium-controlled browser", function () {
    EditPage.open("Whatever");

    var loggedInAs = browser.execute(function () {
      return mw.config.get("wgUserName");
    }).value;

    expect(loggedInAs, "Name of logged in user").to.not.be.null;
  });
});
