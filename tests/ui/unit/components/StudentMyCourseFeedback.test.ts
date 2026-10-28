import { createTestingPinia } from '@pinia/testing'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h, inject, provide } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import MyCourse from '@/pages/homepage/student/overview/myCourse/MyCourse.vue'
import { useCourseStore } from '@/stores/student/CourseStore'
import { useStudentStore } from '@/stores/student/StudentStore'
import { percentageProgressGradient } from '@/helpers/gradeCalculation'

const activeWindowKey = Symbol('activeStudentTestWindow')
const toggleUpdateKey = Symbol('studentTestToggleUpdate')

const WindowStub = defineComponent({
    props: ['modelValue'],
    setup(props, { slots }) {
        provide(activeWindowKey, () => props.modelValue)

        return () => h('div', slots.default?.())
    },
})

const WindowItemStub = defineComponent({
    props: ['value'],
    setup(props, { slots }) {
        const activeWindow = inject<() => unknown>(activeWindowKey, () => undefined)

        return () => activeWindow() === props.value
            ? h('section', { 'data-active-window': props.value }, slots.default?.())
            : null
    },
})

const ButtonToggleStub = defineComponent({
    props: ['modelValue'],
    emits: ['update:modelValue'],
    setup(_props, { slots, emit }) {
        provide(toggleUpdateKey, (value: unknown) => emit('update:modelValue', value))

        return () => h('div', slots.default?.())
    },
})

const ButtonStub = defineComponent({
    props: ['value'],
    emits: ['click'],
    setup(props, { slots, emit }) {
        const updateToggle = inject<((value: unknown) => void) | null>(toggleUpdateKey, null)

        function handleClick(event: MouseEvent) {
            if (updateToggle && props.value !== undefined) {
                updateToggle(props.value)
            }

            emit('click', event)
        }

        return () => h('button', {
            type: 'button',
            'data-toggle-value': props.value,
            onClick: handleClick,
        }, slots.default?.())
    },
})

const wrappers: ReturnType<typeof mount>[] = []

async function mountFeedback({ showBehaviour = true, includeAssessments = true, courseOverrides = {}, entryOverrides = [], pointsProgress = null, url = '/student/course/18' } = {}) {
    const assessmentEntries = includeAssessments ? [
        { id: 101, date: '2026-10-12', category: 'Benotung', type: 'MA', title: 'Mitarbeit Herbst', grade: '1' },
        { id: 102, date: '2027-03-15', category: 'Benotung', type: 'MA', title: 'Mitarbeit Frühling', grade: '2' },
    ] : []
    const pinia = createTestingPinia({
        createSpy: vi.fn,
        initialState: {
            StudentStudentStore: {
                user: { id: 42, first_name: 'Anna', last_name: 'Beispiel', schoolclass: '3B' },
                viewer_type: 'student',
            },
            StudentCourseStore: {
                pointsProgress,
                course: {
                    id: 18,
                    title: 'Digitale Grundbildung',
                    show_behaviour: showBehaviour,
                    sem_2_start: '2027-02-01',
                    teaching_schema: {
                        works: [{ short_name: 'MA', name: 'Mitarbeit' }],
                        grading: { semester_count: 2, categories: [{ name: 'Mitarbeit', works: ['MA'] }] },
                    },
                    behaviour_entries: [
                        { id: 201, date: '2026-10-13', type: 'E', description: 'Respektvoll im Herbst' },
                    ],
                    ...courseOverrides,
                },
                entries: [
                    ...entryOverrides,
                    ...assessmentEntries,
                    { id: 201, date: '2027-03-16', category: 'Verhalten', type: 'E', comment: 'Hilfsbereit im Frühling' },
                    { id: 301, date: '2027-03-17', category: 'Weitere', type: 'FW', comment: 'Zusätzlicher Hinweis' },
                ],
            },
        },
    })
    vi.mocked(useStudentStore(pinia).getCurrentUser).mockResolvedValue(true)
    vi.mocked(useCourseStore(pinia).getCourse).mockResolvedValue(true)
    vi.mocked(useCourseStore(pinia).getCourseEntries).mockResolvedValue(true)

    const router = createRouter({
        history: createMemoryHistory(),
        routes: [{ path: '/student/course/:id', component: MyCourse }],
    })
    await router.push(url)
    await router.isReady()

    const wrapper = mount(MyCourse, {
        global: {
            plugins: [pinia, router],
            stubs: {
                StudentNavigationDrawer: true,
                ParentAccessPanel: true,
                'v-tabs-window': WindowStub,
                'v-tabs-window-item': WindowItemStub,
                'v-btn-toggle': ButtonToggleStub,
                'v-btn': ButtonStub,
                'v-divider': { template: '<hr />' },
            },
        },
    })
    wrappers.push(wrapper)
    await flushPromises()

    return wrapper
}

