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
