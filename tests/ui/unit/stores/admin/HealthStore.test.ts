import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useHealthStore } from '@/stores/admin/HealthStore'

const { notify } = vi.hoisted(() => ({
    notify: vi.fn(),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: () => ({ notify }),
}))

describe('Health store', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
        notify.mockReset()
        globalThis.axios = {
            get: vi.fn(),
            post: vi.fn(),
        } as never
    })

    it('updates the service status after a successful request', async () => {
        const payload = {
            scheduler: { is_healthy: true },
            worker: { is_healthy: true },
            is_healthy: true,
        }
        vi.mocked(globalThis.axios.get).mockResolvedValueOnce({ data: payload } as never)

        const store = useHealthStore()

        await expect(store.fetchStatus()).resolves.toEqual(payload)
        expect(store.scheduler).toEqual(payload.scheduler)
        expect(store.worker).toEqual(payload.worker)
        expect(store.is_healthy).toBe(true)
    })

    it('suppresses notifications only for silent background refreshes', async () => {
        const error = {
            response: {
                status: 503,
                data: { message: 'Health unavailable' },
            },
        }
        vi.mocked(globalThis.axios.get)
            .mockRejectedValueOnce(error)
            .mockRejectedValueOnce(error)

        const store = useHealthStore()

        await expect(store.fetchStatus({ notifyOnError: false })).resolves.toBeNull()
        expect(notify).not.toHaveBeenCalled()

        await expect(store.fetchStatus()).resolves.toBeNull()
        expect(notify).toHaveBeenCalledOnce()
        expect(notify).toHaveBeenCalledWith({
            status: 503,
            message: 'Health unavailable',
            type: 'error',
            timeout: 3000,
        })
    })

    it('starts queue tests with a post request', async () => {
        const payload = {
            test_id: 'f8f6808a-8f08-4edb-9501-e357a6a8aa1a',
            dispatched_at: '2026-07-29T12:00:00+03:00',
        }
        vi.mocked(globalThis.axios.post).mockResolvedValueOnce({ data: payload } as never)

        const store = useHealthStore()

        await expect(store.testQueue()).resolves.toEqual(payload)
        expect(globalThis.axios.post).toHaveBeenCalledWith('/api/admin/health/test-queue')
        expect(globalThis.axios.get).not.toHaveBeenCalled()
    })
})
