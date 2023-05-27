describe('Indexing document EE', () => {
  it('Record incoming mail', () => {
    cy.login();
    cy.visit('home')
    cy.wait(1000)
    cy.get('#indexing')
      .click({force: true})
      cy.wait(3000)
    cy.get('#doctype')
      .click()
    cy.wait(1000)
    cy.get('[title="Demande de renseignements"]')
      .click({force: true});
    cy.get('#priority')
      .click({multiple: true})
    cy.get('[title="Normal"]')
      .click({multiple: true, force: true})
    cy.get('#documentDate')
      .click()
    cy.get('.mat-calendar-body-active')
      .click();
    cy.get('#subject')
      .type('test ee')
    cy.get('#senders')
      .type('pascon')
    cy.get('#senders-6')
      .click()
    cy.get('#destination')
      .click()
    cy.wait(1000)
    cy.get('[title="Pôle Jeunesse et Sport"]')
      .click({multiple: true, force: true});
    cy.wait(1000)
    cy.fixture('sample.pdf').then(fileContent => {
      cy.get('#inputFileMainDoc').attachFile({
          fileContent: fileContent.toString(),
          fileName: 'sample.pdf',
          mimeType: 'application/pdf'
      });
    });
    cy.wait(1000)
    cy.get('.mat-button-wrapper')
      .contains('Valider')
      .click({multiple: true})
    cy.wait(1000)
    cy.get('[placeholder="Ajouter une annotation"]')
      .type('test ee')
      cy.wait(1000)
    cy.get('.mat-dialog-content-container .mat-button-wrapper')
      .contains('Valider')
      .click({multiple: true})
    cy.wait(1000)
    cy.url().should('include', '/resources/')
  })
})