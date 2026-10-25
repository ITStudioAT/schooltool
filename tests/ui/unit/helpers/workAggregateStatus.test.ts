import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { workAggregateStatuses, workCellDeviationStatuses, workCellPendingStatuses, workFullyComplete, workMarkedComplete, workParticipantIds } from '@/helpers/workCellStatus'
import WorkCellStatus from '@/pages/admin/teaching/overview/components/WorkCellStatus.vue'
import CourseWorks from '@/pages/admin/teaching/overview/components/CourseWorks.vue'
import CourseTable from '@/pages/admin/teaching/overview/components/CourseTable.vue'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseWorkStore } from '@/stores/admin/teaching/CourseWorkStore'
import { useCourseStudentEntryStore } from '@/stores/admin/teaching/CourseStudentEntryStore'

const now = Date.parse('2026-10-14T10:00:00Z')
const students = [12, 13, 14].map(user_id => ({ id: user_id + 900, user_id, first_name: 'Person', last_name: String(user_id) }))

function fixture(absent = []) {
    const logs = ['tasks', 'results'].map((purpose, index) => ({ origin: 'dispatch_import', purpose,
        sha256: String(index).repeat(64), mode: 'live', dispatch_at: '2026-10-08T10:00:00Z' }))
    return { id: 65, teaching_course_id: 18, title: 'Dateimanagement', type: 'A5', is_group_work: true,
        date_for_all_groups: '2026-10-06', finish_until_date: '2026-10-07', finish_until_time: '12:00',
        groups: [{ student_ids: [12, 13, 14], grade: '0' }],
        status: { dispatch_logs: logs, dispatch_attempts: [],
            dispatch_notifications: logs.flatMap(log => [12, 13, 14].map(student_id => ({ student_id,
                origin: 'dispatch_import', purpose: log.purpose, log_sha256: log.sha256, sent_at: '2026-10-08T10:00:00Z' }))),
            assessment_json_imports: [12, 13, 14].map(student_id => ({ student_id, exercise_id: 'exercise-65',
                participant_id: `person-${student_id}`, record_checksum: 'a'.repeat(64), imported_at: '2026-10-08T10:00:00Z',
                record: { participant_id: `person-${student_id}`, record_checksum: 'a'.repeat(64),
                    submission_state: absent.includes(student_id) ? 'not_received' : 'received', evaluation_state: 'complete',
                    evaluation_completed_at: '2026-10-08T09:30:00Z' } })),
            assessment_json_packages: [{ exercise_id: 'exercise-65', imported_at: '2026-10-08T10:00:01Z', final_download: {
                exercise_id: 'exercise-65', download_checksum: 'b'.repeat(64), deadline_at: '2026-10-07T10:00:00Z',
                download_completed_at: '2026-10-08T09:00:00Z', scope: 'Gesicherter lokaler Postfachbestand',
                participants: [12, 13, 14].map(id => ({ participant_id: `person-${id}`, submission_sha256: absent.includes(id) ? [] : ['c'.repeat(64)] })),
            } }], submission_checks: [] } } as any
}

