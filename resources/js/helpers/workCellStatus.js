import { assessmentTimeText } from '@/helpers/workAssessmentTimes'
import { dispatchNotificationText, workDispatchRecord, provisionNotificationText } from '@/helpers/workDispatch'
import { personalWorkAssessment, personalWorkProvision, workSubmissionStatus } from '@/helpers/workSubmission'

function notificationLabel(record, purpose) {
    if (!record) return `${purpose === 'tasks' ? 'Aufgabenversand' : 'Ergebnisbenachrichtigung'}: kein bestätigter E-Mail-Versand.`
    return dispatchNotificationText(['prepared', 'scheduled'].includes(record.mode) ? record.planned_at : record.sent_at,
        purpose, record.mode)
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
    const tasks = workDispatchRecord(work, studentId, 'tasks')
    const provision = personalWorkProvision(work, studentId)
    const direct = provision && tasks?.mode !== 'live'
    const output = direct
        ? { icon: 'mdi-folder-open-outline', color: 'success', state: 'available',
            label: provision.method === 'download_release'
                ? `Aufgabe zum Download bereitgestellt; tatsächlicher Download nicht bekannt. Verfügbar seit ${assessmentTimeText(provision.available_at)}.`
                : provisionNotificationText(provision) }
        : { icon: 'mdi-file-send-outline', color: tasks?.mode === 'live' ? 'success' : 'orange-darken-2',
            state: tasks?.mode === 'live' ? 'sent' : 'unknown', label: notificationLabel(tasks, 'tasks')
                + (provision ? ` ${provisionNotificationText(provision)}` : '') }
    const results = workDispatchRecord(work, studentId, 'results')
    return [
        { purpose: 'tasks', ...output },
        { purpose: 'submission', icon: 'mdi-inbox-arrow-down', ...workSubmissionStatus(work, studentId, now) },
        { purpose: 'evaluation', icon: 'mdi-file-check-outline', ...workEvaluationStatus(work, studentId, now) },
        { purpose: 'results', icon: results?.mode === 'live' ? 'mdi-email-check-outline' : 'mdi-email-outline',
            color: results?.mode === 'live' ? 'success' : 'orange-darken-2',
            state: results?.mode === 'live' ? 'sent' : 'unknown', label: notificationLabel(results, 'results') },
    ]
}
