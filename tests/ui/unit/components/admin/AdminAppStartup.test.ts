import { beforeEach, describe, expect, it, vi } from 'vitest'
import AdminApp from '@/pages/admin/App.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

describe('Admin app startup', () => {
    beforeEach(() => {
        vi.mocked(useAdminStore).mockReset()
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

        const ctx: any = {
            $router: {},
            registerRouteNavigationHooks: vi.fn(),
        }

        await (AdminApp as any).beforeMount.call(ctx)

        expect(ctx.registerRouteNavigationHooks).toHaveBeenCalledTimes(1)
        expect(globalThis.axios.get).not.toHaveBeenCalled()
        expect(adminStoreMock.initialize).toHaveBeenCalledWith(ctx.$router)
        expect(adminStoreMock.loadConfig).toHaveBeenCalledTimes(1)
    })
})
