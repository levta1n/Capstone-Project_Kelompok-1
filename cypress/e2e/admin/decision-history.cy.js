import {
  requesterRequestRow,
  uniqueTestLabel,
} from '../../support/test-helpers'
import { createApprovedRequest } from '../../support/proposal-test-helpers'

describe('TC-14: status keputusan di riwayat pemohon', () => {
  it('menampilkan status persetujuan admin di riwayat akun pemohon', () => {
    createApprovedRequest(uniqueTestLabel('TC14')).then((request) => {
      cy.intercept('GET', '**/rest/v1/reservation_requests*').as('refreshedHistory')
      cy.reload()
      cy.wait('@refreshedHistory').should(({ response }) => {
        const historyRecord = response?.body?.find((row) => row.request_code === request.request_code)
        expect(historyRecord?.status, 'decision status in requester history').to.equal('accepted')
      })

      requesterRequestRow(request.request_code)
        .should('contain.text', 'Diterima')
    })
  })
})
