import {
  createPendingRequest,
  loginAs,
  logoutAs,
} from './test-helpers'

export function jakartaDateTimeInput(value) {
  const date = new Date(value)
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Asia/Jakarta',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hourCycle: 'h23',
    }).formatToParts(date).map(({ type, value: partValue }) => [type, partValue]),
  )

  return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`
}

export function jakartaDateInput(value) {
  return jakartaDateTimeInput(value).slice(0, 10)
}

export function addHours(value, hours) {
  return new Date(new Date(value).getTime() + hours * 60 * 60 * 1000).toISOString()
}

export function createApprovedRequest(purpose, daysAhead) {
  loginAs('requester')

  return createPendingRequest(purpose, daysAhead).then((request) => {
    logoutAs('requester')
    loginAs('admin')

    cy.get('input[type="search"]').clear()
    cy.get('input[type="search"]').type(request.request_code)
    cy.intercept('POST', '**/rest/v1/rpc/admin_review_reservation').as('approveRequest')
    cy.get('#permintaan')
      .contains('tr', request.request_code)
      .find(`button[aria-label="Terima ${request.request_code}"]`)
      .click()

    return cy.wait('@approveRequest').then(({ response }) => {
      expect(response?.statusCode).to.equal(200)
      expect(response?.body, 'approval result').to.equal(true)
      logoutAs('admin')
      loginAs('requester')
      return cy.wrap(request, { log: false })
    })
  })
}

export function openRequestFormAtAvailableSchedule(daysAhead = 365, remainingDays = 30) {
  const start = new Date(Date.now() + daysAhead * 24 * 60 * 60 * 1000)
  const date = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Jakarta',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(start)
  const startsAt = `${date}T10:00`
  const endsAt = `${date}T11:00`
  const alias = `proposalAvailability${daysAhead}`

  cy.intercept('POST', '**/rest/v1/rpc/get_room_availability_for').as(alias)
  cy.get('#availability-start').clear()
  cy.get('#availability-start').type(startsAt)
  cy.get('#availability-end').clear()
  cy.get('#availability-end').type(endsAt)
  cy.contains('button', 'Cek jadwal').click()
  cy.wait(`@${alias}`)

  return cy.get('.schedule-room-list').then(($list) => {
    const availableRoom = [...$list.find('article.schedule-room')].find((room) => {
      const requestButton = [...room.querySelectorAll('button')]
        .find((button) => button.textContent.includes('Ajukan ruangan'))
      return requestButton && !requestButton.disabled
    })

    if (!availableRoom) {
      if (remainingDays <= 1) {
        throw new Error('Tidak ada ruangan tersedia untuk pengujian validasi permintaan.')
      }
      return openRequestFormAtAvailableSchedule(daysAhead + 1, remainingDays - 1)
    }

    cy.wrap(availableRoom).contains('button', 'Ajukan ruangan').click()
    return cy.wrap({ startsAt, endsAt }, { log: false })
  })
}

export function checkRoomAvailability(startsAt, endsAt) {
  cy.intercept('POST', '**/rest/v1/rpc/get_room_availability_for').as('checkedAvailability')
  cy.get('#availability-start').clear()
  cy.get('#availability-start').type(jakartaDateTimeInput(startsAt))
  cy.get('#availability-end').clear()
  cy.get('#availability-end').type(jakartaDateTimeInput(endsAt))
  cy.contains('button', 'Cek jadwal').click()

  return cy.wait('@checkedAvailability').then(({ response }) => {
    expect(response?.statusCode).to.equal(200)
    return response.body
  })
}
