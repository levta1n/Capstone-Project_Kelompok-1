import {
  createPendingRequest,
  loginAs,
  logoutAs,
  uniqueTestLabel,
} from '../../support/test-helpers'

describe('Admin menolak permintaan', () => {
  beforeEach(() => {
    loginAs('requester')
  })

  it('menolak permintaan dengan alasan', () => {
    const purpose = uniqueTestLabel('REJECT')
    const reason = uniqueTestLabel('REASON')

    createPendingRequest(purpose).then((request) => {
      logoutAs('requester')
      loginAs('admin')

      cy.get('input[type="search"]').type(request.request_code)
      cy.intercept('POST', '**/rest/v1/rpc/admin_review_reservation').as('adminReview')
      cy.get('#permintaan')
        .contains('tr', request.request_code)
        .find(`button[aria-label="Tolak ${request.request_code}"]`)
        .click()
      cy.get('.modal.show').contains('button', 'Tolak').click()
      cy.get('#request-rejection-reason').type(reason)
      cy.get('button[form="reject-request-form"]').click()
      cy.wait('@adminReview').its('response.statusCode').should('be.oneOf', [200, 201])
      cy.get('#permintaan')
        .contains('tr', request.request_code)
        .should('contain.text', 'Ditolak')
    })
  })
})
