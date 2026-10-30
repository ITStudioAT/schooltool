import { assessmentTimeText } from '@/helpers/workAssessmentTimes'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { dispatchNotificationText, workDispatchRecord, provisionNotificationText } from '@/helpers/workDispatch'
import { personalWorkAssessment, personalWorkProvision, workCollectionEvidence, workSubmissionStatus } from '@/helpers/workSubmission'
import { workSimulationEvaluation } from '@/helpers/workSimulation'

function notificationLabel(record, purpose) {
    if (!record) return `${purpose === 'tasks' ? 'Aufgabenversand' : 'Ergebnisbenachrichtigung'}: kein bestätigter E-Mail-Versand.`
    return dispatchNotificationText(['prepared', 'scheduled'].includes(record.mode) ? record.planned_at : record.sent_at,
        purpose, record.mode)
}

function notificationState(record) {
    if (record?.mode === 'live') return 'sent'
    return ['prepared', 'scheduled'].includes(record?.mode) ? record.mode : 'unknown'
}

export function workEvaluationStatus(work, studentId, now = Date.now()) {
    const record = personalWorkAssessment(work, studentId)
    const unknown = { state: 'unknown', color: 'orange-darken-2', label: 'Bewertungsabschluss nicht belegt.' }
    if (!record) return unknown
    if (record.evaluation_state === 'open') return { ...unknown, state: 'open', label: 'Bewertung noch offen.' }
    if (record.evaluation_state === 'partial') return { ...unknown, state: 'partial', label: 'Bewertung teilweise erfolgt; noch nicht abgeschlossen.' }
    if (record.evaluation_state !== 'complete') return unknown
    const absence = record.submission_state === 'not_received'
    if (absence && workSubmissionStatus(work, studentId, now).state !== 'not_received') {
        return { ...unknown, label: 'Bewertungsabschluss ungeklärt: Nichtabgabe aktuell nicht bestätigt.' }
    }
    if (!absence && record.submission_state !== 'received') return unknown
    const completed = Number.isFinite(Date.parse(record.evaluation_completed_at))
        ? ` Abschluss: ${assessmentTimeText(record.evaluation_completed_at)}.` : ''
    return { state: 'complete', color: 'success',
        label: `${absence ? 'Bestätigte Nichtabgabe abschließend beurteilt; keine eingegangene Arbeit korrigiert.' : 'Bewertung abgeschlossen.'}${completed}` }
}

export function workCellStatuses(work, studentId, now = Date.now()) {
    if (!workAutomationEnabled(work)) return []
    const tasks = workDispatchRecord(work, studentId, 'tasks')
    const provision = personalWorkProvision(work, studentId)
    const direct = provision && tasks?.mode !== 'live'
    const output = direct
        ? { icon: 'mdi-folder-open-outline', color: 'success', state: 'available',
            label: provision.method === 'download_release'
                ? `Aufgabe zum Download bereitgestellt; tatsächlicher Download nicht bekannt. Verfügbar seit ${assessmentTimeText(provision.available_at)}.`
                : provisionNotificationText(provision) }
        : { icon: tasks?.mode === 'scheduled' ? 'mdi-clock-outline' : 'mdi-file-send-outline', color: tasks?.mode === 'live' ? 'success' : 'orange-darken-2',
            state: notificationState(tasks), label: notificationLabel(tasks, 'tasks')
                + (provision ? ` ${provisionNotificationText(provision)}` : '') }
    const results = workDispatchRecord(work, studentId, 'results')
    return [
        { purpose: 'tasks', ...output },
        { purpose: 'submission', icon: 'mdi-inbox-arrow-down', ...workSubmissionStatus(work, studentId, now) },
        { purpose: 'evaluation', icon: 'mdi-file-check-outline', ...workEvaluationStatus(work, studentId, now) },
        { purpose: 'results', icon: results?.mode === 'live' ? 'mdi-email-check-outline' : results?.mode === 'scheduled' ? 'mdi-clock-outline' : 'mdi-email-outline',
            color: results?.mode === 'live' ? 'success' : 'orange-darken-2',
            state: notificationState(results), label: notificationLabel(results, 'results') },
    ]
}

