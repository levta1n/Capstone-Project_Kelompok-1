function safePathSegment(value) {
  return value
    .normalize('NFKD')
    .replace(/[^\w.-]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .toLowerCase() || 'test'
}

afterEach(() => {
  const specParts = Cypress.spec.relative.split(/[\\/]/)
  const e2eIndex = specParts.lastIndexOf('e2e')
  const firstSpecPart = specParts[e2eIndex + 1]
  const functionality = e2eIndex < 0
    ? 'lainnya'
    : firstSpecPart?.includes('.')
      ? firstSpecPart.replace(/\.(cy|spec)\.[^.]+$/, '')
      : firstSpecPart || 'lainnya'
  const specName = specParts.at(-1).replace(/\.[^.]+$/, '')
  const testName = safePathSegment(Cypress.currentTest.titlePath.join('-'))

  cy.screenshot(`${functionality}/${specName}/${testName}`, {
    capture: 'viewport',
  })
})