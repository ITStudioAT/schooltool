import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useModelStore } from '@/stores/admin/ModelStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('Admin ModelStore loading state', () => {
    const adminStoreMock = { is_loading: 0 }
    const notifyMock = vi.fn()

    beforeEach(() => {
        setActivePinia(createPinia())
        adminStoreMock.is_loading = 0
        notifyMock.mockReset()

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useNotificationStore).mockReturnValue({ notify: notifyMock } as never)
        globalThis.axios = {
            get: vi.fn(),
        } as never
    })

    it('releases only its own loading slot after indexing models', async () => {
        adminStoreMock.is_loading = 1
        vi.mocked(globalThis.axios.get).mockResolvedValueOnce({
            data: {
                items: [{ id: 1 }],
                pagination: { current_page: 1 },
            },
        } as never)

        const store = useModelStore()

        await expect(store.index('users', 'Anna', 1)).resolves.toBe(true)
        expect(store.items).toEqual([{ id: 1 }])
        expect(adminStoreMock.is_loading).toBe(1)
    })

    it('releases the loading counter after an index error', async () => {
        vi.mocked(globalThis.axios.get).mockRejectedValueOnce({
            response: {
                status: 500,
                data: { message: 'Fehler' },
            },
        })

        const store = useModelStore()

        await expect(store.index('users', '', 1)).resolves.toBe(false)
        expect(adminStoreMock.is_loading).toBe(0)
        expect(notifyMock).toHaveBeenCalledWith(expect.objectContaining({ status: 500 }))
    })
})
