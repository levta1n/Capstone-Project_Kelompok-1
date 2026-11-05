import { loginAs } from '../../support/test-helpers'
import { openRequestFormAtAvailableSchedule } from '../../support/proposal-test-helpers'

describe('TC-06 dan TC-07: validasi formulir permintaan', () => {
  beforeEach(() => {
    loginAs('requester')
  })

  it('TC-06 menolak pengajuan dengan tujuan wajib yang kosong', () => {
    openRequestFormAtAvailableSchedule()
    cy.get('#request-purpose').clear()
    cy.get('#create-request-form').then(($form) => {
      expect($form[0].checkValidity()).to.equal(false)
      expect($form[0].querySelector('#request-purpose').validity.valueMissing).to.equal(true)
    })

    cy.contains('button', 'Kirim permintaan').click()
    cy.get('#create-request-form').should('be.visible')
    cy.get('#request-purpose').should('have.value', '')
  })

  it('TC-07 menolak pengajuan ketika waktu selesai sama dengan waktu mulai', () => {
    openRequestFormAtAvailableSchedule()
    cy.get('#request-starts').invoke('val').then((startsAt) => {
      cy.get('#request-ends').clear()
      cy.get('#request-ends').type(startsAt)
    })
    cy.get('#request-ends').invoke('val').then((endsAt) => {
      cy.get('#request-starts').should('have.value', endsAt)
    })

    cy.get('#request-purpose').type(`TC-07-${Date.now()}`)
    cy.contains('button', 'Kirim permintaan').click()
    cy.get('#create-request-form [role="alert"]')
      .should('contain.text', 'Waktu selesai harus setelah waktu mulai.')
    cy.get('#create-request-form').should('be.visible')
  })
})
