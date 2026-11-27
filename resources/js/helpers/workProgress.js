import { workSimulationCollection, workSimulationEvaluation, workSimulationNotifications } from '@/helpers/workSimulation'
export { workSimulationCollection, workSimulationEvaluation } from '@/helpers/workSimulation'
import { personalWorkAssessment, workCollectionEvidence, workSubmissionStatus } from '@/helpers/workSubmission'
import { workEvaluationStatus, workCellPendingStatuses, workMarkedComplete } from '@/helpers/workCellStatus'
import { workDispatchNotifications, workProvisionRecords, workTaskDispatchCompleted, workStudentDistribution } from '@/helpers/workDispatch'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { workDeadlineExpired } from '@/helpers/date'

export const workCompletedLabel = 'ABGESCHLOSSEN'

export const workManualCompletionFields = {
    preparation: 'email_preparation_completed', send: 'task_dispatch_completed', collection: 'submission_collection_completed',
    evaluation: 'evaluation_completed', publication: 'publication_completed', notification: 'notification_completed',
}

export function workHasProgress(work) {
    return Boolean(workStudentDistribution(work)) || Object.values(workManualCompletionFields).some(field => work?.status?.[field] === true) || Boolean(workSimulationCollection(work)) || ['assessment_json_imports', 'evaluation_pdfs', 'dispatch_notifications', 'submission_checks', 'provision_events']
        .some(key => (work?.status?.[key] || []).length > 0)
}



export function workLastCollectionRunAt(work) {
    const run = work?.collection_run
    return run?.origin === 'dropbox_collection_run' && Number(run.work_id) === Number(work.id)
        && Number(run.course_id) === Number(work.teaching_course_id) && /^[a-f0-9]{64}$/.test(run.sha256 || '')
        && Number.isFinite(Date.parse(run.completed_at)) && Date.parse(run.completed_at) <= Date.now() ? run.completed_at : null
}

export function workLastCollectionAt(work) {
    const ids = [...new Set((work?.groups || []).flatMap(group => group.student_ids || []).map(String))]
    const receipts = (work?.status?.assessment_json_imports || []).filter(receipt => ids.includes(String(receipt.student_id))
        && personalWorkAssessment(work, receipt.student_id) === receipt.record)
    const exerciseIds = new Set(receipts.map(receipt => receipt.exercise_id))
    const downloads = (work?.status?.assessment_json_packages || []).filter(receipt => exerciseIds.has(receipt.exercise_id)
        && receipt.final_download?.exercise_id === receipt.exercise_id && /^[a-f0-9]{64}$/.test(receipt.final_download.download_checksum || ''))
        .map(receipt => receipt.final_download.download_completed_at)
    const completedRun = workLastCollectionRunAt(work)
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
    const verifiedRun = Boolean(workLastCollectionRunAt(work))
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
        { key: 'publication', title: 'Ergebnisse übernehmen', icon: 'mdi-file-eye-outline', count: published,
            simulation: Boolean(simulation),
            targetCount: gradingRunComplete ? readyIds.length : total,
            complete: publicationComplete, detail: 'Die abgeschlossenen persönlichen Ergebnisse des aktuellen Bewertungslaufs werden übernommen. Dokumentierte offene Einzelfälle blockieren diesen Schritt nicht.' },
        { key: 'notification', title: 'Schüler:innen informieren', icon: 'mdi-bell-outline', count: informed,
            simulation: Boolean(simulatedNotifications || simulation),
            targetCount: gradingRunComplete ? readyIds.length : total,
            complete: notificationComplete, completedAt: notificationCompletedAt, review: earlier > 0,
            detail: simulatedNotifications ? `Simulation: ${informed} persönliche Ergebnisbenachrichtigungen in Mailpit bestätigt. ${simulatedNotifications.teacher_count} separate Lehrernachricht(en). Offene oder nicht übernommene Bewertungen bleiben ausständig.`
                : `Bestätigte persönliche Ergebnisnachrichten mit zugehörigem Versandprotokoll. Tests und Entwürfe zählen nicht als Verständigung.${earlier ? ` Bei ${earlier} Personen liegt die Nachricht vor dem gespeicherten Bewertungsabschluss. Der damalige Versand ist belegt; eine Verständigung über einen später geänderten Bewertungsstand ist damit nicht bestätigt.` : ''}` },
    ].map(stage => {
        const completionField = workManualCompletionFields[stage.key]
        const manuallyCompleted = work?.status?.[completionField] === true
        return { ...stage, completionField, manuallyCompleted, complete: stage.complete || manuallyCompleted }
    }).map((stage, index, all) => {
        const blocked = all.slice(0, index).find(previous => !previous.complete
            && !(stage.key === 'evaluation' && previous.key === 'collection' && verifiedRun))
        return { ...stage, blocked: Boolean(blocked), blockedBy: blocked?.title || '',
        total: stage.targetCount ?? total, state: stage.complete ? 'complete' : stage.count > 0 ? 'partial' : 'open',
        color: stage.review ? 'warning' : stage.complete ? 'success' : stage.count > 0 ? 'warning' : 'error',
        label: `${stage.title}: ${stage.manuallyCompleted ? 'manuell erledigt' : stage.complete ? 'abgeschlossen' : stage.count > 0 ? 'teilweise erledigt' : 'noch offen'}${stage.simulation ? ' · Simulation' : ''}` }
    })
}

