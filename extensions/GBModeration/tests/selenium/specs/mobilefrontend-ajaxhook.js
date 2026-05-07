"use strict";

const expect = require("chai").expect,
  MobileFrontend = require("../pageobjects/mobilefrontend.page.js"),
  PostEdit = require("../pageobjects/postedit.page.js");

/*
	Title of MediaWiki page which should be edited during this test.
*/
var PageName = "Test " + browser.getTestString(),
  Content = browser.getTestString(),
  Summary = "funny change #" + browser.getTestString(),
  bot;

describe("When non-automoderated user saves a new edit in MobileFrontend", function () {
  before(function () {
    // We need to be logged in for ApiModerationPreload.
    bot = browser.loginIntoNewAccount();
  });

  it("edit should be saved without errors", function () {
    MobileFrontend.edit(PageName, 0, Content, Summary);
    expect(MobileFrontend.error, "MobileFrontend.error").to.be.null;
  });

  it('should show postedit notification "Success: your edit has been sent to moderation"', function () {
    PostEdit.init();
    expect(PostEdit.notification.isDisplayed(), "notification.isDisplayed").to
      .be.true;
    expect(PostEdit.editLink.query.action, "editLink.query.action").to.equal(
      "edit",
    );
  });

  it("edit should be queued for moderation", function () {
    return bot
      .request({
        action: "query",
        prop: "moderationpreload",
        mptitle: PageName,
      })
      .then(function (apiResponse) {
        var mp = apiResponse.query.moderationpreload;

        expect(mp.wikitext, "preloadedChange.wikitext").to.equal(Content);
        expect(mp.comment, "preloadedChange.summary").to.equal(Summary);
      });
  });
});
