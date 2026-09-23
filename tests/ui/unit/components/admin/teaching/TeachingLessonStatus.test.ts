import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { flushPromises, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import Teaching from '@/pages/admin/teaching/Teaching.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseDateStore } from '@/stores/admin/teaching/CourseDateStore'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'

let wrapper: ReturnType<typeof mount> | undefined

function course(overrides: Record<string, unknown> = {}) {
    return {
        id: 18, user_id: 7, school_id: 3, schoolyear_id: 4,
        title: 'Mathematik', classes: ['2C'], details_loaded: true,
        course_dates: [{ id: 1, date: '2026-09-14', hours: [2] }],
        ...overrides,
    }
}

function mountTeaching() {
    const components = Object.fromEntries(Object.entries((Teaching as any).components).map(([name, component]) => [
        name,
        name === 'AdminPageHeader' ? component : { name, template: '<div />' },
    ]))
    wrapper = mount({ ...Teaching, components }, {
        global: {
            mocks: {
                $route: { path: '/admin/teaching', params: {}, query: {} },
                $router: { replace: vi.fn().mockResolvedValue(undefined) },
            },
            stubs: { 'v-sheet': { template: '<div><slot /></div>' }, 'v-container': { template: '<div><slot /></div>' }, 'v-dialog': true },
        },
    })
    return wrapper
}

function statusTexts() {
    return wrapper!.findAll('.teaching-context__item').map((item) => item.text())
}

describe('Teaching header lesson status', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
        vi.useFakeTimers()
        vi.setSystemTime(new Date(2026, 8, 14, 8, 30))
        useAdminStore().config = {
            user: { id: 7 }, roles: ['teacher'], selected_school: { id: 3, name: 'Testschule' },
            selected_schoolyear: { id: 4, from: '2026-09-01', until: '2027-08-31' },
        } as any
        useCourseStore().courses = [course()] as any
        useSchoolHourStore().school_hours = [
            { hour: 1, from: '08:00:00', until: '08:45:00' },
            { hour: 2, from: '09:00:00', until: '09:45:00' },
            { hour: 3, from: '10:00:00', until: '10:45:00' },
        ] as any
        vi.spyOn(useCourseStore(), 'index').mockResolvedValue(true)
        vi.spyOn(useSchoolHourStore(), 'index').mockResolvedValue(true)
    })

    afterEach(() => {
        wrapper?.unmount()
        wrapper = undefined
        vi.restoreAllMocks()
        vi.useRealTimers()
    })

    it('counts only the selected teacher in the current schoolyear and keeps the clock below the header', async () => {
        useCourseStore().courses = [
            course({ students_info: [{ id: 21 }, { id: 22, canceled_at: '2026-09-01' }] }),
            course({ id: 19, user_id: '7', students_info: [{ id: 21 }, { id: 23 }] }),
            course({ id: 20, user_id: 8, students_info: [{ id: 24 }] }),
            course({ id: 21, schoolyear_id: 99, students_info: [{ id: 25 }] }),
            course({ id: 22, school_id: 99, students_info: [{ id: 26 }] }),
        ] as any
        mountTeaching()
        await flushPromises()
        const header = wrapper!.find('header')
        expect(header.text()).toContain('2 Kurse')
        expect(header.text()).toContain('2 Schüler:innen')
        expect(header.text()).not.toContain('14.09.2026')
        expect(wrapper!.find('[aria-label="Unterricht und Schuljahr"]').text()).toContain('14.09.2026')

        ;(useAdminStore().config as any).user = { id: 8, last_name: 'Ausgewählt', first_name: 'Kim' }
        await flushPromises()
        expect(header.text()).toContain('1 Kurs')
        expect(header.text()).toContain('1 Schüler:in')
        expect(header.text()).toContain('Ausgewählt Kim')
    })

    it('shows confirmed zero counts and an explicit empty lesson state', async () => {
        useCourseStore().courses = []
        mountTeaching()
        expect(wrapper!.find('header').text()).toContain('Kennzahlen werden geladen')
        await flushPromises()
        expect(wrapper!.find('header').text()).toContain('0 Kurse')
        expect(wrapper!.find('header').text()).toContain('0 Schüler:innen')
        expect(wrapper!.text()).toContain('Kein weiterer Unterricht geplant')
    })

    it('does not replace failed initial loading with zero counts or an empty schedule', async () => {
        useCourseStore().courses = []
        vi.mocked(useCourseStore().index).mockResolvedValueOnce(false)
        mountTeaching()
        await flushPromises()
        expect(wrapper!.find('header').text()).toContain('Kennzahlen nicht verfügbar')
        expect(wrapper!.find('header').text()).not.toContain('0 Kurse')
        expect(wrapper!.text()).toContain('Unterrichtszeiten nicht verfügbar')
    })

    it('renders the next lesson beside the clock and updates into the active lesson', async () => {
        mountTeaching()
        await flushPromises()
        expect(statusTexts()[0]).toContain('14.09.2026')
        expect(statusTexts()[1]).toMatch(/^Nächster Unterricht in /)
        expect((wrapper!.vm as any).nextLessonStartAt).toEqual(new Date(2026, 8, 14, 9))
        expect(statusTexts()[1]).not.toMatch(/\d+\s*(s|Sekunden?)\b/)

        await vi.advanceTimersByTimeAsync(30 * 60 * 1000)
        expect(statusTexts()[1]).toMatch(/^Unterricht läuft – noch /)
        expect((wrapper!.vm as any).activeLessonEndAt).toEqual(new Date(2026, 8, 14, 9, 45))
        expect(statusTexts()[1]).not.toMatch(/\d+\s*(s|Sekunden?)\b/)
    })

    it('uses actual single hours so a gap is not considered an active lesson', async () => {
        vi.setSystemTime(new Date(2026, 8, 14, 9))
        useCourseStore().courses = [course({ course_dates: [{ id: 1, date: '2026-09-14', hours: [3, 1] }] })] as any
        mountTeaching()
        await flushPromises()
        expect((wrapper!.vm as any).activeLessonEndAt).toBeNull()
        expect((wrapper!.vm as any).nextLessonStartAt).toEqual(new Date(2026, 8, 14, 10))
        expect(statusTexts()[1]).toMatch(/^Nächster Unterricht in /)
    })

    it('ignores free lessons and a selected canceled date while counting down to another lesson', async () => {
        const canceled = { id: 2, date: '2026-09-14', hours: [2], status: ['entfällt'] }
        useCourseStore().courses = [course({ course_dates: [
            canceled,
            { id: 3, date: '2026-09-14', hours: [2], status: ['free'] },
            { id: 4, date: '2026-09-14', hours: [3], status: [] },
        ] })] as any
        mountTeaching()
        useCourseDateStore().selected_courseDate = canceled as any
        await flushPromises()
        expect((wrapper!.vm as any).nextLessonStartAt).toEqual(new Date(2026, 8, 14, 10))
        expect(statusTexts()[1]).toMatch(/^Nächster Unterricht in /)
    })

    it.each([
        { label: 'another teacher', field: 'user_id' },
        { label: 'another school', field: 'school_id' },
        { label: 'another schoolyear', field: 'schoolyear_id' },
    ])('ignores courses from $label', async ({ field }) => {
        useCourseStore().courses = [
            course({ id: 19, [field]: 99 }),
            course({ course_dates: [{ id: 1, date: '2026-09-14', hours: [3] }] }),
        ] as any
        mountTeaching()
        await flushPromises()
        expect((wrapper!.vm as any).nextLessonStartAt).toEqual(new Date(2026, 8, 14, 10))
    })

    it.each(['user', 'selected_school', 'selected_schoolyear'])('does not show a countdown without %s context', async (field) => {
        ;(useAdminStore().config as any)[field] = null
        mountTeaching()
        await flushPromises()
        expect(statusTexts()).toHaveLength(1)
    })

    it('shows no countdown for passed or unscheduled dates and reacts when valid dates arrive', async () => {
        useCourseStore().courses = [course({ course_dates: [
            { id: 1, date: '2026-09-13', hours: [2] },
            { id: 2, date: '2026-09-14', hours: [99] },
            { id: 3, date: '2026-09-14', hours: [] },
        ] })] as any
        mountTeaching()
        await flushPromises()
        expect(statusTexts()).toHaveLength(1)
        useCourseStore().courses = [course()] as any
        await nextTick()
        expect(statusTexts()[1]).toMatch(/^Nächster Unterricht in /)
    })

    it.each([
        { now: new Date(2026, 8, 12, 5, 45), expected: '2 Tagen 3 Std. 15 Min.' },
        { now: new Date(2026, 8, 13, 8, 59), expected: '1 Tag 1 Min.' },
        { now: new Date(2026, 8, 13, 9), expected: '24 Std. 0 Min.' },
        { now: new Date(2026, 8, 13, 10), expected: '23 Std. 0 Min.' },
        { now: new Date(2026, 8, 14, 8, 59), expected: '1 Min.' },
        { now: new Date(2026, 8, 14, 8, 59, 30), expected: 'weniger als 1 Minute' },
    ])('formats the visible countdown as $expected', async ({ now, expected }) => {
        vi.setSystemTime(now)
        mountTeaching()
        await flushPromises()
        expect(statusTexts()[1]).toBe(`Nächster Unterricht in ${expected}`)
    })

    it('updates the clock and countdown across local midnight', async () => {
        vi.setSystemTime(new Date(2026, 8, 13, 23, 59, 59))
        mountTeaching()
        await flushPromises()
        expect(statusTexts()[0]).toContain('13.09.2026')
        expect(statusTexts()[1]).toBe('Nächster Unterricht in 9 Std. 0 Min.')
        await vi.advanceTimersByTimeAsync(2000)
        expect(statusTexts()[0]).toContain('14.09.2026')
        expect(statusTexts()[1]).toBe('Nächster Unterricht in 8 Std. 59 Min.')
    })

    it('ignores dates outside the selected year and invalid school-hour times', async () => {
        ;(useAdminStore().config as any).selected_schoolyear.until = '2026-09-15'
        useSchoolHourStore().school_hours.push({ hour: 4, from: '25:00', until: '25:45' } as any)
        useCourseStore().courses = [course({ course_dates: [
            { id: 1, date: '2026-09-16', hours: [2] },
            { id: 2, date: '2026-09-14', hours: [4] },
        ] })] as any
        mountTeaching()
        await flushPromises()
        expect(statusTexts()).toHaveLength(1)
    })

    it('waits for both new-context data sources and never displays old-year lessons during refresh', async () => {
        mountTeaching()
        await flushPromises()
        expect(statusTexts()[1]).toBe('Nächster Unterricht in 30 Min.')
        let finishCourses = () => {}
        let finishHours = () => {}
        vi.mocked(useCourseStore().index).mockImplementationOnce(() => new Promise<boolean>((resolve) => {
            finishCourses = () => {
                useCourseStore().courses = [course({ schoolyear_id: 5 })] as any
                resolve(true)
            }
        }))
        vi.mocked(useSchoolHourStore().index).mockImplementationOnce(() => new Promise<boolean>((resolve) => {
            finishHours = () => {
                useSchoolHourStore().school_hours = [{ hour: 2, from: '10:00', until: '10:45' }] as any
                resolve(true)
            }
        }))
        ;(useAdminStore().config as any).selected_schoolyear.id = 5
        await flushPromises()
        expect(useCourseStore().index).toHaveBeenCalledOnce()
        expect(useSchoolHourStore().index).toHaveBeenCalledOnce()
        expect(statusTexts()).toHaveLength(1)
        finishCourses()
        await flushPromises()
        expect(statusTexts()).toHaveLength(1)
        finishHours()
        await flushPromises()
        expect(statusTexts()[1]).toBe('Nächster Unterricht in 1 Std. 30 Min.')
    })

    it('refreshes only the newest context after an older request finishes', async () => {
        mountTeaching()
        await flushPromises()
        let finishOldRequest = () => {}
        useCourseStore().courses_request_promise = new Promise<void>((resolve) => { finishOldRequest = resolve })
        ;(useAdminStore().config as any).selected_schoolyear.id = 5
        await nextTick()
        ;(useAdminStore().config as any).selected_schoolyear.id = 6
        await flushPromises()
        expect(useCourseStore().index).not.toHaveBeenCalled()
        expect(statusTexts()).toHaveLength(1)
        vi.mocked(useCourseStore().index).mockImplementationOnce(async () => {
            useCourseStore().courses = [course({ schoolyear_id: 6 })] as any
            return true
        })
        finishOldRequest()
        await flushPromises()
        expect(useCourseStore().index).toHaveBeenCalledOnce()
        expect(useSchoolHourStore().index).toHaveBeenCalledOnce()
        expect(statusTexts()[1]).toBe('Nächster Unterricht in 30 Min.')
    })

    it('keeps the countdown hidden when refreshing the context fails', async () => {
        mountTeaching()
        await flushPromises()
        vi.mocked(useCourseStore().index).mockResolvedValueOnce(false)
        ;(useAdminStore().config as any).selected_schoolyear.id = 5
        await flushPromises()
        expect(statusTexts()).toHaveLength(1)
        expect((wrapper!.vm as any).lessonContextLoading).toBe(true)
    })

    it.each(['school_id', 'schoolyear_id'])('refreshes a nonempty cache from a different %s on entry', async (field) => {
        useCourseStore().courses = [course({ [field]: 99 })] as any
        let finishCourses = () => {}
        vi.mocked(useCourseStore().index).mockImplementationOnce(() => new Promise<boolean>((resolve) => {
            finishCourses = () => {
                useCourseStore().courses = [course()] as any
                resolve(true)
            }
        }))
        mountTeaching()
        await flushPromises()
        expect(useCourseStore().index).toHaveBeenCalledOnce()
        expect(useSchoolHourStore().index).toHaveBeenCalledOnce()
        expect(statusTexts()).toHaveLength(1)
        finishCourses()
        await flushPromises()
        expect(statusTexts()[1]).toBe('Nächster Unterricht in 30 Min.')
        expect((wrapper!.vm as any).lessonContextLoading).toBe(false)
    })
})
