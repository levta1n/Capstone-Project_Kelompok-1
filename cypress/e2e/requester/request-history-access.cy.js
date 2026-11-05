import { loginAs } from '../../support/test-helpers'

describe('TC-13: cakupan riwayat permintaan pemohon', () => {
  it('membatasi permintaan riwayat ke ID pengguna yang sedang login', () => {
    cy.intercept('GET', '**/auth/v1/user').as('currentUser')
    cy.intercept('GET', '**/rest/v1/reservation_requests*').as('requestHistory')

    loginAs('requester')

    cy.wait('@currentUser').then(({ response }) => {
      const userId = response?.body?.id
      expect(userId, 'authenticated user ID').to.be.a('string').and.not.be.empty

      cy.wait('@requestHistory').then(({ request, response: historyResponse }) => {
        expect(request.url).to.include(`requester_id=eq.${userId}`)
        expect(historyResponse?.statusCode).to.equal(200)
        expect(historyResponse?.body).to.be.an('array')
      })
    })
  })
})
