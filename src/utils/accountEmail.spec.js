import { describe, expect, it } from 'vitest'
import { getEmailSignInCandidates } from './accountEmail'

describe('getEmailSignInCandidates', () => {
  it('uses the new account domain first and supports existing account domains', () => {
    expect(getEmailSignInCandidates('123456@campus.univ.ac.id')).toEqual([
      '123456@campus.univ.ac.id',
      '123456@ecampus.ut.ac.id',
      '123456@elearning.ut.ac.id',
    ])
  })

  it('preserves an entered legacy address as the first login candidate', () => {
    expect(getEmailSignInCandidates('123456@elearning.ut.ac.id')).toEqual([
      '123456@elearning.ut.ac.id',
      '123456@campus.univ.ac.id',
      '123456@ecampus.ut.ac.id',
    ])
  })

  it('does not rewrite unrelated email domains', () => {
    expect(getEmailSignInCandidates('user@example.com')).toEqual(['user@example.com'])
  })
})
