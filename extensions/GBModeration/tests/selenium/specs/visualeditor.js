"use strict";

const expect = require("chai").expect,
  VisualEditor = require("../pageobjects/visualeditor.page.js"),
  PostEdit = require("../pageobjects/postedit.page.js"),
  EditPage = require("../pageobjects/edit.page.js");

/*
	Title of MediaWiki page which should be edited during this test.
*/
var PageName = "Test " + browser.getTestString(),
  Content = browser.getTestString(),
  Summary = "funny change #" + browser.getTestString(),
  ExistingPagePromise;

describe("VisualEditor", function () {
  before(function () {
    ExistingPagePromise = browser.precreatePageAsync();
  });

  it("should save the new edit without errors", function () {
    VisualEditor.edit(PageName, Content, Summary);

    expect(VisualEditor.error, "VisualEditor.error").to.be.null;
  });

  it('should cause postedit notification "Success: your edit has been sent to moderation"', function () {
    PostEdit.init();

    expect(PostEdit.notification.isDisplayed(), "notification.isDisplayed").to
      .be.true;
    expect(
      PostEdit.editLink.query.veaction,
      "editLink.query.veaction",
    ).to.equal("edit");
  });

  it("should show pending edit when opening the edit form", function () {
    browser.refresh(); /* Make sure old VisualEditor form isn't still in the DOM */
    VisualEditor.open(PageName);

    browser.waitUntil(() => VisualEditor.content.getText());
    expect(VisualEditor.content.getText(), "VisualEditor.content").to.equal(
      Content,
    );
  });

  it("should suggest summary of the pending edit", function () {
    /* To see the summary, we need to open "Describe what you changed" dialog */
    VisualEditor.content.addValue("+");
    VisualEditor.saveButton.click();

    expect(VisualEditor.summary.getValue(), "VisualEditor.summary").to.equal(
      Summary,
    );
  });

  it('should show pending edit when switching from "Edit source"', function () {
    /* Emulate the switch from "Edit source" to VisualEditor */
    EditPage.open(PageName);
    VisualEditor.openSwitch();

    browser.waitUntil(() => VisualEditor.content.getText());
    expect(VisualEditor.content.getText(), "VisualEditor.content").to.equal(
      Content,
    );
  });

  it("shouldn't show empty page after editing the existing article", function () {
    /*
			Test .showParsed notification (see [ajaxhook.ve.js]),
			which is only used when editing an existing article.

			First we need an existing article. Because of the moderation,
			such article can only be created by an automoderated user.
		*/
    var ExistingPageName = browser.call(() => ExistingPagePromise);

    /* Now that we have an existing page, edit it again as anonymous user */
    VisualEditor.edit(ExistingPageName, "Suggested content: " + Content);
    PostEdit.init();

    // Wait for text to appear, it happens in onload event
    browser.waitUntil(function () {
      return PostEdit.pageContent.getText();
    });

    expect(PostEdit.pageContent.getText(), "PostEdit.pageContent").to.not.equal(
      "",
    );
  });
});
