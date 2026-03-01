import { describe, expect, it } from 'vitest'
import MyCourse from '@/pages/homepage/student/overview/myCourse/MyCourse.vue'

describe('Student MyCourse date status display', () => {
    it('treats entfaellt status as free-like status', () => {
        const hasFreeStatus = (MyCourse as any).methods.hasFreeStatus.call({}, ['entfaellt'])

        expect(hasFreeStatus).toBe(true)
    })

    it('returns green icon color for entfaellt status', () => {
        const ctx = {
            hasFreeStatus: (MyCourse as any).methods.hasFreeStatus,
        }
        const color = (MyCourse as any).methods.getDateIconColor.call(ctx, ['entfaellt'])

        expect(color).toBe('#4caf50')
    })

    it('returns free row class for entfaellt status', () => {
        const ctx = {
            hasFreeStatus: (MyCourse as any).methods.hasFreeStatus,
        }
        const rowClass = (MyCourse as any).methods.getDateStatusClass.call(ctx, ['entfaellt'])

        expect(rowClass).toBe('date-row--free')
    })
})
