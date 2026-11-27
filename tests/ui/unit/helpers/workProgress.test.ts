import { expect, it } from 'vitest'
import { workProgressNextAction, workProgressStages, workLastCollectionAt, workLastCollectionRunAt, workSimulationCollection, workSimulationEvaluation, workManualCompletionFields, workWorkflowProgress } from '@/helpers/workProgress'
import { isInitialWork, workNeedsPreparation } from '@/helpers/workOverview'

function historicalWork() {
    return { id: 64, groups: [{ student_ids: [12, 13] }], status: {
        assessment_json_imports: [12, 13].map(id => ({ student_id: id, exercise_id: 'old-exercise', participant_id: `person-${id}`,
            record_checksum: 'a'.repeat(64), record: { participant_id: `person-${id}`, record_checksum: 'a'.repeat(64),
                submission_state: 'received', evaluation_state: 'complete', email_collected_at: null, evaluation_completed_at: '2026-10-07T10:00:00Z' } })),
        evaluation_pdfs: [12, 13].map(id => ({ student_id: id, origin: 'evaluation_import', sha256: 'b'.repeat(64), file_path: `teaching/${id}.pdf` })),
        dispatch_logs: [{ purpose: 'results', origin: 'dispatch_import', sha256: 'c'.repeat(64) }],
        dispatch_notifications: [12, 13].map(id => ({ student_id: id, purpose: 'results', origin: 'dispatch_import',
            sent_at: '2026-10-08T10:00:00Z', log_sha256: 'c'.repeat(64) })),
    } } as any
}

it('advances manual task dispatch completion to collection while leaving all later stages open', () => {
    const work = { id: 64, groups: [{ student_ids: [12] }], status: { task_dispatch_completed: true } }
    expect(workProgressNextAction(work)).toBe('Abgaben einsammeln')
    expect(workProgressStages(work).every(stage => !stage.complete && stage.state === 'open')).toBe(true)
    work.status.task_dispatch_completed = false
    expect(workProgressNextAction(work)).toBe('')
    expect(isInitialWork(work)).toBe(true)
    expect(workNeedsPreparation(work, null)).toBe(true)
})

it('counts confirmed Mailpit result notifications separately and invalidates changed assessments', () => {
    const work = historicalWork()
    work.teaching_course_id = 16
    work.status.assessment_json_imports[0].record.pdf = { sha256: 'b'.repeat(64) }
    work.simulation_notification = { origin: 'mailpit_result_notification', simulation: true, work_id: 64, course_id: 16,
        teacher_count: 1, rows: [{ student_id: 12, sent_at: '2026-10-24T17:00:00Z', record_checksum: 'a'.repeat(64), pdf_sha256: 'b'.repeat(64) }] }
    let stage = workProgressStages(work).find(stage => stage.key === 'notification')!
    expect(stage.count).toBe(1)
    expect(stage.label).toContain('Simulation')
    expect(stage.detail).toContain('1 separate Lehrernachricht')
    expect(stage.complete).toBe(false)
    work.status.assessment_json_imports[0].record_checksum = 'd'.repeat(64)
    stage = workProgressStages(work).find(stage => stage.key === 'notification')!
    expect(stage.count).toBe(0)
    work.simulation_notification.rows = []
    expect(workProgressStages(work).find(stage => stage.key === 'notification')!.count).toBe(0)
})

it('unlocks grading after manual collection without adding evidence or completing later steps', () => {
    const work = { id: 64, groups: [{ student_ids: [12, 13] }], status: { submission_collection_completed: true } }
    const original = JSON.stringify(work)
    expect(workProgressStages(work)[0]).toMatchObject({ complete: true, count: 0, manuallyCompleted: true,
        label: 'Abgaben einsammeln: manuell erledigt' })
    expect(workProgressStages(work)[1]).toMatchObject({ complete: false, blocked: false, count: 0 })
    expect(workProgressStages(work).slice(2).every(stage => stage.blocked && !stage.complete)).toBe(true)
    expect(workProgressNextAction(work)).toBe('Bewerten')
    expect(workLastCollectionAt(work)).toBeNull()
    expect(JSON.stringify(work)).toBe(original)
    work.status.submission_collection_completed = false
    expect(workProgressStages(work)[1].blocked).toBe(true)
    expect(workProgressNextAction(work)).toBe('')
})

