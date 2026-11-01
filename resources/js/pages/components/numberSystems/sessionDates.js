import { formatViennaDateTime } from '@/helpers/date'

export function numberSystemSessionDates(session) {
    const started = formatViennaDateTime(session.started_at)
    const ended = formatViennaDateTime(session.ended_at)
    const created = formatViennaDateTime(session.created_at)
    const parts = []
    if (started) { parts.push(`Gestartet: ${started}`) }
    else { parts.push(ended ? 'Kein Spielstart erfasst' : 'Noch nicht gestartet') }
    if (!started && created) { parts.push(`Erstellt: ${created}`) }
    if (ended) { parts.push(`Beendet: ${ended}`) }
    return parts.join(' · ')
}
