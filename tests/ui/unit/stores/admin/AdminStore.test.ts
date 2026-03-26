import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: () => ({
        notify: vi.fn(),
    }),
}))

describe('Admin store config loading', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
        globalThis.axios = {
            get: vi.fn(),
        } as never
    })

    it('loads admin config without requesting a csrf cookie first', async () => {
        const store = useAdminStore()
        const payload = {
            is_auth: true,
            selected_school: { id: 7 },
            selected_schoolyear: { id: 3 },
            selected_register: null,
            health: { queue_working: true },
        }

        vi.mocked(globalThis.axios.get).mockResolvedValueOnce({
            data: payload,
        } as never)

        const result = await store.loadConfig()

        expect(globalThis.axios.get).toHaveBeenCalledTimes(1)
        expect(globalThis.axios.get).toHaveBeenCalledWith('/api/admin/config', {})
        expect(result).toEqual(payload)
        expect(store.config).toEqual(payload)
        expect(store.selected_school).toEqual(payload.selected_school)
        expect(store.selected_schoolyear).toEqual(payload.selected_schoolyear)
    })

    it('reuses the same in-flight config request for concurrent callers', async () => {
        const store = useAdminStore()
        const payload = {
            is_auth: true,
            selected_school: { id: 8 },
            selected_schoolyear: { id: 4 },
            selected_register: null,
            health: { queue_working: false },
        }

        let resolveRequest: ((value: unknown) => void) | null = null
        vi.mocked(globalThis.axios.get).mockReturnValueOnce(new Promise((resolve) => {
            resolveRequest = resolve
        }) as never)

        const firstRequest = store.loadConfig()
        const secondRequest = store.loadConfig()

        expect(globalThis.axios.get).toHaveBeenCalledTimes(1)
        expect(store.config_request_promise).not.toBeNull()

        resolveRequest?.({ data: payload })

        await expect(firstRequest).resolves.toEqual(payload)
        await expect(secondRequest).resolves.toEqual(payload)
        expect(store.config).toEqual(payload)
        expect(store.config_request_promise).toBeNull()
    })
})
