import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from 'axios'
import CourseEvaluations from '@/pages/admin/teaching/overview/components/CourseEvaluations.vue'

vi.mock('axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@/actions/App/Http/Controllers/Admin/Teaching/CourseEvaluationController', () => ({
    show: { url: (id: number, options: any) => `/api/admin/teaching/courses/${id}/evaluations?semester=${options.query.semester}` },
}))

function reportFixture() {
    return {
        course_id: 18, schoolyear_id: 4, semester: 3, semester_count: 2, rounding: false, issues: [],
        students: [{
            course_student_id: 100, user_id: 10, import116_id: null,
            year: { result: null, status: 'incomplete', trace: {}, issues: [{ message: 'Semestergewichtung fehlt.', severity: 'error' }] },
            semesters: [{ semester: 1, result: 2.3333333333333335, status: 'complete', issues: [], parts: [{
                id: 7, name: 'Mitarbeit', result: 2.3333333333333335, status: 'complete', config: { weight: 2 },
                trace: { rule_label: 'Gewichteter Mittelwert', values: [2, 3], weights: [2, 1], weighted_sum: 7, weight_sum: 3 }, issues: [],
                types: [{ id: 2, short_name: 'P', name: 'Prüfung', result: 2.3333333333333335, status: 'complete',
                    config: { properties_mode: 'points', maximum_points: 12.5, points_grade_thresholds: { 1: 10, 2: 8, 3: 6, 4: 4 } },
                    trace: { rule_label: 'Punkte gemäß Notengrenzen', sum: 9.25 }, issues: [],
                    entries: [{ id: 1, date: '2026-09-13', source: 'course_work', raw_value: '9.25', numeric_value: 9.25, grade: 2.3333333333333335, status: 'included', issues: [] }],
                }],
            }], unassigned_types: [], excluded_entries: [] }],
        }],
    }
}

function mountReport() {
    return mount(CourseEvaluations, {
        props: { course: { id: 18, schoolyear_id: 4, students_info: [{ user_id: 10, last_name: 'Muster', first_name: 'Anna' }] }, schoolyear: { id: 4 }, activeSemester: 3 },
        global: { stubs: { 'v-btn-toggle': { template: '<div><slot /></div>' }, 'v-btn': { template: '<button><slot /></button>' }, 'v-alert': { template: '<div><slot /></div>' }, 'v-icon': true, 'v-spacer': true } },
    })
}

beforeEach(() => vi.clearAllMocks())