it('permits grading preparation after a verified run without claiming complete collection', () => {
    const work = { id: 64, teaching_course_id: 16, groups: [{ student_ids: [12] }], status: {},
        collection_run: { origin: 'dropbox_collection_run', work_id: 64, course_id: 16, sha256: 'e'.repeat(64),
            completed_at: '2026-10-24T13:46:16Z', stored_at: '2026-10-24T13:48:22Z', checkpoint_state: 'open' } } as any
    expect(workLastCollectionRunAt(work)).toBe('2026-10-24T13:46:16Z')
    expect(workProgressStages(work)[0]).toMatchObject({ complete: false, state: 'open' })
    expect(workProgressStages(work)[1]).toMatchObject({ complete: false, blocked: false })
    expect(workProgressStages(work)[2].blocked).toBe(true)
    work.collection_run.work_id = 99
    expect(workLastCollectionRunAt(work)).toBeNull()
    expect(workProgressStages(work)[1].blocked).toBe(true)
    work.collection_run.work_id = 64
    work.collection_run.completed_at = '2099-01-01T00:00:00Z'
    expect(workLastCollectionRunAt(work)).toBeNull()
})

it('retains actual collection evidence when manual collection completion is revoked', () => {
    const work = historicalWork()
    work.status.submission_collection_completed = true
    const before = workProgressStages(work)[0].count
    work.status.submission_collection_completed = false
    expect(workProgressStages(work)[0]).toMatchObject({ complete: true, count: before, manuallyCompleted: false })
    expect(workProgressStages(work)[1].blocked).toBe(false)
})

it('advances all manual steps without creating downstream evidence and reopens the revoked step', () => {
    const work = { id: 64, groups: [{ student_ids: [12] }], status: {} } as any
    const nextActions = ['E-Mails versenden', 'Abgaben einsammeln', 'Bewerten', 'Ergebnisse übernehmen', 'Schüler:innen informieren', 'Abgeschlossen']
    Object.values(workManualCompletionFields).forEach((field, index) => {
        work.status[field] = true
        expect(workProgressNextAction(work)).toBe(nextActions[index])
        expect(workProgressStages(work).every(stage => stage.count === 0)).toBe(true)
        expect(workLastCollectionAt(work)).toBeNull()
        expect(workProgressStages(work).at(-1)?.completedAt).toBeNull()
    })
    work.status.evaluation_completed = false
    expect(workProgressNextAction(work)).toBe('Bewerten')
    expect(workProgressStages(work).slice(2).every(stage => stage.blocked && stage.manuallyCompleted)).toBe(true)
    Object.values(workManualCompletionFields).forEach(field => { work.status[field] = false })
    expect(isInitialWork(work)).toBe(true)
    expect(workNeedsPreparation(work, null)).toBe(true)
    expect(workProgressNextAction(work)).toBe('')
})

function simulatedWork() {
    return { id: 86, teaching_course_id: 16, groups: [{ student_ids: [12, 13] }], status: {},
        simulation_collection: { simulation: true, origin: 'mailpit_simulation', work_id: 86, course_id: 16,
            run_id: 'simulator-run', student_ids: [12, 13], collected_count: 1, non_submission_count: 1, processed_count: 2,
            complete: true, completed_at: '2026-10-24T15:35:00Z', last_collected_at: '2026-10-24T15:35:00Z' } } as any
}

