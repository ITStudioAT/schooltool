export function workDispatchNotifications(work, purpose = 'results') {
    if (!work?.id) return []
    const logs = work.status?.dispatch_logs || []

    return (work.status?.dispatch_notifications || [])
        .filter(notification => notification.origin === 'dispatch_import'
            && (notification.purpose || 'results') === purpose
            && notification.student_id
            && Number.isFinite(Date.parse(notification.sent_at))
            && logs.some(log => log.origin === 'dispatch_import' && (log.purpose || 'results') === purpose && log.sha256 === notification.log_sha256))
        .sort((first, second) => Date.parse(second.sent_at) - Date.parse(first.sent_at))
}

export function workDispatchNotification(work, studentId, purpose = 'results') {
    if (!studentId) return null

    return workDispatchNotifications(work, purpose).find(notification => String(notification.student_id) === String(studentId)) || null
}

export function workDispatchRecord(work, studentId, purpose, aggregate = false) {
    if (!work?.id || (!aggregate && !studentId)) return null
    const success = aggregate ? workDispatchNotifications(work, purpose)[0] : workDispatchNotification(work, studentId, purpose)
    if (success) return { ...success, mode: 'live' }
    return (work.status?.dispatch_attempts || []).findLast(attempt => attempt.origin === 'dispatch_import'
        && attempt.purpose === purpose && attempt.student_id
        && (aggregate || String(attempt.student_id) === String(studentId))
        && (work.status?.dispatch_logs || []).some(log => log.origin === 'dispatch_import'
            && log.purpose === purpose && log.sha256 === attempt.log_sha256)) || null
}

export function dispatchNotificationText(sentAt, purpose = 'results', mode = 'live') {
    const prefix = purpose === 'tasks' ? 'Aufgabenversand' : 'Ergebnisbenachrichtigung'
    const action = mode === 'live' ? (purpose === 'tasks' ? 'Aufgaben per E-Mail versandt' : 'Über die Korrektur per E-Mail verständigt')
        : `${prefix}: ${mode === 'test' ? 'lokaler Mailpit-Test' : 'kein bestätigter Live-Versand'}`
    if (!sentAt || !Number.isFinite(Date.parse(sentAt))) return mode === 'live' ? '' : `${action}.`
    const parts = new Intl.DateTimeFormat('de-AT', {
        timeZone: 'Europe/Vienna', year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(new Date(sentAt))
    const values = Object.fromEntries(parts.map(part => [part.type, part.value]))

    return `${action} am ${values.day}.${values.month}.${values.year} um ${values.hour}:${values.minute} Uhr.`
}
