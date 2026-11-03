import {
  createPendingRequest,
  loginAs,
  requesterRequestRow,
  uniqueTestLabel,
} from '../../support/test-helpers'

describe('Pemohon membuat permintaan ruangan', () => {
  beforeEach(() => {
    loginAs('requester')
  })

  it('mengirim permintaan dan melihat status Menunggu', () => {
    const purpose = uniqueTestLabel('REQUEST')
    createPendingRequest(purpose).then((request) => {
      requesterRequestRow(request.request_code).should('contain.text', 'Menunggu')
      requesterRequestRow(request.request_code).should('contain.text', 'Menunggu')
    })
  })
})
