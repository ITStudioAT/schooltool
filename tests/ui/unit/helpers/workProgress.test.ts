import { expect, it } from 'vitest'
import { workProgressNextAction, workProgressStages, workLastCollectionAt, workSimulationCollection, workSimulationEvaluation } from '@/helpers/workProgress'

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
    expect(workProgressNextAction(work)).toBe('Ergebnisse bereitstellen')
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
