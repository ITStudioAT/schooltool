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


function latestEvidenceTime(values) {
    return values.filter(value => Number.isFinite(Date.parse(value)))
        .sort((first, second) => Date.parse(second) - Date.parse(first))[0]
}

export function workProvisionEvidence(work, date = '', dispatch = {}) {
    const status = work?.status || {}
    const proofWork = { ...work, status: { ...status, dispatch_logs: (status.dispatch_logs || []).filter(log => log.recipient_scope !== 'teacher') } }
    const groups = work?.groups || []
    const groupDate = group => group.date || work?.date_for_all_groups || ''
    const dates = [...new Set(groups.map(groupDate).filter(Boolean))]
    const selectedDate = date || work?.date_for_all_groups || (dates.length === 1 ? dates[0] : '')
    const isGroupView = Boolean(selectedDate) && dates.some(group => group !== selectedDate)
    const assigned = new Set()
    const other = new Set()
    for (const group of groups) {
        const inScope = !selectedDate || groupDate(group) === selectedDate || dates.length === 0
        for (const id of group.student_ids || []) (inScope ? assigned : other).add(String(id))
    }
    const belongs = id => Boolean(id) && (assigned.has(String(id)) || groups.length === 0)
    // Older receipts identify a person but do not distinguish two dates of the same work.
    const belongsToHistoricalScope = id => belongs(id) && (!isGroupView || !other.has(String(id)))
    const rows = []
    const release = status.student_distribution || {}
    const portalInScope = !isGroupView || (release.student_ids || []).some(belongs)
    if (portalInScope && release.published_at) {
        const state = release.ended_at ? 'Freigabe beendet' : release.withdrawn_at ? 'Freigabe pausiert' : ''
        rows.push({ key: 'portal', label: 'Im Schülerbereich bereitgestellt', state })
    } else if (portalInScope && release.prepared_at) {
        rows.push({ key: 'portal', label: 'Schülerbereich vorbereitet' })
    } else if (release.deleted_at) {
        rows.push({ key: 'portal', label: 'Bereitstellung im Schülerbereich entfernt' })
    }

    const batches = Object.entries(status.assignment_mail?.batches || {})
        .filter(([slot]) => !selectedDate || slot === selectedDate || slot === 'all')
        .flatMap(([slot, batch]) => {
            const recipients = (batch.recipients || []).filter(row => belongs(row.student_id))
            if (!recipients.length && ((batch.recipients || []).some(row => row.student_id) || (slot === 'all' && isGroupView))) return []
            return [{ ...batch, recipients }]
        })
    const acceptedBy = transport => batches.filter(batch => batch.transport === transport)
        .flatMap(batch => batch.recipients.filter(row => ['accepted', 'already_sent'].includes(row.state)
            && Number.isFinite(Date.parse(row.accepted_at))).map(row => ({ ...row, withdrawn: Boolean(batch.withdrawn_at) })))
    const postmark = acceptedBy('postmark')
    const imported = workDispatchNotifications(proofWork, 'tasks').filter(row => belongsToHistoricalScope(row.student_id))
    const legacy = !isGroupView && dates.length <= 1 && ['sent', 'partial'].includes(dispatch.status) && Number(dispatch.confirmed_count) > 0
    if (postmark.length) {
        const count = new Set(postmark.map(row => String(row.student_id))).size
        const detail = `Postmark hat ${count} ${count === 1 ? 'Nachricht' : 'Nachrichten'} angenommen. Zustellung nicht bestätigt.`
        rows.push({ key: 'email', label: 'Per E-Mail versandt', detail,
            state: postmark.every(row => row.withdrawn) && !imported.length ? 'Verteilung zurückgezogen' : '',
            at: latestEvidenceTime(postmark.map(row => row.accepted_at)) })
    } else if (imported.length || (legacy && dispatch.transport === 'office_outlook')) {
        rows.push({ key: 'email', label: 'Per E-Mail versandt',
            detail: imported.length ? 'Gespeicherter Versandbeleg' : 'Versandbeleg im Prüfungsordner',
            at: imported[0]?.sent_at })
    }

    const historicalAttempts = [...new Set((status.dispatch_attempts || [])
        .map(row => row.student_id).filter(belongsToHistoricalScope).map(String))]
        .map(id => workDispatchRecord(proofWork, id, 'tasks'))
        .filter(Boolean)
    const mailpit = acceptedBy('mailpit')
    const historicalTests = historicalAttempts.filter(row => ['mailpit', 'test'].includes(row.mode))
    const legacyMailpit = legacy && dispatch.transport === 'mailpit'
    if (mailpit.length || historicalTests.length || legacyMailpit) {
        rows.push({ key: 'mailpit', label: 'Lokaler Mailpit-Test', at: latestEvidenceTime([
            ...mailpit.map(row => row.accepted_at), ...historicalTests.map(row => row.sent_at),
        ]) })
    }
    const pending = batches.filter(batch => !batch.withdrawn_at)
    const pendingStates = pending.flatMap(batch => batch.recipients.length ? batch.recipients.map(row => row.state) : [batch.state])
    const scheduled = historicalAttempts.filter(row => row.mode === 'scheduled')
    const prepared = historicalAttempts.filter(row => row.mode === 'prepared')
    if (pendingStates.includes('sending')) {
        rows.push({ key: 'email-pending', label: 'E-Mail-Versand läuft' })
    } else if (pendingStates.includes('queued') || scheduled.length) {
        rows.push({ key: 'email-pending', label: 'E-Mail-Versand geplant', at: latestEvidenceTime(scheduled.map(row => row.planned_at)) })
    } else if (pendingStates.includes('draft') || prepared.length) {
        rows.push({ key: 'email-pending', label: 'E-Mail vorbereitet', at: latestEvidenceTime([
            ...prepared.map(row => row.prepared_at), ...pending.filter(batch => batch.state === 'draft').map(batch => batch.prepared_at),
        ]) })
    } else if (!rows.some(row => row.key === 'email' || row.key === 'mailpit') && pending.length) {
        rows.push({ key: 'email-pending', label: 'E-Mail-Versand nicht bestätigt' })
    }

    const records = workProvisionRecords(proofWork, null, true).filter(row => belongsToHistoricalScope(row.student_id))
    for (const [method, label] of [['direct_issue', 'Direkt ausgegeben'], ['download_release', 'Zum Download bereitgestellt']]) {
        const evidence = records.filter(row => row.method === method)
            .sort((first, second) => Date.parse(second.available_at) - Date.parse(first.available_at))
        if (!evidence.length) continue
        const count = new Set(evidence.map(row => String(row.student_id))).size
        rows.push({ key: method, label, detail: `Für ${count} ${count === 1 ? 'Person' : 'Personen'} belegt. Individueller Empfang nicht bestätigt.`,
            at: evidence[0].available_at })
    }

    if (rows.length) return rows
    const chosen = status.provision_method?.channel
    return [{ key: 'choice', label: chosen === 'portal' ? 'Schülerbereich gewählt' : chosen === 'email' ? 'E-Mail gewählt' : 'Bereitstellungsweg offen' }]
}