it('shows collection completed from the selected simulator run without claiming real submission or evaluation evidence', () => {
    const work = simulatedWork()
    const original = JSON.stringify(work)
    expect(workProgressStages(work)[0]).toMatchObject({ complete: true, count: 2, state: 'complete', simulation: true,
        label: 'Abgaben einsammeln: abgeschlossen · Simulation' })
    expect(workProgressStages(work).slice(1).every(stage => stage.state === 'open')).toBe(true)
    expect(workProgressNextAction(work)).toBe('Bewerten')
    expect(workLastCollectionAt(work)).toBeNull()
    expect(JSON.stringify(work)).toBe(original)
    work.simulation_collection = { ...work.simulation_collection, collected_count: 0, processed_count: 1, complete: false, completed_at: null }
    expect(workProgressStages(work)[0]).toMatchObject({ complete: false, state: 'partial' })
})

it.each(['work', 'course', 'roster', 'duplicate', 'counts', 'date', 'unmarked'])('rejects mismatched simulator evidence: %s', fault => {
    const work = simulatedWork()
    const run = work.simulation_collection
    if (fault === 'work') run.work_id = 64
    if (fault === 'course') run.course_id = 17
    if (fault === 'roster') run.student_ids = [12, 14]
    if (fault === 'duplicate') run.student_ids = [12, 12, 13]
    if (fault === 'counts') run.processed_count = 3
    if (fault === 'date') run.completed_at = null
    if (fault === 'unmarked') run.simulation = false
    expect(workSimulationCollection(work)).toBeNull()
    expect(workProgressStages(work)[0].state).toBe('open')
})

it('shows only verified simulation evaluation counts and keeps incomplete cases open', () => {
    const work = simulatedWork()
    work.simulation_collection.evaluation = { simulation: true, run_id: 'simulator-run', completed_count: 1, partial_count: 1, open_count: 0,
        rows: [{ student_id: 12, person: 'Person 12', state: 'complete', result: '4,5 von 5 Punkten' },
            { student_id: 13, person: 'Person 13', state: 'partial', result: 'Teilbewertung – PDF fehlt' }] }
    const original = JSON.stringify(work)
    expect(workProgressStages(work)[1]).toMatchObject({ count: 1, state: 'partial', complete: false, simulation: true })
    expect(workProgressNextAction(work)).toBe('Bewerten')
    expect(JSON.stringify(work)).toBe(original)
    work.simulation_collection.evaluation.completed_count = 2
    expect(workSimulationEvaluation(work)).toBeNull()
    work.simulation_collection.evaluation.completed_count = 1
    work.simulation_collection.evaluation.run_id = 'other-run'
    expect(workSimulationEvaluation(work)).toBeNull()
})

it('uses collection and matching download completion times without inventing a time from imports or checks', () => {
    const work = historicalWork()
    work.status.folder_imported_at = '2026-10-23T12:00:00Z'
    work.status.submission_checks = [{ check: { checked_at: '2026-10-23T11:00:00Z' } }]
    expect(workLastCollectionAt(work)).toBeNull()
    work.status.assessment_json_imports[0].record.email_collected_at = '2026-10-07T10:00:00Z'
    expect(workLastCollectionAt(work)).toBe('2026-10-07T10:00:00Z')
    work.status.assessment_json_packages = [{ exercise_id: 'old-exercise', final_download: {
        exercise_id: 'old-exercise', download_checksum: 'b'.repeat(64), download_completed_at: '2026-10-08T10:00:00Z' } }]
    expect(workLastCollectionAt(work)).toBe('2026-10-08T10:00:00Z')
    work.status.assessment_json_packages[0].exercise_id = 'other-exercise'
    expect(workLastCollectionAt(work)).toBe('2026-10-07T10:00:00Z')
})

it('recognizes all completed historical steps without recreating work or inventing collection times', () => {
    const work = historicalWork()
    const original = JSON.stringify(work)
    expect(workProgressStages(work).every(stage => stage.complete)).toBe(true)
    expect(workProgressNextAction(work)).toBe('Abgeschlossen')
    expect(JSON.stringify(work)).toBe(original)
})

