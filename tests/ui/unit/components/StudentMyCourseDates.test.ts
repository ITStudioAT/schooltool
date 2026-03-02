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

    it('recognizes today date entries', () => {
        const methods = (MyCourse as any).methods
        const ctx = {
            normalizeDateKey: methods.normalizeDateKey,
        }
        const todayKey = methods.normalizeDateKey.call(ctx, new Date())
        const isToday = methods.isDateToday.call(ctx, todayKey)

        expect(isToday).toBe(true)
    })

    it('does not mark other dates as today', () => {
        const methods = (MyCourse as any).methods
        const ctx = {
            normalizeDateKey: methods.normalizeDateKey,
        }
        const yesterday = new Date()
        yesterday.setDate(yesterday.getDate() - 1)
        const yesterdayKey = methods.normalizeDateKey.call(ctx, yesterday)
        const isToday = methods.isDateToday.call(ctx, yesterdayKey)

        expect(isToday).toBe(false)
    })
})
