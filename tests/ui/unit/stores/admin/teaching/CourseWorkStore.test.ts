import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useCourseWorkStore } from '@/stores/admin/teaching/CourseWorkStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

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
        get: vi.fn(),
        put: vi.fn(),
    }

    beforeEach(() => {
        setActivePinia(createPinia())

        adminStoreMock.is_loading = 0
        notifyMock.mockReset()
        axiosMock.get.mockReset()
        axiosMock.put.mockReset()

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useNotificationStore).mockReturnValue(notificationStoreMock as never)
        globalThis.axios = axiosMock as never
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
