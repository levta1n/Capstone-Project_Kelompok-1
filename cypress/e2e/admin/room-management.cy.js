import {
  loginAs,
  uniqueTestLabel,
} from '../../support/test-helpers'

describe('Admin mengelola ruangan', () => {
  beforeEach(() => {
    loginAs('admin')
  })

  it('menambah, mengubah, dan menonaktifkan ruangan', () => {
    const originalName = uniqueTestLabel('ROOM')
    const updatedName = `${originalName}-EDITED`

    cy.contains('button', 'Kelola ruangan').click()
    cy.get('#room-name').type(originalName)
    cy.get('#room-building').clear()
    cy.get('#room-building').type('Gedung Cypress')
    cy.get('#room-floor').clear()
    cy.get('#room-floor').type('Lantai Cypress')
    cy.get('#room-capacity').clear()
    cy.get('#room-capacity').type('12')
    cy.get('.modal.show').contains('button', 'Tambah ruangan').click()
    cy.contains('.list-group-item', originalName).should('be.visible')

    cy.get(`button[aria-label="Edit ${originalName}"]`).click()
    cy.get('#room-name').clear()
    cy.get('#room-name').type(updatedName)
    cy.get('#room-capacity').clear()
    cy.get('#room-capacity').type('16')
    cy.get('.modal.show').contains('button', 'Simpan perubahan').click()
    cy.contains('.list-group-item', updatedName).should('contain.text', '16 tempat')

    cy.contains('.list-group-item', updatedName).contains('button', 'Nonaktifkan').click()
    cy.contains('.list-group-item', updatedName).should('contain.text', 'Nonaktif')
  })
})
