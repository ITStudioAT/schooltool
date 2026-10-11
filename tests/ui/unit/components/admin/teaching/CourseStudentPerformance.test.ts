import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, nextTick } from 'vue'
import { createVuetify } from 'vuetify'
import { VChip } from 'vuetify/components/VChip'
import { VTooltip } from 'vuetify/components/VTooltip'
import CourseStudentPerformance from '@/pages/admin/teaching/overview/components/CourseStudentPerformance.vue'
import { isInTeachingSemester, teachingDateKey, teachingPerformanceDateScope } from '@/helpers/teachingSemester'

const student = { id: 12, user_id: 12, first_name: 'Anna', last_name: 'Test' }
const course = { id: 18, schoolyear_id: 3 }
const schoolyear = { id: 3, name: '2025/26', from: '2025-09-01', until: '2026-08-31' }
const wrappers = []
const ActivatorOnlyTooltip = defineComponent({
    setup(_, { slots }) {
        return () => slots.activator?.({ props: {} })
    },
})

afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
    document.querySelectorAll('.v-overlay-container').forEach((container) => container.remove())
    vi.unstubAllGlobals()
})

function mountPerformance(options) {
    const wrapper = mount(CourseStudentPerformance, {
        ...options,
        props: { schoolyear, ...options.props },
        global: { stubs: { 'v-tooltip': ActivatorOnlyTooltip }, ...options.global },
    })
    wrappers.push(wrapper)
    return wrapper
}

async function mountInteractive(props) {
    vi.stubGlobal('visualViewport', Object.assign(new EventTarget(), { width: 1024, height: 768, offsetLeft: 0, offsetTop: 0, scale: 1 }))
    const wrapper = mountPerformance({
        attachTo: document.body,
        props: { student, course, ...props },
        global: {
            plugins: [createVuetify({ components: { VTooltip, VChip } })],
            components: { 'v-tooltip': VTooltip, 'v-chip': VChip },
            stubs: { 'v-tooltip': false, 'v-chip': false, VTooltip: false, VChip: false },
        },
    })
    await nextTick()
    return wrapper
}

function chip(wrapper, type) {
    return wrapper.findAll('[aria-label]').find((item) => item.attributes('aria-label')?.startsWith(type + ':') || item.attributes('aria-label')?.startsWith(type + ' ·'))
}

async function waitForTooltip() {
    await new Promise((resolve) => setTimeout(resolve, 320))
    await nextTick()
}

async function hoverDetails(wrapper, type = 'MA') {
    await chip(wrapper, type).trigger('mouseenter')
    await waitForTooltip()
    const tooltip = document.querySelector('.v-overlay--active[role="tooltip"]')
    expect(tooltip).not.toBeNull()
    return tooltip
}

function entry(overrides = {}) {
    return { id: 1, user_id: 12, teaching_course_id: 18, type: 'MA', date: '2026-02-03', ...overrides }
}

