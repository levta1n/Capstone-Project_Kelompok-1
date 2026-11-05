import {
  jakartaDateInput,
  createApprovedRequest,
} from '../../support/proposal-test-helpers'
import {
  loginAs,
  logoutAs,
  uniqueTestLabel,
} from '../../support/test-helpers'

describe('TC-15: laporan berdasarkan periode', () => {
  it('meminta laporan untuk periode pilihan dan memuat status serta data ruangan', () => {
    createApprovedRequest(uniqueTestLabel('TC15')).then((request) => {
      const reportDate = jakartaDateInput(request.starts_at)

      logoutAs('requester')
      loginAs('admin')
      cy.get('.dashboard-sidebar').should('be.visible')
      cy.contains('button', 'Ekspor laporan').click()
      cy.get('#report-from').clear()
      cy.get('#report-from').type(reportDate)
      cy.get('#report-to').clear()
      cy.get('#report-to').type(reportDate)

      cy.intercept('POST', '**/rest/v1/rpc/admin_booking_report').as('bookingReport')
      cy.intercept('POST', '**/rest/v1/rpc/admin_room_usage_report').as('roomUsageReport')
      cy.get('.modal.show').contains('button', 'Unduh laporan ZIP').click()

      cy.wait('@bookingReport').then(({ request: rpcRequest, response }) => {
        expect(response?.statusCode).to.equal(200)
        expect(new Date(rpcRequest.body.p_to).getTime())
          .to.be.greaterThan(new Date(rpcRequest.body.p_from).getTime())
        const booking = response?.body?.find((row) => row.request_code === request.request_code)
        expect(booking?.status, 'booking status in selected period report').to.equal('accepted')
      })
      cy.wait('@roomUsageReport').then(({ response }) => {
        expect(response?.statusCode).to.equal(200)
        expect(response?.body).to.be.an('array')
      })
    })
  })
})