function activeStudentIds(students = []) {
    return [...new Set(students.filter(student => !student.canceled_at && !student.deleted_at)
        .map(student => Object.prototype.hasOwnProperty.call(student, 'user_id') ? student.user_id : student.id)
        .filter(Boolean).map(String))]
}

export function workParticipantIds(work, students = []) {
    const assigned = new Set((work?.groups || []).flatMap(group => group.student_ids || []).map(String))
    return activeStudentIds(students).filter(id => assigned.has(id))
}

export function workAggregateStatuses(work, students = [], now = Date.now()) {
    const studentIds = workParticipantIds(work, students)
    const personal = studentIds.map(studentId => workCellStatuses(work, studentId, now))
    const collection = workCollectionEvidence(work, studentIds)
    const definitions = {
        tasks: 'Aufgabenbereitstellung / Versand', submission: 'Abgabenstatus',
        evaluation: 'Bewertung', results: 'Ergebnisbenachrichtigung',
    }
    const actions = {
        tasks: 'mit belegter Bereitstellung oder bestätigtem E-Mail-Versand', submission: 'mit belegtem Abgabeneingang',
        evaluation: 'mit abgeschlossener Bewertung', results: 'mit bestätigter Ergebnis-E-Mail',
    }
    return workCellStatuses(work, null, now).map(base => {
        const statuses = personal.map(items => items.find(item => item.purpose === base.purpose))
        const count = statuses.filter(item => item.color === 'success').length
        const total = studentIds.length
        const scheduled = statuses.filter(item => item.state === 'scheduled').length
        const prepared = statuses.filter(item => item.state === 'prepared').length
        const missing = statuses.filter(item => item.state === 'not_received').length
        const dispatchAction = ['tasks', 'results'].includes(base.purpose) && count > 0
        const collected = base.purpose === 'submission' && collection
        const complete = total > 0 && (count === total || dispatchAction || collected)
        const partial = !complete && (count > 0 || (scheduled > 0 && scheduled < total) || (prepared > 0 && prepared < total))
        const state = complete ? 'complete' : partial ? 'partial' : scheduled === total && total ? 'scheduled'
            : prepared === total && total ? 'prepared' : missing === total && total ? 'not_received' : 'unknown'
        const label = collected ? `Abholung durchgeführt; ${count} von ${total} Abgaben vorhanden.`
            : `${definitions[base.purpose]}: ${count} von ${total} Personen ${actions[base.purpose]}.`
        const details = [partial ? 'Teilstand; Gesamtabschluss nicht belegt.' : '',
            !total ? 'Keine aktiven Personen dieser Arbeit zugeordnet.' : '',
            missing ? `${missing} bestätigte Nichtabgabe${missing === 1 ? '' : 'n'}.` : '',
            scheduled ? `${scheduled} serverseitig geplant; Versand nicht bestätigt.` : '',
            prepared ? `${prepared} vorbereitet; Versand nicht bestätigt.` : '',
            collected && collection === 'local' ? 'Belegter lokaler Abschlussbestand; keine Aussage über eine vollständige Serversuche.' : '',
        ].filter(Boolean).join(' ')
        const successful = statuses.find(item => item.color === 'success')
        return { ...base, state, count, total,
            icon: complete && successful ? successful.icon : scheduled ? 'mdi-clock-outline' : base.icon,
            color: complete ? 'success' : missing ? 'error' : partial ? 'warning' : total ? 'orange-darken-2' : 'grey-darken-1',
            label: `${label}${details ? ` ${details}` : ''}` }
    })
}

function statusCategory(status) {
    if (status.color === 'success') return 'complete'
    if (['scheduled', 'prepared', 'partial', 'not_received'].includes(status.state)) return status.state
    return 'unknown'
}

