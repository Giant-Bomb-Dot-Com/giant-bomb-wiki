describe("GiantBomb Skin", () => {
  it("should display games on home page, navigate to game detail page, and verify all game page features", () => {
    // Visit home page (GiantBomb is default skin)
    cy.visit("/");

    // Check that the landing page container is visible
    cy.get("#landing-page-container").should("be.visible");

    // Check that games are displayed in the game grid
    cy.get(".game-grid").should("be.visible");
    cy.get(".game-card").should("have.length.greaterThan", 0);

    // Get the first game card link and verify elements
    cy.get(".game-card-link")
      .first()
      .within(() => {
        // Verify game card has essential elements
        cy.get(".game-image").should("be.visible");
        cy.get(".game-title").should("be.visible");
      });

    // Click the game card link to navigate to game page
    cy.get(".game-card-link").first().click();

    // Verify we're now on a game detail page
    cy.url().should("include", "/Games/");

    // Verify game page elements are visible
    cy.get(".game-page").should("be.visible");
    cy.get(".game-hero").should("be.visible");
    cy.get(".game-hero__title").should("be.visible");

    // Verify game image is displayed
    cy.get(".game-hero__image").should("be.visible");
    cy.get(".game-hero__image img").should("be.visible");
    cy.get(".game-hero__image img").should("have.attr", "src");

    // Check if image viewer component is present
    cy.get('[data-vue-component="GameImageViewer"]').should("exist");
    cy.get(".game-image").should("be.visible");
    cy.get(".game-image-container").should("exist");

    // Check if platforms section exists with clickable tags
    cy.get(".game-info-item").contains("Platform").should("exist");
    cy.get(".game-tag--platform").should("have.length.greaterThan", 0);
    cy.get(".game-tag--platform").first().should("have.attr", "href");

    // Check for different game sections
    cy.get(".game-content").should("be.visible");
    cy.get(".game-section").should("have.length.greaterThan", 0);
    cy.get(".game-sidebar").should("be.visible");
  });
});
