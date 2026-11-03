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
    it('waits for all connection probes before reporting a system or connection failure', () => {
        const dashboardStatus = (IndexPage as any).computed.dashboardStatus
        const context = { config: { is_auth: true, user: { roles: ['super_admin'] } },
            health_loaded: true, health_loading: false, healthStore: { is_healthy: false },
            connections_status: { loading: true, hasError: true } }

        expect(dashboardStatus.call(context)).toMatchObject({ state: 'loading', title: 'Status wird geprüft' })
        context.connections_status.loading = false
        expect(dashboardStatus.call(context)).toMatchObject({ state: 'error', detail: expect.stringContaining('Scheduler oder Worker') })
        context.healthStore.is_healthy = true
        expect(dashboardStatus.call(context)).toMatchObject({ state: 'error', detail: expect.stringContaining('Verbindungsprüfung') })
        context.connections_status.hasError = false
        expect(dashboardStatus.call(context)).toMatchObject({ state: 'healthy', title: 'Prüfung abgeschlossen' })
    })

    it('does not wait for hidden connection checks for other roles and keeps unknown health separate from success', () => {
        const dashboardStatus = (IndexPage as any).computed.dashboardStatus
        const context = { config: { is_auth: true, user: { roles: ['admin'] } },
            health_loaded: true, health_loading: false, healthStore: { is_healthy: null },
            connections_status: { loading: true, hasError: true } }

        expect(dashboardStatus.call(context)).toMatchObject({ state: 'error', detail: 'Systemstatus konnte nicht geladen werden' })
        context.healthStore.is_healthy = true
        expect(dashboardStatus.call(context)).toMatchObject({ state: 'healthy' })
        context.health_loading = true
        expect(dashboardStatus.call(context)).toMatchObject({ state: 'loading' })
        context.health_loading = false
        context.health_loaded = false
        expect(dashboardStatus.call(context)).toMatchObject({ state: 'loading' })
    })

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
