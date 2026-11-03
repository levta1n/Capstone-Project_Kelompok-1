import { loginAs } from '../support/test-helpers'

describe('Login dan navigasi role', () => {
  it('membuka halaman login dari root', () => {
    cy.visit('/')
    cy.get('#login-form').should('be.visible')
    cy.get('#email').should('be.visible')
    cy.get('#password').should('be.visible')
  })

  it('login admin menuju dashboard manajemen', () => {
    loginAs('admin')
    cy.get('.dashboard-sidebar').should('contain.text', 'Pengguna')
  })

  it('login pemohon menuju portal permintaan', () => {
    loginAs('requester')
    cy.get('.requester-page').should('contain.text', 'Permintaan saya')
  })
})
