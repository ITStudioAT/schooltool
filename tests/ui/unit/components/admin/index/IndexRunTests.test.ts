import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
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

describe('Index runTests', () => {
    it('shows abgelaufen for expired entries in the Meine Lizenzen card', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/admin/index/Index.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<div class="text-h6 font-weight-bold">Meine Lizenzen</div>')
        expect(source).toContain('<template v-else>abgelaufen</template>')
        expect(source).not.toContain('<template v-else>nicht aktiv</template>')
    })

    it('uses the shortened admin dashboard hero title', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/admin/index/Index.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<div class="text-h5 font-weight-bold">SchoolTool</div>')
        expect(source).not.toContain('Zentrale Übersicht für Systemzustand, Team und Lizenzen')
        expect(source).not.toContain('Behalten Sie Admins, Gesundheitschecks und aktive Schul-Lizenzen in einer Oberfläche im Blick.')
    })

    it('counts active and expired school plus personal licences in the KPI', () => {
        const activeLicenceCount = (IndexPage as any).computed.activeLicenceCount
        const expiredLicenceCount = (IndexPage as any).computed.expiredLicenceCount

        const context: Record<string, any> = {
            schoolLicencesWithSchoolLicence: [
                { school_licence_enabled: true, valid_until: '2026-07-10' },
                { school_licence_enabled: true, valid_until: '2026-01-10' },
            ],
            myLicenceEntries: [
                { is_active: true, valid_until: '2026-07-10' },
                { is_active: true, valid_until: null },
                { is_active: false, valid_until: '2026-07-10' },
            ],
            isLicenceActive: (licence: Record<string, any>) => licence.valid_until >= '2026-03-28',
            isMyLicenceEntryActive: (entry: Record<string, any>) => entry.is_active && (!entry.valid_until || entry.valid_until >= '2026-03-28'),
        }

        expect(activeLicenceCount.call(context)).toBe(3)
        expect(expiredLicenceCount.call(context)).toBe(2)
    })

    it('shows queue and test action buttons only for admin and super_admin', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/admin/index/Index.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain(`v-if="isAllowed(['admin', 'super_admin'])" class="d-flex align-center ga-3"`)
        expect(source).toContain(`v-if="isAllowed(['admin', 'super_admin'])"`)
        expect(source).toContain('Queues neu starten')
        expect(source).toContain('Queue testen')
        expect(source).not.toContain(`v-if="isAllowed(['super_admin'])" class="d-flex align-center ga-3"`)
    })

    it('loads dashboard data and health status for an authenticated lunch_admin', async () => {
        globalThis.axios = {
            get: vi.fn().mockResolvedValue({}),
        } as never

        const loadSchoolInfos = vi.fn().mockResolvedValue(true)
        const loadConfig = vi.fn().mockResolvedValue(true)
        const fetchStatus = vi.fn().mockResolvedValue({})
        vi.mocked(useAdminStore).mockReturnValue({
            is_loading: 0,
            loadConfig,
        } as never)
        vi.mocked(useHealthStore).mockReturnValue({ fetchStatus } as never)
        vi.mocked(useSchoolStore).mockReturnValue({
            loadSchoolInfos,
        } as never)

        const context: Record<string, any> = {
            config: {
                is_auth: true,
                selected_school: { id: 42 },
                user: { roles: ['lunch_admin', 'lunch_user'] },
            },
            isAllowed(roles: string[]) {
                return this.config.user.roles.some((role: string) => roles.includes(role))
            },
            health_loaded: false,
        }

        await (IndexPage as any).beforeMount.call(context)

        expect(globalThis.axios.get).toHaveBeenCalledWith('/sanctum/csrf-cookie')
        expect(loadConfig).toHaveBeenCalledWith({ includeSchoolInfos: true, includeEnvironmentVersions: true })
        expect(loadSchoolInfos).toHaveBeenCalledWith(42)
        expect(fetchStatus).toHaveBeenCalled()
        expect(context.health_loaded).toBe(true)
    })

    it('treats a valid role as active even when the legacy activation flag is false', () => {
        const isUserLicenceRoleActive = (IndexPage as any).methods.isUserLicenceRoleActive

        expect(isUserLicenceRoleActive.call({}, { is_active: true, is_activated: false })).toBe(true)
        expect(isUserLicenceRoleActive.call({}, { is_active: false, is_activated: true })).toBe(false)
    })

    it('aborts queue polling when queue test id is missing', async () => {
        const checkQueueTest = vi.fn()
        const context: Record<string, any> = {
            healthStore: {
                testQueue: vi.fn().mockResolvedValue(true),
                checkQueueTest,
            },
            queue_test_running: false,
            queue_test_visible: false,
        }

        await (IndexPage as any).methods.runQueueTest.call(context)

        expect(checkQueueTest).not.toHaveBeenCalled()
        expect(context.queue_test_running).toBe(false)
        expect(context.queue_test_visible).toBe(true)
    })

    it('stops polling when queue status request fails', async () => {
        vi.useFakeTimers()
        const checkQueueTest = vi.fn().mockResolvedValue(false)
        const context: Record<string, any> = {
            healthStore: {
                testQueue: vi.fn().mockResolvedValue({ test_id: 'f8f6808a-8f08-4edb-9501-e357a6a8aa1a' }),
                checkQueueTest,
            },
            queue_test_running: false,
            queue_test_visible: false,
        }

        const queueTestPromise = (IndexPage as any).methods.runQueueTest.call(context)
        await vi.advanceTimersByTimeAsync(1000)
        await queueTestPromise

        expect(checkQueueTest).toHaveBeenCalledTimes(1)
        expect(context.queue_test_running).toBe(false)
        expect(context.queue_test_visible).toBe(true)
        vi.useRealTimers()
    })
})
