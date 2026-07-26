import { describe, expect, it, vi } from 'vitest'
import IndexPage from '@/pages/admin/index/Index.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useHealthStore } from '@/stores/admin/HealthStore'
import { useSchoolStore } from '@/stores/admin/SchoolStore'

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: vi.fn(),
}))

vi.mock('@/stores/admin/HealthStore', () => ({
    useHealthStore: vi.fn(),
}))

vi.mock('@/stores/admin/SchoolStore', () => ({
    useSchoolStore: vi.fn(),
}))

describe('Admin dashboard loading state', () => {
    it('releases the global loading state when initialization fails unexpectedly', async () => {
        globalThis.axios = {
            get: vi.fn().mockResolvedValue({}),
        } as never

        const adminStore = {
            is_loading: 0,
            loadConfig: vi.fn(),
        }
        vi.mocked(useAdminStore).mockReturnValue(adminStore as never)
        vi.mocked(useHealthStore).mockReturnValue({
            fetchStatus: vi.fn(),
        } as never)
        vi.mocked(useSchoolStore).mockReturnValue({
            loadSchoolInfos: vi.fn().mockRejectedValue(new Error('request aborted')),
        } as never)

        const context: Record<string, any> = {
            config: {
                is_auth: true,
                environment_versions: {},
                selected_school: { id: 42 },
            },
            health_loaded: false,
        }

        await expect((IndexPage as any).beforeMount.call(context)).rejects.toThrow('request aborted')
        expect(adminStore.is_loading).toBe(0)
    })
})
