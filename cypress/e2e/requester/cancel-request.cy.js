import {
  cancelRequestByCode,
  createPendingRequest,
  loginAs,
  uniqueTestLabel,
} from '../../support/test-helpers'

describe('Pemohon membatalkan permintaan', () => {
  beforeEach(() => {
    loginAs('requester')
  })

  it('membatalkan permintaan yang masih menunggu', () => {
    const purpose = uniqueTestLabel('CANCEL')

    createPendingRequest(purpose).then((request) => {
      cancelRequestByCode(request.request_code)
    })
  })
})
