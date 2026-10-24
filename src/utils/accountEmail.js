export const ACCOUNT_EMAIL_DOMAIN = 'campus.univ.ac.id'

const LEGACY_EMAIL_DOMAINS = ['ecampus.ut.ac.id', 'elearning.ut.ac.id']

export function getEmailSignInCandidates(email) {
  const trimmedEmail = email.trim()
  const separator = trimmedEmail.lastIndexOf('@')
  if (separator < 1) return [trimmedEmail]

  const username = trimmedEmail.slice(0, separator)
  const domain = trimmedEmail.slice(separator + 1).toLowerCase()
  if (![ACCOUNT_EMAIL_DOMAIN, ...LEGACY_EMAIL_DOMAINS].includes(domain)) {
    return [trimmedEmail]
  }

  return [...new Set([
    trimmedEmail,
    `${username}@${ACCOUNT_EMAIL_DOMAIN}`,
    ...LEGACY_EMAIL_DOMAINS.map((legacyDomain) => `${username}@${legacyDomain}`),
  ])]
}
