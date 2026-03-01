import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useCourseDateStore } from '@/stores/admin/teaching/CourseDateStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('CourseDateStore', () => {
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

    it('index sends course_id query and keeps selection if still present', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [{ id: 2, date: '2026-03-01' }, { id: 3, date: '2026-03-08' }],
            },
        })

        const store = useCourseDateStore()
        store.selected_courseDate = { id: 3 } as never

        const result = await store.index(77)

        expect(result).toBe(true)
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/teaching/course_dates', {
            params: { course_id: 77 },
        })
        expect(store.selected_courseDate?.id).toBe(3)
    })

    it('update returns false and notifies when id is missing', async () => {
        const store = useCourseDateStore()
        const result = await store.update({ date: '2026-03-02' } as never)

        expect(result).toBe(false)
        expect(axiosMock.put).not.toHaveBeenCalled()
        expect(notifyMock).toHaveBeenCalledWith(
            expect.objectContaining({
                status: 422,
            }),
        )
    })
})