it('reopens grading only after a collection with documented new or changed submissions', () => {
    const work = historicalWork()
    work.teaching_course_id = 16
    work.collection_run = { origin: 'dropbox_collection_run', work_id: 64, course_id: 16, sha256: 'e'.repeat(64),
        completed_at: '2026-10-24T13:46:16Z', stored_at: '2026-10-24T13:48:22Z', checkpoint_state: 'open', changes_detected: true }
    const original = JSON.stringify(work)
    expect(workLastCollectionAt(work)).toBe('2026-10-24T13:46:16Z')
    expect(workProgressStages(work)[0].complete).toBe(true)
    expect(workProgressStages(work)[1].complete).toBe(false)
    expect(workProgressNextAction(work)).toBe('Bewerten')
    expect(JSON.stringify(work)).toBe(original)
    work.collection_run.changes_detected = false
    expect(workProgressNextAction(work)).toBe('Abgeschlossen')
    work.collection_run.last_changed_at = '2026-10-24T12:00:00Z'
    expect(workProgressNextAction(work)).toBe('Bewerten')
    work.collection_run.work_id = 86
    expect(workLastCollectionAt(work)).toBeNull()
    work.collection_run.work_id = 64
    work.collection_run.sha256 = ''
    expect(workLastCollectionAt(work)).toBeNull()
})

it('advances a verified grading pass and preserves it on unchanged collection but blocks later steps after a changed return', () => {
    const work = simulatedWork()
    work.simulation_collection.last_check_at = '2026-10-24T15:35:00Z'
    work.simulation_collection.evaluation = { simulation: true, run_id: 'simulator-run', run_complete: true,
        run_completed_at: '2026-10-24T16:00:00Z', completed_count: 1, partial_count: 1, open_count: 0, rows: [
            { student_id: 12, person: 'Person 12', state: 'complete', result: '4,5 von 5 Punkten', pdf_sha256: 'b'.repeat(64), evaluation_completed_at: '2026-10-24T16:00:00Z' },
            { student_id: 13, person: 'Person 13', state: 'partial', result: 'Teilbewertung' },
        ] }
    expect(workProgressStages(work)[1]).toMatchObject({ complete: true, count: 1, total: 2, color: 'success' })
    expect(workProgressNextAction(work)).toBe('Ergebnisse übernehmen')
    expect(workProgressStages(work).map(stage => stage.blocked)).toEqual([false, false, false, true])
    const receipt = historicalWork().status.assessment_json_imports[0]
    receipt.exercise_id = 'simulation:simulator-run:work:86'
    receipt.record.evaluation_completed_at = '2026-10-24T16:00:00Z'
    receipt.record.pdf = { sha256: 'b'.repeat(64) }
    work.status = { assessment_json_imports: [receipt], evaluation_pdfs: [historicalWork().status.evaluation_pdfs[0]] }
    expect(workProgressStages(work)[2]).toMatchObject({ count: 1, total: 1, complete: true })
    expect(workProgressNextAction(work)).toBe('Schüler:innen informieren')
    work.simulation_notification = { origin: 'mailpit_result_notification', simulation: true, work_id: 86, course_id: 16, teacher_count: 1,
        rows: [{ student_id: 12, record_checksum: receipt.record_checksum, pdf_sha256: 'b'.repeat(64), sent_at: '2026-10-24T17:00:00Z' }] }
    expect(workProgressNextAction(work)).toBe('Abgeschlossen')
    work.simulation_collection.last_check_at = '2026-10-24T18:00:00Z'
    expect(workProgressNextAction(work)).toBe('Abgeschlossen')
    expect(workProgressStages(work).map(stage => stage.blocked)).toEqual([false, false, false, false])
    work.simulation_collection.last_collected_at = '2026-10-24T18:00:00Z'
    work.simulation_collection.evaluation.run_complete = false
    expect(workProgressNextAction(work)).toBe('Bewerten')
    expect(workProgressStages(work).map(stage => stage.blocked)).toEqual([false, false, true, true])
    expect(workProgressStages(work).slice(1).every(stage => !stage.complete)).toBe(true)
    expect(receipt.record.evaluation_completed_at).toBe('2026-10-24T16:00:00Z')
})

