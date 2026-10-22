import { describe, expect, it } from 'vitest'
import { formatAppDateTime, parseAppDateTime } from './dateTime'

describe('Jakarta date-time conversion', () => {
  it('parses date-time input as WIB regardless of machine timezone', () => {
    expect(parseAppDateTime('2026-10-23T01:00').toISOString())
      .toBe('2026-10-22T18:00:00.000Z')
  })

  it('formats timestamps as WIB for date-time inputs', () => {
    expect(formatAppDateTime(new Date('2026-10-23T05:15:00.000Z')))
      .toBe('2026-10-23T12:15')
  })
})
