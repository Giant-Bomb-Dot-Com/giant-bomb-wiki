"use strict";

const expect = require("chai").expect,
  EditPage = require("../pageobjects/edit.page.js"),
  MobileFrontend = require("../pageobjects/mobilefrontend.page.js"),
  PostEdit = require("../pageobjects/postedit.page.js");

/*
	Title of MediaWiki page which should be edited during this test.
*/
var PageName = "Test " + browser.getTestString(),
  ExistingPagePromise,
  subtests = [
    [
      "desktop",
      function (title) {
        EditPage.edit(title, browser.getTestString());
      },
    ],
    [
      "MobileFrontend",
      function (title) {
        MobileFrontend.edit(title, 0, browser.getTestString());
      },
    ],
  ];

describe("Postedit notification", function () {
  before(function () {
    ExistingPagePromise = browser.precreatePageAsync();
    browser.loginIntoNewAccount();
  });

  /* Run the same tests for desktop and mobile view */
  new Map(subtests).forEach((doTestEdit, subTest) => {
    describe("(" + subTest + ")", () => {
      /*---------------- Desktop/mobile subtest -----------------------------------*/

      before(function () {
        doTestEdit(PageName);
      });

      it("should be visible", function () {
        PostEdit.init();
        expect(PostEdit.notification.isDisplayed(), "notification.isDisplayed")
          .to.be.true;
      });

      it("shouldn't contain default text (for wikis without Moderation)", function () {
        /* i18n messages from MediaWiki core:
			postedit-confirmation-created
			postedit-confirmation-saved
		*/
        expect(PostEdit.text).to.not.contain("The page has been created");
        expect(PostEdit.text).to.not.contain("Your edit was saved");
      });

      it('should contain "Pending Review" icon', function () {
        expect(PostEdit.pendingIcon.isDisplayed(), "pendingIcon.isDisplayed").to
          .be.true;
      });

      it('should say "your edit has been sent to moderation"', function () {
        expect(PostEdit.text).to.contain(
          "Success: your edit has been sent to moderation",
        );
      });

      it('should contain "continue editing" link', function () {
        expect(PostEdit.editLink.isDisplayed(), "editLink.isDisplayed").to.be
          .true;

        expect(PostEdit.editLink.query.title, "editLink.query.title").to.equal(
          PageName.replace(/ /g, "_"),
        );
        expect(
          PostEdit.editLink.query.action,
          "editLink.query.action",
        ).to.equal("edit");
      });

      it('shouldn\'t contain "sign up" link if the user is logged in', function () {
        expect(PostEdit.signupLink.isDisplayed(), "signupLink.isDisplayed").to
          .be.false;
      });

      it("shouldn't disappear after 3.5 seconds", function () {
        /* Default postedit notification of MediaWiki is removed after 3.5 seconds
			(because it's not important whether the user reads it or not).

			Make sure that Moderation has countered this behavior,
			because its message contains links that user might want to follow,
			plus information about Moderation for first-time editors.
		*/
        PostEdit.waitUsualFadeTime();
        expect(PostEdit.notification.isDisplayed(), "notification.isDisplayed")
          .to.be.true;
      });

      it("should be removed when you click on it", function () {
        /* Clicking on notification should remove it */
        PostEdit.notification.click();
        PostEdit.notification.waitForDisplayed(
          500,
          true,
        ); /* Wait for it to vanish */
      });

      it("should be shown after editing the existing article", function () {
        /*
			Older MobileFrontend (for MediaWiki <=1.26) reloaded the page
			when creating a new article and didn't reload it when editing
			existing article. Our notification should work in both situations.
		*/
        var ExistingPageName = browser.call(() => ExistingPagePromise);

        doTestEdit(ExistingPageName);
        PostEdit.init();

        expect(PostEdit.notification.isDisplayed(), "notification.isDisplayed")
          .to.be.true;
      });

      /*---------------- Desktop/mobile subtest -----------------------------------*/
    });
  }); // .forEach( function( subTest ) { describe( ...
}); /* describe( ..., function { */