it('also advances a complete imported grading run with open individuals outside simulation', () => {
    const work = historicalWork()
    work.status.assessment_json_imports[1].record.evaluation_state = 'open'
    work.status.assessment_json_imports[1].record.evaluation_completed_at = null
    work.status.assessment_json_packages = [{ exercise_id: 'old-exercise', package_checksum: 'd'.repeat(64), imported_at: '2026-10-08T12:00:00Z' }]
    expect(workProgressStages(work)[1]).toMatchObject({ complete: true, count: 1, total: 2 })
    expect(workProgressStages(work)[2]).toMatchObject({ complete: true, count: 1, total: 1 })
    expect(workProgressNextAction(work)).toBe('Abgeschlossen')
    work.collection_run = { origin: 'dropbox_collection_run', work_id: work.id, course_id: 16, sha256: 'e'.repeat(64), completed_at: '2026-10-09T12:00:00Z', changes_detected: true }
    work.teaching_course_id = 16
    expect(workProgressNextAction(work)).toBe('Bewerten')
})

it('keeps the four stages independent and preserves partial counts and duplicate protection', () => {
    const work = historicalWork()
    work.status.assessment_json_imports[1].record.evaluation_state = 'partial'
    work.status.evaluation_pdfs = [work.status.evaluation_pdfs[0], work.status.evaluation_pdfs[0]]
    work.status.dispatch_notifications = [work.status.dispatch_notifications[0], work.status.dispatch_notifications[0]]
    const stages = workProgressStages(work)
    expect(stages.map(stage => stage.count)).toEqual([2, 1, 1, 1])
    expect(stages.map(stage => stage.state)).toEqual(['complete', 'partial', 'partial', 'partial'])
    expect(workProgressNextAction(work)).toBe('Bewerten')
})

it('excludes teacher messages and wrong purposes and flags notifications before a newer correction', () => {
    const work = historicalWork()
    work.status.dispatch_notifications[0].purpose = 'tasks'
    work.status.assessment_json_imports[1].record.evaluation_completed_at = '2026-10-09T10:00:00Z'
    work.status.dispatch_notifications.push({ ...work.status.dispatch_notifications[1], student_id: 999 })
    expect(workProgressStages(work).find(stage => stage.key === 'notification')).toMatchObject({ count: 1, complete: false, review: true, color: 'warning' })
})

it('shows manual completion without counting an earlier notification as proof for the latest assessment', () => {
    const work = historicalWork()
    work.status.assessment_json_imports[1].record.evaluation_completed_at = '2026-10-09T10:00:00Z'
    const progress = workWorkflowProgress(work)
    expect(progress.completed).toBe(false)
    expect(progress.next).toMatchObject({ label: 'Schüler:innen informieren', action: 'notice' })
    expect(progress.stages.at(-1)).toMatchObject({ count: 2, complete: false, review: true })

    const marked = workWorkflowProgress({ ...work, status: { ...work.status, completion_override: true } })
    expect(marked.completed).toBe(true)
    expect(marked.next).toEqual({ phase: null, label: 'ABGESCHLOSSEN' })
    expect(marked.stages).toEqual(progress.stages)

    work.status.completion_override = false
    expect(workWorkflowProgress(work).next).toEqual(progress.next)
    work.status.dispatch_notifications[1].sent_at = '2026-10-10T10:00:00Z'
    expect(workWorkflowProgress(work)).toMatchObject({ completed: false, next: { phase: null, label: 'Offen' } })
    delete work.status.completion_override
    expect(workWorkflowProgress(work)).toMatchObject({ completed: true, next: { phase: null, label: 'ABGESCHLOSSEN' } })
})

