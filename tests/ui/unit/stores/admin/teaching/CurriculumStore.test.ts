import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useCurriculumStore } from '@/stores/admin/teaching/CurriculumStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('Admin Teaching CurriculumStore', () => {
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

    it('loads a single curriculum by id', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: {
                    id: 15,
                    title: 'Deutsch',
                },
            },
        })

        const store = useCurriculumStore()
        const curriculum = await store.show(15)

        expect(curriculum).toEqual({
            id: 15,
            title: 'Deutsch',
        })
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/teaching/curricula/15')
        expect(adminStoreMock.is_loading).toBe(0)
        expect(notifyMock).not.toHaveBeenCalled()
    })

    it('returns null and notifies when loading a curriculum fails', async () => {
        axiosMock.get.mockRejectedValue({
            response: {
                status: 404,
                data: {
                    message: 'Nicht gefunden.',
                },
            },
        })

        const store = useCurriculumStore()
        const curriculum = await store.show(99)

        expect(curriculum).toBeNull()
        expect(notifyMock).toHaveBeenCalledWith({
            status: 404,
            message: 'Nicht gefunden.',
            type: 'error',
            timeout: 3000,
        })
        expect(adminStoreMock.is_loading).toBe(0)
    })
})
