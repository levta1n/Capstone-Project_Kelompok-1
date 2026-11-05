import { loginAs } from '../../support/test-helpers'

describe('TC-02 dan TC-03: akses dan autentikasi', () => {
  it('TC-02 menolak login dengan kata sandi yang salah dan menampilkan pesan', () => {
    cy.env(['requesterEmail']).then(({ requesterEmail }) => {
      expect(requesterEmail, 'CYPRESS_REQUESTER_EMAIL').to.be.a('string').and.not.be.empty

      cy.visit('/')
      cy.get('#email').type(requesterEmail, { log: false })
      cy.get('#password').type(`invalid-${Date.now()}`, { log: false })
      cy.get('#login-form').find('button[type="submit"]').click()

      cy.get('[role="alert"]').should('have.text', 'Email atau kata sandi salah.')
      cy.location('pathname').should('eq', '/')
    })
  })

  it('TC-02 mengalihkan pengguna yang belum login dari halaman terlindungi', () => {
    cy.visit('/dashboard')
    cy.location('pathname').should('eq', '/')
    cy.get('#login-form').should('be.visible')
  })

  it('TC-03 pemohon tidak dapat membuka dashboard atau menu admin', () => {
    loginAs('requester')
    cy.visit('/dashboard')

    cy.location('pathname').should('eq', '/my-requests')
    cy.get('.requester-page').should('be.visible')
    cy.get('.dashboard-sidebar').should('not.exist')
    cy.contains('button', 'Kelola ruangan').should('not.exist')
    cy.contains('button', 'Ekspor laporan').should('not.exist')
  })
})
