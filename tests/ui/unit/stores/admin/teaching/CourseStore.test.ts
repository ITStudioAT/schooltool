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
})