describe('Shared work status and personal deviations', () => {
    it('shows only a personal pending evaluation or result transfer and suppresses completed and general icons', () => {
        const work = fixture()
        work.status.evaluation_pdfs = [{ origin: 'evaluation_import', student_id: 12, sha256: 'e'.repeat(64), file_path: 'private/12.pdf' }]
        work.status.assessment_json_imports[1].record.evaluation_state = 'partial'
        work.status.assessment_json_imports[1].record.evaluation_completed_at = null
        expect(workCellPendingStatuses(work, 12, now)).toEqual([])
        expect(workCellPendingStatuses(work, 13, now).map(item => [item.purpose, item.state])).toEqual([['evaluation', 'partial']])
        expect(workCellPendingStatuses(work, 14, now).map(item => item.purpose)).toEqual(['publication'])
        expect(workCellPendingStatuses({ ...work, status: {} }, 12, now)).toEqual([])
        expect(workCellPendingStatuses(work, 99, now)).toEqual([])
        const wrapper = mount(WorkCellStatus, { props: { work, studentId: 12, now, pendingOnly: true } })
        try { expect(wrapper.find('[role="img"]').exists()).toBe(false) } finally { wrapper.unmount() }
    })

    it('keeps a completed simulator assessment pending until that exact result has been transferred', () => {
        const work = fixture()
        const run = 'test-run'
        work.simulation_collection = { origin: 'mailpit_simulation', simulation: true, run_id: run,
            work_id: work.id, course_id: work.teaching_course_id, student_ids: [12, 13, 14], collected_count: 3, non_submission_count: 0,
            processed_count: 3, complete: true, completed_at: '2026-10-24T15:00:00Z', evaluation: {
                simulation: true, run_id: run, completed_count: 1, partial_count: 1, open_count: 1, rows: [
                    { student_id: 12, state: 'complete', result: '4,5 von 5 Punkten', evaluation_completed_at: '2026-10-24T16:00:00Z', pdf_sha256: 'f'.repeat(64) },
                    { student_id: 13, state: 'partial', result: '2,5 von 5 Punkten (vorläufig)' },
                    { student_id: 14, state: 'open', result: 'Noch offen' },
                ],
            } }
        expect(workCellPendingStatuses(work, 12, now)[0].purpose).toBe('publication')
        const receipt = work.status.assessment_json_imports[0]
        receipt.exercise_id = `simulation:${run}:work:${work.id}`
        receipt.record.evaluation_completed_at = '2026-10-24T16:00:00Z'
        work.status.evaluation_pdfs = [{ origin: 'evaluation_import', student_id: 12, sha256: 'f'.repeat(64), file_path: 'private/test.pdf' }]
        expect(workCellPendingStatuses(work, 12, now)).toEqual([])
        receipt.record.evaluation_completed_at = '2026-10-24T16:00:00.79244Z'
        work.simulation_collection.evaluation.rows[0].evaluation_completed_at = '2026-10-24T16:00:00.792440Z'
        expect(workCellPendingStatuses(work, 12, now)).toEqual([])
        receipt.record.evaluation_completed_at = '2026-10-24T16:00:01.79244Z'
        expect(workCellPendingStatuses(work, 12, now)[0].purpose).toBe('publication')
        receipt.record.evaluation_completed_at = '2026-10-24T16:00:00.79244Z'
        expect(workCellPendingStatuses(work, 13, now)[0].label).toContain('2,5 von 5 Punkten')
        expect(workCellPendingStatuses(work, 14, now)[0].purpose).toBe('evaluation')
        work.simulation_collection.evaluation.rows[0].pdf_sha256 = 'a'.repeat(64)
        expect(workCellPendingStatuses(work, 12, now)[0].purpose).toBe('publication')
    })
    it.each([true, false])('uses an explicit manual completion of %s without altering four evidence states', override => {
        const work = fixture()
        if (override) work.status.dispatch_notifications = []
        const automatic = workFullyComplete(work, students, now)
        const evidence = workAggregateStatuses(work, students, now)
        expect(automatic).toBe(!override)
        expect(workMarkedComplete(work, students, now)).toBe(automatic)
        work.status.completion_override = override
        expect(workMarkedComplete(work, students, now)).toBe(override)
        expect(workFullyComplete(work, students, now)).toBe(automatic)
        expect(workAggregateStatuses(work, students, now)).toEqual(evidence)
    })

    it('counts only unique active registered people assigned to the saved work', () => {
        const work = fixture()
        work.groups.push({ student_ids: [12, 16] })
        const roster = [...students, students[0], { id: 99, user_id: 99 }, { id: 12, user_id: null },
            { id: 916, user_id: 16, canceled_at: '2026-10-01' }]
        expect(workParticipantIds(work, roster)).toEqual(['12', '13', '14'])
        expect(workAggregateStatuses(work, roster, now).map(item => [item.count, item.total])).toEqual([[3, 3], [3, 3], [3, 3], [3, 3]])
    })

    it('shows a completed collection action green while only missing submissions remain red in person cells', () => {
        const work = fixture([13, 14])
        const aggregate = mount(WorkCellStatus, { props: { work, students, now, aggregate: true, nativeTitle: true } })
        const received = mount(WorkCellStatus, { props: { work, students, now, studentId: 12, deviationsOnly: true } })
        const absent = mount(WorkCellStatus, { props: { work, students, now, studentId: 13, deviationsOnly: true } })
        try {
            expect(aggregate.findAll('[aria-label]')).toHaveLength(4)
            const collection = aggregate.get('[data-submission-state]')
            expect(collection.attributes('color')).toBe('success')
            expect(collection.attributes('title')).toContain('Abholung durchgeführt; 1 von 3 Abgaben vorhanden.')
                .toContain('lokaler Abschlussbestand').toContain('keine Aussage über eine vollständige Serversuche')
            expect(received.findAll('[aria-label]')).toHaveLength(0)
            expect(absent.findAll('[aria-label]')).toHaveLength(1)
            expect(absent.get('[data-submission-state="not_received"]').attributes('color')).toBe('error')
            expect(workFullyComplete(work, students, now)).toBe(true)
        } finally { aggregate.unmount(); received.unmount(); absent.unmount() }
    })

    it.each([0, 1, 3])('shows none, partial and all completed evaluations using the %s actual records', count => {
        const work = fixture()
        work.status.assessment_json_imports.forEach((receipt, index) => { receipt.record.evaluation_state = index < count ? 'complete' : 'open' })
        const evaluation = workAggregateStatuses(work, students, now).find(item => item.purpose === 'evaluation')!
        expect(evaluation.label).toContain(`${count} von 3 Personen`)
        expect(evaluation.state).toBe(count === 3 ? 'complete' : count ? 'partial' : 'unknown')
        expect(evaluation.color === 'success').toBe(count === 3)
        expect(workFullyComplete(work, students, now)).toBe(count === 3)
        if (count === 1) expect(workCellDeviationStatuses(work, 14, students, now).some(item => item.purpose === 'evaluation')).toBe(true)
    })

    it('keeps server schedules unconfirmed and exposes a personal missing send beside a completed dispatch action', () => {
        const work = fixture()
        work.status.dispatch_notifications = []
        work.status.dispatch_attempts = work.status.dispatch_logs.flatMap(log => students.map(student => ({
            origin: 'dispatch_import', purpose: log.purpose, student_id: student.user_id, log_sha256: log.sha256,
            mode: 'scheduled', planned_at: '2026-10-08T09:00:00Z', sent_at: null,
        })))
        const mail = workAggregateStatuses(work, students, now).filter(item => ['tasks', 'results'].includes(item.purpose))
        expect(mail.every(item => item.state === 'scheduled' && item.color !== 'success' && item.icon === 'mdi-clock-outline')).toBe(true)
        expect(mail.every(item => item.label.includes('0 von 3') && item.label.includes('Versand nicht bestätigt'))).toBe(true)
        expect(workFullyComplete(work, students, now)).toBe(false)
        work.status.dispatch_notifications = [{ ...work.status.dispatch_attempts[0], sent_at: '2026-10-08T10:00:00Z' }]
        const generalTask = workAggregateStatuses(work, students, now).find(item => item.purpose === 'tasks')!
        expect(generalTask.color).toBe('success')
        expect(generalTask.label).toContain('1 von 3').toContain('2 serverseitig geplant')
        expect(workCellDeviationStatuses(work, 12, students, now).some(item => item.purpose === 'tasks')).toBe(false)
        expect(workCellDeviationStatuses(work, 13, students, now).find(item => item.purpose === 'tasks')?.state).toBe('scheduled')
    })

    it.each(['collection', 'evaluation', 'information', 'unresolved absence', 'scheduled', 'missing original', 'no people'])
        ('does not complete a work with missing evidence: %s', missing => {
            const work = fixture([13, 14])
            let roster = students
            if (missing === 'collection') work.status.assessment_json_packages = []
            if (missing === 'evaluation') work.status.assessment_json_imports[0].record.evaluation_state = 'open'
            if (missing === 'information' || missing === 'scheduled') work.status.dispatch_notifications = work.status.dispatch_notifications.filter(item => item.purpose !== 'results' || item.student_id !== 14)
            if (missing === 'unresolved absence') work.status.assessment_json_imports[1].record.submission_state = 'unresolved'
            if (missing === 'scheduled') work.status.dispatch_attempts = [{ origin: 'dispatch_import', purpose: 'results', student_id: 14,
                log_sha256: work.status.dispatch_logs[1].sha256, mode: 'scheduled', planned_at: '2026-10-08T10:00:00Z' }]
            if (missing === 'missing original') work.status.dispatch_logs = []
            if (missing === 'no people') roster = []
            expect(workFullyComplete(work, roster, now)).toBe(false)
        })

    it('keeps collection evidence independent of completed correction and result dispatch', () => {
        const work = fixture()
        work.status.assessment_json_packages[0].final_download.participants.pop()
        const submission = workAggregateStatuses(work, students, now).find(item => item.purpose === 'submission')!
        expect(submission.label).not.toContain('Abholung durchgeführt')
        expect(workFullyComplete(work, students, now)).toBe(true)
        work.status.assessment_json_imports = []
        expect(workAggregateStatuses(work, students, now).every(item => item.purpose !== 'evaluation' || item.color !== 'success')).toBe(true)
        expect(workFullyComplete(work, students, now)).toBe(false)
    })

    it('completes corrected and notified work without collection or original task dispatch evidence', () => {
        const work = fixture()
        work.status.assessment_json_packages = []
        work.status.dispatch_logs = work.status.dispatch_logs.filter(log => log.purpose === 'results')
        work.status.dispatch_notifications = work.status.dispatch_notifications.filter(item => item.purpose === 'results')
        expect(workFullyComplete(work, students, now)).toBe(true)
    })

    it('requires a new result dispatch after a newer recorded correction', () => {
        const work = fixture()
        work.status.assessment_json_imports[0].record.evaluation_completed_at = '2026-10-08T10:30:00Z'
        expect(workFullyComplete(work, students, now)).toBe(false)
        work.status.dispatch_notifications.find(item => item.purpose === 'results' && item.student_id === 12).sent_at = '2026-10-08T10:31:00Z'
        expect(workFullyComplete(work, students, now)).toBe(true)
    })

    it('keeps automatic status symbols hidden in manual mode', () => {
        const work = { ...fixture(), automation_enabled: false }
        expect(workAggregateStatuses(work, students, now)).toEqual([])
        expect(workCellDeviationStatuses(work, 12, students, now)).toEqual([])
        expect(workFullyComplete(work, students, now)).toBe(false)
    })

    it.each(['points', 'grades'])('completes manual work from all saved individual %s including zero and reopens when cleared', key => {
        const field = key === 'points' ? 'points' : 'grade'
        const work = { ...fixture(), automation_enabled: false, status: {}, is_group_work: false,
            groups: [{ student_ids: [12, 13, 14], use_individual_grades: true, grade: 5,
                [key]: [{ student_id: 12, [field]: 2 }, { student_id: 13, [field]: 3 }, { student_id: 14, [field]: '' }] }] }
        expect(workMarkedComplete(work, students, now)).toBe(false)
        work.groups[0][key][2][field] = 0
        expect(workMarkedComplete(work, students, now)).toBe(true)
        expect(workFullyComplete(work, students, now)).toBe(false)
        work.groups[0][key][2][field] = ''
        expect(workMarkedComplete(work, students, now)).toBe(false)
    })

    it('uses shared group grades and object values only for the assigned active participants', () => {
        const work = { ...fixture(), automation_enabled: false, status: {},
            groups: [{ student_ids: [12, 13], grade: 2 }, { student_ids: [14], use_individual_grades: true, points: { 14: { points: 0 } } }] }
        const roster = [...students, { user_id: 15, canceled_at: null }, { user_id: 16, canceled_at: '2026-10-01' }]
        expect(workMarkedComplete(work, roster, now)).toBe(true)
        work.is_group_work = false
        expect(workMarkedComplete(work, roster, now)).toBe(false)
        work.groups.push({ student_ids: [15], grade: 3 } as never)
        expect(workMarkedComplete(work, roster, now)).toBe(true)
        work.status = { completion_override: false }
        expect(workMarkedComplete(work, roster, now)).toBe(false)
        expect(workMarkedComplete({ ...work, status: {}, groups: [] }, [], now)).toBe(false)
    })
})

