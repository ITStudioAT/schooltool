import { workSimulationCollection, workSimulationEvaluation, workSimulationNotifications } from '@/helpers/workSimulation'
export { workSimulationCollection, workSimulationEvaluation } from '@/helpers/workSimulation'
import { personalWorkAssessment, workCollectionEvidence, workSubmissionStatus } from '@/helpers/workSubmission'
import { workEvaluationStatus, workCellPendingStatuses } from '@/helpers/workCellStatus'
import { workDispatchNotifications, workTaskDispatchCompleted } from '@/helpers/workDispatch'

export const workManualCompletionFields = {
    preparation: 'email_preparation_completed', send: 'task_dispatch_completed', collection: 'submission_collection_completed',
    evaluation: 'evaluation_completed', publication: 'publication_completed', notification: 'notification_completed',
}

export function workHasProgress(work) {
    return Object.values(workManualCompletionFields).some(field => work?.status?.[field] === true) || Boolean(workSimulationCollection(work)) || ['assessment_json_imports', 'evaluation_pdfs', 'dispatch_notifications', 'submission_checks', 'provision_events']
        .some(key => (work?.status?.[key] || []).length > 0)
}



export function workLastCollectionAt(work) {
    const ids = [...new Set((work?.groups || []).flatMap(group => group.student_ids || []).map(String))]
    const receipts = (work?.status?.assessment_json_imports || []).filter(receipt => ids.includes(String(receipt.student_id))
        && personalWorkAssessment(work, receipt.student_id) === receipt.record)
    const exerciseIds = new Set(receipts.map(receipt => receipt.exercise_id))
    const downloads = (work?.status?.assessment_json_packages || []).filter(receipt => exerciseIds.has(receipt.exercise_id)
        && receipt.final_download?.exercise_id === receipt.exercise_id && /^[a-f0-9]{64}$/.test(receipt.final_download.download_checksum || ''))
        .map(receipt => receipt.final_download.download_completed_at)
    const run = work?.collection_run
    const completedRun = run?.origin === 'dropbox_collection_run' && Number(run.work_id) === Number(work.id)
        && Number(run.course_id) === Number(work.teaching_course_id) && /^[a-f0-9]{64}$/.test(run.sha256 || '')
        ? run.completed_at : null
    return [...downloads, ...receipts.map(receipt => receipt.record.email_collected_at), completedRun]
        .filter(value => typeof value === 'string' && Number.isFinite(Date.parse(value)))
        .sort((first, second) => Date.parse(second) - Date.parse(first))[0] || null
}



