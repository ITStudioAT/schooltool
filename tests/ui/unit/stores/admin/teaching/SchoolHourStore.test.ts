import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('SchoolHourStore', () => {
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

    it('index loads school hours list', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [{ id: 1, hour: 1, from: '08:00', until: '08:50' }],
            },
        })

        const store = useSchoolHourStore()
        const result = await store.index()

        expect(result).toBe(true)
        expect(store.school_hours).toEqual([{ id: 1, hour: 1, from: '08:00', until: '08:50' }])
        expect(adminStoreMock.is_loading).toBe(0)
    })

    it('reuses the same in-flight school hour request for concurrent callers', async () => {
        let resolveRequest: ((value: unknown) => void) | null = null
        axiosMock.get.mockReturnValueOnce(new Promise((resolve) => {
            resolveRequest = resolve
        }))

        const store = useSchoolHourStore()
        const firstRequest = store.index()
        const secondRequest = store.index()

        expect(axiosMock.get).toHaveBeenCalledTimes(1)
        expect(store.school_hours_request_promise).not.toBeNull()

        resolveRequest?.({
            data: {
                data: [{ id: 2, hour: 2, from: '08:55', until: '09:45' }],
            },
        })

        await expect(firstRequest).resolves.toBe(true)
        await expect(secondRequest).resolves.toBe(true)
        expect(store.school_hours).toEqual([{ id: 2, hour: 2, from: '08:55', until: '09:45' }])
        expect(store.school_hours_request_promise).toBeNull()
    })

    it('store creates multiple school hours and returns created list', async () => {
        axiosMock.post.mockResolvedValue({
            data: {
                created: 2,
                data: [
                    { id: 2, hour: 2, from: '08:55', until: '09:45' },
                    { id: 3, hour: 3, from: '09:50', until: '10:40' },
                ],
            },
        })

        const store = useSchoolHourStore()
        const payload = {
            entries: [
                { hour: 2, from: '08:55', until: '09:45' },
                { hour: 3, from: '09:50', until: '10:40' },
            ],
        }
        const result = await store.store(payload)

        expect(result).toEqual([
            { id: 2, hour: 2, from: '08:55', until: '09:45' },
            { id: 3, hour: 3, from: '09:50', until: '10:40' },
        ])
        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/teaching/school_hours', payload)
        expect(adminStoreMock.is_loading).toBe(0)
    })

    it('update notifies and returns false on backend error', async () => {
        axiosMock.put.mockRejectedValue({
            response: {
                status: 422,
                data: { message: 'Ungültige Daten.' },
            },
        })

        const store = useSchoolHourStore()
        const result = await store.update(3, { hour: 3, from: '09:50', until: '10:40' })

        expect(result).toBe(false)
        expect(notifyMock).toHaveBeenCalledWith({
            status: 422,
            message: 'Ungültige Daten.',
            type: 'error',
            timeout: 3000,
        })
        expect(adminStoreMock.is_loading).toBe(0)
    })

    it('destroy removes school hour', async () => {
        axiosMock.delete.mockResolvedValue({})

        const store = useSchoolHourStore()
        const result = await store.destroy(4)

        expect(result).toBe(true)
        expect(axiosMock.delete).toHaveBeenCalledWith('/api/admin/teaching/school_hours/4')
        expect(adminStoreMock.is_loading).toBe(0)
    })
})
