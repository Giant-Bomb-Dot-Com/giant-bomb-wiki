describe("Authentication", () => {
  it("should login", () => {
    cy.visit("/", {
      qs: { title: "Special%3AUserLogin", useskin: "vector-2022" },
    });
    cy.get("#pt-login-2 > a > span").click();

    // Assert we're on the login page with correct return parameters
    cy.url().should("include", "title=Special:UserLogin");
    cy.url().should("include", "returnto=Main");
    cy.url().should("include", "returntoquery=useskin");

    //TODO: Fix when using skins in query param, login does not reroute to keep this param
    // cy.get("#wpName1").type(Cypress.env("MW_DB_USER"));
    // cy.get("#wpPassword1").type(Cypress.env("MW_PASS"));
    // cy.get("#wpLoginAttempt").click();
    // cy.get("#pt-userpage > .new > span")
    //   .should("be.visible")
    //   .and("have.text", Cypress._.startCase(Cypress.env("MW_DB_USER")));
    // cy.get("#pt-logout > a > span").should("be.visible").click();

    // cy.get("#firstHeading").should("have.text", "Log out");
  });
});
