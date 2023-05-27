// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add("login", (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add("drag", { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add("dismiss", { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This will overwrite an existing command --
// Cypress.Commands.overwrite("visit", (originalFn, url, options) => { ... })
import 'cypress-file-upload';

Cypress.Commands.add("login", () => {
    cy.visit('/')
    cy.wait(1000)
    /*cy.get("body").then($body => {
        if ($body.find("#alertComponentClose").length > 0) {   
            cy.get('#alertComponentClose')
                .click()
        }
    });*/
    cy.get('#login')
        .type('bbain')
    cy.get('#password')
        .type('maarch')
    cy.get('#submit')
        .click()
})