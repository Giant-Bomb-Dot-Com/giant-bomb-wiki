describe("Special Version", () => {
  it("should have the GiantBomb skin", () => {
    cy.visit("/Special:Version");
    cy.get("#mw-version-ext-skin-GiantBomb > :nth-child(1) > .external").should(
      "be.visible",
    );
  });

  it("should have the Semantic MediaWiki extension", () => {
    cy.visit("/Special:Version");
    cy.get(
      "#mw-version-ext-semantic-SemanticMediaWiki > :nth-child(1) > .external",
    ).should("be.visible");
  });
});
