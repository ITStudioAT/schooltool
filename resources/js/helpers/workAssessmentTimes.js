export function assessmentTimeText(value) {
    if (!value || !Number.isFinite(Date.parse(value))) return 'Zeitpunkt unbekannt'
    const parts = new Intl.DateTimeFormat('de-AT', {
        timeZone: 'Europe/Vienna', year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(new Date(value))
    const values = Object.fromEntries(parts.map(part => [part.type, part.value]))
    return `${values.day}.${values.month}.${values.year}, ${values.hour}:${values.minute} Uhr`
}

export function assessmentCompletionText(value, state) {
    if (state === 'open') return 'noch offen'
    if (state === 'partial') return 'noch nicht abgeschlossen (teilweise beurteilt)'
    return assessmentTimeText(value)
}

export function currentAssessmentRecord(work, studentId) {
    if (!work?.id || !studentId) return null
    return (work.status?.assessment_json_imports || []).findLast(receipt => String(receipt.student_id) === String(studentId))?.record || null
}
