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

        expect(source).toContain('<h3 class="admin-card-title">Meine Lizenzen</h3>')
        expect(source).toContain('<span v-else class="licence-tag is-expired">abgelaufen</span>')
        expect(source).not.toContain('<span v-else class="licence-tag is-expired">nicht aktiv</span>')
    })

    it('uses the shortened admin dashboard hero title', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/admin/index/Index.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<h2 class="admin-hero-title">Zentrale Übersicht</h2>')
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

        expect(source).toContain(`v-if="isAllowed(['admin', 'super_admin'])" class="mt-3"`)
        expect(source).toContain(`v-if="isAllowed(['admin', 'super_admin'])"`)
        expect(source).toContain('Queues neu starten')
        expect(source).toContain('Tests prüfen')
        expect(source).not.toContain(`v-if="isAllowed(['super_admin'])" class="mt-3"`)
    })

    it('loads school infos but skips automatic health checks for lunch_admin on beforeMount', async () => {
        globalThis.axios = {
            get: vi.fn().mockResolvedValue({}),
        } as never

        const loadSchoolInfos = vi.fn().mockResolvedValue(true)
        vi.mocked(useAdminStore).mockReturnValue({
            is_loading: 0,
        } as never)
        vi.mocked(useHealthStore).mockReturnValue({} as never)
        vi.mocked(useSchoolStore).mockReturnValue({
            loadSchoolInfos,
        } as never)

        const runTests = vi.fn()
        const context: Record<string, any> = {
            config: {
                is_auth: true,
                selected_school: { id: 42 },
                user: { roles: ['lunch_admin', 'lunch_user'] },
            },
            isAllowed(roles: string[]) {
                return this.config.user.roles.some((role: string) => roles.includes(role))
            },
            runTests,
        }

        await (IndexPage as any).beforeMount.call(context)

        expect(globalThis.axios.get).toHaveBeenCalledWith('/sanctum/csrf-cookie')
        expect(loadSchoolInfos).toHaveBeenCalledWith(42)
        expect(runTests).not.toHaveBeenCalled()
    })

    it('treats a valid role as active even when the legacy activation flag is false', () => {
        const isUserLicenceRoleActive = (IndexPage as any).methods.isUserLicenceRoleActive

        expect(isUserLicenceRoleActive.call({}, { is_active: true, is_activated: false })).toBe(true)
        expect(isUserLicenceRoleActive.call({}, { is_active: false, is_activated: true })).toBe(false)
    })

    it('aborts queue polling when queue test id is missing', async () => {
        const checkQueueStatus = vi.fn()
        const context: Record<string, any> = {
            healthStore: {
                checkCronStatus: vi.fn().mockResolvedValue({}),
                testQueue: vi.fn().mockResolvedValue(true),
                checkQueueStatus,
            },
            data: {},
            cron_status: { is_healthy: 1 },
            test_step: 0,
            all_tests_result: 0,
            queue_test_status: 'waiting',
            queue_test_result: 0,
            cron_test_status: 'waiting',
            cron_test_result: 0,
        }

        await (IndexPage as any).methods.runTests.call(context)

        expect(checkQueueStatus).not.toHaveBeenCalled()
        expect(context.queue_test_result).toBe(0)
        expect(context.queue_test_status).toBe('finished')
    })

    it('stops polling when queue status request fails', async () => {
        const checkQueueStatus = vi.fn().mockResolvedValue(false)
        const context: Record<string, any> = {
            healthStore: {
                checkCronStatus: vi.fn().mockResolvedValue({}),
                testQueue: vi.fn().mockResolvedValue(true),
                checkQueueStatus,
            },
            data: { testId: 'f8f6808a-8f08-4edb-9501-e357a6a8aa1a' },
            cron_status: { is_healthy: 1 },
            test_step: 0,
            all_tests_result: 0,
            queue_test_status: 'waiting',
            queue_test_result: 0,
            cron_test_status: 'waiting',
            cron_test_result: 0,
        }

        await (IndexPage as any).methods.runTests.call(context)

        expect(checkQueueStatus).toHaveBeenCalledTimes(1)
        expect(context.queue_test_result).toBe(0)
        expect(context.queue_test_status).toBe('finished')
    })
})