describe('Course evaluations report', () => {
    it('rejects a response for another course rather than rendering its student data', async () => {
        const report = reportFixture()
        report.course_id = 99
        vi.mocked(axios.get).mockResolvedValue({ data: { data: report } })
        const wrapper = mountReport()
        await flushPromises()
        expect(wrapper.text()).toContain('passt nicht zum ausgewählten Unterricht')
        expect(wrapper.find('.evaluation-student').exists()).toBe(false)
        wrapper.unmount()
    })

    it('shows a retryable load error without leaving an old report visible', async () => {
        vi.mocked(axios.get).mockRejectedValue(new Error('Unavailable'))
        const wrapper = mountReport()
        await flushPromises()
        expect(wrapper.text()).toContain('Bitte erneut versuchen.')
        expect(wrapper.find('.evaluation-student').exists()).toBe(false)
        wrapper.unmount()
    })

    it('sorts the report by the matched roster names without mutating backend order', () => {
        const component = CourseEvaluations as any
        const students = [{ course_student_id: 1, user_id: 10 }, { course_student_id: 2, user_id: 20 }]
        const context = {
            ...component.methods, report: { students }, course: { students_info: [
                { user_id: 10, last_name: 'Zeller', first_name: 'Anna' },
                { user_id: 20, last_name: 'Bauer', first_name: 'Ben' },
            ] },
        }
        expect(component.computed.sortedStudents.call(context)).toEqual([students[1], students[0]])
        expect(students[0].user_id).toBe(10)
    })

    it('renders saved rules, raw values, full result precision and missing configuration', async () => {
        vi.mocked(axios.get).mockResolvedValue({ data: { data: reportFixture() } })
        const wrapper = mountReport()
        await flushPromises()
        expect(axios.get).toHaveBeenCalledWith('/api/admin/teaching/courses/18/evaluations?semester=3')
        expect(wrapper.text()).toContain('Muster, Anna')
        expect(wrapper.text()).toContain('2,3333333333333335')
        expect(wrapper.text()).toContain('9,25')
        expect(wrapper.text()).toContain('Gewichteter Mittelwert')
        expect(wrapper.text()).toContain('Semestergewichtung fehlt.')
        expect(wrapper.text()).toContain('Nicht berechenbar')
        expect(wrapper.text()).not.toContain('[object Object]')
        wrapper.unmount()
    })

    it('discards stale responses after changing semester', async () => {
        let resolveOld: any
        vi.mocked(axios.get).mockImplementationOnce(() => new Promise((resolve) => { resolveOld = resolve }))
        const wrapper = mountReport()
        const current = reportFixture()
        current.students[0].semesters[0].parts[0].name = 'Aktuelle Auswertung'
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { data: current } })
        await wrapper.setProps({ activeSemester: 1 })
        await flushPromises()
        const old = reportFixture()
        old.students[0].semesters[0].parts[0].name = 'Veraltete Auswertung'
        resolveOld({ data: { data: old } })
        await flushPromises()
        expect(wrapper.text()).toContain('Aktuelle Auswertung')
        expect(wrapper.text()).not.toContain('Veraltete Auswertung')
        wrapper.unmount()
    })

    it('does not request a report for a mismatched schoolyear', async () => {
        const wrapper = mountReport()
        await wrapper.setProps({ schoolyear: { id: 5 } })
        await flushPromises()
        expect(wrapper.text()).toContain('gehört nicht zum ausgewählten Schuljahr')
        expect(axios.get).toHaveBeenCalledTimes(1)
        wrapper.unmount()
    })

    it('never matches a roster id from a different identity kind', () => {
        const methods = (CourseEvaluations as any).methods
        const context = { course: { students_info: [{ id: 10, import116_id: 10, first_name: 'Andere', last_name: 'Person' }] } }
        expect(methods.studentLabel.call(context, { course_student_id: 10, user_id: 10 })).toBe('Schüler:in nicht zuordenbar')
        expect(methods.studentLabel.call(context, { course_student_id: 20, import116_id: 10 })).toBe('Person, Andere')
    })

    it('omits cached inactive calculation settings from the active rule explanation', () => {
        const methods = (CourseEvaluations as any).methods
        expect(methods.configurationLines.call(methods, {
            properties_mode: 'plus', allows_maximum_plus: true, maximum_plus_grading_mode: 'standard_percentage',
            maximum_plus_grade_thresholds: { 1: 999 }, grading_part_weight: 999,
        }).join(' ')).not.toContain('999')
        expect(methods.configurationLines.call(methods, {
            properties_mode: 'points', points_grade_thresholds: { 1: 999 }, maximum_points: 10,
        }, { allowed_entry_types: 'points', points_assessment_mode: 'overall' }).join(' ')).not.toContain('999')
    })

    it('keeps zero and full backend precision without numeric conversion or rounding', () => {
        const display = (CourseEvaluations as any).methods.displayValue
        expect(display(0)).toBe('0')
        expect(display('2.123456789012345678901')).toBe('2,123456789012345678901')
        expect(display(null)).toBe('—')
    })

    it('shows exact rational results and every backend weight contribution without recalculating', () => {
        const methods = (CourseEvaluations as any).methods
        expect(methods.resultLabel.call(methods, { result: 7 / 3, result_exact: '7/3', status: 'complete' })).toContain('exakt: 7/3')
        const lines = methods.traceLines.call(methods, {
            numerator: '7/3', denominator: '2', contributions: ['4/3', '1'], weights: ['1', '1'],
            adjustments: [{ type_id: 9, balance: 2, amount: 0.1, delta: -0.2, applied: false }],
        }, [{ id: 9, name: 'Mitarbeit' }])
        expect(lines).toContain('Zähler: 7/3')
        expect(lines).toContain('Nenner: 2')
        expect(lines).toContain('Gewichtete Beiträge: 4/3 · 1')
        expect(lines).toContain('Mitarbeit: Saldo 2 · Anpassungswert 0,1 · Änderung -0,2 · Nicht angewendet')
    })
})
