import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import Overview from '@/pages/admin/tutoring/components/Overview.vue'

describe('Tutoring overview requests', () => {
    it('renders request counts and requester details in the admin overview', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/tutoring/components/Overview.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('requestCount(item)')
        expect(source).toContain('requestStudentSummary(item)')
        expect(source).toContain('selectedOfferRequests')
        expect(source).toContain('requestCountLabel(item)')
        expect(source).toContain('stats.requests_count')
        expect(source).toContain('stats.requesting_students_count')
    })

    it('summarizes requesting students from the offer payload', () => {
        const methods = (Overview as any).methods
        const ctx = {
            requestCount: methods.requestCount,
            requestStudentName: methods.requestStudentName,
        }

        const summary = methods.requestStudentSummary.call(ctx, {
            requests_count: 3,
            requests: [
                { from_user: { last_name: 'Muster', first_name: 'Mina' } },
                { from_user: { last_name: 'Berger', first_name: 'Ben' } },
                { from_user: { last_name: 'Huber', first_name: 'Hanna' } },
            ],
        })

        expect(summary).toBe('Muster Mina, Berger Ben +1')
    })
})