export function workCellDeviationStatuses(work, studentId, students, now = Date.now()) {
    const aggregate = workAggregateStatuses(work, students, now)
    return workCellStatuses(work, studentId, now).filter(item => {
        const common = aggregate.find(status => status.purpose === item.purpose)
        return !common || statusCategory(item) !== statusCategory(common)
    })
}

export function workCellPendingStatuses(work, studentId, now = Date.now()) {
    if (!work || !workAutomationEnabled(work) || !studentId) return []
    const ids = (work.groups || []).flatMap(group => group.student_ids || []).map(String)
    if (!ids.includes(String(studentId))) return []
    const simulation = workSimulationEvaluation(work)
    const simulated = simulation?.rows.find(row => String(row.student_id) === String(studentId))
    const record = personalWorkAssessment(work, studentId)
    if (!simulation && !(work.status?.assessment_json_imports || []).length) return []
    const evaluation = simulated || workEvaluationStatus(work, studentId, now)
    if (evaluation.state !== 'complete') return [{ purpose: 'evaluation', icon: 'mdi-file-check-outline',
        state: evaluation.state, color: 'orange-darken-2',
        label: simulated ? `Bewertung ${simulated.state === 'partial' ? 'teilweise erfolgt' : 'noch offen'} · Simulation. ${simulated.result}`
            : record ? evaluation.label : 'Bewertung noch offen.' }]
    const receipt = (work.status?.assessment_json_imports || []).findLast(item => String(item.student_id) === String(studentId))
    const completedAt = Date.parse(record?.evaluation_completed_at)
    const simulatedCompletedAt = Date.parse(simulated?.evaluation_completed_at)
    const current = !simulated || (receipt?.exercise_id === `simulation:${simulation.run_id}:work:${work.id}`
        && record?.evaluation_state === 'complete' && Number.isFinite(completedAt) && completedAt === simulatedCompletedAt)
    const pdf = (work.status?.evaluation_pdfs || []).find(item => item.origin === 'evaluation_import' && String(item.student_id) === String(studentId)
        && item.file_path && /^[a-f0-9]{64}$/.test(item.sha256 || '')
        && (!record?.pdf?.sha256 || record.pdf.sha256 === item.sha256)
        && (!simulated?.pdf_sha256 || simulated.pdf_sha256 === item.sha256))
    if (record?.evaluation_state === 'complete' && current && pdf) return []
    return [{ purpose: 'publication', state: 'pending', color: 'orange-darken-2', icon: 'mdi-file-import-outline',
        label: `Bewertung abgeschlossen; Ergebnis noch nicht übernommen${simulated ? ' · Simulation' : ''}.` }]
}

export function workFullyComplete(work, students, now = Date.now()) {
    if (!workAutomationEnabled(work)) return false
    const studentIds = workParticipantIds(work, students)
    return studentIds.length > 0 && studentIds.every(studentId => {
        const result = workDispatchRecord(work, studentId, 'results')
        if (workEvaluationStatus(work, studentId, now).state !== 'complete' || result?.mode !== 'live') return false
        const completedAt = Date.parse(personalWorkAssessment(work, studentId)?.evaluation_completed_at)
        return !Number.isFinite(completedAt) || Date.parse(result.sent_at) >= completedAt
    })
}

export function workMarkedComplete(work, students, now = Date.now()) {
    const override = work?.status?.completion_override
    if (typeof override === 'boolean') return override
    if (workAutomationEnabled(work)) return workFullyComplete(work, students, now)

    const ids = work?.is_group_work ? workParticipantIds(work, students) : activeStudentIds(students)
    const hasValue = value => value !== null && value !== undefined && String(value).trim() !== ''
    const individualValue = (values, id, key) => Array.isArray(values)
        ? values.find(item => String(item.student_id) === id)?.[key]
        : values?.[id]?.[key] ?? values?.[id]
    return ids.length > 0 && ids.every(id => (work?.groups || []).some(group =>
        (group.student_ids || []).some(studentId => String(studentId) === id)
        && (hasValue(individualValue(group.points, id, 'points'))
            || hasValue(individualValue(group.grades, id, 'grade'))
            || (!group.use_individual_grades && hasValue(group.grade)))))
}
