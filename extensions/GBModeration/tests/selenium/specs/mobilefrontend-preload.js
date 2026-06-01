"use strict";

const expect = require("chai").expect,
  MobileFrontend = require("../pageobjects/mobilefrontend.page.js");

/*
	Title of MediaWiki page which should be edited during this test.
*/
var PageName = "Test " + browser.getTestString(),
  Sections = [
    "Beginning of the article " + browser.getTestString(),
    "== Header 1 ==\n" + browser.getTestString(),
    "== Header 2 ==\n" + browser.getTestString(),
  ];

// When saving the edit, MobileFrontend adds /* Section name */ to the
// edit summary. However, Moderation preloads the previous summary when
// subsequently editing the same page.
// Here we test that Moderation deletes this /* Section name */ to avoid ugly
// summaries like "/* Section 1 */ /* Section 3 */ /* Section 1 */ fix typo".
var ExpectedSummary = "funny change #" + browser.getTestString(),
  SavedSummary = "/* Some section name */ " + ExpectedSummary;

describe("When user opens MobileFrontend editor and has a pending edit", function () {
  before(function () {
    var bot = browser.loginIntoNewAccount();
    return bot.getEditToken().then(() => {
      // Because our newly created test account is NOT automoderated,
      // this edit will be queued for moderation
      return bot
        .edit(PageName, Sections.join("\n\n"), SavedSummary)
        .catch(function (error) {
          expect(error.code, "error.code").to.equal("moderation-edit-queued");
        });
    });
  });

  /* Test preloading of a single section into MobileFrontend */
  for (var sectionNumber in Sections) {
    it(
      "section #" + sectionNumber + " should be shown",
      function (idx) {
        MobileFrontend.open(PageName, idx);

        browser.waitUntil(function () {
          return MobileFrontend.content.getValue();
        });

        return expect(
          MobileFrontend.content.getValue(),
          "MobileFrontend.content[" + idx + "]",
        ).to.equal(Sections[idx]);
      }.bind(null, sectionNumber),
    );
  }

  it("edit summary should be shown", function () {
    /* To see the summary, we need to open "How did you improve the page?" dialog */
    MobileFrontend.content.addValue("+");
    MobileFrontend.nextButton.click();

    expect(
      MobileFrontend.summary.getValue(),
      "MobileFrontend.summary",
    ).to.equal(ExpectedSummary);
  });
});
