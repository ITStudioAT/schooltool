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
            put: vi.fn(),
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

    it('requests expensive dashboard config only when asked', async () => {
        const store = useAdminStore()
        vi.mocked(globalThis.axios.get).mockResolvedValueOnce({ data: {} } as never)

        await store.loadConfig({ includeSchoolInfos: true, includeEnvironmentVersions: true })

        expect(globalThis.axios.get).toHaveBeenCalledWith('/api/admin/config', {
            params: {
                include_school_infos: 1,
                include_environment_versions: 1,
            },
        })
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

    it('keeps the saved personal shell color preference when the config reload is stale', async () => {
        const store = useAdminStore()
        const payload = {
            is_auth: true,
            user: { use_school_color_for_admin_ui: true },
            selected_school: { id: 7, color: '#336699' },
            selected_schoolyear: { id: 3 },
            selected_register: null,
        }

        vi.mocked(globalThis.axios.put).mockResolvedValueOnce({
            data: { use_school_color_for_admin_ui: false },
        } as never)
        vi.mocked(globalThis.axios.get).mockResolvedValueOnce({
            data: JSON.parse(JSON.stringify(payload)),
        } as never)

        const result = await store.saveAdminShellColorPreference(false)

        expect(globalThis.axios.put).toHaveBeenCalledWith('/api/admin/user-preferences/admin-shell-color', {
            use_school_color_for_admin_ui: false,
        })
        expect(globalThis.axios.get).toHaveBeenCalledWith('/api/admin/config', {})
        expect(store.config?.user.use_school_color_for_admin_ui).toBe(false)
        expect(result).toBe(true)
    })
})