async function openFeedback(wrapper: ReturnType<typeof mount>) {
    const feedbackButton = wrapper.findAll('nav button').find((button) => button.text().includes('Leistungen'))
    expect(feedbackButton).toBeDefined()
    await feedbackButton!.trigger('click')
    await flushPromises()
}

afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
})

describe('student combined performance and behaviour page', () => {
    it.each([undefined, true, false])('applies stars visibility %s to the header, summary and details independently of points', async (visible) => {
        const wrapper = await mountFeedback({ courseOverrides: { show_stars: visible, show_points_progress: true,
            stars: [{ id: 'star-1', value: 1, comment: 'Besonderer Einsatz', date: '2026-10-01' }] },
            pointsProgress: { course_id: 18, parts: [{ id: 7, name: 'Basis', semester: 1, status: 'complete', sum: 5, maximum: 10 }] } })
        expect(wrapper.find('.stars-inline').exists()).toBe(visible !== false)
        expect(wrapper.find('.student-summary-card--stars').exists()).toBe(visible !== false)
        expect(wrapper.find('.stars-beautiful-list').exists()).toBe(visible !== false)
        expect(wrapper.text().includes('Besonderer Einsatz')).toBe(visible !== false)
        expect(wrapper.find('.student-points-progress').exists()).toBe(false)
        await openFeedback(wrapper)
        expect(wrapper.get('.student-points-progress').text()).toContain('50 %')
    })

    it.each([1, 2])('uses the assigned area semester count %s despite an older two-semester schema', async (count) => {
        const wrapper = await mountFeedback({ url: '/student/course/18?panel=entries', courseOverrides: { semester_count: count } })
        expect(wrapper.vm.hasTwoSemesters).toBe(count === 2)
        expect(wrapper.find('[aria-label="Semester für Leistungen und Verhalten"]').exists()).toBe(count === 2)
        expect(wrapper.vm.selectedSemester).toBe(count === 1 ? 1 : 3)
        expect(wrapper.vm.selectedSemesterDates).toBe(count === 1 ? 1 : 3)
        expect(wrapper.get('#student-performance').text()).toContain('Mitarbeit Herbst')
        expect(wrapper.get('#student-performance').text()).toContain('Mitarbeit Frühling')
    })
    it('completely omits points progress when disabled and retains the performance list', async () => {
        const wrapper = await mountFeedback({ url: '/student/course/18?panel=entries', courseOverrides: { show_points_progress: false } })
        expect(wrapper.find('.student-points-progress').exists()).toBe(false)
        expect(wrapper.get('#student-performance').text()).toContain('Mitarbeit Herbst')
    })

    it('shows the canonical points basis in its selected semester, including a real zero', async () => {
        const options = { url: '/student/course/18?panel=entries', courseOverrides: { show_points_progress: true },
            pointsProgress: { course_id: 18, semester_count: 2, parts: [
                { id: 7, name: 'Basis', semester: 1, status: 'complete', sum: 7.75, maximum: 10 },
                { id: 7, name: 'Basis', semester: 2, status: 'complete', sum: 0, maximum: 15 },
            ] } }
        const wrapper = await mountFeedback(options)
        expect(wrapper.findAll('.student-points-progress-part')).toHaveLength(2)
        expect(wrapper.get('.student-points-progress').text()).toContain('Erreicht: 7,75 Punkte · Maximal möglich: 10 Punkte')
        expect(wrapper.get('.student-points-progress').text()).toContain('77,5 %')
        const secondSemester = wrapper.findAll('button').find(button => button.attributes('data-toggle-value') === '2')!
        await secondSemester.trigger('click')
        await flushPromises()
        expect(wrapper.findAll('.student-points-progress-part')).toHaveLength(1)
        expect(wrapper.get('.student-points-progress').text()).not.toContain('7,75')
        expect(wrapper.get('.student-points-progress').text()).toContain('Erreicht: 0 Punkte · Maximal möglich: 15 Punkte')
        expect(wrapper.findAll('.student-points-progress v-progress-linear')).toHaveLength(1)
        const reload = await mountFeedback(options)
        expect(reload.find('.student-points-progress').exists()).toBe(true)
    })

    it('shows corrected points and the bounded change in the student progress', async () => {
        const wrapper = await mountFeedback({ url: '/student/course/18?panel=entries', courseOverrides: { show_points_progress: true },
            pointsProgress: { course_id: 18, parts: [{ id: 7, name: 'Basis', semester: 1, status: 'complete', sum: 20, maximum: 20,
                adjustment: { source: 'Mitarbeit', original: 19, change: 1, requested_change: 5 } }] } })
        expect(wrapper.get('.student-points-progress').text()).toContain('Erreicht: 20 Punkte')
        expect(wrapper.get('.student-points-progress').text()).toContain('Vorher: 19 Punkte · Mitarbeit: +1 Punkte (berechnet +5, begrenzt)')
        expect(wrapper.get('.student-points-progress-percent').text()).toBe('100 %')
    })

    it.each([
        [5.65, 6.65, 1, 'positive', 56.5, 10],
        [3.15, 2.65, -0.5, 'negative', 26.5, 5],
    ])('marks the actual change from %s to %s on the progress bar', async (original, sum, change, direction, left, width) => {
        const wrapper = await mountFeedback({ url: '/student/course/18?panel=entries', courseOverrides: { show_points_progress: true },
            pointsProgress: { course_id: 18, parts: [{ id: 7, name: 'Basis', semester: 1, status: 'complete', sum, maximum: 10,
                adjustment: { source: 'Mitarbeit', original, change, requested_change: change } }] } })
        const segment = wrapper.get('.student-points-adjustment')
        expect(segment.classes()).toContain(`student-points-adjustment--${direction}`)
        expect(parseFloat((segment.element as HTMLElement).style.left)).toBeCloseTo(left)
        expect(parseFloat((segment.element as HTMLElement).style.width)).toBeCloseTo(width)
        expect(parseFloat((wrapper.get('.student-points-adjustment-start').element as HTMLElement).style.left)).toBeCloseTo(original / 10 * 100)
        expect(wrapper.get('.student-points-progress v-progress-linear').attributes('aria-valuetext')).toContain(`vorher ${String(original).replace('.', ',')} Punkte`)
    })

    it('does not mark a correction when the actual change is zero', async () => {
        const wrapper = await mountFeedback({ url: '/student/course/18?panel=entries', courseOverrides: { show_points_progress: true },
            pointsProgress: { course_id: 18, parts: [{ id: 7, name: 'Basis', semester: 1, status: 'complete', sum: 10, maximum: 10,
                adjustment: { source: 'Mitarbeit', original: 10, change: 0, requested_change: 5 } }] } })
        expect(wrapper.find('.student-points-adjustment').exists()).toBe(false)
        expect(wrapper.find('.student-points-adjustment-start').exists()).toBe(false)
    })

    it('shows points exclusively in the performance tab across navigation and reloads', async () => {
        const options = { courseOverrides: { show_points_progress: true },
            pointsProgress: { course_id: 18, parts: [{ id: 7, name: 'Basis', semester: 1, status: 'complete', sum: 7.75, maximum: 10 }] } }
        const wrapper = await mountFeedback(options)
        expect(wrapper.vm.currentTab).toBe('overview')
        expect(wrapper.find('.student-points-progress').exists()).toBe(false)
        await openFeedback(wrapper)
        expect(wrapper.get('.student-points-progress').text()).toContain('77,5 %')
        expect(wrapper.get('.student-points-progress v-progress-linear').attributes('aria-valuetext')).toBe('7,75 von 10 Punkten, 77,5 Prozent')
        for (const label of ['Übersicht', 'Weitere', 'Termine']) {
            const tab = wrapper.findAll('nav button').find((button) => button.text().includes(label))!
            await tab.trigger('click')
            await flushPromises()
            expect(wrapper.find('.student-points-progress').exists()).toBe(false)
        }
        const reload = await mountFeedback({ ...options, url: '/student/course/18?panel=entries' })
        expect(reload.get('.student-points-progress').text()).toContain('77,5 %')
    })

    it('uses the admin gradient with points and percentage without direct grade assignments', async () => {
        const wrapper = await mountFeedback({ url: '/student/course/18?panel=entries', courseOverrides: { show_points_progress: true },
            pointsProgress: { course_id: 18, parts: [{ id: 7, name: 'Basis', semester: 1, status: 'complete', sum: 20, maximum: 40 }] } })
        expect(wrapper.get('.student-points-progress-percent').text()).toBe('50 %')
        expect(wrapper.get('.student-points-progress-fill').attributes('style')).toContain(percentageProgressGradient)
        expect(wrapper.get('.student-points-progress-values').text()).toContain('Erreicht: 20 Punkte · Maximal möglich: 40 Punkte')
        expect(wrapper.find('.student-points-grade-scale').exists()).toBe(false)
        expect(wrapper.find('.student-points-grade-boundary').exists()).toBe(false)
        expect(wrapper.get('.student-points-progress').text()).not.toMatch(/Sehr gut|Nicht genügend|Befriedigend|Notengrenzen/)
        expect(wrapper.find('.student-points-progress [title]').exists()).toBe(false)
        expect(wrapper.find('.calculated-grades-section').exists()).toBe(false)
    })

    it('shows independent points bases with their own sums and maxima instead of combining them', async () => {
        const wrapper = await mountFeedback({ url: '/student/course/18?panel=entries', courseOverrides: { show_points_progress: true },
            pointsProgress: { course_id: 18, parts: [
                { id: 7, name: 'Grundlagen', semester: 1, status: 'complete', sum: 7.75, maximum: 10 },
                { id: 8, name: 'Praxis', semester: 1, status: 'complete', sum: 7, maximum: 35 },
            ] } })
        const areas = wrapper.findAll('.student-points-progress-part')
        expect(areas).toHaveLength(2)
        expect(areas[0].text()).toContain('Grundlagen')
        expect(areas[0].text()).toContain('Erreicht: 7,75 Punkte · Maximal möglich: 10 Punkte')
        expect(areas[1].text()).toContain('Praxis')
        expect(areas[1].text()).toContain('Erreicht: 7 Punkte · Maximal möglich: 35 Punkte')
        expect(wrapper.findAll('.student-points-progress v-progress-linear')).toHaveLength(2)
    })

    it('shows only unlabelled ticks at the actual boundaries without exposing a grade legend or tooltips', async () => {
        const wrapper = await mountFeedback({ url: '/student/course/18?panel=entries', courseOverrides: { show_points_progress: true },
            pointsProgress: { course_id: 18, parts: [{ id: 7, name: 'Basis', semester: 1, status: 'complete', sum: 20, maximum: 40,
                grade_boundaries: [20, 45, 75, 95],
            }] } })
        const ticks = wrapper.findAll('.student-points-grade-boundary')
        expect(ticks.map((tick) => tick.attributes('style'))).toEqual(['left: 20%;', 'left: 45%;', 'left: 75%;', 'left: 95%;'])
        expect(ticks.every((tick) => tick.text() === '' && !tick.attributes('title'))).toBe(true)
        expect(wrapper.get('.student-points-progress-percent').text()).toBe('50 %')
        expect(wrapper.find('.student-points-grade-scale').exists()).toBe(false)
        expect(wrapper.get('.student-points-progress').text()).not.toMatch(/Sehr gut|Nicht genügend|Befriedigend|Notengrenzen/)
    })

    it.each([
        { course_id: 99, parts: [{ id: 7, name: 'Fremder Kurs', semester: 1, status: 'complete', sum: 5, maximum: 5 }] },
        { course_id: 18, parts: [{ id: 7, name: 'Basis', semester: 1, status: 'incomplete', sum: null, maximum: null }] },
        { course_id: 18, parts: [{ id: 7, name: 'Basis', semester: 1, status: 'complete', sum: 0, maximum: 0 }] },
    ])('never invents a progress bar for foreign or incomplete points data %s', async (progress) => {
        const wrapper = await mountFeedback({ url: '/student/course/18?panel=entries', courseOverrides: { show_points_progress: true }, pointsProgress: progress })
        expect(wrapper.find('.student-points-progress v-progress-linear').exists()).toBe(false)
        expect(wrapper.get('.student-points-progress').text()).not.toContain('Fremder Kurs')
        expect(wrapper.get('.student-points-progress').text()).not.toContain('0/0')
    })

    it('keeps the type beside the title, hides PDF-backed inline feedback and shows actual points without changing data', async () => {
        const comment = 'Ausführliche persönliche Bewertung nur im PDF.'
        const entry = { id: 109, date: '2026-10-02', category: 'Benotung', type: 'A5', title: 'Dateimanagement',
            grade: '3.40', properties_mode: 'points', maximum_points: 12.5, comment,
            work: { id: 8, evaluation_pdf: { name: 'personal.pdf', sha256: 'b'.repeat(64) } } }
        const wrapper = await mountFeedback({ url: '/student/course/18?panel=entries', entryOverrides: [entry] })
        const row = wrapper.findAll('.entry-row').find(item => item.text().includes('Dateimanagement'))!
        expect(row.get('.entry-title-row').text()).toContain('DateimanagementA5 - A5')
        expect(row.find('.entry-comment').exists()).toBe(false)
        expect(row.text()).not.toContain(comment)
        expect(row.get('.entry-result').text()).toContain('Erreicht3,40 PunkteMaximal möglich: 12,5 Punkte')
        expect(row.get('[href*="/works/8/evaluations/"]').text()).toBe('Auswertung (PDF)')
        expect(entry.comment).toBe(comment)
        expect(entry.grade).toBe('3.40')
    })

    it.each([
        ['0', null, '0,00 Punkte', 'nicht hinterlegt'],
        ['', 20, 'offen', '20 Punkte'],
        ['NA', 20, 'NA', '20 Punkte'],
        ['4,35', 5, '4,35 Punkte', '5 Punkte'],
    ])('renders saved points %s and maximum %s honestly, preserving feedback without a PDF', async (grade, maximum, value, maximumText) => {
        const wrapper = await mountFeedback({ url: '/student/course/18?panel=entries', entryOverrides: [
            { id: 109, date: '2026-10-02', category: 'Benotung', type: 'A5', title: 'Auftrag', grade,
                properties_mode: 'points', maximum_points: maximum, comment: 'Noch ohne PDF.', work: { id: 8 } },
        ] })
        const row = wrapper.findAll('.entry-row').find(item => item.text().includes('Auftrag'))!
        expect(row.get('.entry-result-value').text()).toBe(value)
        expect(row.get('.entry-result-maximum').text()).toBe(`Maximal möglich: ${maximumText}`)
        expect(row.get('.entry-comment').text()).toBe('Noch ohne PDF.')
        expect(row.find('[href*="/evaluations/"]').exists()).toBe(false)
    })

    it('does not relabel ordinary grades as points or infer a maximum from the type name', async () => {
        const wrapper = await mountFeedback({ url: '/student/course/18?panel=entries', entryOverrides: [
            { id: 109, date: '2026-10-02', category: 'Benotung', type: 'A5', title: 'Schularbeit', grade: '2',
                properties_mode: 'fixed', maximum_points: null, work: { id: 8 } },
        ] })
        const row = wrapper.findAll('.entry-row').find(item => item.text().includes('Schularbeit'))!
        expect(row.get('.entry-result').text()).toBe('Bewertung2')
        expect(row.find('.entry-result-maximum').exists()).toBe(false)
    })

    it('renders the personal evaluation link on Leistungen and preserves it after a reload', async () => {
        const sha256 = 'b'.repeat(64)
        const options = {
            url: '/student/course/18?panel=entries',
            entryOverrides: [{ id: 109, date: '2026-10-02', category: 'Benotung', type: 'MA', title: 'E-Mails', grade: '4.5',
                work: { id: 8, evaluation_pdf: { name: 'personal.pdf', sha256 } } }],
        }
        const wrapper = await mountFeedback(options)
        const link = wrapper.get(`[href="/api/homepage/student/courses/18/works/8/evaluations/${sha256}"]`)
        expect(link.text()).toBe('Auswertung (PDF)')
        expect(link.attributes('target')).toBe('_blank')
        expect(link.attributes('title')).toBe('personal.pdf')
        expect(wrapper.findAll('[href*="/evaluations/"]')).toHaveLength(1)
        const reloaded = await mountFeedback(options)
        expect(reloaded.get(`[href="${link.attributes('href')}"]`).text()).toBe('Auswertung (PDF)')
    })
    it.each([
        ['overview', 'Übersicht'],
        ['entries', 'Leistungen'],
        ['additional', 'Weitere'],
        ['dates', 'Termine'],
    ])('preserves the %s menu selection in the URL and restores it after remounting', async (panel, label) => {
        const wrapper = await mountFeedback({ url: '/student/course/18?school=CDGym' })
        const button = wrapper.findAll('nav button').find((item) => item.text().includes(label))
        await button!.trigger('click')
        await flushPromises()

        expect(wrapper.vm.$route.query).toEqual({ school: 'CDGym', panel })
        expect(wrapper.get(`[data-active-window="${panel}"]`).exists()).toBe(true)

        const reloaded = await mountFeedback({ url: wrapper.vm.$route.fullPath })
        expect(reloaded.get(`[data-active-window="${panel}"]`).exists()).toBe(true)
    })

    it.each(['unknown', '', 'overview&panel=dates'])('falls back to overview for an invalid panel %s', async (panel) => {
        const wrapper = await mountFeedback({ url: `/student/course/18?panel=${panel}` })

        expect(wrapper.get('[data-active-window="overview"]').exists()).toBe(true)
    })

    it('opens old behaviour links on the combined page and reacts to later URL changes', async () => {
        const wrapper = await mountFeedback({ url: '/student/course/18?panel=behaviour' })
        expect(wrapper.get('[data-active-window="entries"]').exists()).toBe(true)

        await wrapper.vm.$router.push({ query: { panel: 'additional' } })
        await flushPromises()

        expect(wrapper.get('[data-active-window="additional"]').text()).toContain('Zusätzlicher Hinweis')
    })

    it('shows assessments and both behaviour sources together after opening one page', async () => {
        const wrapper = await mountFeedback()
        expect(wrapper.find('[data-testid="student-performance-entries"]').exists()).toBe(false)

        await openFeedback(wrapper)

        const performances = wrapper.get('[data-testid="student-performance-entries"]')
        const behaviour = wrapper.get('[data-testid="student-behaviour-entries"]')
        expect(performances.text()).toContain('Mitarbeit Herbst')
        expect(performances.text()).toContain('Mitarbeit Frühling')
        expect(performances.text()).not.toContain('Hilfsbereit im Frühling')
        expect(behaviour.text()).toContain('Respektvoll im Herbst')
        expect(behaviour.text()).toContain('Hilfsbereit im Frühling')
        expect(behaviour.text()).not.toContain('Mitarbeit Herbst')
        expect(wrapper.text()).not.toContain('Zusätzlicher Hinweis')
        expect(wrapper.findAll('nav button').some((button) => button.text().trim() === 'Verhalten')).toBe(false)
    })

    it('keeps behaviour visible when the course has no assessments', async () => {
        const wrapper = await mountFeedback({ includeAssessments: false })

        await openFeedback(wrapper)

        expect(wrapper.get('[data-testid="student-performance-entries"]').text()).toContain('Noch keine Leistungen')
        expect(wrapper.get('[data-testid="student-behaviour-entries"]').text()).toContain('Hilfsbereit im Frühling')
        expect(wrapper.get('[data-testid="student-behaviour-entries"]').text()).toContain('Respektvoll im Herbst')
    })

    it('hides all behaviour content when the teacher disables it and keeps assessments available', async () => {
        const wrapper = await mountFeedback({ showBehaviour: false })

        await openFeedback(wrapper)

        expect(wrapper.get('[data-testid="student-performance-entries"]').text()).toContain('Mitarbeit Herbst')
        expect(wrapper.find('[data-testid="student-behaviour-entries"]').exists()).toBe(false)
        expect(wrapper.text()).not.toContain('Respektvoll im Herbst')
        expect(wrapper.text()).not.toContain('Hilfsbereit im Frühling')
        expect(wrapper.findAll('nav button').some((button) => button.text().includes('Verhalten'))).toBe(false)
    })

    it('filters assessments and behaviour together when a semester is selected', async () => {
        const wrapper = await mountFeedback()
        await openFeedback(wrapper)

        await wrapper.get('button[data-toggle-value="1"]').trigger('click')

        const performances = wrapper.get('[data-testid="student-performance-entries"]')
        const behaviour = wrapper.get('[data-testid="student-behaviour-entries"]')
        expect(performances.text()).toContain('Mitarbeit Herbst')
        expect(performances.text()).not.toContain('Mitarbeit Frühling')
        expect(behaviour.text()).toContain('Respektvoll im Herbst')
        expect(behaviour.text()).not.toContain('Hilfsbereit im Frühling')

        await wrapper.get('button[data-toggle-value="2"]').trigger('click')

        expect(performances.text()).toContain('Mitarbeit Frühling')
        expect(performances.text()).not.toContain('Mitarbeit Herbst')
        expect(behaviour.text()).toContain('Hilfsbereit im Frühling')
        expect(behaviour.text()).not.toContain('Respektvoll im Herbst')
    })
})

