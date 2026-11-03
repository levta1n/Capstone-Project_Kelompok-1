import {
  cancelRequestByCode,
  createPendingRequest,
  loginAs,
  logoutAs,
  uniqueTestLabel,
} from '../../support/test-helpers'

describe('Admin menyetujui permintaan', () => {
  beforeEach(() => {
    loginAs('requester')
  })

  it('menyetujui permintaan Menunggu melalui daftar admin', () => {
    const purpose = uniqueTestLabel('APPROVE')

    createPendingRequest(purpose).then((request) => {
      logoutAs('requester')
      loginAs('admin')

      cy.get('input[type="search"]').type(request.request_code)
      cy.intercept('POST', '**/rest/v1/rpc/admin_review_reservation').as('adminReview')
      cy.get('#permintaan')
        .contains('tr', request.request_code)
        .find(`button[aria-label="Terima ${request.request_code}"]`)
        .click()
      cy.wait('@adminReview').its('response.statusCode').should('be.oneOf', [200, 201])
      cy.get('#permintaan')
        .contains('tr', request.request_code)
        .should('contain.text', 'Diterima')

      logoutAs('admin')
      loginAs('requester')
      cancelRequestByCode(request.request_code)
    })
  })
})