export function workProgressStages(work, now = Date.now()) {
    const ids = [...new Set((work?.groups || []).flatMap(group => group.student_ids || []).map(String))]
    const total = ids.length
    const collection = workCollectionEvidence(work, ids)
    const simulation = workSimulationCollection(work)
    const simulatedEvaluation = workSimulationEvaluation(work)
    const simulatedNotifications = workSimulationNotifications(work)
    const received = ids.filter(id => workSubmissionStatus(work, id, now).state === 'received').length
    const missing = ids.filter(id => workSubmissionStatus(work, id, now).state === 'not_received').length
    const evaluated = ids.filter(id => workEvaluationStatus(work, id, now).state === 'complete').length
    const run = work.collection_run
    const verifiedRun = run?.origin === 'dropbox_collection_run' && Number(run.work_id) === Number(work.id)
        && Number(run.course_id) === Number(work.teaching_course_id) && /^[a-f0-9]{64}$/.test(run.sha256 || '')
    const collectionTime = Date.parse(simulation?.last_collected_at || (verifiedRun
        ? run.last_changed_at || (run.changes_detected === true ? run.completed_at : null) : null))
    const latestPackage = (work.status?.assessment_json_packages || []).findLast(item => /^[a-f0-9]{64}$/.test(item.package_checksum || '')
        && ids.every(id => (work.status?.assessment_json_imports || []).some(receipt => String(receipt.student_id) === id
            && receipt.exercise_id === item.exercise_id && personalWorkAssessment(work, id) === receipt.record)))
    const packageTime = Date.parse(latestPackage?.imported_at)
    const legacyCompletion = Math.max(...ids.map(id => Date.parse(personalWorkAssessment(work, id)?.evaluation_completed_at)).filter(Number.isFinite))
    const gradingTime = simulatedEvaluation ? Date.parse(simulatedEvaluation.run_completed_at) : Number.isFinite(packageTime) ? packageTime : legacyCompletion
    const currentCycle = !Number.isFinite(collectionTime) || (Number.isFinite(gradingTime) && gradingTime >= collectionTime)
    const gradingRunComplete = simulatedEvaluation ? simulatedEvaluation.run_complete === true && currentCycle
        : Boolean(latestPackage && currentCycle)
    const evaluationComplete = total > 0 && currentCycle && (simulatedEvaluation && typeof simulatedEvaluation.run_complete === 'boolean'
        ? gradingRunComplete : gradingRunComplete || (simulatedEvaluation ? simulatedEvaluation.completed_count : evaluated) === total)
    const readyIds = simulatedEvaluation ? simulatedEvaluation.rows.filter(row => row.state === 'complete').map(row => String(row.student_id))
        : ids.filter(id => workEvaluationStatus(work, id, now).state === 'complete')
    const publicationIds = gradingRunComplete ? readyIds : ids
    const published = publicationIds.filter(id => !workCellPendingStatuses(work, id, now).length
        && (work.status?.evaluation_pdfs || []).some(pdf => pdf.origin === 'evaluation_import'
        && String(pdf.student_id) === id && /^[a-f0-9]{64}$/.test(pdf.sha256 || '') && pdf.file_path
        && (!personalWorkAssessment(work, id)?.pdf?.sha256 || personalWorkAssessment(work, id).pdf.sha256 === pdf.sha256))).length
    const notifications = workDispatchNotifications(work, 'results')
    const informed = publicationIds.filter(id => (simulatedNotifications?.rows || notifications).some(row => String(row.student_id) === id)).length
    const publicationComplete = evaluationComplete && (gradingRunComplete ? published === readyIds.length : total > 0 && published === total)
    const earlier = ids.filter(id => {
        const latest = notifications.find(row => String(row.student_id) === id)
        return latest && Date.parse(latest.sent_at) < Date.parse(personalWorkAssessment(work, id)?.evaluation_completed_at)
    }).length
    const notificationComplete = publicationComplete && (gradingRunComplete ? informed === readyIds.length : total > 0 && informed === total)
        && earlier === 0 && (!simulatedNotifications || (simulatedNotifications.teacher_count > 0
            && (simulatedNotifications.completed_at === undefined || Number.isFinite(Date.parse(simulatedNotifications.completed_at)))))
    const notificationCompletedAt = notificationComplete ? simulatedNotifications?.completed_at
        || notifications.filter(row => publicationIds.includes(String(row.student_id))).map(row => row.sent_at)
            .sort((a, b) => Date.parse(b) - Date.parse(a))[0] || null : null
    return [
        { key: 'collection', title: 'Abgaben einsammeln', icon: 'mdi-inbox-arrow-down-outline', count: simulation ? simulation.processed_count : received + missing,
            simulation: Boolean(simulation), lastCollectedAt: simulation?.last_collected_at || null,
            complete: simulation ? simulation.complete : total > 0 && (received + missing === total || Boolean(collection)),
            detail: simulation ? `Simulation aus Mailpit: ${simulation.collected_count} gesicherte Rückgaben, ${simulation.non_submission_count} vorgesehene Nichtabgaben.`
                : collection ? 'Abholung durchgeführt; fehlende Abgaben sind im bestätigten Bestand berücksichtigt.'
                : received + missing === total && total > 0 ? `Abgabenbestand belegt: ${received} eingegangene Abgaben, ${missing} bestätigte ${missing === 1 ? 'Nichtabgabe' : 'Nichtabgaben'}.`
                    : 'Vorhandene Abgaben und bestätigte Nichtabgaben werden getrennt berücksichtigt. Fehlende Zeitangaben in älteren Protokollen werden nicht erfunden.' },
        { key: 'evaluation', title: 'Bewerten', icon: 'mdi-file-check-outline', count: simulatedEvaluation ? simulatedEvaluation.completed_count : evaluated,
            simulation: Boolean(simulatedEvaluation),
            complete: evaluationComplete,
            detail: simulatedEvaluation ? `Simulation: ${simulatedEvaluation.completed_count} abgeschlossen, ${simulatedEvaluation.partial_count} teilweise bewertet, ${simulatedEvaluation.open_count} offen.`
                + (gradingRunComplete ? ' Der Bewertungslauf für den aktuellen Bestand ist abgeschlossen; offene Einzelfälle bleiben dokumentiert.' : ' Der aktuelle Bestand muss noch bewertet werden.')
                : gradingRunComplete ? 'Bewertungslauf für den aktuellen Bestand abgeschlossen. Offene Einzelfälle bleiben dokumentiert.' : 'Abgeschlossene persönliche Bewertungen aus dem gespeicherten Bewertungsbestand.' },
        { key: 'publication', title: 'Ergebnisse bereitstellen', icon: 'mdi-file-eye-outline', count: published,
            simulation: Boolean(simulation),
            targetCount: gradingRunComplete ? readyIds.length : total,
            complete: publicationComplete, detail: 'Die abgeschlossenen persönlichen Ergebnisse des aktuellen Bewertungslaufs werden übernommen. Dokumentierte offene Einzelfälle blockieren diesen Schritt nicht.' },
        { key: 'notification', title: 'Schüler:innen informieren', icon: 'mdi-email-check-outline', count: informed,
            simulation: Boolean(simulatedNotifications || simulation),
            targetCount: gradingRunComplete ? readyIds.length : total,
            complete: notificationComplete, completedAt: notificationCompletedAt, review: earlier > 0,
            detail: simulatedNotifications ? `Simulation: ${informed} persönliche Ergebnisbenachrichtigungen in Mailpit bestätigt. ${simulatedNotifications.teacher_count} separate Lehrernachricht(en). Offene oder nicht übernommene Bewertungen bleiben ausständig.`
                : `Bestätigte persönliche Ergebnisnachrichten mit zugehörigem Versandprotokoll. Tests und Entwürfe zählen nicht als Verständigung.${earlier ? ` Bei ${earlier} Personen liegt die Nachricht vor dem gespeicherten Bewertungsabschluss. Der damalige Versand ist belegt; eine Verständigung über einen später geänderten Bewertungsstand ist damit nicht bestätigt.` : ''}` },
    ].map(stage => {
        const completionField = workManualCompletionFields[stage.key]
        const manuallyCompleted = work?.status?.[completionField] === true
        return { ...stage, completionField, manuallyCompleted, complete: stage.complete || manuallyCompleted }
    }).map((stage, index, all) => ({ ...stage, blocked: all.slice(0, index).some(previous => !previous.complete),
        blockedBy: all.slice(0, index).find(previous => !previous.complete)?.title || '',
        total: stage.targetCount ?? total, state: stage.complete ? 'complete' : stage.count > 0 ? 'partial' : 'open',
        color: stage.review ? 'warning' : stage.complete ? 'success' : stage.count > 0 ? 'warning' : 'error',
        label: `${stage.title}: ${stage.manuallyCompleted ? 'manuell erledigt' : stage.complete ? 'abgeschlossen' : stage.count > 0 ? 'teilweise erledigt' : 'noch offen'}${stage.simulation ? ' · Simulation' : ''}` }))
}

export function workProgressNextAction(work, now = Date.now()) {
    if (!workHasProgress(work)) return ''
    const stages = workProgressStages(work, now)
    if (work?.status?.email_preparation_completed === true && !workTaskDispatchCompleted(work)
        && !workDispatchNotifications(work, 'tasks').length
        && !stages.some(stage => stage.complete || stage.count > 0)) return 'E-Mails versenden'
    return stages.find(stage => !stage.complete)?.title || 'Abgeschlossen'
}