export function workProgressNextAction(work, now = Date.now()) {
    if (!workHasProgress(work)) return ''
    const stages = workProgressStages(work, now)
    if (!workStudentDistribution(work) && work?.status?.email_preparation_completed === true && !workTaskDispatchCompleted(work)
        && !workDispatchNotifications(work, 'tasks').length
        && !stages.some(stage => stage.complete || stage.count > 0)) return 'E-Mails versenden'
    return stages.find(stage => !stage.complete)?.title || 'Abgeschlossen'
}

export function workForProgressDate(work, date) {
    const groups = work?.groups || []
    const groupDate = group => group.date || work.date_for_all_groups || ''
    if (!date || !groups.some(groupDate)) return work
    const scopedGroups = groups.filter(group => groupDate(group) === date)
    if (scopedGroups.length === groups.length) return work

    const otherIds = new Set(groups.filter(group => groupDate(group) !== date).flatMap(group => group.student_ids || []).map(String))
    const status = { ...work.status }
    for (const key of ['portal_submissions', 'portal_submission_reopenings', 'assessment_json_imports', 'evaluation_pdfs', 'dispatch_notifications', 'provision_events']) {
        // Work-level receipts and upload windows cannot distinguish two assignments to the same person.
        status[key] = (status[key] || []).filter(row => !otherIds.has(String(row.student_id)))
    }
    if (status.student_distribution) {
        status.student_distribution = { ...status.student_distribution,
            student_ids: (status.student_distribution.student_ids || []).filter(id => !otherIds.has(String(id))) }
    }
    return { ...work, groups: scopedGroups, status, collection_run: work.collection_run?.date === date ? work.collection_run : null }
}

export function workHasPortalFiles(work, date = '') {
    const groups = work.groups || []
    const ids = new Set(groups.filter(group => !date || (group.date || work.date_for_all_groups) === date)
        .flatMap(group => group.student_ids || []).map(String))
    return (work.status?.portal_submissions || []).some(file => file?.student_id && !file.withdrawn_at
        && (!groups.length || ids.has(String(file.student_id))))
}

function hasSavedGrade(group, id) {
    const valueFor = (values, key) => Array.isArray(values) ? values.find(row => String(row.student_id) === id)?.[key]
        : values?.[id]?.[key] ?? values?.[id]
    return [valueFor(group.points, 'points'), valueFor(group.grades, 'grade'), !group.use_individual_grades ? group.grade : null]
        .some(value => value != null && !['', 'NA', 'VL', 'F'].includes(String(value).trim()))
}

export const portalCopyLabel = 'Portalabgaben nach Dropbox kopieren'
export const portalLinkLabel = 'Dropbox-Prüfungsordner verknüpfen'
export const portalCheckLabel = 'Dropbox-Prüfungsordner prüfen'

