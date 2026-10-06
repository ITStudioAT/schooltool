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

export function formatViennaDateTime(timestamp) {
    if (!timestamp || !Number.isFinite(Date.parse(timestamp))) return ''

    const parts = new Intl.DateTimeFormat('de-AT', {
        timeZone: 'Europe/Vienna', year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(new Date(timestamp))
    const values = Object.fromEntries(parts.map(part => [part.type, part.value]))

    return `${values.day}.${values.month}.${values.year} um ${values.hour}:${values.minute} Uhr`
}
