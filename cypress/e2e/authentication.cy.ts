describe("Authentication", () => {
  it("should login", () => {
    cy.visit("/", { qs: { title: "Special%3AUserLogin" } });
    cy.get("#pt-login > a > span").click();
    cy.get("#wpName1").type(Cypress.env("MW_DB_USER"));
    cy.get("#wpPassword1").type(Cypress.env("MW_PASS"));
    cy.get("#wpLoginAttempt").click();
    cy.get("#pt-userpage > .new > span")
      .should("be.visible")
      .and("have.text", Cypress._.startCase(Cypress.env("MW_DB_USER")));
    cy.get("#pt-logout > a > span").should("be.visible").click();

    cy.get("#firstHeading").should("have.text", "Log out");
  });
});