describe('Compact course student performance', () => {
    it('shows authoritative points per part and student and follows the selected semester', async () => {
        const wrapper = mountPerformance({ props: {
            student: { ...student, course_student_id: 42 }, course, activeSemester: 1,
            gradingReport: { course_id: 18, schoolyear_id: 3, semester_count: 2, students: [
                { course_student_id: 99, user_id: 99, semesters: [{ semester: 1, parts: [{ id: 1, name: 'Fremd', status: 'complete', trace: { method: 'sum_percent', sum: 99, maximum: 100 } }] }] },
                { course_student_id: 42, user_id: null, semesters: [
                    { semester: 1, issues: [{ code: 'incomplete_part', severity: 'error' }], parts: [
                        { id: 1, name: 'Aufträge', status: 'complete', trace: { method: 'sum_percent', sum: 18, maximum: 25 } },
                        { id: 2, name: 'Tests', status: 'complete', trace: { method: 'overall_points', sum: 6.25, maximum: 10 } },
                        { id: 3, name: 'Mitarbeit', status: 'complete', result: 2.5, trace: { method: 'weighted_mean' } },
                        { id: 4, name: 'Referat', status: 'empty', trace: { method: 'sum_percent', sum: 0, maximum: 0 } },
                        { id: 5, name: 'Offen', status: 'incomplete', trace: { method: 'sum_percent', sum: 4, maximum: 10 } },
                    ] },
                    { semester: 2, parts: [{ id: 1, name: 'Aufträge', status: 'complete', trace: { method: 'sum_percent', sum: 7, maximum: 20 } }] },
                ] },
            ] },
        } })
        const summaries = () => wrapper.findAll('.grading-part-card').map((card) => `${card.get('.grading-part-card-title').text()} ${card.get('.grading-part-card-value').text()}`).join(' ')
        expect(summaries()).toContain('Aufträge: 18 / 25 Punkte')
        expect(summaries()).toContain('Tests: 6,25 / 10 Punkte')
        expect(summaries()).toContain('Mitarbeit: Bewertung 2,5')
        expect(summaries()).toContain('Referat: Keine Bewertung')
        expect(summaries()).toContain('Offen: Nicht vollständig berechenbar')
        expect(summaries()).not.toContain('Fremd')
        expect(summaries()).not.toContain('7 / 20')
        expect(wrapper.get('.assignment-progress').text()).toBe('Aufträge72 %')
        expect(wrapper.get('v-progress-linear').attributes('model-value')).toBe('72')
        await wrapper.setProps({ activeSemester: 2 })
        expect(summaries()).toBe('Aufträge: 7 / 20 Punkte')
        expect(wrapper.get('.assignment-progress').text()).toBe('Aufträge35 %')
        await wrapper.setProps({ activeSemester: 3 })
        expect(summaries()).toContain('Aufträge · Sem 1: 18 / 25 Punkte')
        expect(summaries()).toContain('Aufträge · Sem 2: 7 / 20 Punkte')
        expect(wrapper.get('.assignment-progress').text()).toBe('Aufträge55,56 %')
        await wrapper.setProps({ student: { ...student, user_id: 99, course_student_id: 99 } })
        expect(summaries()).toContain('Fremd')
        expect(summaries()).not.toContain('Aufträge')
        expect(wrapper.find('.assignment-progress').exists()).toBe(false)
    })

    it.each([
        { status: 'complete', sum: 0, maximum: 5, text: '0 %', fill: '0' },
        { status: 'complete', sum: 4.6, maximum: 5, text: '92 %', fill: '92' },
        { status: 'complete', sum: 6, maximum: 5, text: '120 % · außerhalb 0–100 %', fill: '100' },
        { status: 'complete', sum: -1, maximum: 5, text: '-20 % · außerhalb 0–100 %', fill: '0' },
        { status: 'incomplete', sum: 4, maximum: 5, text: 'Nicht vollständig berechenbar', fill: '0', unknown: true },
        { status: 'empty', sum: 0, maximum: 0, text: 'Keine Bewertung', fill: '0', unknown: true },
        { status: 'complete', sum: 4, maximum: 0, text: 'Keine Maximalpunkte', fill: '0', unknown: true },
        { status: 'complete', sum: 4, maximum: undefined, text: 'Keine Maximalpunkte', fill: '0', unknown: true },
        { status: 'complete', sum: undefined, maximum: 5, text: 'Keine Punktesumme verfügbar', fill: '0', unknown: true },
    ])('shows authoritative assignment progress or a clear unavailable state: $text', ({ status, sum, maximum, text, fill, unknown }) => {
        const wrapper = mountPerformance({ props: { student, course, activeSemester: 1,
            gradingReport: { course_id: 18, schoolyear_id: 3, semester_count: 2, students: [{ user_id: 12, semesters: [{ semester: 1,
                parts: [{ id: 7, name: 'Aufträge', status, trace: { method: 'sum_percent', sum, maximum } }],
            }] }] },
        } })
        expect(wrapper.get('.assignment-progress').text()).toBe(`Aufträge${text}`)
        expect(wrapper.get('v-progress-linear').attributes('model-value')).toBe(fill)
        expect(wrapper.get('v-progress-linear').attributes('aria-hidden')).toBe(unknown ? 'true' : undefined)
        expect(wrapper.find('.assignment-progress-fill').exists()).toBe(!unknown)
    })

    it('does not merge distinct configured parts with the same assignment title', () => {
        const wrapper = mountPerformance({ props: { student, course, activeSemester: 1,
            gradingReport: { course_id: 18, schoolyear_id: 3, semester_count: 2, students: [{ user_id: 12, semesters: [{ semester: 1,
                parts: [7, 8].map((id) => ({ id, name: 'Aufträge', status: 'complete', trace: { method: 'sum_percent', sum: 4, maximum: 5 } })),
            }] }] },
        } })
        expect(wrapper.findAll('.assignment-progress').map((progress) => progress.text())).toEqual(['Aufträge80 %', 'Aufträge80 %'])
    })

    it('shows progress from point calculation data after renaming a part without adding percentages to grades or balances', () => {
        const wrapper = mountPerformance({ props: { student, course, activeSemester: 1,
            gradingReport: { course_id: 18, schoolyear_id: 3, semester_count: 2, students: [{ user_id: 12, semesters: [{ semester: 1, parts: [
                { id: 7, name: 'Leistungsfeststellungen', status: 'complete', trace: { method: 'sum_percent', sum: 4.1, maximum: 5 } },
                { id: 8, name: 'Weitere Punkte', status: 'complete', trace: { method: 'overall_points', sum: 10, maximum: 20 } },
                { id: 9, name: 'Mitarbeit', status: 'complete', result: 1, trace: { method: 'plus_minus', purpose: 'adjust_grade', balance: 2, balance_status: 'complete' } },
                { id: 10, name: 'Prüfung', status: 'complete', result: 5, trace: { method: 'single_grade' } },
            ] }] }] },
        } })
        expect(wrapper.findAll('.assignment-progress').map((progress) => progress.text())).toEqual(['Leistungsfeststellungen82 %', 'Weitere Punkte50 %'])
        expect(wrapper.findAll('v-progress-linear').map((progress) => progress.attributes('model-value'))).toEqual(['82', '50'])
        expect(wrapper.text()).toContain('Mitarbeit:Saldo +2')
        expect(wrapper.text()).toContain('Prüfung:Note 5')
    })

    it('keeps the selected plus-minus rule pending without a points percentage', () => {
        const wrapper = mountPerformance({ props: { student, course, activeSemester: 1,
            gradingReport: { course_id: 18, schoolyear_id: 3, semester_count: 2, students: [{ user_id: 12, semesters: [{ semester: 1,
                parts: [{ id: 7, name: 'Aufträge', status: 'incomplete', trace: { method: 'plus_minus' } }],
            }] }] },
        } })
        expect(wrapper.get('.grading-part-card').text()).toContain('Berechnungsregel wird noch festgelegt')
        expect(wrapper.find('.assignment-progress').exists()).toBe(false)
        expect(wrapper.find('.assignment-progress-fill').exists()).toBe(false)
    })

    it.each(['grade_each', 'grade_mean'])('keeps grade method %s visible as pending without a computed grade', (method) => {
        const wrapper = mountPerformance({ props: { student, course, activeSemester: 1,
            gradingReport: { course_id: 18, schoolyear_id: 3, semester_count: 2, students: [{ user_id: 12, semesters: [{ semester: 1,
                parts: [{ id: 7, name: 'Prüfung', status: 'incomplete', result: null, trace: { method } }],
            }] }] },
        } })
        expect(wrapper.get('.grading-part-card-value').text()).toBe('Regel offen')
        expect(wrapper.get('.grading-part-card').attributes('title')).toContain(method === 'grade_each' ? 'Jede Note extra rechnen' : 'Notendurchschnitt')
    })

    it.each([
        [3, 'complete', 'Saldo +3', null], [-2, 'complete', 'Saldo -2', null], [0, 'complete', 'Saldo 0', null],
        [null, 'empty', 'Saldo 0', null], [null, 'incomplete', 'Saldo unvollständig', null],
        [3, 'complete', 'Saldo +3', 'own_grade'], [-2, 'complete', 'Saldo -2', 'adjust_grade'],
    ])('shows the defined sign balance %s separately from the pending grade', (balance, balanceStatus, label, purpose) => {
        const wrapper = mountPerformance({ props: { student, course, activeSemester: 1,
            gradingReport: { course_id: 18, schoolyear_id: 3, semester_count: 2, students: [{ user_id: 12, semesters: [{ semester: 1,
                parts: [{ id: 7, name: 'Aufträge', status: 'incomplete', result: null,
                    trace: { method: 'plus_minus', balance, balance_status: balanceStatus, purpose } }],
            }] }] },
        } })
        expect(wrapper.get('.grading-part-card-value').text()).toBe(label)
        if (purpose === 'adjust_grade') expect(wrapper.get('.grading-part-card').text()).toBe(`Aufträge:${label}`)
        else expect(wrapper.get('.grading-part-card').text()).toContain('Note noch offen')
        expect(wrapper.find('.assignment-progress').exists()).toBe(false)
        expect(wrapper.get('.grading-part-card').text()).not.toContain('%')
    })

    it.each([
        [2, '-1/2', 2.5],
        [8, '-5/4', 1.75],
    ])('shows only balance %s even when the report contains an applied adjustment', (balance, delta, result) => {
        const wrapper = mountPerformance({ props: { student, course, activeSemester: 1,
            gradingReport: { course_id: 18, schoolyear_id: 3, semester_count: 2, students: [{ user_id: 12, semesters: [{ semester: 1,
                parts: [{ id: 7, name: 'Mitarbeit', status: 'complete', result,
                    config: { sign_adjustment: { improvement_factor: '0.25', max_improvement: '1.25', deterioration_factor: '0.25', max_deterioration: '1' } },
                    trace: { method: 'plus_minus', purpose: 'adjust_grade', balance, balance_status: 'complete',
                        base_exact: '3', delta_exact: delta, adjusted_exact: String(result), rule_label: 'Gespeicherte Anpassungsregel' } }],
            }] }] },
        } })
        expect(wrapper.get('.grading-part-card-value').text()).toBe(`Saldo +${balance}`)
        expect(wrapper.get('.grading-part-card').text()).toBe(`Mitarbeit:Saldo +${balance}`)
        expect(wrapper.get('.grading-part-card').text()).not.toContain('offen')
        expect(wrapper.get('.grading-part-card').classes()).not.toContain('grading-part-card--incomplete')
    })

    it.each(['complete', 'empty', 'incomplete'])('shows only the balance state %s when adjustment prerequisites are incomplete', (balanceStatus) => {
        const wrapper = mountPerformance({ props: { student, course, activeSemester: 1,
            gradingReport: { course_id: 18, schoolyear_id: 3, semester_count: 2, students: [{ user_id: 12, semesters: [{ semester: 1,
                parts: [{ id: 7, name: 'Mitarbeit', status: 'incomplete', result: null,
                    config: { sign_adjustment: { improvement_factor: '0.25', max_improvement: '1.25', deterioration_factor: '0.25', max_deterioration: '1' } },
                    trace: { method: 'plus_minus', purpose: 'adjust_grade', balance: balanceStatus === 'complete' ? 2 : null, balance_status: balanceStatus } }],
            }] }] },
        } })
        expect(wrapper.get('.grading-part-card').text()).toBe(`Mitarbeit:${balanceStatus === 'complete'
            ? 'Saldo +2' : balanceStatus === 'empty' ? 'Saldo 0' : 'Saldo unvollständig'}`)
        expect(wrapper.text()).not.toContain('Notenanpassung −0,5')
    })

    it('shows a directly adopted single standard grade as a note', () => {
        const wrapper = mountPerformance({ props: { student, course, activeSemester: 1,
            gradingReport: { course_id: 18, schoolyear_id: 3, semester_count: 2, students: [{ user_id: 12, semesters: [{ semester: 1,
                parts: [{ id: 7, name: 'Prüfung', status: 'complete', result: 2, trace: { method: 'single_grade', values: [2], count: 1 } }],
            }] }] },
        } })
        expect(wrapper.get('.grading-part-card-value').text()).toBe('Note 2')
    })

    it('toggles calculated semester and year cards using only the matching report results', async () => {
        const part = { id: 7, name: 'Leistungsfeststellungen', status: 'complete', trace: { method: 'sum_percent', sum: 4, maximum: 5 } }
        const report = { course_id: 18, schoolyear_id: 3, semester_count: 2, students: [
            { user_id: 12, semesters: [
                { semester: 1, status: 'complete', result: 2, result_exact: '42/25', parts: [part] },
                { semester: 2, status: 'complete', result: 4, parts: [part] },
            ], year: { status: 'complete', result: 3, result_exact: '14/5' } },
            { user_id: 13, semesters: [{ semester: 1, status: 'incomplete', result: null, parts: [part],
                issues: [{ severity: 'error', message: 'Verpflichtende Prüfung fehlt.' }] }], year: { status: 'incomplete', result: null } },
        ] }
        const wrapper = mountPerformance({ props: { student: { ...student, sem_grade: 5 }, course, activeSemester: 1, gradingReport: report } })
        const cards = () => wrapper.findAll('.grading-part-card').map((card) => card.text())
        expect(cards()).toEqual(['Leistungsfeststellungen:4 / 5 Punkte'])
        await wrapper.setProps({ showCalculatedGrade: true })
        expect(cards()).toEqual(['Leistungsfeststellungen:4 / 5 Punkte', 'Note:21,68'])
        await wrapper.setProps({ activeSemester: 2 })
        expect(cards()).toEqual(['Leistungsfeststellungen:4 / 5 Punkte', 'Note:4'])
        await wrapper.setProps({ activeSemester: 3 })
        expect(cards()).toEqual(['Leistungsfeststellungen · Sem 1:4 / 5 Punkte', 'Note · Sem 1:21,68',
            'Leistungsfeststellungen · Sem 2:4 / 5 Punkte', 'Note · Sem 2:4', 'Gesamtnote:32,80'])
        await wrapper.setProps({ student: { ...student, user_id: 13 }, activeSemester: 1 })
        expect(cards()).toContain('Note:Nicht berechenbar')
        expect(wrapper.findAll('.grading-part-card').at(-1).attributes('title')).toBe('Verpflichtende Prüfung fehlt.')
        await wrapper.setProps({ showCalculatedGrade: false })
        expect(cards()).toEqual(['Leistungsfeststellungen:4 / 5 Punkte'])
        await wrapper.setProps({ showCalculatedGrade: true, course: { ...course, id: 99 } })
        expect(cards()).toEqual([])
    })

    it.each([['5/3', '1,67'], ['1', '1,00'], ['42/25', '1,68']])('shows exact raw %s to two decimal places below the final grade', (raw, displayed) => {
        const wrapper = mountPerformance({ props: { student, course, activeSemester: 1, showCalculatedGrade: true,
            gradingReport: { course_id: 18, schoolyear_id: 3, semester_count: 2, students: [{ user_id: 12,
                semesters: [{ semester: 1, status: 'complete', result: 2, result_exact: raw, parts: [] }],
            }] },
        } })
        expect(wrapper.get('.grading-part-card-value').text()).toBe('2')
        expect(wrapper.get('.grading-part-card .text-caption').text()).toBe(displayed)
    })

    it.each([[null, 'empty'], [0, 'complete']])('shows neutral balance for %s without changing the underlying performance report', (balance, balanceStatus) => {
        const trace = { method: 'plus_minus', purpose: 'adjust_grade', balance, balance_status: balanceStatus, values: balance === null ? [] : [0] }
        const wrapper = mountPerformance({ props: { student, course, activeSemester: 1,
            gradingReport: { course_id: 18, schoolyear_id: 3, semester_count: 2, students: [{ user_id: 12, semesters: [{ semester: 1,
                parts: [{ id: 7, name: 'Mitarbeit', status: balanceStatus === 'empty' ? 'empty' : 'incomplete', result: null, trace }],
            }] }] },
        } })
        expect(wrapper.get('.grading-part-card').text()).toBe('Mitarbeit:Saldo 0')
        expect(trace.balance).toBe(balance)
        expect(trace.balance_status).toBe(balanceStatus)
        expect(trace.values).toEqual(balance === null ? [] : [0])
    })

    it('shows the configured average as a decimal without rounding to a school grade', () => {
        const wrapper = mountPerformance({ props: { student, course, activeSemester: 1,
            gradingReport: { course_id: 18, schoolyear_id: 3, semester_count: 2, students: [{ user_id: 12, semesters: [{ semester: 1,
                parts: [{ id: 7, name: 'Schularbeiten', status: 'complete', result: 1.6, trace: { method: 'standard_grade_mean', rule_label: 'Chronologische Arbeiten je Semester' } }],
            }] }] },
        } })
        expect(wrapper.get('.grading-part-card-value').text()).toBe('Notendurchschnitt 1,6')
        expect(wrapper.get('.grading-part-card').text()).not.toContain('Regel offen')
    })

    it('shows optional standard grade cards only for the person and semester with actual entries', async () => {
        const exam = (required, entries = []) => ({ id: 7, name: 'Prüfung', config: { is_required: required },
            types: [{ config: { calculation_mode: 'grades' }, entries }],
            status: entries.length ? 'complete' : required ? 'incomplete' : 'empty',
            result: entries.length ? 5 : null, trace: { method: 'single_grade' } })
        const report = { course_id: 18, schoolyear_id: 3, semester_count: 2, students: [
            { user_id: 12, semesters: [{ semester: 1, parts: [exam(false)] }, { semester: 2, parts: [exam(false, [{ value: '5' }])] }] },
            { user_id: 13, semesters: [{ semester: 1, parts: [exam(false, [{ value: '5' }])] }] },
            { user_id: 14, semesters: [{ semester: 1, parts: [exam(true)] }] },
        ] }
        const wrapper = mountPerformance({ props: { student, course, activeSemester: 1, gradingReport: report } })
        expect(wrapper.find('.grading-part-card').exists()).toBe(false)
        await wrapper.setProps({ student: { ...student, user_id: 13 } })
        expect(wrapper.get('.grading-part-card').text()).toBe('Prüfung:Note 5')
        await wrapper.setProps({ student })
        expect(wrapper.find('.grading-part-card').exists()).toBe(false)
        await wrapper.setProps({ activeSemester: 2 })
        expect(wrapper.get('.grading-part-card').text()).toBe('Prüfung:Note 5')
        await wrapper.setProps({ student: { ...student, user_id: 14 }, activeSemester: 1 })
        expect(wrapper.get('.grading-part-card').text()).toContain('Prüfung:Nicht vollständig berechenbar')
    })

    it('retains optional cards with incomplete entries and optional cards of other types', () => {
        const wrapper = mountPerformance({ props: { student, course, activeSemester: 1,
            gradingReport: { course_id: 18, schoolyear_id: 3, semester_count: 2, students: [{ user_id: 12, semesters: [{ semester: 1, parts: [
                { id: 7, name: 'Prüfung', config: { is_required: false }, status: 'incomplete', result: null,
                    types: [{ config: { calculation_mode: 'grades' }, entries: [{ value: 'ungültig' }] }], trace: { method: 'single_grade' } },
                { id: 8, name: 'Punktearbeit', config: { is_required: false }, status: 'empty',
                    types: [{ config: { calculation_mode: 'points' }, entries: [] }], trace: { method: 'sum_percent' } },
            ] }] }] },
        } })
        expect(wrapper.findAll('.grading-part-card')).toHaveLength(2)
        expect(wrapper.text()).toContain('Prüfung:Nicht vollständig berechenbar')
        expect(wrapper.text()).toContain('Punktearbeit:Keine Bewertung')
    })

    it('shows a configured own sign grade alongside its net balance without point percentages', () => {
        const wrapper = mountPerformance({ props: { student, course, activeSemester: 1,
            gradingReport: { course_id: 18, schoolyear_id: 3, semester_count: 2, students: [{ user_id: 12, semesters: [{ semester: 1,
                parts: [{ id: 7, name: 'Mitarbeit', status: 'complete', result: 3,
                    trace: { method: 'plus_minus', balance: 0, balance_status: 'complete', purpose: 'own_grade', thresholds: { 4: -2, 3: 0, 2: 2, 1: 4 } } }],
            }] }] },
        } })
        expect(wrapper.get('.grading-part-card').text()).toContain('Saldo 0Note 3')
        expect(wrapper.get('.grading-part-card').text()).not.toContain('%')
        expect(wrapper.find('.assignment-progress').exists()).toBe(false)
    })

    it('shows both semester sign balances without treating either as a grade', () => {
        const wrapper = mountPerformance({ props: { student, course, activeSemester: 3,
            gradingReport: { course_id: 18, schoolyear_id: 3, semester_count: 2, students: [{ user_id: 12,
                semesters: [1, 2].map((semester) => ({ semester, parts: [{ id: 7, name: 'Mitarbeit', status: 'incomplete', result: null,
                    trace: { method: 'plus_minus', balance: semester === 1 ? 2 : -1, balance_status: 'complete' } }] })),
            }] },
        } })
        expect(wrapper.findAll('.grading-part-card').map((card) => card.text())).toEqual([
            'Mitarbeit · Sem 1:Saldo +2Note noch offen', 'Mitarbeit · Sem 2:Saldo -1Note noch offen',
        ])
    })

    it.each([
        [0, 'Nicht genügend'],
        [49.999, 'Nicht genügend'],
        [50, 'Genügend'],
        [62.499, 'Genügend'],
        [62.5, 'Befriedigend'],
        [74.999, 'Befriedigend'],
        [75, 'Gut'],
        [87.499, 'Gut'],
        [87.5, 'Sehr gut'],
        [100, 'Sehr gut'],
    ])('clips the full gradient at unrounded share %s with boundary label %s', (sum, label) => {
        const wrapper = mountPerformance({ props: { student, course, activeSemester: 1,
            gradingReport: { course_id: 18, schoolyear_id: 3, semester_count: 2, students: [{ user_id: 12, semesters: [{ semester: 1,
                parts: [{ id: 7, name: 'Aufträge', status: 'complete', trace: { method: 'sum_percent', sum, maximum: 100 } }],
            }] }] },
        } })
        expect(wrapper.get('v-progress-linear').attributes('title')).toBe(label)
        expect(Number(wrapper.get('v-progress-linear').attributes('model-value'))).toBeCloseTo(sum as number)
        const fill = wrapper.get('.assignment-progress-fill').element as HTMLElement
        expect(Number(fill.style.clipPath.split(' ')[1].replace('%', ''))).toBeCloseTo(100 - Number(sum))
        expect(fill.style.backgroundImage).toContain('#e53935 50%, #fb8c00 50%')
        expect(fill.style.backgroundImage).toContain('#43a047 87.5%')
        if (sum === 49.999) expect(wrapper.get('.assignment-progress-label').text()).toContain('50 %')
    })

    it.each([{ course_id: 99, schoolyear_id: 3 }, { course_id: 18, schoolyear_id: 99 }])('does not show a grading report for a different scope', (scope) => {
        const wrapper = mountPerformance({ props: { student, course, gradingReport: { ...scope, students: [{ user_id: 12, semesters: [{ semester: 1, parts: [{ id: 1, name: 'Fremd', status: 'empty' }] }] }] } } })
        expect(wrapper.find('[aria-label="Benotungsteile"]').exists()).toBe(false)
        expect(wrapper.find('.assignment-progress').exists()).toBe(false)
    })
    it('shows only the selected student evaluation PDF inside the work details', async () => {
        const wrapper = await mountInteractive({
            schema: { works: [{ short_name: 'MA', name: 'Mitarbeit' }] },
            entries: [entry({ source: 'course_work', teaching_course_work_id: 8, grade: '4.5' })],
            works: [{ id: 8, teaching_course_id: course.id, title: 'E-Mails', status: { evaluation_pdfs: [
                { student_id: null, name: 'Gesamtübersicht.pdf', sha256: 'a'.repeat(64), origin: 'evaluation_import' },
                { student_id: student.user_id, name: 'personal.pdf', sha256: 'b'.repeat(64), origin: 'evaluation_import' },
                { student_id: 99, name: 'foreign.pdf', sha256: 'c'.repeat(64), origin: 'evaluation_import' },
            ] } }],
        })
        const tooltip = await hoverDetails(wrapper)
        const links = tooltip.querySelectorAll('[href]')
        expect(links).toHaveLength(1)
        expect(links[0].textContent).toContain('Auswertung (PDF)')
        expect(links[0].getAttribute('href')).toBe(`/api/admin/teaching/course_works/8/evaluations/${'b'.repeat(64)}?inline=1`)
    })
    it('renders each repeated entry separately and opens only its details on hover', async () => {
        const wrapper = await mountInteractive({
            schema: { works: [{ short_name: 'MA', name: 'Mitarbeit' }] },
            entries: Array.from({ length: 35 }, (_, index) => entry({ id: index + 1, grade: '+', description: `Kommentar ${index + 1}` })),
        })
        expect(wrapper.findAll('.v-chip').map((item) => item.text())).toEqual(Array(35).fill('MA: +'))
        expect(document.querySelector('[role="tooltip"]')).toBeNull()
        const tooltip = await hoverDetails(wrapper)
        expect(tooltip.textContent).toContain('MA · Mitarbeit')
        expect(tooltip.textContent).not.toContain('2025/26')
        expect(tooltip.querySelectorAll('.performance-detail-item')).toHaveLength(1)
        expect(tooltip.textContent).toContain('Kommentar 1')
        expect(tooltip.textContent).not.toContain('Kommentar 35')
        expect(tooltip.querySelector('[role="region"]').getAttribute('tabindex')).toBe('0')
        await chip(wrapper, 'MA').trigger('mouseleave')
        tooltip.querySelector('.v-overlay__content').dispatchEvent(new MouseEvent('mouseenter'))
        await waitForTooltip()
        expect(tooltip.classList.contains('v-overlay--active')).toBe(true)
        tooltip.querySelector('.v-overlay__content').dispatchEvent(new MouseEvent('mouseleave'))
        await waitForTooltip()
        expect(tooltip.classList.contains('v-overlay--active')).toBe(false)
    })

    it('opens from keyboard focus and closes with Escape', async () => {
        const wrapper = await mountInteractive({ entries: [entry({ description: 'Tastaturdetail' })] })
        const activator = chip(wrapper, 'MA')
        expect(activator.attributes('tabindex')).toBe('0')
        activator.element.focus()
        await waitForTooltip()
        const tooltip = document.querySelector('.v-overlay--active[role="tooltip"]')
        expect(tooltip?.textContent).toContain('Tastaturdetail')
        expect(activator.attributes('aria-describedby')).toBe(tooltip.id)
        await activator.trigger('keydown', { key: 'ArrowDown' })
        expect(document.activeElement).toBe(tooltip.querySelector('[role="region"]'))
        document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
        await waitForTooltip()
        expect(document.querySelector('.v-overlay--active[role="tooltip"]')).toBeNull()
        expect(document.activeElement).toBe(activator.element)
        await activator.trigger('mouseenter')
        await waitForTooltip()
        expect(document.querySelector('.v-overlay--active[role="tooltip"]')).not.toBeNull()
    })

    it('closes explicitly with X without immediately reopening and supports fresh hover and focus', async () => {
        const wrapper = await mountInteractive({ entries: [entry({ description: 'Schließbares Detail' })] })
        const activator = chip(wrapper, 'MA')
        const tooltip = await hoverDetails(wrapper)
        tooltip.querySelector('button[aria-label="Detailfenster schließen"]').click()
        await waitForTooltip()
        expect(document.querySelector('.v-overlay--active[role="tooltip"]')).toBeNull()
        expect(document.activeElement).toBe(activator.element)
        await activator.trigger('mouseleave')
        await activator.trigger('mouseenter')
        await waitForTooltip()
        const reopened = document.querySelector('.v-overlay--active[role="tooltip"]')
        expect(reopened?.textContent).toContain('Schließbares Detail')
        reopened.querySelector('button[aria-label="Detailfenster schließen"]').click()
        await waitForTooltip()
        expect(document.querySelector('.v-overlay--active[role="tooltip"]')).toBeNull()
        activator.element.blur()
        activator.element.focus()
        await waitForTooltip()
        expect(document.querySelector('.v-overlay--active[role="tooltip"]')?.textContent).toContain('Schließbares Detail')
    })

    it('shows only the selected course, student, schoolyear and semester details', async () => {
        const wrapper = await mountInteractive({
            activeSemester: 1, semesterTwoStartDate: '2026-02-09',
            student: { ...student, comment: 'PRIVATE-STUDENT', special_information: 'PRIVATE-HEALTH' },
            entries: [
                entry({ description: 'VISIBLE', grade: '+' }),
                entry({ id: 2, date: '2026-02-09', description: 'OTHER-SEMESTER' }),
                entry({ id: 3, date: '2025-08-31', description: 'OTHER-YEAR' }),
                entry({ id: 4, date: null, description: 'UNDATED' }),
                entry({ id: 5, user_id: 99, description: 'OTHER-STUDENT' }),
                entry({ id: 6, teaching_course_id: 99, description: 'OTHER-COURSE' }),
            ],
        })
        const tooltip = await hoverDetails(wrapper)
        expect(tooltip.querySelectorAll('.performance-detail-item')).toHaveLength(1)
        expect(tooltip.textContent).toContain('VISIBLE')
        expect(tooltip.textContent).not.toContain('Semester 1')
        for (const hidden of ['OTHER-SEMESTER', 'OTHER-YEAR', 'UNDATED', 'OTHER-STUDENT', 'OTHER-COURSE', 'PRIVATE-STUDENT', 'PRIVATE-HEALTH']) expect(tooltip.textContent).not.toContain(hidden)
        await wrapper.setProps({ schoolyear: { id: 4, from: '2026-09-01', until: '2027-08-31' } })
        expect(document.body.textContent).not.toContain('VISIBLE')
    })

    it.each([
        [[{ student_id: 12, comment: 'INDIVIDUAL' }, { student_id: 99, comment: 'OTHER-STUDENT' }], 'INDIVIDUAL'],
        [{ 12: 'INDIVIDUAL', 99: 'OTHER-STUDENT' }, 'INDIVIDUAL'],
        [{ 12: { comment: 'INDIVIDUAL' }, 99: { comment: 'OTHER-STUDENT' } }, 'INDIVIDUAL'],
        [[{ student_id: 99, comment: 'OTHER-STUDENT' }], 'GROUP-FALLBACK'],
    ])('selects the current student work comment from its supported shapes with group fallback', async (comments, expectedComment) => {
        const wrapper = await mountInteractive({
            entries: [entry({ source: 'course_work', teaching_course_work_id: 7, grade: '1', date: '2026-01-01' })],
            works: [{ id: 7, teaching_course_id: 18, finish_until_date: '2026-02-03', title: 'Projekt', description: 'Recherche', groups: [
                { student_ids: [99], comment: 'OTHER-GROUP', comments: [{ student_id: 12, comment: 'WRONG-MEMBERSHIP' }] },
                { student_ids: ['12', '99'], comment: 'GROUP-FALLBACK', comments },
            ] }],
        })
        expect(wrapper.text()).toBe('MA: 1')
        const tooltip = await hoverDetails(wrapper)
        expect(tooltip.textContent).toContain(expectedComment)
        expect(tooltip.textContent).toContain('03.02.2026')
        expect(tooltip.textContent).toContain('Aufgabe: Recherche')
        expect(tooltip.textContent).toContain('Projekt')
        for (const hidden of ['OTHER-STUDENT', 'OTHER-GROUP', 'WRONG-MEMBERSHIP']) expect(tooltip.textContent).not.toContain(hidden)
        if (expectedComment === 'INDIVIDUAL') expect(tooltip.textContent).not.toContain('GROUP-FALLBACK')
    })

    it('shows ungraded records separately beside recorded values', () => {
        const wrapper = mountPerformance({ props: {
            student, course, usesEntryAreas: true,
            behaviourEntries: [entry({ type: 'V', kind: 'behaviour', grade: '+' }), entry({ id: 2, type: 'V', kind: 'behaviour' })],
            entries: [entry({ type: 'PÜ', grade: 0 }), entry({ id: 2, type: 'PÜ', grade: '++++' })],
        } })
        expect(wrapper.findAll('v-chip').map((item) => item.text())).toEqual(['PÜ: 0', 'PÜ: ++++', 'V: +', 'V'])
    })

    it('shows ungraded behaviour dates and comments without an invented result label', async () => {
        const wrapper = await mountInteractive({
            usesEntryAreas: true,
            course: { ...course, teaching_entry_area: { entry_definitions: [{ short_name: 'D', name: 'Disziplinarbogen', category: 'Verhalten', has_properties: false }] } },
            entries: [entry({ type: 'D', description: 'Vereinbarung eingehalten' })],
        })
        expect(wrapper.text()).toBe('D')
        const tooltip = await hoverDetails(wrapper, 'D')
        expect(tooltip.textContent).toContain('03.02.2026')
        expect(tooltip.textContent).toContain('Vereinbarung eingehalten')
        expect(tooltip.textContent).not.toContain('Ergebnis:')
        expect(tooltip.textContent).not.toContain('Ohne Bewertung')
    })

    it('shows reminder descriptions, due times and completion without inventing a grade', async () => {
        const wrapper = await mountInteractive({
            behaviourEntries: [entry({ kind: 'notification', type: 'E', description: 'Unterschrift nachreichen', due_date: '2026-02-10', due_time: '09:30', done_date: '2026-02-11' })],
        })
        const tooltip = await hoverDetails(wrapper, 'E')
        for (const detail of ['Unterschrift nachreichen', 'Fällig: 10.02.2026 09:30', 'Erledigt: 11.02.2026']) expect(tooltip.textContent).toContain(detail)
        expect(tooltip.textContent).not.toContain('Ergebnis:')
    })

    it.each([
        [{ ...student, sem_1_grade: '2' }, [], 'Note Sem 1: 2', 'Bewertung: 2'],
        [student, [{ id: 1, teaching_course_id: 18, user_id: 12, semester: 1, category_name: 'Kompetenz', value: 'Bestanden' }], 'Kompetenz · Sem 1: Bestanden', 'Bewertung: Bestanden'],
    ])('opens assigned grade and evaluation information on hover', async (gradedStudent, evaluations, chipText, detailText) => {
        const wrapper = await mountInteractive({ student: gradedStudent, evaluations })
        const activator = wrapper.findAll('.v-chip').find((item) => item.text() === chipText)
        expect(activator.attributes('tabindex')).toBe('0')
        expect(document.querySelector('[role="tooltip"]')).toBeNull()
        await activator.trigger('mouseenter')
        await waitForTooltip()
        const tooltip = document.querySelector('.v-overlay--active[role="tooltip"]')
        expect(tooltip?.textContent).toContain(detailText)
        expect(tooltip.textContent).not.toContain('2025/26')
    })

    it('normalizes serialized UTC midnights to the local calendar day at year and semester boundaries', () => {
        const yearStart = new Date(2025, 8, 1).toISOString()
        const semesterStart = new Date(2026, 1, 9).toISOString()
        expect(teachingDateKey(yearStart)).toBe('2025-09-01')
        expect(teachingDateKey(semesterStart)).toBe('2026-02-09')
        expect(teachingPerformanceDateScope(yearStart, 1, '2026-02-09', schoolyear)).toBe('included')
        expect(teachingPerformanceDateScope(semesterStart, 1, '2026-02-09', schoolyear)).toBe('excluded')
        expect(teachingPerformanceDateScope(semesterStart, 2, '2026-02-09', schoolyear)).toBe('included')
    })
    it.each([1, 2, 3])('excludes previous and following schoolyear records in semester %s', (activeSemester) => {
        const wrapper = mountPerformance({ props: {
            student, course, activeSemester, semesterTwoStartDate: '2026-02-09',
            entries: [
                entry({ date: '2025-08-31', type: 'PREVIOUS' }),
                entry({ id: 2, date: '2025-09-01', type: 'FIRST' }),
                entry({ id: 3, date: '2026-08-31', type: 'LAST' }),
                entry({ id: 4, date: '2026-09-01', type: 'FOLLOWING' }),
            ],
            behaviourEntries: [entry({ kind: 'behaviour', date: '2025-08-31', type: 'OLD-BEHAVIOUR' }), entry({ id: 2, kind: 'notification', date: '2026-09-01', type: 'FUTURE-REMINDER' })],
        } })
        for (const type of ['PREVIOUS', 'FOLLOWING', 'OLD-BEHAVIOUR', 'FUTURE-REMINDER']) expect(wrapper.text()).not.toContain(type)
        expect(wrapper.text().includes('FIRST')).toBe(activeSemester !== 2)
        expect(wrapper.text().includes('LAST')).toBe(activeSemester !== 1)
    })

    it('hides all stale course-year totals immediately when the selected schoolyear changes', async () => {
        const wrapper = mountPerformance({ props: {
            student: { ...student, sem_1_grade: '1', stars: [{ date: '2026-01-01' }] }, course,
            entries: [entry()], evaluations: [{ id: 1, teaching_course_id: 18, user_id: 12, semester: 1, category_name: 'Kompetenz', value: 'Bestanden' }],
        } })
        expect(wrapper.text()).toContain('MA')
        await wrapper.setProps({ schoolyear: { id: 4, from: '2026-09-01', until: '2027-08-31' } })
        for (const text of ['MA', 'Note Sem 1', 'Kompetenz', 'Bestanden']) expect(wrapper.text()).not.toContain(text)
        expect(wrapper.text()).toContain('ausgewählte Schuljahr')
    })

    it('classifies unassignable records separately rather than adding them to exact totals', () => {
        for (const activeSemester of [1, 2, 3]) {
            expect(teachingPerformanceDateScope(null, activeSemester, '2026-02-09', schoolyear)).toBe('unassigned')
            expect(teachingPerformanceDateScope('2026-02-30', activeSemester, '2026-02-09', schoolyear)).toBe('unassigned')
        }
        expect(teachingPerformanceDateScope('2026-02-01', 1, null, schoolyear)).toBe('unassigned')
        expect(teachingPerformanceDateScope('2026-02-01', 2, '2027-02-01', schoolyear)).toBe('unassigned')
        expect(teachingPerformanceDateScope('2026-02-01', 3, null, schoolyear)).toBe('included')
        const wrapper = mountPerformance({ props: { student, course, schoolyear: { id: 3 }, entries: [entry()] } })
        expect(wrapper.text()).toContain('Schuljahresgrenzen fehlen')
        expect(wrapper.text()).toContain('nicht mitgezählt')
        expect(wrapper.text()).not.toContain('MA')
    })

    it('uses the completion year of work results and never includes an out-of-year result twice', () => {
        const wrapper = mountPerformance({ props: {
            student, course,
            entries: [entry({ source: 'course_work', teaching_course_work_id: 7, date: '2025-08-20', type: 'CURRENT' }), entry({ id: 2, source: 'course_work', teaching_course_work_id: 8, date: '2026-08-20', type: 'NEXT' })],
            works: [{ id: 7, teaching_course_id: 18, finish_until_date: '2025-09-01' }, { id: 8, teaching_course_id: 18, finish_until_date: '2026-09-01' }],
        } })
        expect(wrapper.text()).toContain('CURRENT')
        expect(wrapper.text()).not.toContain('NEXT')
    })
    it('shows all records separately with effective grades and without long details or tables', () => {
        const wrapper = mountPerformance({ props: {
            student, course,
            entries: [entry({ grade: '4', effective_grade: '+', description: 'Langer individueller Kommentar' }), entry({ id: 2, grade: '+' }), entry({ id: 3, grade: '-' }), entry({ id: 4, type: 'ALT' }), entry({ id: 5, type: null, date: null, description: 'Nur Beschreibung' })],
            schema: { works: [{ short_name: 'MA', name: 'Mitarbeit' }] },
        } })
        expect(wrapper.findAll('v-chip').filter((item) => item.text().startsWith('MA:')).map((item) => item.text())).toEqual(['MA: +', 'MA: +', 'MA: -'])
        expect(wrapper.text()).toContain('ALT')
        expect(wrapper.text()).toContain('Ohne Zeitraumzuordnung: 1 (nicht mitgezählt)')
        expect(wrapper.text()).toContain('Offen')
        expect(wrapper.text()).not.toContain('Langer individueller Kommentar')
        expect(wrapper.text()).not.toContain('Nur Beschreibung')
        expect(wrapper.find('table, v-table').exists()).toBe(false)
    })

    it('sorts individual records by their full type name and then date', () => {
        const wrapper = mountPerformance({ props: {
            student, usesEntryAreas: true,
            course: { ...course, teaching_entry_area: { entry_definitions: [
                { short_name: 'Z', name: 'Mitarbeit', category: 'Benotung' },
                { short_name: 'A', name: 'Praktische Übung', category: 'Benotung' },
                { short_name: 'P', name: 'Prüfung', category: 'Benotung' },
            ] } },
            entries: [
                entry({ id: 1, type: 'P', grade: '2' }),
                entry({ id: 2, type: 'Z', grade: '-', date: '2026-02-05' }),
                entry({ id: 3, type: 'A', grade: '+' }),
                entry({ id: 4, type: 'Z', grade: '+', date: '2026-02-01' }),
                entry({ id: 5, type: 'P', grade: '2' }),
            ],
        } })
        expect(wrapper.findAll('v-chip').map((item) => item.text())).toEqual(['Z: +', 'Z: -', 'A: +', 'P: 2', 'P: 2'])
        expect(wrapper.text()).not.toContain('×')
    })

    it('keeps grading, discipline, warnings and legacy reminders distinct', () => {
        const wrapper = mountPerformance({ props: {
            student, usesEntryAreas: true,
            course: { ...course, teaching_entry_area: { entry_definitions: [
                { short_name: 'PÜ', name: 'Praktische Übung', category: 'Benotung' },
                { short_name: 'D', name: 'Disziplinarbogen', category: 'Verhalten', has_properties: false },
                { short_name: 'FW', name: 'Frühwarnung', category: 'Weitere', has_properties: false },
            ] } },
            entries: [entry({ type: 'PÜ', grade: '++++' }), entry({ id: 2, type: 'D' }), entry({ id: 3, type: 'FW' })],
            behaviourEntries: [entry({ kind: 'behaviour', type: 'V' }), entry({ id: 2, kind: 'notification', due_date: '2026-02-10', done_date: '2026-02-11' })],
        } })
        for (const text of ['PÜ: ++++', 'D', 'FW', 'V', 'MA: Erledigt']) expect(wrapper.text()).toContain(text)
        expect(wrapper.findAll('[role="group"]').map((group) => group.attributes('aria-label'))).toEqual(['Benotung', 'Verhalten', 'Weitere', 'Erinnerungen'])
        expect(wrapper.findAll('.performance-row').map((row) => row.findAll('[role="group"]').map((group) => group.attributes('aria-label')))).toEqual([['Benotung'], ['Verhalten'], ['Weitere', 'Erinnerungen']])
        for (const label of ['Benotung:', 'Verhalten:', 'Weitere:', 'Erinnerungen:']) expect(wrapper.text()).not.toContain(label)
        expect(chip(wrapper, 'FW').text()).not.toContain('Offen')
    })

    it.each([
        [{ student_id: '12', comment: 'Gut erklärt' }],
        { 12: 'Gut erklärt' },
    ])('counts synchronized work results once and leaves work comments in the detail view', (comments) => {
        const wrapper = mountPerformance({ props: {
            student, course, entries: [entry({ source: 'course_work', teaching_course_work_id: '7', grade: '1', description: 'Zusammenarbeit' })],
            works: [{ id: 7, teaching_course_id: 18, title: 'Projekt', description: 'Recherche', is_group_work: true, groups: [{ student_ids: ['12'], grades: [{ student_id: 12, grade: '1' }], comment: 'Zusammenarbeit', comments }] }],
        } })
        expect(chip(wrapper, 'MA').text()).toContain('MA')
        expect(chip(wrapper, 'MA').text()).toContain('1')
        for (const text of ['Projekt', 'Recherche', 'Zusammenarbeit', 'Gut erklärt']) expect(wrapper.text()).not.toContain(text)
    })

    it.each([1, 2, 3])('filters all categories, assigned grades and evaluations for semester %s', (activeSemester) => {
        const wrapper = mountPerformance({ props: {
            student: { ...student, sem_1_grade: '2', sem_2_grade: '1', stars: [{ date: '2026-02-01', comment: 'Langes Lob' }] },
            course, activeSemester, semesterTwoStartDate: '2026-02-09',
            entries: [
                entry({ grade: '+', date: '2026-02-08T23:59:59' }),
                entry({ id: 2, grade: '-', date: '2026-02-09T00:00:00' }),
                entry({ id: 3, grade: '0', date: null }),
            ],
            behaviourEntries: [
                entry({ kind: 'behaviour', type: 'V1', date: '2026-02-08' }),
                entry({ id: 2, kind: 'notification', type: 'E2', date: '2026-02-09', due_date: '2026-03-01' }),
            ],
            evaluations: [1, 2, 3].map((semester) => ({ id: semester, user_id: '12', teaching_course_id: '18', semester, category_name: 'Kompetenz', value: 'Wert' + semester })),
        } })
        const text = wrapper.text()
        expect(text).not.toContain('0')
        expect(text).toContain('Ohne Zeitraumzuordnung: 1 (nicht mitgezählt)')
        expect(text.includes('+')).toBe(activeSemester !== 2)
        expect(text.includes('-')).toBe(activeSemester !== 1)
        expect(text.includes('V1')).toBe(activeSemester !== 2)
        expect(text.includes('E2')).toBe(activeSemester !== 1)
        expect(text.includes('Note Sem 1: 2')).toBe(activeSemester !== 2)
        expect(text.includes('Note Sem 2: 1')).toBe(activeSemester !== 1)
        for (const semester of [1, 2, 3]) expect(text.includes('Wert' + semester)).toBe(activeSemester === 3 || activeSemester === semester)
        expect(text).not.toContain('Langes Lob')
    })

    it('uses work completion dates like the table, falling back to entry dates', async () => {
        const wrapper = mountPerformance({ props: {
            student, course, activeSemester: 1, semesterTwoStartDate: '2026-02-09',
            entries: [entry({ source: 'course_work', teaching_course_work_id: 7, date: '2026-02-01', grade: '1' }), entry({ id: 2, type: 'ALT', source: 'course_work', teaching_course_work_id: 8, date: '2026-02-01', grade: '2' })],
            works: [{ id: 7, teaching_course_id: 18, finish_until_date: '2026-02-09' }, { id: 8, teaching_course_id: 18, finish_until_date: null }],
        } })
        expect(wrapper.text()).not.toContain('MA')
        expect(wrapper.text()).toContain('ALT')
        await wrapper.setProps({ activeSemester: 2 })
        expect(wrapper.text()).toContain('MA')
        expect(wrapper.text()).not.toContain('ALT')
        await wrapper.setProps({ activeSemester: 3 })
        expect(wrapper.text()).toContain('MA')
        expect(wrapper.text()).toContain('ALT')
    })

    it.each([1, 2, 3])('filters modern behaviour and warnings for each student in semester %s', async (activeSemester) => {
        const wrapper = mountPerformance({ props: {
            student, activeSemester, semesterTwoStartDate: '2026-02-09', usesEntryAreas: true,
            course: { ...course, teaching_entry_area: { entry_definitions: [
                { short_name: 'D', category: 'Verhalten', has_properties: false },
                { short_name: 'FW', category: 'Weitere', has_properties: false },
            ] } },
            entries: [entry({ type: 'D', date: '2026-02-08' }), entry({ id: 2, type: 'FW', date: '2026-02-09' }), entry({ id: 3, user_id: 13, type: 'D', date: '2026-02-09' })],
        } })
        expect(wrapper.text().includes('D')).toBe(activeSemester !== 2)
        expect(wrapper.text().includes('FW')).toBe(activeSemester !== 1)
        await wrapper.setProps({ student: { ...student, id: 13, user_id: 13 } })
        expect(wrapper.text().includes('D')).toBe(activeSemester !== 1)
        expect(wrapper.text()).not.toContain('FW')
    })

    it('retains undated records and all dates when the semester boundary is missing or invalid', () => {
        for (const semester of [1, 2, 3]) {
            expect(isInTeachingSemester(null, semester, '2026-02-09')).toBe(true)
            expect(isInTeachingSemester('invalid', semester, '2026-02-09')).toBe(true)
            expect(isInTeachingSemester('2026-02-09', semester, null)).toBe(true)
            expect(isInTeachingSemester('2026-02-09', semester, 'invalid')).toBe(true)
        }
        expect(isInTeachingSemester('2026-02-08', 1, '2026-02-09')).toBe(true)
        expect(isInTeachingSemester('2026-02-09', 1, '2026-02-09')).toBe(false)
        expect(isInTeachingSemester('2026-02-09', 2, '2026-02-09')).toBe(true)
    })

    it('rejects stale course data and another student with an overlapping import ID', async () => {
        const wrapper = mountPerformance({ props: { student, course, entries: [entry({ teaching_course_id: 99 }), entry({ user_id: 13 })] } })
        expect(wrapper.text()).toContain('Keine Leistungen im Zeitraum.')
        await wrapper.setProps({ student: { ...student, user_id: null, import116_id: 12 }, entries: [entry()] })
        expect(wrapper.text()).not.toContain('MA')
    })

    it('distinguishes empty data from loading and failure', async () => {
        const wrapper = mountPerformance({ props: { student, course, loading: true } })
        expect(wrapper.find('[role="status"]').exists()).toBe(true)
        expect(wrapper.text()).not.toContain('Keine Leistungen')
        await wrapper.setProps({ loading: false, loadFailed: true })
        expect(wrapper.find('[role="alert"]').text()).toContain('nicht vollständig')
        expect(wrapper.text()).not.toContain('Keine Leistungen')
    })

    it('uses valid legacy default grades and preserves a recorded zero', () => {
        const wrapper = mountPerformance({ props: {
            student, course,
            schema: { works: [{ short_name: 'MA', default_grade: '2', grades: [{ grade: '2' }] }] },
            entries: [entry(), entry({ id: 2, effective_grade: '', grade: 0 })],
        } })
        expect(wrapper.findAll('v-chip').map((item) => item.text())).toEqual(['MA: 2', 'MA: 0'])
    })
})
