import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('Admin Teaching CourseStore', () => {
    const adminStoreMock = { is_loading: 0 }
    const notifyMock = vi.fn()
    const notificationStoreMock = { notify: notifyMock }
    const axiosMock = {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    }

    beforeEach(() => {
        setActivePinia(createPinia())

        adminStoreMock.is_loading = 0
        notifyMock.mockReset()
        axiosMock.get.mockReset()
        axiosMock.post.mockReset()
        axiosMock.put.mockReset()
        axiosMock.delete.mockReset()

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useNotificationStore).mockReturnValue(notificationStoreMock as never)

        globalThis.axios = axiosMock as never
    })

    it('refreshCourseById reloads courses and updates selected course with normalized student collections', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [
                    {
                        id: 5,
                        title: 'Biologie',
                        students: [{ id: 10, first_name: 'Anna' }],
                        students_deleted: [],
                    },
                ],
                classes: ['1A'],
            },
        })

        const store = useCourseStore()
        const course = await store.refreshCourseById(5)

        expect(course?.id).toBe(5)
        expect(store.selected_course?.id).toBe(5)
        expect(store.selected_course_id).toBe(5)
        expect(store.selected_course?.students).toEqual([10])
        expect(store.selected_course?.students_info).toEqual([{ id: 10, first_name: 'Anna' }])
    })

    it('normalizes student collections for courses loaded before URL selection', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [
                    {
                        id: 16,
                        title: 'DGB1',
                        students: [
                            { id: 10, first_name: 'Anna' },
                            { id: 11, first_name: 'Paul' },
                        ],
                        students_deleted: [{ id: 12, first_name: 'Mara' }],
                    },
                ],
                classes: ['3B'],
            },
        })

        const store = useCourseStore()

        await expect(store.index()).resolves.toBe(true)
        expect(store.courses[0].students).toEqual([10, 11])
        expect(store.courses[0].students_info).toEqual([
            { id: 10, first_name: 'Anna' },
            { id: 11, first_name: 'Paul' },
        ])
        expect(store.courses[0].students_deleted).toEqual([12])
        expect(store.courses[0].students_deleted_info).toEqual([{ id: 12, first_name: 'Mara' }])
    })

    it('stores the authenticated users entry areas from the course index response', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [],
                classes: ['1A'],
                entry_areas: [
                    { id: 11, name: 'DGB' },
                    { id: 4, name: 'INF' },
                ],
                uses_entry_areas_for_grading_schema: true,
            },
        })

        const store = useCourseStore()

        await expect(store.index()).resolves.toBe(true)
        expect(store.entry_areas).toEqual([
            { id: 11, name: 'DGB' },
            { id: 4, name: 'INF' },
        ])
        expect(store.uses_entry_areas_for_grading_schema).toBe(true)
    })

    it('stores a course with a Bereich and without a legacy schema in new schoolyears', async () => {
        axiosMock.post.mockResolvedValue({ data: { id: 5 } })
        const store = useCourseStore()
        store.uses_entry_areas_for_grading_schema = true
        const payload = {
            title: 'Mathematik',
            teaching_schema_id: null,
            teaching_entry_area_id: 11,
            students: [],
            students_deleted: [],
        }

        await expect(store.store(payload)).resolves.toEqual({ id: 5 })
        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/teaching/courses', payload)
        expect(notifyMock).not.toHaveBeenCalled()
    })

    it('rejects a legacy schema without a Bereich in new schoolyears', async () => {
        const store = useCourseStore()
        store.uses_entry_areas_for_grading_schema = true

        await expect(store.store({ teaching_schema_id: 'legacy', teaching_entry_area_id: null })).resolves.toBe(false)
        expect(axiosMock.post).not.toHaveBeenCalled()
        expect(notifyMock).toHaveBeenCalledWith(expect.objectContaining({ status: 422 }))
    })

    it('refreshCourseById returns null when course does not exist after reload', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [{ id: 7, title: 'Deutsch', students: [], students_deleted: [] }],
                classes: ['1A'],
            },
        })

        const store = useCourseStore()
        const course = await store.refreshCourseById(999)

        expect(course).toBeNull()
        expect(store.selected_course).toBeNull()
        expect(store.selected_course_id).toBeNull()
    })

    it('reuses the same in-flight course index request for concurrent callers', async () => {
        let resolveRequest: ((value: unknown) => void) | null = null
        axiosMock.get.mockReturnValueOnce(
            new Promise((resolve) => {
                resolveRequest = resolve
            })
        )

        const store = useCourseStore()
        const firstRequest = store.index()
        const secondRequest = store.index()

        expect(axiosMock.get).toHaveBeenCalledTimes(1)
        expect(store.courses_request_promise).not.toBeNull()

        resolveRequest?.({
            data: {
                data: [{ id: 9, title: 'Physik', students: [], students_deleted: [] }],
                classes: ['2A'],
            },
        })

        await expect(firstRequest).resolves.toBe(true)
        await expect(secondRequest).resolves.toBe(true)
        expect(store.courses).toEqual([{
            id: 9,
            title: 'Physik',
            students: [],
            students_deleted: [],
            students_deleted_info: [],
            students_info: [],
        }])
        expect(store.classes).toEqual(['2A'])
        expect(store.courses_request_promise).toBeNull()
    })
})