const portalDate = '2026-11-20'
const portalDeadline = '2026-11-26'
const portalNow = Date.parse(`${portalDeadline}T11:30:01Z`)
const portalEnd = `${portalDeadline}T11:00:00Z`
const portalRelease = { published_at: `${portalDate}T08:00:00Z`, student_ids: [12] }
const portalWork = {
    id: 65, teaching_course_id: 2, date_for_all_groups: portalDate,
    groups: [{ student_ids: [12] }], finish_until_date: portalDeadline, finish_until_time: '12:30',
    status: { submission_method: { channel: 'portal' }, student_distribution: portalRelease },
}

it.each([
    { case: 'expired final deadline', distribution: portalRelease, time: portalNow, cutoff: 'deadline' },
    { case: 'release ended early', distribution: { ...portalRelease, ended_at: portalEnd }, time: Date.parse(portalEnd), cutoff: 'ended' },
])('advances to grading without fabricating any evidence after $case', ({ distribution, time, cutoff }) => {
    const work = { ...portalWork, status: { ...portalWork.status, student_distribution: distribution } }
    const progress = workWorkflowProgress(work, portalDate, time)
    expect(progress).toMatchObject({ portalCutoff: cutoff, portalSubmissionClosed: true,
        submissionComplete: false, assessmentComplete: false, completed: false,
        next: { phase: 'assessment', label: 'Bewerten' } })
    expect(progress.stages.map(stage => [stage.count, stage.complete])).toEqual(Array(4).fill([0, false]))
    expect(progress.stages[1].blocked).toBe(true)
    expect(progress.stages[2].blocked).toBe(true)
})

it('completes provision for the affected portal group after the server saves its release end', () => {
    const otherDate = '2026-11-23'
    const work = { ...portalWork, groups: [
        { date: portalDate, student_ids: [12, 14] }, { date: otherDate, student_ids: [13] },
    ] }
    expect(workWorkflowProgress(work, portalDate, portalNow)).toMatchObject({ portalSubmissionClosed: true, provisionComplete: false })
    const ended = { ...work, status: { ...work.status, student_distribution: {
        ...portalRelease, ended_at: new Date(portalNow).toISOString(),
    } } }
    expect(workWorkflowProgress(ended, portalDate, portalNow)).toMatchObject({ portalSubmissionClosed: true,
        provisionComplete: true, next: { label: 'Bewerten' } })
    expect(workWorkflowProgress(ended, otherDate, portalNow)).toMatchObject({ portalSubmissionClosed: false, provisionComplete: false })
})

it('uses only the checked target and relevant dated copy progress for the closed portal recommendation', () => {
    const secondDate = '2026-11-23'
    const closed = { ...portalWork, groups: [{ date: portalDate, student_ids: [12] }, { date: secondDate, student_ids: [13] }],
        status: { ...portalWork.status, automation_enabled: false,
            student_distribution: { ...portalRelease, student_ids: [12, 13], ended_at: new Date(portalNow).toISOString() },
            portal_submissions: [{ id: 'a', student_id: 12 }, { id: 'b', student_id: 13 }],
        }, portal_copy_progress: { [portalDate]: { folder_id: 'id:older', complete: true, observed_at: '2026-11-24T10:00:00Z' } },
    }
    const inspect = (work, date = portalDate, exercise = null) => workWorkflowProgress(work, date, portalNow, {}, [], exercise)
    expect(inspect(closed).next?.label).toBe('Bewerten')
    expect(inspect(closed, secondDate).next?.label).toBe('Bewerten')
    const newer = { status: 'found', folder: { id: 'id:replacement' }, checked_at: '2026-11-24T11:00:00Z', preparation: { status: 'ready' } }
    expect(inspect(closed, portalDate, newer).next?.action).toBe('copy-portal')
    const replacedCandidates = { ...newer, folder: { id: 'id:older' }, preparation: { status: 'ready', portal_candidates_id: 'id:new-candidates' } }
    expect(inspect(closed, portalDate, replacedCandidates).next?.action).toBe('copy-portal')
    const withoutFiles = { ...closed, status: { ...closed.status, portal_submissions: [{ id: 'a', student_id: 12, withdrawn_at: '>0' }] } }
    expect(inspect(withoutFiles, portalDate, newer).next?.label).toBe('Bewerten')
    const graded = { ...closed, groups: [{ ...closed.groups[0], grade: '2' }, closed.groups[1]] }
    expect(inspect(graded, portalDate, newer)).toMatchObject({ assessmentComplete: true, portalCopyPending: false })
})

