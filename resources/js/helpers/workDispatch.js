export function workStudentDistribution(work, studentId = null) {
    const release = work?.status?.student_distribution
    if (!release?.published_at || release.withdrawn_at) return null
    const assignedIds = new Set((work.groups || []).flatMap(group => group.student_ids || []).map(String))
    const recipients = (release.student_ids || []).filter(id => assignedIds.has(String(id)))
    return recipients.length && (studentId === null || recipients.some(id => String(id) === String(studentId))) ? release : null
}

export function workTaskDispatchCompleted(work) {
    return work?.status?.task_dispatch_completed === true
}

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

export function workProvisionRecords(work, studentId, aggregate = false) {
    if (!work?.id || (!aggregate && !studentId)) return []
    const logs = work.status?.dispatch_logs || []
    return (work.status?.provision_events || []).filter(event => event.origin === 'provision_import' && event.student_id
        && (aggregate || String(event.student_id) === String(studentId))
        && ['download_release', 'direct_issue'].includes(event.method) && Number.isFinite(Date.parse(event.available_at))
        && logs.some(log => log.origin === 'provision_import' && log.purpose === 'provision' && log.sha256 === event.log_sha256))
}

export function provisionNotificationText(record, aggregate = false, count = 1) {
    const action = record.method === 'download_release' ? 'Zum Download bereitgestellt' : 'Direkt ausgegeben'
    const parts = new Intl.DateTimeFormat('de-AT', { timeZone: 'Europe/Vienna', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(new Date(record.available_at))
    const values = Object.fromEntries(parts.map(part => [part.type, part.value]))
    return `${aggregate ? 'Zuletzt ' + action[0].toLowerCase() + action.slice(1) : action} am ${values.day}.${values.month}.${values.year} um ${values.hour}:${values.minute} Uhr.${aggregate ? ` ${count} ${count === 1 ? 'Person' : 'Personen'} mit belegter Verfügbarkeit.` : ''} Individueller Download oder Empfang nicht bestätigt.`
}

export function workDispatchRecord(work, studentId, purpose, aggregate = false) {
    if (!work?.id || (!aggregate && !studentId)) return null
    const success = aggregate ? workDispatchNotifications(work, purpose)[0] : workDispatchNotification(work, studentId, purpose)
    if (success) return { ...success, mode: 'live' }
    const attempts = (work.status?.dispatch_attempts || []).filter(attempt => attempt.origin === 'dispatch_import'
        && attempt.purpose === purpose && attempt.student_id
        && (aggregate || String(attempt.student_id) === String(studentId))
        && (work.status?.dispatch_logs || []).some(log => log.origin === 'dispatch_import'
            && log.purpose === purpose && log.sha256 === attempt.log_sha256))
    const planning = attempts.filter(attempt => ['prepared', 'scheduled'].includes(attempt.mode))
        .sort((first, second) => Number(second.mode === 'scheduled') - Number(first.mode === 'scheduled')
            || Number(isDispatchReadyRecord(work, second)) - Number(isDispatchReadyRecord(work, first))
            || (Date.parse(second.prepared_at) || 0) - (Date.parse(first.prepared_at) || 0))

    const localDelivery = attempts.filter(attempt => attempt.mode === 'mailpit' && attempt.provider_id && Number.isFinite(Date.parse(attempt.sent_at)))
        .sort((first, second) => Date.parse(second.sent_at) - Date.parse(first.sent_at))
    return localDelivery[0] || planning[0] || attempts.at(-1) || null
}

export function workDispatchPlanningRecords(work, purpose) {
    const studentIds = [...new Set((work?.status?.dispatch_attempts || [])
        .filter(attempt => attempt.purpose === purpose && attempt.student_id)
        .map(attempt => String(attempt.student_id)))]

    return studentIds.map(studentId => workDispatchRecord(work, studentId, purpose))
        .filter(record => ['prepared', 'scheduled'].includes(record?.mode))
}

function isDispatchReadyRecord(work, record) {
    return record?.mode === 'prepared' && record.purpose === 'tasks'
        && (work.status?.dispatch_logs || []).some(log => log.origin === 'dispatch_import' && log.purpose === 'tasks'
            && log.sha256 === record.log_sha256 && log.dispatch_ready === true)
}

export function workDispatchReady(work, studentId = null, aggregate = false) {
    return isDispatchReadyRecord(work, workDispatchRecord(work, studentId, 'tasks', aggregate))
}

export function dispatchNotificationText(sentAt, purpose = 'results', mode = 'live') {
    const prefix = purpose === 'tasks' ? 'Aufgabenversand' : 'Ergebnisbenachrichtigung'
    const action = mode === 'live' ? (purpose === 'tasks' ? 'Aufgaben per E-Mail versandt' : 'Über die Korrektur per E-Mail verständigt')
        : mode === 'mailpit' ? `${prefix}: versandt · Testversand über Mailpit`
        : mode === 'scheduled' ? `${prefix}: serverseitig geplant`
            : mode === 'prepared' ? `${prefix}: vorbereitet${sentAt ? ' · geplanter Versand' : ''}`
                : `${prefix}: ${mode === 'test' ? 'lokaler Mailpit-Test' : 'kein bestätigter Live-Versand'}`
    if (!sentAt || !Number.isFinite(Date.parse(sentAt))) return mode === 'live' ? '' : `${action}.`
    const parts = new Intl.DateTimeFormat('de-AT', {
        timeZone: 'Europe/Vienna', year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(new Date(sentAt))
    const values = Object.fromEntries(parts.map(part => [part.type, part.value]))

    return `${action} am ${values.day}.${values.month}.${values.year} um ${values.hour}:${values.minute} Uhr.${['prepared', 'scheduled'].includes(mode) ? ' Versand noch nicht bestätigt.' : ''}`
}
