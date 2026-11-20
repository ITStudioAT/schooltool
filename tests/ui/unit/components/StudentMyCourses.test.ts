import { createTestingPinia } from '@pinia/testing'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import MyCourses from '@/pages/homepage/student/overview/myCourses/MyCourses.vue'
import { useCourseStore } from '@/stores/student/CourseStore'

const wrappers: ReturnType<typeof mount>[] = []

const twoCourses = [
    { id: 18, title: 'Digitale Grundbildung', teacher: 'Anna Müller', classes: ['3B'], students_count: 15 },
    { id: 22, title: 'Mathematik', teacher: 'Max Berger', classes: ['4A'], students_count: 20 },
]

async function mountCourses(courses = twoCourses, query: Record<string, string> = { school: 'CDGym' }) {
    const pinia = createTestingPinia({
        createSpy: vi.fn,
        initialState: { StudentCourseStore: { courses } },
    })
    vi.mocked(useCourseStore(pinia).getCourses).mockResolvedValue(true)
    const push = vi.fn()
    const replace = vi.fn()
    const wrapper = mount(MyCourses, {
        global: {
            plugins: [pinia],
            mocks: {
                $router: { push, replace },
                $route: { query },
            },
        },
    })
    wrappers.push(wrapper)
    await flushPromises()

    return { wrapper, push, replace }
}

afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
})

describe('student subject navigation', () => {
    it('opens a subject from a native button and preserves the existing query', async () => {
        const { wrapper, push } = await mountCourses()
        const courseButton = wrapper.get('button[aria-label="Digitale Grundbildung öffnen"]')

        expect(courseButton.attributes('type')).toBe('button')
        await courseButton.trigger('click')

        expect(push).toHaveBeenCalledWith({ path: '/student/course/18', query: { school: 'CDGym' } })
    })

    it('returns directly to the tasks tab of the named course when the learner belongs to it', async () => {
        const { replace } = await mountCourses(undefined, { panel: 'tasks', course: '22' })
        expect(replace).toHaveBeenCalledWith({ path: '/student/course/22', query: { panel: 'tasks' } })
    })

    it.each([{}, { course: '99' }])('falls back to a choice of enrolled courses for an unknown course %j', async query => {
        const { wrapper, push, replace } = await mountCourses(undefined, { panel: 'tasks', ...query })
        expect(replace).not.toHaveBeenCalled()
        expect(wrapper.get('h2').text()).toContain('Meine Aufgaben')
        await wrapper.get('button[aria-label="Aufgaben für Mathematik öffnen"]').trigger('click')
        expect(push).toHaveBeenCalledWith({ path: '/student/course/22', query: { panel: 'tasks' } })
    })

    it.each([
        [' digitale ', 'Digitale Grundbildung'],
        ['MÜLLER', 'Digitale Grundbildung'],
        ['4a', 'Mathematik'],
    ])('finds a subject by name, teacher or class using %s', async (query, expectedTitle) => {
        const { wrapper } = await mountCourses()

        await wrapper.get('input[aria-label="Fächer suchen"]').setValue(query)

        expect(wrapper.findAll('.subject-card')).toHaveLength(1)
        expect(wrapper.get('.subject-title').text()).toBe(expectedTitle)
    })

    it('lets students recover from a search with no results', async () => {
        const { wrapper } = await mountCourses()
        await wrapper.get('input[aria-label="Fächer suchen"]').setValue('Unbekannt')

        expect(wrapper.get('[role="status"]').text()).toContain('Kein passendes Fach gefunden')
        await wrapper.get('.subjects-reset').trigger('click')

        expect(wrapper.findAll('.subject-card')).toHaveLength(2)
        expect((wrapper.get('input').element as HTMLInputElement).value).toBe('')
    })

    it('shows the empty enrollment state without an unnecessary search field', async () => {
        const { wrapper } = await mountCourses([])

        expect(wrapper.text()).toContain('Hier ist Platz für deine Fächer')
        expect(wrapper.find('input[type="search"]').exists()).toBe(false)
    })
})
