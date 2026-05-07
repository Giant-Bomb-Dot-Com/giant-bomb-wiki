"use strict";

const expect = require("chai").expect,
  EditPage = require("../pageobjects/edit.page.js"),
  MobileFrontend = require("../pageobjects/mobilefrontend.page.js"),
  PostEdit = require("../pageobjects/postedit.page.js");

var subtests = [
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

describe("Postedit notification (for anonymous user)", function () {
  /* Run the same tests for desktop and mobile view */
  new Map(subtests).forEach((doTestEdit, subTest) => {
    describe("(" + subTest + ")", () => {
      /*---------------- Desktop/mobile subtest -----------------------------------*/

      before(function () {
        doTestEdit("Test " + browser.getTestString());
      });

      it('should contain "sign up" link', function () {
        PostEdit.init();

        expect(PostEdit.signupLink.isDisplayed(), "signupLink.isDisplayed").to
          .be.true;
        expect(
          PostEdit.signupLink.query.title,
          "signupLink.query.title",
        ).to.equal("Special:CreateAccount");
      });

      /*---------------- Desktop/mobile subtest -----------------------------------*/
    });
  }); // .forEach( function( subTest ) { describe( ...
}); /* describe( ..., function { */
