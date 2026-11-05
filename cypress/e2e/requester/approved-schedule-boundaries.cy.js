import {
  requesterRequestRow,
  uniqueTestLabel,
} from '../../support/test-helpers'
import {
  addHours,
  checkRoomAvailability,
  createApprovedRequest,
} from '../../support/proposal-test-helpers'

describe('TC-05, TC-09, dan TC-16: detail dan batas jadwal ruangan', () => {
  it('TC-05 menampilkan detail permintaan yang disetujui dan jadwal ruangannya', () => {
    createApprovedRequest(uniqueTestLabel('TC05')).then((request) => {
      requesterRequestRow(request.request_code)
      cy.get(`#requests-list button[aria-label="Detail ${request.request_code}"]`).click()
      cy.get('.modal.show')
        .should('contain.text', 'Mulai')
        .and('contain.text', 'Selesai')

      cy.get('.modal.show button[aria-label="Tutup detail"]').click()
      checkRoomAvailability(request.starts_at, request.ends_at).then((rooms) => {
        const room = rooms.find((candidate) => candidate.room_id === request.room_id)
        expect(room, 'approved room in availability response').to.exist
        expect(room.conflict_count).to.be.greaterThan(0)
        expect(room.is_available).to.equal(false)
      })
    })
  })

  it('TC-09 menandai ruangan tidak tersedia pada jadwal yang sudah disetujui', () => {
    createApprovedRequest(uniqueTestLabel('TC09')).then((request) => {
      checkRoomAvailability(request.starts_at, request.ends_at).then((rooms) => {
        const room = rooms.find((candidate) => candidate.room_id === request.room_id)
        expect(room, 'approved room in availability response').to.exist
        expect(room.availability_status).to.equal('Bentrok')
        expect(room.is_available).to.equal(false)
        cy.contains('.schedule-room', room.name).within(() => {
          cy.contains('Bentrok')
          cy.contains('button', 'Ajukan ruangan').should('be.disabled')
        })
      })
    })
  })

  it('TC-16 menganggap waktu tepat setelah akhir peminjaman sebagai tersedia', () => {
    createApprovedRequest(uniqueTestLabel('TC16')).then((request) => {
      checkRoomAvailability(request.ends_at, addHours(request.ends_at, 1)).then((rooms) => {
        const room = rooms.find((candidate) => candidate.room_id === request.room_id)
        expect(room, 'approved room in adjacent availability response').to.exist
        expect(room.conflict_count).to.equal(0)
        expect(room.is_available).to.equal(true)
        cy.contains('.schedule-room', room.name).should('contain.text', 'Tersedia')
      })
    })
  })
})