it('keeps copying next after the deadline while waiting for the release to end and hides it during a personal extension', () => {
    const work = { ...portalWork, portal_copy_progress: { [portalDate]: { linked: true, folder_id: null, complete: false } },
        status: { ...portalWork.status, portal_submissions: [{ id: 'original', student_id: 12 }] },
    }
    expect(workWorkflowProgress(work, portalDate, portalNow)).toMatchObject({
        portalSubmissionClosed: true, portalCopyPending: true, portalCopyTargetConfirmed: false, next: { action: 'copy-portal' },
    })
    const reopened = { ...work, status: { ...work.status, portal_submission_reopenings: [
        { student_id: 12, date: portalDate, ends_at: new Date(portalNow + 3600000).toISOString() },
    ] } }
    expect(workWorkflowProgress(reopened, portalDate, portalNow)).toMatchObject({ reopenedCount: 1, portalCopyPending: false })
})

it.each([
    { case: 'open deadline', channel: 'portal', distribution: portalRelease, time: portalNow - 2000, next: 'Abgeben' },
    { case: 'paused release', channel: 'portal', distribution: { ...portalRelease, withdrawn_at: portalEnd }, time: portalNow, next: 'Bereitstellen' },
    { case: 'unpublished release', channel: 'portal', distribution: { prepared_at: portalEnd }, time: portalNow, next: 'Bereitstellen' },
    { case: 'email submission', channel: 'email', distribution: portalRelease, time: portalNow, next: 'Abgeben' },
])('keeps the existing phase for $case', ({ channel, distribution, time, next }) => {
    const work = { ...portalWork, status: { submission_method: { channel }, student_distribution: distribution } }
    expect(workWorkflowProgress(work, portalDate, time)).toMatchObject({ portalSubmissionClosed: false,
        submissionComplete: false, next: { label: next } })
})

it('keeps the affected date open only for a current personal deadline', () => {
    const otherDate = '2026-11-23'
    const reopening = { student_id: 12, date: portalDate, ends_at: `${portalDeadline}T13:00:00+00:00`, release_ended_at: portalEnd }
    const work = { ...portalWork, groups: [{ date: portalDate, student_ids: [12] }, { date: otherDate, student_ids: [13] }],
        status: { ...portalWork.status, student_distribution: { ...portalRelease, student_ids: [12, 13], ended_at: portalEnd },
            portal_submission_reopenings: [reopening] } }
    expect(workWorkflowProgress(work, portalDate, portalNow)).toMatchObject({ reopenedCount: 1,
        portalSubmissionClosed: false, submissionComplete: false, next: { label: 'Abgeben' } })
    expect(workWorkflowProgress(work, otherDate, portalNow)).toMatchObject({ reopenedCount: 0,
        portalSubmissionClosed: true, next: { label: 'Bewerten' } })
    expect(workWorkflowProgress(work, portalDate, Date.parse(reopening.ends_at) + 1000)).toMatchObject({
        reopenedCount: 0, portalSubmissionClosed: true, next: { label: 'Bewerten' } })
    const assignedTwice = { ...work, groups: [work.groups[0], { date: otherDate, student_ids: [12, 13] }] }
    expect(workWorkflowProgress(assignedTwice, '', portalNow)).toMatchObject({
        reopenedCount: 0, portalSubmissionClosed: true, next: { label: 'Bewerten' } })
})

