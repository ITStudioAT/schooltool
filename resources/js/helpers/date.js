/**
 * Parses a date string or Date object into a local-time Date.
 *
 * new Date("2024-01-15") interprets "YYYY-MM-DD" as UTC midnight,
 * which shifts to the previous day in timezones east of UTC (e.g. CET).
 * This function splits the string and uses new Date(year, month, day)
 * to create the date in local time instead.
 */
export function parseLocalDate(date) {
    if (date instanceof Date) return date
    if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
        const [year, month, day] = date.split('-').map(Number)
        return new Date(year, month - 1, day)
    }
    return new Date(date)
}

export function applicationDate(date = new Date()) {
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Vienna' }).format(date)
}

export function workDeadlineExpired(work, now = Date.now()) {
    const date = work?.finish_until_date
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date || '') || !Number.isFinite(now)) return false
    const [year, month, day] = date.split('-').map(Number)
    const wallDate = Date.UTC(year, month - 1, day)
    if (new Date(wallDate).toISOString().slice(0, 10) !== date) return false
    const formatter = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Europe/Vienna', year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    })
    const localParts = timestamp => Object.fromEntries(formatter.formatToParts(new Date(timestamp)).map(part => [part.type, part.value]))
    const localKey = timestamp => {
        const parts = localParts(timestamp)
        return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`
    }
    if (!work.finish_until_time) return localKey(now).slice(0, 10) > date
    if (!/^(?:[01]\d|2[0-3]):[0-5]\d$/.test(work.finish_until_time)) return false
    const [hour, minute] = work.finish_until_time.split(':').map(Number)
    const wallDeadline = wallDate + (hour * 60 + minute) * 60000
    const offsets = [-86400000, 0, 86400000].map(delta => {
        const sample = wallDeadline + delta
        const parts = localParts(sample)
        return Date.UTC(Number(parts.year), Number(parts.month) - 1, Number(parts.day), Number(parts.hour), Number(parts.minute)) - sample
    })
    const candidates = [...new Set(offsets)].map(offset => wallDeadline - offset)
        .filter(timestamp => localKey(timestamp) === `${date}T${work.finish_until_time}`)
    // During the repeated autumn hour, retain the deadline until its last occurrence.
    return candidates.length > 0 && now > Math.max(...candidates)
}

export function formatViennaDateTime(timestamp) {
    if (!timestamp || !Number.isFinite(Date.parse(timestamp))) return ''

    const parts = new Intl.DateTimeFormat('de-AT', {
        timeZone: 'Europe/Vienna', year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(new Date(timestamp))
    const values = Object.fromEntries(parts.map(part => [part.type, part.value]))

    return `${values.day}.${values.month}.${values.year} um ${values.hour}:${values.minute} Uhr`
}
