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

        expect(source).toContain('<h3>Meine Lizenzen</h3>')
        expect(source).toContain('<template v-else>abgelaufen</template>')
        expect(source).not.toContain('<template v-else>nicht aktiv</template>')
    })

    it('uses the compact Calm Focus dashboard header', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/admin/index/Index.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="admin-dashboard-page__onebar"')
        expect(source).toContain('<div class="admin-dashboard-page__brand-name">SchoolTool</div>')
        expect(source).not.toContain('<span class="admin-dashboard-page__meta-chip">v{{ appVersion }}</span>')
        expect(source).toContain('class="admin-dashboard-page__overview-grid"')
        expect(source).toContain('class="admin-dashboard-page__licence-grid"')
        expect(source).not.toContain('<div class="admin-dashboard-page__eyebrow">Berechtigungen</div>')
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

    it('shows diagnostics only after an unhealthy status was detected', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/admin/index/Index.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain(`v-if="health_loaded && healthStore.is_healthy === false && isAllowed(['admin', 'super_admin'])"`)
        expect(source).toContain('Diagnose starten')
        expect(source).toContain('diagnostics_visible: false')
        expect(source).toContain('id="system-diagnostics"')
        expect(source).toContain('Queues neu starten')
        expect(source).toContain('Queue testen')
        expect(source).not.toContain('Status prüfen')
        expect(source.indexOf('id="system-diagnostics"')).toBeLessThan(source.indexOf('Queue testen'))
    })

    it('uses neutral icons instead of invented licence abbreviations', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/admin/index/Index.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<v-icon icon="mdi-certificate-outline" size="18" />')
        expect(source).not.toContain('licenceBadgeText')
    })

    it('hides account roles behind the account details button by default', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/admin/index/Index.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('account_details_visible: false')
        expect(source).toContain('class="admin-dashboard-page__account-name"')
        expect(source).toContain('font-size: 1.65rem')
        expect(source).not.toContain('<v-avatar')
        expect(source).not.toContain('userBadgeText')
        expect(source).toContain('v-show="account_details_visible"')
        expect(source).toContain('aria-controls="account-details"')
        expect(source).toContain(`{{ account_details_visible ? 'Weniger anzeigen' : 'Mehr anzeigen' }}`)
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
        expect(loadConfig).toHaveBeenCalledWith({ includeSchoolInfos: true, includeEnvironmentVersions: false })
        expect(loadSchoolInfos).toHaveBeenCalledWith(42)
        expect(fetchStatus).toHaveBeenCalled()
        expect(context.health_loaded).toBe(true)
    })

    it('refreshes health silently while visible and when the window regains focus', async () => {
        vi.useFakeTimers()
        const refreshHealth = vi.fn().mockResolvedValue(undefined)
        const context: Record<string, any> = {
            health_poll_interval: null,
            refreshHealth,
        }
        context.handleHealthFocus = (IndexPage as any).methods.handleHealthFocus.bind(context)

        ;(IndexPage as any).mounted.call(context)
        await vi.advanceTimersByTimeAsync(30_000)

        expect(refreshHealth).toHaveBeenCalledWith(false)

        window.dispatchEvent(new Event('focus'))
        expect(refreshHealth).toHaveBeenCalledTimes(2)

        ;(IndexPage as any).unmounted.call(context)
        window.dispatchEvent(new Event('focus'))
        await vi.advanceTimersByTimeAsync(30_000)

        expect(refreshHealth).toHaveBeenCalledTimes(2)
        vi.useRealTimers()
    })

    it('closes diagnostics after a silent health refresh reports recovery', async () => {
        const fetchStatus = vi.fn().mockResolvedValue({ is_healthy: true })
        const context: Record<string, any> = {
            healthStore: { fetchStatus },
            health_loading: false,
            health_loaded: false,
            diagnostics_visible: true,
        }

        await (IndexPage as any).methods.refreshHealth.call(context, false)

        expect(fetchStatus).toHaveBeenCalledWith({ notifyOnError: false })
        expect(context.health_loaded).toBe(true)
        expect(context.diagnostics_visible).toBe(false)
        expect(context.health_loading).toBe(false)
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
