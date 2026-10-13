import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { workSubmissionStatus } from '@/helpers/workSubmission'
import WorkSubmissionStatus from '@/pages/admin/teaching/overview/components/WorkSubmissionStatus.vue'

const now = Date.parse('2026-10-14T10:00:00Z')

function fixture(state = 'received') {
    const record = { participant_id: 'person-12', record_checksum: 'a'.repeat(64), submission_state: state,
        evaluation_state: 'open', total_minor: null, email_collected_at: null, submission_note: '' }
    return { id: 65, teaching_course_id: 18, is_group_work: false,
        finish_until_date: '2026-10-07', finish_until_time: '12:00',
        groups: [{ student_ids: [12, 13], grade: '0' }],
        status: { assessment_json_imports: [{ student_id: 12, exercise_id: 'exercise-65', participant_id: 'person-12',
            record_checksum: record.record_checksum, imported_at: '2026-10-08T10:00:00Z', record }],
        assessment_json_packages: [], submission_checks: [] } } as any
}

function finalDownload() {
    const work = fixture('not_received')
    work.status.assessment_json_packages.push({ exercise_id: 'exercise-65', imported_at: '2026-10-08T10:00:01Z',
        final_download: { exercise_id: 'exercise-65', download_checksum: 'b'.repeat(64),
            deadline_at: '2026-10-07T10:00:00Z', download_completed_at: '2026-10-08T09:00:00Z',
            scope: 'Gesicherter lokaler Postfachbestand', participants: [{ participant_id: 'person-12', submission_sha256: [] }] } })
    return work
}