describe('student assigned grade visibility', () => {
    const assignedGrades = {
        sem_1_grade: '2',
        sem_2_grade: '3',
        behaviour_1_grade: 'SZ',
        behaviour_2_grade: 'Z',
    }

    it('shows both assigned grade types by default for each configured semester', async () => {
        const wrapper = await mountFeedback({ courseOverrides: assignedGrades })
        const grades = wrapper.get('[data-testid="student-assigned-grades"]')

        expect(grades.findAll('.semester-grade-value').map((grade) => grade.text())).toEqual(['2', '3'])
        expect(grades.findAll('.behaviour-value').map((grade) => grade.text())).toEqual(['Verhaltensnote: SZ', 'Verhaltensnote: Z'])
    })

    it('hides assigned semester grades independently of assigned behaviour grades', async () => {
        const wrapper = await mountFeedback({
            courseOverrides: {
                ...assignedGrades,
                teacher_teaching_student_grade_columns: { show_semester_grade: false },
            },
        })
        const grades = wrapper.get('[data-testid="student-assigned-grades"]')

        expect(grades.find('.semester-grade-value').exists()).toBe(false)
        expect(grades.findAll('.behaviour-value').map((grade) => grade.text())).toEqual(['Verhaltensnote: SZ', 'Verhaltensnote: Z'])
    })

    it('hides assigned behaviour grades independently of assigned semester grades', async () => {
        const wrapper = await mountFeedback({
            courseOverrides: {
                ...assignedGrades,
                teacher_teaching_student_grade_columns: { show_behaviour_grade: false },
            },
        })
        const grades = wrapper.get('[data-testid="student-assigned-grades"]')

        expect(grades.findAll('.semester-grade-value').map((grade) => grade.text())).toEqual(['2', '3'])
        expect(grades.find('.behaviour-value').exists()).toBe(false)
    })

    it('does not resurrect the removed calculated grade display from historical settings', async () => {
        const wrapper = await mountFeedback({
            courseOverrides: {
                ...assignedGrades,
                teacher_teaching_student_grade_columns: {
                    show_semester_grade: false,
                    show_behaviour_grade: false,
                    show_sem1: true,
                    show_sem2: true,
                    show_year: true,
                },
            },
        })

        expect(wrapper.find('[data-testid="student-assigned-grades"]').exists()).toBe(false)
        expect(wrapper.find('.calculated-grades-section').exists()).toBe(false)
        expect(wrapper.text()).not.toContain('Berechnete Noten')
    })

    it('keeps modern and legacy behaviour feedback when only the assigned behaviour grade is hidden', async () => {
        const wrapper = await mountFeedback({
            courseOverrides: {
                ...assignedGrades,
                teacher_teaching_student_grade_columns: { show_behaviour_grade: false },
            },
        })
        expect(wrapper.find('.behaviour-value').exists()).toBe(false)

        await openFeedback(wrapper)

        const behaviour = wrapper.get('[data-testid="student-behaviour-entries"]')
        expect(behaviour.text()).toContain('Hilfsbereit im Frühling')
        expect(behaviour.text()).toContain('Respektvoll im Herbst')
    })

    it('uses the single-semester grade fields when the course schema has one semester', async () => {
        const wrapper = await mountFeedback({
            courseOverrides: {
                ...assignedGrades,
                sem_grade: '4',
                behaviour_grade: 'WZ',
                teaching_schema: {
                    works: [{ short_name: 'MA', name: 'Mitarbeit' }],
                    grading: { semester_count: 1, categories: [{ name: 'Mitarbeit', works: ['MA'] }] },
                },
            },
        })
        const grades = wrapper.get('[data-testid="student-assigned-grades"]')

        expect(grades.findAll('.semester-grade-value').map((grade) => grade.text())).toEqual(['4'])
        expect(grades.findAll('.behaviour-value').map((grade) => grade.text())).toEqual(['Verhaltensnote: WZ'])
        expect(grades.text()).not.toContain('2. Semester')
    })

    it('respects the course-wide behaviour setting even when its assigned grade column is enabled', async () => {
        const wrapper = await mountFeedback({
            showBehaviour: false,
            courseOverrides: {
                ...assignedGrades,
                teacher_teaching_student_grade_columns: { show_behaviour_grade: true },
            },
        })
        const grades = wrapper.get('[data-testid="student-assigned-grades"]')

        expect(grades.find('.behaviour-value').exists()).toBe(false)
        expect(grades.findAll('.semester-grade-value')).toHaveLength(2)
    })
})