describe('Work card status placement', () => {
    function mountCards(component, work) {
        const pinia = createTestingPinia({ createSpy: vi.fn, initialState: {
            AdminAdminStore: { action: '', config: { user: { teaching_active_semester: 1 } } },
            AdminTeachingStore: { settings: {} },
        } })
        useCourseStore(pinia).selected_course = { id: 18, title: 'INF 1', classes: ['1A'], students_info: students,
            course_dates: [{ id: 1, date: '2026-10-06' }] } as never
        useCourseWorkStore(pinia).courseWorks = [work] as never
        const wrapper = mount(component, { props: component === CourseTable ? { view: 'entries' } : {}, global: {
            plugins: [pinia], stubs: { ItsGridBox: { template: '<div><slot name="title"/><slot name="header-actions"/><slot/></div>' },
                WorkEvaluationImport: true, WorkImportActions: true, WorkAiExport: true, WorkEvaluationPdf: true,
                CourseStudentNotes: true, CourseStudentIndicators: true, CurriculumPdfPreview: true, ItsRichTextEditor: true,
                'v-tab': true, 'v-tabs': true, 'v-textarea': true, 'v-date-input': true, 'v-list-subheader': true, 'v-divider': true, 'v-checkbox': true, 'v-switch': true },
        } })
        return { wrapper, pinia }
    }

    it('keeps automatic overview cards compact with the title, date and next pending action', async () => {
        const { wrapper } = mountCards(CourseWorks, fixture([13, 14]))
        try {
            await flushPromises()
            const header = wrapper.get('.work-list-item .work-row')
            expect(header.get('.work-title-second-line').text()).toBe('Dateimanagement')
            expect(header.findAll('[data-work-status], [data-submission-state]')).toHaveLength(0)
            expect(header.text()).toContain('06.10.2026').toContain('Ergebnisse bereitstellen')
            expect(header.get('.work-title-second-line').findAll('[data-work-status], [data-submission-state]')).toHaveLength(0)
        } finally { wrapper.unmount() }
    })

    it.each([['works', CourseWorks], ['table', CourseTable]])('retains both independent deadline rows in existing manual %s cards', async (_, component) => {
        const work = fixture()
        work.automation_enabled = false
        work.communicated_deadline = null
        const { wrapper, pinia } = mountCards(component, work)
        const selector = component === CourseTable ? '.course-table-work-summary' : '.work-card-header'
        const refresh = async () => {
            useCourseWorkStore(pinia).courseWorks = [{ ...work }] as never
            await flushPromises()
            return wrapper.get(selector)
        }
        try {
            await flushPromises()
            let header = await refresh()
            expect(header.findAll('[data-work-deadline]').map(row => row.attributes('data-work-deadline'))).toEqual(['first', 'second'])
            expect(header.findAll('[data-work-deadline] [role="img"]').map(label => [label.attributes('aria-label'), label.attributes('title')]))
                .toEqual([['1. Frist', '1. Frist'], ['2. Frist', '2. Frist']])
            expect(header.get('[data-work-deadline="first"]').text()).toBe('1. —')
            expect(header.get('[data-work-deadline="second"]').text()).toContain('2.').toContain('07.10.').toContain('12:00')
            work.communicated_deadline = { at: '2026-10-06T09:45:00Z', date: '2026-10-06', time: '11:45' }
            work.status.dispatch_logs = []
            work.status.dispatch_notifications = []
            work.status.dispatch_attempts = [{ student_id: 12, mode: 'scheduled', planned_at: '2026-10-06T09:00:00Z' }]
            header = await refresh()
            expect(header.get('[data-work-deadline="first"]').text()).toContain('1.').toContain('06.10.').toContain('11:45')
            expect(header.get('[data-work-deadline="second"]').text()).toContain('07.10.').toContain('12:00')
            expect(header.text()).not.toContain('abgelaufen').not.toContain('Beurteilung abgeschlossen').not.toContain('Fertig bis')
            expect(header.findAll('[data-work-status], [data-submission-state]')).toHaveLength(0)
            work.communicated_deadline = null
            work.finish_until_date = null
            work.finish_until_time = null
            header = await refresh()
            expect(header.findAll('[data-work-deadline]').map(row => row.text())).toEqual(['1. —', '2. —'])
        } finally { wrapper.unmount() }
    })

    it('shows the next action on the table card while personal cells show only unfinished evaluations or transfers', async () => {
        const work = fixture([13, 14])
        const { wrapper, pinia } = mountCards(CourseTable, work)
        try {
            await flushPromises()
            useCourseWorkStore(pinia).courseWorks = [work] as never
            useCourseStudentEntryStore(pinia).courseEntries = students.map(student => ({ id: student.user_id,
                user_id: student.user_id, date: '2026-10-06', type: 'A5', source: 'course_work', teaching_course_work_id: 65, grade: '0' })) as never
            await flushPromises()
            const header = wrapper.get('.course-table-work-summary')
            expect(header.findAll('[data-work-status], [data-submission-state]')).toHaveLength(0)
            expect(header.get('.course-table-work-summary-next-action').text()).toContain('Ergebnisse bereitstellen')
            expect(header.get('.course-table-work-summary-meta').findAll('[data-work-status], [data-submission-state]')).toHaveLength(0)
            expect(header.find('[aria-label="Automatische Beurteilung"]').exists()).toBe(true)
            const cells = wrapper.findAll('.course-table-entry-cell-performance')
            expect(cells).toHaveLength(3)
            expect(cells.flatMap(cell => cell.findAll('[data-submission-state]'))).toHaveLength(0)
            expect(cells.flatMap(cell => cell.findAll('[data-work-status="publication"]'))).toHaveLength(3)
            expect(cells.every(cell => cell.text().includes('0'))).toBe(true)
            work.status.assessment_json_imports.forEach(receipt => { receipt.record.submission_state = 'received' })
            work.status.assessment_json_packages = []
            useCourseWorkStore(pinia).courseWorks = [{ ...work }] as never
            await flushPromises()
            expect(wrapper.get('.course-table-work-summary-next-action').text()).toContain('Ergebnisse bereitstellen')
            work.status.dispatch_notifications = work.status.dispatch_notifications.filter(item => item.purpose !== 'results' || item.student_id !== 14)
            useCourseWorkStore(pinia).courseWorks = [{ ...work }] as never
            await flushPromises()
            expect(wrapper.get('.course-table-work-summary-next-action').text()).toContain('Ergebnisse bereitstellen')
        } finally { wrapper.unmount() }
    })

    it.each([0, 1, 3])('shows %s completed people in the first header row independently of the manual brain', async completed => {
        const work = fixture()
        work.automation_enabled = false
        work.groups[0].grade = null
        work.groups[0].use_individual_grades = true
        work.groups[0].grades = students.slice(0, completed).map(student => ({ student_id: student.user_id, grade: '0' }))
        work.status.completion_override = true
        const { wrapper } = mountCards(CourseTable, work)
        try {
            await flushPromises()
            const card = wrapper.get('.course-table-work-summary')
            const count = card.get('.course-table-work-summary-count')
            expect(count.get('span').text()).toBe(completed === 3 ? '3' : `${completed}/3`)
            expect(count.classes()).toContain(completed === 3 ? 'text-success' : 'text-error')
            expect(count.attributes('aria-label')).toBe(`${completed} von 3 Leistungen erledigt; ${3 - completed} offen.`)
            expect(card.find('.course-table-work-summary-progress').exists()).toBe(false)
            expect(card.get('.course-table-work-summary-meta').findAll('v-icon').find(icon => icon.text() === 'mdi-brain')?.attributes('color')).toBe('success')
        } finally { wrapper.unmount() }
    })

    it('keeps a zero-person header red rather than claiming completion', async () => {
        const work = { ...fixture(), groups: [], automation_enabled: false }
        const { wrapper } = mountCards(CourseTable, work)
        try {
            await flushPromises()
            const count = wrapper.get('.course-table-work-summary-count')
            expect(count.get('span').text()).toBe('0/0')
            expect(count.classes()).toContain('text-error')
        } finally { wrapper.unmount() }
    })
})