describe('Personal work submission evidence', () => {
    it.each(['open', 'partial', 'complete'])('shows a received submission green with %s evaluation, independently of points', state => {
        const work = fixture()
        Object.assign(work.status.assessment_json_imports[0].record, { evaluation_state: state, total_minor: state === 'complete' ? 0 : null })
        expect(workSubmissionStatus(work, '12', now)).toMatchObject({ state: 'received', color: 'success' })
    })

    it('labels collection time as collection and preserves an evidenced late-submission note', () => {
        const work = fixture()
        Object.assign(work.status.assessment_json_imports[0].record, {
            email_collected_at: '2026-10-08T09:00:00Z', submission_note: 'Abgabe verspätet eingegangen.' })
        const status = workSubmissionStatus(work, 12, now)
        expect(status.color).toBe('success')
        expect(status.label).toContain('Abgabe eingesammelt: 08.10.2026, 11:00 Uhr').toContain('Abgabe verspätet eingegangen.')
    })

    it('does not infer late submission from a collection after the deadline', () => {
        const work = fixture()
        work.status.assessment_json_imports[0].record.email_collected_at = '2026-10-08T09:00:00Z'
        expect(workSubmissionStatus(work, 12, now).label).not.toContain('verspätet')
    })

    it.each(['unresolved', 'not_received'])('keeps %s neutral without a confirmed negative proof, even for zero points', state => {
        const work = fixture(state)
        work.status.assessment_json_imports[0].record.total_minor = 0
        expect(workSubmissionStatus(work, 12, now)).toMatchObject({ state: 'unknown', color: 'grey-darken-1' })
    })

    it('does not turn task provision, dispatch or result notifications into submission evidence', () => {
        const work = fixture()
        work.status.assessment_json_imports = []
        Object.assign(work.status, { provision_imports: [{ student_id: 12, available_at: '2026-10-01T10:00:00Z' }],
            dispatch_notifications: [{ student_id: 12, purpose: 'tasks' }, { student_id: 12, purpose: 'results' }] })
        expect(workSubmissionStatus(work, 12, now).state).toBe('unknown')
    })

    it('does not share an individual receipt with group peers or an enrollment id', () => {
        const work = fixture()
        work.is_group_work = true
        expect(workSubmissionStatus(work, 12, now).state).toBe('received')
        expect(workSubmissionStatus(work, 13, now).state).toBe('unknown')
        expect(workSubmissionStatus(work, 999, now).state).toBe('unknown')
        work.groups = [{ student_ids: [13] }]
        expect(workSubmissionStatus(work, 12, now).state).toBe('unknown')
    })

    it.each(['missing student', 'wrong participant', 'wrong checksum'])('rejects an incomplete person binding: %s', change => {
        const work = fixture()
        const receipt = work.status.assessment_json_imports[0]
        if (change === 'missing student') receipt.student_id = null
        if (change === 'wrong participant') receipt.participant_id = 'person-13'
        if (change === 'wrong checksum') receipt.record_checksum = 'c'.repeat(64)
        expect(workSubmissionStatus(work, 12, now).state).toBe('unknown')
    })

    it('shows the validated local final-download absence red while an overall server check is still open', () => {
        const work = finalDownload()
        work.submission_check_status = { complete: false }
        const status = workSubmissionStatus(work, 12, now)
        expect(status).toMatchObject({ state: 'not_received', color: 'error' })
        expect(status.label).toContain('lokalen Abschlussdownload').toContain('Gesicherter lokaler Postfachbestand')
            .toContain('Keine Aussage über eine vollständige Serversuche')
    })

    it.each(['changed deadline', 'missing deadline', 'other person', 'other exercise', 'older package', 'has files'])
        ('keeps final-download absence neutral for %s', change => {
            const work = finalDownload()
            const packageReceipt = work.status.assessment_json_packages[0]
            if (change === 'changed deadline') work.finish_until_time = '13:00'
            if (change === 'missing deadline') work.finish_until_time = null
            if (change === 'other person') packageReceipt.final_download.participants[0].participant_id = 'person-13'
            if (change === 'other exercise') packageReceipt.final_download.exercise_id = 'exercise-66'
            if (change === 'older package') packageReceipt.imported_at = '2026-10-08T09:00:00Z'
            if (change === 'has files') packageReceipt.final_download.participants[0].submission_sha256 = ['c'.repeat(64)]
            expect(workSubmissionStatus(work, 12, now).state).toBe('unknown')
        })

    it('uses a server-validated completed check and the exact participant rather than global completeness alone', () => {
        const work = fixture('not_received')
        work.submission_check_status = { complete: true, student_ids: [12], checked_at: '2026-10-08T09:00:00Z',
            deadline_date: '2026-10-07', deadline_time: '12:00' }
        work.status.submission_checks = [{ check: { kind: 'schooltool.submission_checkv2', exercise_id: 'exercise-65',
            state: 'complete', checked_at: '2026-10-08T09:00:00Z', participants: [{ participant_id: 'person-12',
                available_at: '2026-10-01T10:00:00Z', email_result: 'not_found', other_result: 'not_received' }] } }]
        expect(workSubmissionStatus(work, 12, now)).toMatchObject({ state: 'not_received', color: 'error' })
        expect(workSubmissionStatus(work, 12, now).label).toContain('abgeschlossener Abgabeprüfung')
        work.status.submission_checks[0].check.participants[0].email_result = 'unresolved'
        expect(workSubmissionStatus(work, 12, now).state).toBe('unknown')
        work.status.submission_checks[0].check.participants[0].email_result = 'not_found'
        work.submission_check_status.complete = false
        expect(workSubmissionStatus(work, 12, now).state).toBe('unknown')
    })

    it('preserves an explicit teacher decision as its own bounded evidence', () => {
        const work = fixture('not_received')
        work.status.assessment_json_packages = [{ exercise_id: 'exercise-65', imported_at: '2026-10-08T10:00:01Z',
            teacher_absence_decision: { exercise_id: 'exercise-65', decision_checksum: 'b'.repeat(64),
                deadline_at: '2026-10-07T10:00:00Z', decided_at: '2026-10-08T09:00:00Z', participant_ids: ['person-12'] } }]
        expect(workSubmissionStatus(work, 12, now).label).toContain('ausdrücklicher Lehrkraftentscheidung')
    })

    it('refreshes the accessible icon when a newer received record replaces a confirmed absence', async () => {
        const wrapper = mount(WorkSubmissionStatus, { props: { work: finalDownload(), studentId: 12, now } })
        try {
            expect(wrapper.get('[aria-label]').attributes('color')).toBe('error')
            await wrapper.setProps({ work: fixture() })
            const icon = wrapper.get('[aria-label]')
            expect(icon.attributes('color')).toBe('success')
            expect(icon.attributes('title')).toBe(icon.attributes('aria-label'))
            await wrapper.setProps({ studentId: 13 })
            expect(wrapper.get('[aria-label]').attributes('color')).toBe('grey-darken-1')
        } finally { wrapper.unmount() }
    })
})