export function workWorkflowProgress(work, date = '', now = Date.now(), dispatch = {}, students = [], exercise = null) {
    const scoped = workForProgressDate(work, date)
    const groups = scoped.groups || []
    const ids = [...new Set(groups.flatMap(group => group.student_ids || []).map(String))]
    const stages = workProgressStages(scoped, now)
    const [collection, evaluation, publication, notification] = stages
    const status = scoped.status || {}
    const release = status.student_distribution || {}
    const channel = status.submission_method?.channel
    const portalFiles = workHasPortalFiles(work, date)
    const portal = channel === 'portal' || (!channel && (work.status?.portal_submissions || []).length > 0)
    const scopedDate = date || work.date_for_all_groups || null
    const manual = !workAutomationEnabled(work)
    const assessed = manual ? ids.filter(id => groups.some(group =>
        (group.student_ids || []).some(studentId => String(studentId) === id) && hasSavedGrade(group, id))).length : evaluation.count
    const assessmentComplete = evaluation.complete || (manual && ids.length > 0 && assessed === ids.length)
    const verifiedCollection = Boolean(workLastCollectionRunAt(scoped) && scoped.collection_run?.checkpoint_state === 'complete')
    const covered = new Set([
        ...(release.published_at && (!release.withdrawn_at || release.ended_at) ? release.student_ids || [] : []),
        ...workDispatchNotifications(scoped, 'tasks').map(row => row.student_id),
        ...workProvisionRecords(scoped, null, true).map(row => row.student_id),
    ].map(String))
    const workRelease = work.status?.student_distribution
    const releaseRecipients = new Set([...(workRelease?.student_ids || []), ...(workRelease?.ended_email_student_ids || [])].map(String))
    const releaseInScope = !releaseRecipients.size || !work.groups?.length || ids.some(id => releaseRecipients.has(id))
    const mailWithdrawal = Math.max(Date.parse(release.deleted_at) || 0, Date.parse(release.withdrawn_at) || 0)
    let portalAccessByMail = false
    for (const [slot, batch] of Object.entries(status.assignment_mail?.batches || {})) {
        if ((slot !== 'all' && scopedDate && slot !== scopedDate) || batch.transport !== 'postmark' || batch.withdrawn_at) continue
        for (const recipient of batch.recipients || []) {
            const acceptedAt = Date.parse(recipient.accepted_at)
            if (['accepted', 'already_sent'].includes(recipient.state) && Number.isFinite(acceptedAt)) {
                covered.add(String(recipient.student_id))
            }
            if (recipient.state === 'accepted' && acceptedAt > mailWithdrawal && ids.includes(String(recipient.student_id))) {
                portalAccessByMail = true
            }
        }
    }
    const portalReleaseEnded = Boolean(release.ended_at && releaseInScope)
    const portalAccess = (release.published_at && !release.withdrawn_at && releaseInScope) || portalAccessByMail
    const portalDeadlineExpired = channel === 'portal' && (portalReleaseEnded || portalAccess)
        && workDeadlineExpired(work, now)
    const portalCutoff = channel === 'portal'
        ? (portalDeadlineExpired ? 'deadline' : portalReleaseEnded ? 'ended' : null) : null
    const reopenedCount = portalCutoff ? (status.portal_submission_reopenings || []).filter(record => {
        const studentId = String(record.student_id)
        if (!ids.includes(studentId) || (record.release_ended_at ?? null) !== (release.ended_at ?? null)
            || !(Date.parse(record.ends_at) >= now)) return false
        const dates = new Set(work.groups.filter(group => (group.student_ids || []).some(id => String(id) === studentId))
            .map(group => group.date || work.date_for_all_groups || scopedDate))
        return dates.size === 1 && dates.has(record.date)
    }).length : 0
    const portalSubmissionClosed = Boolean(portalCutoff) && !reopenedCount
    const submissionComplete = !reopenedCount && (collection.complete || verifiedCollection || assessmentComplete)
    const legacyDelivery = scoped === work && ['sent', 'partial'].includes(dispatch?.status) && dispatch.transport !== 'mailpit'
        && Number(dispatch.confirmed_count) > 0
    const provisionComplete = (channel === 'portal' && portalReleaseEnded)
        || submissionComplete || assessmentComplete || workTaskDispatchCompleted(scoped)
        || (legacyDelivery && dispatch.status === 'sent')
        || (ids.length > 0 && ids.every(id => covered.has(id)))
    const provisionStarted = provisionComplete || legacyDelivery || ids.some(id => covered.has(id))
        || (scoped === work && Boolean(release.ended_at) && !release.withdrawn_at)
    const collectionAvailable = Boolean(release.ended_at) && !collection.simulation
        && (portal ? portalFiles : channel === 'email' || workAutomationEnabled(work))
    const copyDates = [...new Set(groups.map(group => group.date || work.date_for_all_groups))]
    const copyDate = date || (copyDates.length === 1 ? copyDates[0] : !copyDates.length && work.date_for_all_groups)
    const copy = copyDate ? work.portal_copy_progress?.[copyDate] : null
    const checkedTarget = exercise?.status === 'found' && !exercise.deletion_pending && !exercise.deletionPending
        && exercise.preparation?.status === 'ready' && exercise.preparation.portal_candidates_id !== null ? exercise.folder?.id : null
    const useCheck = ['found', 'missing'].includes(exercise?.status)
        && (!copy || (Date.parse(exercise.checked_at || exercise.checkedAt) || 0) >= (Date.parse(copy.observed_at) || 0))
    const target = useCheck ? checkedTarget : (copy?.candidates_id !== null && copy?.folder_id) || checkedTarget
    const sameCandidates = !useCheck || exercise.preparation?.portal_candidates_id === undefined
        || copy?.candidates_id === exercise.preparation.portal_candidates_id
    const portalCopyHasBinding = Boolean(target || copy?.linked)
    // For automatic assessment, the copy remains the next step even if its destination still needs a binding.
    const portalCopyNeeded = portalCopyHasBinding || (portalReleaseEnded && !manual)
    const portalCopyPending = channel === 'portal' && portalSubmissionClosed && portalFiles && !collection.simulation
        && !assessmentComplete && !exercise?.deletionPending && !exercise?.deletion_pending
        && Boolean(copyDate && portalCopyNeeded) && !(target && copy?.complete && copy.folder_id === target && sameCandidates)
    let next = null
    if (portalCopyPending) {
        next = target || !portalReleaseEnded ? { phase: 'submission', label: portalCopyLabel, action: 'copy-portal' }
            : portalCopyHasBinding ? { phase: 'submission', label: portalCheckLabel, compactLabel: 'Ordner prüfen', action: 'check-portal-target' }
                : { phase: 'submission', label: portalLinkLabel, compactLabel: 'Ordner verknüpfen', action: 'link-portal-target' }
    } else if (submissionComplete || portalSubmissionClosed) {
        next = { phase: 'assessment', label: 'Bewerten' }
        if (assessmentComplete) {
            const informedIds = new Set((status.grade_visibility?.student_notice?.student_ids || []).map(String))
            if (!manual && !publication.complete) next = { phase: 'assessment', label: publication.title, action: 'publish' }
            else if (status.grade_visibility?.visible === false) next = { phase: 'assessment', label: 'Ergebnisse freigeben', action: 'visibility' }
            else if (notification.complete || (ids.length > 0 && ids.every(id => informedIds.has(id)))) next = { phase: null, label: workCompletedLabel }
            else next = { phase: 'assessment', label: notification.title, action: 'notice' }
        }
    } else if (!reopenedCount && !release.ended_at && (release.withdrawn_at || (release.prepared_at && !release.published_at))) {
        next = { phase: 'provision', label: 'Bereitstellen', action: 'provision' }
    } else if (provisionStarted || portalFiles || collection.count || collectionAvailable || reopenedCount) {
        next = collectionAvailable && !reopenedCount ? { phase: 'submission', label: collection.title, action: 'collect' }
            : { phase: 'submission', label: 'Abgeben', action: !channel ? 'submission' : null }
    } else if (status.email_preparation_completed === true) {
        next = { phase: 'provision', label: 'E-Mails versenden', action: 'provision' }
    }
    if (reopenedCount) {
        stages[0] = { ...collection, complete: false, color: 'warning', state: 'partial', label: 'Abgabestatus: Upload wieder geöffnet' }
    } else if (submissionComplete && !collection.complete) {
        stages[0] = { ...collection, complete: true, color: 'success', state: 'complete', label: 'Abgabestatus: abgeschlossen' }
    }
    if (assessmentComplete && !evaluation.complete) {
        stages[1] = { ...evaluation, complete: true, count: assessed, color: 'success', state: 'complete', label: 'Bewertung: abgeschlossen' }
    }
    if (submissionComplete) {
        for (let index = 1; index < stages.length; index++) {
            const previous = stages.slice(0, index).find(stage => !stage.complete)
            stages[index] = { ...stages[index], blocked: Boolean(previous), blockedBy: previous?.title || '' }
        }
    }
    const override = status.completion_override
    const completed = typeof override === 'boolean' ? override : !reopenedCount && (next?.label === workCompletedLabel
        || (manual && workMarkedComplete(work, students, now)))
    if (completed) {
        next = { phase: null, label: workCompletedLabel }
    } else if (next?.label === workCompletedLabel) {
        next = { phase: null, label: 'Offen' }
    }
    return { stages, scoped, assessed, total: ids.length, provisionComplete, reopenedCount, portalCutoff, portalSubmissionClosed,
        portalCopyPending: portalCopyPending && !completed, portalCopyTargetConfirmed: Boolean(target), portalCopyHasBinding, portalCopyDate: copyDate,
        preparationProven: provisionStarted || Boolean(collection.count) || assessmentComplete,
        submissionComplete, assessmentComplete, completed, next }
}
