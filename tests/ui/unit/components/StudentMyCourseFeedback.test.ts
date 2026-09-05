import { createTestingPinia } from '@pinia/testing'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h, inject, provide } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import MyCourse from '@/pages/homepage/student/overview/myCourse/MyCourse.vue'
import { useCourseStore } from '@/stores/student/CourseStore'
import { useStudentStore } from '@/stores/student/StudentStore'

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

async function mountFeedback({ showBehaviour = true, includeAssessments = true, courseOverrides = {}, url = '/student/course/18' } = {}) {
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

    it('keeps configured calculated grades visible when both assigned grade types are hidden', async () => {
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
        expect(wrapper.get('.calculated-grades-section').text()).toContain('Berechnete Noten')
        expect(wrapper.findAll('.calculated-grades-section .grade-calculated')).toHaveLength(3)
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