it('closes the upload only for a date with a portal release or confirmed mail access', () => {
    const otherDate = '2026-11-23'
    const work = { ...portalWork, groups: [{ date: portalDate, student_ids: [12] }, { date: otherDate, student_ids: [13] }] }
    expect(workWorkflowProgress(work, otherDate, portalNow)).toMatchObject({ portalSubmissionClosed: false })
    const batch = { transport: 'postmark', recipients: [{ student_id: 13, state: 'accepted', accepted_at: `${otherDate}T09:00:00Z` }] }
    const emailed = { ...work, status: { ...work.status, assignment_mail: { batches: { [otherDate]: batch } } } }
    expect(workWorkflowProgress(emailed, otherDate, portalNow)).toMatchObject({ portalSubmissionClosed: true, next: { label: 'Bewerten' } })
    const withdrawn = { ...emailed, status: { ...emailed.status, student_distribution: {
        ...portalRelease, withdrawn_at: `${otherDate}T10:00:00Z`,
    } } }
    expect(workWorkflowProgress(withdrawn, otherDate, portalNow)).toMatchObject({ portalSubmissionClosed: false })
})

it('keeps the later steps and explicit completion for closed portal work with recorded grading', () => {
    const graded = { ...portalWork, status: { ...portalWork.status, evaluation_completed: true } }
    expect(workWorkflowProgress(graded, portalDate, portalNow)).toMatchObject({ portalSubmissionClosed: true,
        assessmentComplete: true, next: { label: 'Ergebnisse übernehmen' } })
    const completed = { ...graded, status: { ...graded.status, completion_override: true } }
    expect(workWorkflowProgress(completed, portalDate, portalNow)).toMatchObject({ completed: true,
        next: { label: 'ABGESCHLOSSEN' } })
})

it('does not infer collection or grading from an overall PDF or a sent notification', () => {
    const work = historicalWork()
    work.status.assessment_json_imports = []
    work.status.evaluation_pdfs = [{ origin: 'evaluation_import', student_id: null, sha256: 'b'.repeat(64), file_path: 'overall.pdf' }]
    expect(workProgressStages(work).slice(0, 3).map(stage => stage.complete)).toEqual([false, false, false])
    expect(workProgressNextAction(work)).toBe('Abgaben einsammeln')
})

it('does not use a mismatched personal receipt as proof', () => {
    const work = historicalWork()
    work.status.assessment_json_imports[0].record.participant_id = 'wrong-person'
    expect(workProgressStages(work).slice(0, 2).map(stage => stage.count)).toEqual([1, 1])
})

it('counts a confirmed non-submission as processed without calling it a received submission', () => {
    const work = historicalWork()
    work.finish_until_date = '2026-10-07'
    work.finish_until_time = '12:00'
    const absent = work.status.assessment_json_imports[1]
    absent.imported_at = '2026-10-08T10:00:00Z'
    absent.record.submission_state = 'not_received'
    work.status.assessment_json_packages = [{ exercise_id: absent.exercise_id, imported_at: '2026-10-08T11:00:00Z',
        teacher_absence_decision: { exercise_id: absent.exercise_id, decision_checksum: 'd'.repeat(64),
            deadline_at: '2026-10-07T10:00:00Z', participant_ids: [absent.participant_id], decided_at: '2026-10-08T09:00:00Z' } }]
    const stage = workProgressStages(work, Date.parse('2026-10-20T10:00:00Z'))[0]
    expect(stage).toMatchObject({ count: 2, complete: true })
    expect(stage.detail).toContain('1 eingegangene Abgaben, 1 bestätigte Nichtabgabe')
})
