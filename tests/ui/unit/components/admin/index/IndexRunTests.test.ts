import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import IndexPage from '@/pages/admin/index/Index.vue'

describe('Index runTests', () => {
    it('shows abgelaufen for expired entries in the Meine Lizenzen card', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/admin/index/Index.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<h3 class="admin-card-title">Meine Lizenzen</h3>')
        expect(source).toContain('<span v-else class="licence-tag is-expired">abgelaufen</span>')
        expect(source).not.toContain('<span v-else class="licence-tag is-expired">nicht aktiv</span>')
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
