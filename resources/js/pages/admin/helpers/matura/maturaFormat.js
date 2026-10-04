export const statusLabels = {
    requested: 'Wartet im Raum', approved: 'Freigegeben · Platz reserviert', departed: 'Unterwegs zur Station',
    arrived: 'An der Zwischenstation', toilet: 'Auf der Toilette', returning: 'Auf dem Rückweg',
    completed: 'Zurück im Raum', cancelled: 'Storniert', voided: 'Fehlbuchung',
}

export function time(value, date = false) {
    if (!value) return '—'
    return new Intl.DateTimeFormat('de-AT', {
        timeZone: 'Europe/Vienna', ...(date ? { day: '2-digit', month: '2-digit' } : {}),
        hour: '2-digit', minute: '2-digit', second: '2-digit',
    }).format(new Date(value))
}

export function duration(seconds) {
    if (seconds === null || seconds === undefined) return '—'
    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`
}

export function operationKey() {
    if (globalThis.crypto.randomUUID) return globalThis.crypto.randomUUID()
    const bytes = globalThis.crypto.getRandomValues(new Uint8Array(16))
    bytes[6] = (bytes[6] & 15) | 64
    bytes[8] = (bytes[8] & 63) | 128
    const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('')
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`
}

export function errorMessage(error) {
    return Object.values(error.response?.data?.errors ?? {}).flat()[0]
        || error.response?.data?.message || 'Keine Verbindung. Bitte Verbindung prüfen und erneut versuchen.'
}
