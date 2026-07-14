import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createResourceStore } from '@/stores/admin/ResourceStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('Admin ResourceStore loading state', () => {
    const adminStoreMock = { is_loading: 0 }
    const notifyMock = vi.fn()
    const actions = createResourceStore('users').actions()
    const storeContext = {
        items: [],
        timeout: 3000,
    }

    beforeEach(() => {
        adminStoreMock.is_loading = 0
        storeContext.items = []
        notifyMock.mockReset()

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useNotificationStore).mockReturnValue({ notify: notifyMock } as never)
        globalThis.axios = {
            get: vi.fn(),
        } as never
    })

    it('releases the global loading counter after a successful index request', async () => {
        vi.mocked(globalThis.axios.get).mockResolvedValueOnce({ data: [{ id: 1 }] } as never)

        await expect(actions.index.call(storeContext)).resolves.toBe(true)

        expect(storeContext.items).toEqual([{ id: 1 }])
        expect(adminStoreMock.is_loading).toBe(0)
    })

    it('releases only its own loading slot when another request is still active', async () => {
        adminStoreMock.is_loading = 1
        vi.mocked(globalThis.axios.get).mockResolvedValueOnce({ data: [] } as never)

        await expect(actions.index.call(storeContext)).resolves.toBe(true)

        expect(adminStoreMock.is_loading).toBe(1)
    })

    it('releases the global loading counter after a failed index request', async () => {
        vi.mocked(globalThis.axios.get).mockRejectedValueOnce({
            response: {
                status: 500,
                data: { message: 'Fehler' },
            },
        })

        await expect(actions.index.call(storeContext)).resolves.toBe(false)

        expect(adminStoreMock.is_loading).toBe(0)
        expect(notifyMock).toHaveBeenCalledWith(expect.objectContaining({ status: 500 }))
    })
})
