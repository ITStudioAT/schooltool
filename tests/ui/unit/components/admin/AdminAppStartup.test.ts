import { beforeEach, describe, expect, it, vi } from 'vitest'
import AdminApp from '@/pages/admin/App.vue'
import { useAdminRouteNavigation } from '@/composables/useAdminRouteNavigation'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolStore } from '@/stores/admin/SchoolStore'

vi.mock('@/composables/useAdminRouteNavigation', () => ({
    useAdminRouteNavigation: vi.fn(),
}))

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/admin/SchoolStore', () => ({
    useSchoolStore: vi.fn(),
}))

describe('Admin app startup', () => {
    beforeEach(() => {
        vi.mocked(useAdminStore).mockReset()
        vi.mocked(useSchoolStore).mockReset()
        vi.mocked(useAdminRouteNavigation).mockReset()
        globalThis.axios = {
            get: vi.fn(),
        } as never
    })

    it('loads admin config without an eager csrf-cookie request', async () => {
        const adminStoreMock = {
            is_loading: 0,
            initialize: vi.fn(),
            loadConfig: vi.fn(async () => true),
        }

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useSchoolStore).mockReturnValue({} as never)
        const registerRouteNavigationHooks = vi.fn()
        vi.mocked(useAdminRouteNavigation).mockReturnValue({ registerRouteNavigationHooks } as never)

        const ctx: any = {
            $router: {},
            $route: { path: '/admin/teaching' },
            isAdminHomeRoute: (AdminApp as any).methods.isAdminHomeRoute,
        }

        await (AdminApp as any).beforeMount.call(ctx)

        expect(registerRouteNavigationHooks).toHaveBeenCalledTimes(1)
        expect(globalThis.axios.get).not.toHaveBeenCalled()
        expect(adminStoreMock.initialize).toHaveBeenCalledWith(ctx.$router)
        expect(adminStoreMock.loadConfig).toHaveBeenCalledWith({
            includeSchoolInfos: false,
            includeEnvironmentVersions: false,
        })
        expect(adminStoreMock.is_loading).toBe(0)
    })

    it('releases the startup loading state when config loading throws unexpectedly', async () => {
        const adminStoreMock = {
            is_loading: 0,
            initialize: vi.fn(),
            loadConfig: vi.fn(async () => {
                throw new Error('network aborted')
            }),
        }

        vi.mocked(useAdminStore).mockReturnValue(adminStoreMock as never)
        vi.mocked(useSchoolStore).mockReturnValue({} as never)
        vi.mocked(useAdminRouteNavigation).mockReturnValue({ registerRouteNavigationHooks: vi.fn() } as never)

        const ctx: any = {
            $router: {},
            $route: { path: '/admin/teaching' },
            isAdminHomeRoute: (AdminApp as any).methods.isAdminHomeRoute,
        }

        await expect((AdminApp as any).beforeMount.call(ctx)).rejects.toThrow('network aborted')
        expect(adminStoreMock.is_loading).toBe(0)
    })
})
