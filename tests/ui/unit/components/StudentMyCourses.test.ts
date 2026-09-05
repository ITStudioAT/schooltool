import { createTestingPinia } from '@pinia/testing'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import MyCourses from '@/pages/homepage/student/overview/myCourses/MyCourses.vue'
import { useCourseStore } from '@/stores/student/CourseStore'

const wrappers: ReturnType<typeof mount>[] = []

async function mountCourses(courses = [
    { id: 18, title: 'Digitale Grundbildung', teacher: 'Anna Müller', classes: ['3B'], students_count: 15 },
    { id: 22, title: 'Mathematik', teacher: 'Max Berger', classes: ['4A'], students_count: 20 },
]) {
    const pinia = createTestingPinia({
        createSpy: vi.fn,
        initialState: { StudentCourseStore: { courses } },
    })
    vi.mocked(useCourseStore(pinia).getCourses).mockResolvedValue(true)
    const push = vi.fn()
    const wrapper = mount(MyCourses, {
        global: {
            plugins: [pinia],
            mocks: {
                $router: { push },
                $route: { query: { school: 'CDGym' } },
            },
        },
    })
    wrappers.push(wrapper)
    await flushPromises()

    return { wrapper, push }
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
