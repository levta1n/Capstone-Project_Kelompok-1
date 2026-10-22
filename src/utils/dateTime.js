export const APPLICATION_TIME_ZONE = 'Asia/Jakarta'

const jakartaDateTimeFormatter = new Intl.DateTimeFormat('en-CA', {
  timeZone: APPLICATION_TIME_ZONE,
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
  hourCycle: 'h23',
})

export function parseAppDateTime(value) {
  if (value instanceof Date) return new Date(value.getTime())
  if (typeof value !== 'string') return new Date(value)

  const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2})(?:\.(\d{1,3}))?)?$/.exec(value)
  if (!match) return new Date(value)

  const [, yearValue, monthValue, dayValue, hourValue, minuteValue, secondValue = '0', millisecondValue = '0'] = match
  const year = Number(yearValue)
  const month = Number(monthValue)
  const day = Number(dayValue)
  const hour = Number(hourValue)
  const minute = Number(minuteValue)
  const second = Number(secondValue)
  const millisecond = Number(millisecondValue.padEnd(3, '0'))

  const localDate = new Date(Date.UTC(year, month - 1, day, hour, minute, second, millisecond))
  if (
    localDate.getUTCFullYear() !== year
    || localDate.getUTCMonth() !== month - 1
    || localDate.getUTCDate() !== day
    || hour > 23
    || minute > 59
    || second > 59
  ) {
    return new Date(Number.NaN)
  }

  return new Date(Date.UTC(year, month - 1, day, hour - 7, minute, second, millisecond))
}

export function formatAppDateTime(value) {
  const date = value instanceof Date ? value : new Date(value)
  if (!Number.isFinite(date.getTime())) return ''

  const parts = Object.fromEntries(
    jakartaDateTimeFormatter.formatToParts(date).map(({ type, value: partValue }) => [type, partValue]),
  )
  return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`
}
