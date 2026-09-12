import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useCourseWorkStore } from '@/stores/admin/teaching/CourseWorkStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useCourseStudentEntryStore } from '@/stores/admin/teaching/CourseStudentEntryStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('CourseWorkStore', () => {
    const adminStoreMock = { is_loading: 0 }
    const notifyMock = vi.fn()
    const notificationStoreMock = { notify: notifyMock }
    const axiosMock = {
        delete: vi.fn(),
        get: vi.fn(),
        put: vi.fn(),
    }

    beforeEach(() => {
        setActivePinia(createPinia())

        adminStoreMock.is_loading = 0
        notifyMock.mockReset()
        axiosMock.get.mockReset()
        axiosMock.delete.mockReset()
        axiosMock.put.mockReset()

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useNotificationStore).mockReturnValue(notificationStoreMock as never)
        globalThis.axios = axiosMock as never
    })

    it.each([true, false])('updates all student caches only after a successful work deletion: %s', async (success) => {
        const store = useCourseWorkStore()
        const entries = useCourseStudentEntryStore()
        store.courseWorks = [{ id: 10 }, { id: 11 }] as never
        store.selected_courseWork = { id: 10 } as never
        const rows = [
            { id: 1, user_id: 20, source: 'course_work', teaching_course_work_id: 10 },
            { id: 2, user_id: 21, source: 'course_work', teaching_course_work_id: '10' },
            { id: 3, user_id: 20, source: 'manual', teaching_course_work_id: null },
            { id: 4, user_id: 21, source: 'course_work', teaching_course_work_id: 11 },
        ]
        entries.courseEntries = [...rows] as never
        entries.entries = [...rows] as never
        if (success) axiosMock.delete.mockResolvedValue({ status: 204 })
        else axiosMock.delete.mockRejectedValue(new Error('Failed'))
        expect(await store.destroy(10)).toBe(success)
        expect(entries.courseEntries).toEqual(success ? rows.slice(2) : rows)
        expect(entries.entries).toEqual(success ? rows.slice(2) : rows)
        expect(store.courseWorks).toEqual(success ? [{ id: 11 }] : [{ id: 10 }, { id: 11 }])
        expect(store.selected_courseWork).toEqual(success ? null : { id: 10 })
    })

    it('index loads works by course id', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [{ id: 10, title: 'Testarbeit' }],
            },
        })

        const store = useCourseWorkStore()
        const result = await store.index(55)

        expect(result).toBe(true)
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/teaching/course_works', {
            params: { course_id: 55 },
        })
        expect(store.courseWorks).toEqual([{ id: 10, title: 'Testarbeit' }])
    })

    it('update returns false if id is missing', async () => {
        const store = useCourseWorkStore()
        const result = await store.update({ title: 'No id' } as never)

        expect(result).toBe(false)
        expect(axiosMock.put).not.toHaveBeenCalled()
        expect(notifyMock).toHaveBeenCalledWith(expect.objectContaining({ status: 422 }))
    })
})
