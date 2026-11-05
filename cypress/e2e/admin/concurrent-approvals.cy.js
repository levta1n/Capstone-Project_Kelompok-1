import {
  createPendingRequest,
  loginAs,
  logoutAs,
  uniqueTestLabel,
} from '../../support/test-helpers'

describe('TC-12: persetujuan jadwal bentrok secara bersamaan', () => {
  beforeEach(() => {
    loginAs('requester')
  })

  it('memastikan maksimal satu dari dua permintaan bentrok berstatus disetujui', () => {
    const firstPurpose = uniqueTestLabel('TC12-FIRST')
    const secondPurpose = uniqueTestLabel('TC12-SECOND')

    createPendingRequest(firstPurpose, 500).then((firstRequest) => {
      createPendingRequest(secondPurpose, 500).then((secondRequest) => {
        expect(secondRequest.room_id).to.equal(firstRequest.room_id)
        expect(secondRequest.starts_at).to.equal(firstRequest.starts_at)
        expect(secondRequest.ends_at).to.equal(firstRequest.ends_at)

        logoutAs('requester')
        loginAs('admin')

        cy.intercept('POST', '**/rest/v1/rpc/admin_review_reservation', (request) => {
          request.continue((response) => response.setDelay(1200))
        }).as('adminReview')
        cy.get('input[type="search"]').type(firstRequest.request_code)
        cy.get(`#permintaan button[aria-label="Terima ${firstRequest.request_code}"]`).click()
        cy.get('input[type="search"]').clear()
        cy.get('input[type="search"]').type(secondRequest.request_code)
        cy.get(`#permintaan button[aria-label="Terima ${secondRequest.request_code}"]`).click()

        cy.wait('@adminReview')
        cy.wait('@adminReview')
        cy.intercept('GET', '**/rest/v1/reservation_requests*').as('finalRequests')
        cy.reload()
        cy.wait('@finalRequests').should(({ response }) => {
          const requests = response?.body?.filter((request) =>
            [firstRequest.request_code, secondRequest.request_code].includes(request.request_code),
          )
          expect(requests, 'both reviewed requests').to.have.length(2)
          expect(requests.filter((request) => request.status === 'accepted')).to.have.length.at.most(1)
        })
      })
    })
  })
})
