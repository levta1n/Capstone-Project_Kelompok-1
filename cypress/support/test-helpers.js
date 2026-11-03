const roleRoute = {
  admin: '/dashboard',
  requester: '/my-requests',
}

export function loginAs(role) {
  cy.env([`${role}Email`, `${role}Password`]).then((credentials) => {
    const email = credentials[`${role}Email`]
    const password = credentials[`${role}Password`]
    if (typeof email !== 'string' || !email.trim()) {
      throw new Error(`CYPRESS_${role.toUpperCase()}_EMAIL harus diatur.`)
    }
    if (typeof password !== 'string' || !password) {
      throw new Error(`CYPRESS_${role.toUpperCase()}_PASSWORD harus diatur.`)
    }

    cy.visit('/')
    cy.get('#email').type(email, { log: false })
    cy.get('#password').type(password, { log: false })
    cy.get('#login-form').find('button[type="submit"]').click()
    cy.location('pathname', { timeout: 20000 }).should('eq', roleRoute[role])

    if (role === 'admin') {
      cy.contains('h1', 'Dashboard Manajemen').should('be.visible')
    } else {
      cy.get('.requester-page h1').should('be.visible')
    }

    dismissInitialPasswordPrompt()
  })
}

export function dismissInitialPasswordPrompt() {
  cy.get('body').then(($body) => {
    if ($body.find('#password-prompt-title').length) {
      cy.get('#password-prompt-title')
        .closest('[role="dialog"]')
        .contains('button', 'Nanti')
        .click()
      cy.get('#password-prompt-title').should('not.exist')
    }
  })
}

export function logoutAs(role) {
  const selector = role === 'admin'
    ? '.dashboard-header-actions button.dashboard-logout'
    : '.requester-header button[aria-label="Keluar"]'

  cy.get(selector).click()
  cy.location('pathname', { timeout: 15000 }).should('eq', '/')
}

function jakartaDateInput(daysAhead) {
  const date = new Date(Date.now() + daysAhead * 24 * 60 * 60 * 1000)
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Asia/Jakarta',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).formatToParts(date).map(({ type, value }) => [type, value]),
  )

  return `${parts.year}-${parts.month}-${parts.day}`
}

function scheduleOffsetForPurpose(purpose) {
  let hash = 5381
  for (const character of purpose) {
    hash = (hash * 33 + character.charCodeAt(0)) >>> 0
  }
  return 14 + (hash % 240)
}

function selectAvailableSchedule(daysAhead, remainingDays = 30) {
  const startsAt = `${jakartaDateInput(daysAhead)}T10:00`
  const endsAt = `${jakartaDateInput(daysAhead)}T11:00`
  const availabilityAlias = `scheduleAvailability${daysAhead}`

  cy.intercept('POST', '**/rest/v1/rpc/get_room_availability_for').as(availabilityAlias)
  cy.get('#availability-start').clear()
  cy.get('#availability-start').type(startsAt)
  cy.get('#availability-end').clear()
  cy.get('#availability-end').type(endsAt)
  cy.contains('button', 'Cek jadwal').click()
  cy.wait(`@${availabilityAlias}`)

  return cy.get('.schedule-room-list').then(($list) => {
    const availableRoom = [...$list.find('article.schedule-room')].find((room) => {
      const requestButton = [...room.querySelectorAll('button')]
        .find((button) => button.textContent.includes('Ajukan ruangan'))
      return requestButton && !requestButton.disabled
    })

    if (!availableRoom) {
      if (remainingDays <= 1) {
        throw new Error('Tidak ada ruangan tersedia dalam 30 jadwal mendatang. Siapkan ruangan kosong di database testing.')
      }
      return selectAvailableSchedule(daysAhead + 1, remainingDays - 1)
    }

    cy.wrap(availableRoom).contains('button', 'Ajukan ruangan').click()
    return cy.then(() => ({ startsAt, endsAt }))
  })
}

export function requesterRequestRow(requestCode) {
  function findOnCurrentPage() {
    return cy.get('#requests-list').then(($section) => {
      const requestRow = [...$section.find('article.request-row')]
        .find((row) => row.textContent.includes(requestCode))
      if (requestRow) {
        return cy.contains('#requests-list article.request-row', requestCode)
      }

      const nextPage = $section.find('button[aria-label="Halaman berikutnya"]')
      if (nextPage.length && !nextPage.is(':disabled')) {
        cy.wrap(nextPage).click()
        return cy.then(findOnCurrentPage)
      }

      throw new Error(`Permintaan ${requestCode} tidak ditemukan di daftar pemohon.`)
    })
  }

  return findOnCurrentPage()
}

export function createPendingRequest(purpose, daysAhead = scheduleOffsetForPurpose(purpose)) {
  selectAvailableSchedule(daysAhead)
  cy.get('#request-participants').clear()
  cy.get('#request-participants').type('1')
  cy.get('#request-purpose').type(purpose)
  cy.intercept('GET', '**/rest/v1/reservation_requests*').as('reservationList')
  cy.contains('button', 'Kirim permintaan').click()

  let createdRequest
  let updatedRequestCount
  return cy.get('@reservationList.all').should((calls) => {
    const matchingResponse = calls.find(({ response }) =>
      Array.isArray(response?.body)
      && response.body.some((request) => request.purpose === purpose),
    )?.response
    expect(matchingResponse, 'new request should be returned by the reservation list API').to.exist
    createdRequest = matchingResponse.body.find((row) => row.purpose === purpose)
    updatedRequestCount = matchingResponse.body.length
  }).then(() => {
    return cy.get('#requests-list').should(($section) => {
      expect($section.text()).to.include(`dari ${updatedRequestCount}`)
    })
  }).then(() => {
    expect(createdRequest, 'created request record').to.exist
    expect(createdRequest.status).to.equal('pending')
    return createdRequest
  })
}

export function cancelRequestByCode(requestCode) {
  cy.intercept('POST', '**/rest/v1/rpc/cancel_reservation').as('cancelReservation')
  requesterRequestRow(requestCode)
  cy.get(`#requests-list button[aria-label="Batalkan ${requestCode}"]`).click()

  cy.wait('@cancelReservation').should(({ response }) => {
    expect(response?.statusCode).to.equal(200)
    expect(response?.body, 'cancellation RPC result').to.equal(true)
  })

  cy.intercept('GET', '**/rest/v1/reservation_requests*').as('reservationAfterCancellation')
  cy.reload()
  cy.get('.requester-page h1').should('be.visible')
  cy.wait('@reservationAfterCancellation').should(({ response }) => {
    const updatedRequest = response?.body?.find((request) => request.request_code === requestCode)
    expect(updatedRequest?.status, 'request status after cancellation').to.equal('cancelled')
  })
}

export function uniqueTestLabel(prefix) {
  return `CYPRESS-${prefix}-${Date.now()}`
}
