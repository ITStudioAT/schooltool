import { describe, expect, it } from 'vitest'
import {
    buildCalendarDays,
    calendarVisibleRange,
    formatCalendarDate,
    isCalendarDate,
    moveCalendarDate,
    parseCalendarDate,
} from '@/domains/materialsV2/calendar'

describe('Materials V2 calendar helpers', () => {
    it('validates and parses local calendar dates without UTC conversion', () => {
        expect(isCalendarDate('2026-02-28')).toBe(true)
        expect(isCalendarDate('2026-02-30')).toBe(false)
        expect(formatCalendarDate(parseCalendarDate('2026-09-15'))).toBe('2026-09-15')
    })

    it('moves month dates while clamping the day to the target month', () => {
        expect(moveCalendarDate('2026-01-31', 'month', 1)).toBe('2026-02-28')
        expect(moveCalendarDate('2026-09-15', 'week', -1)).toBe('2026-09-08')
    })

    it('builds Monday-based calendar days with reminders ordered by time', () => {
        const focusDate = parseCalendarDate('2026-09-15')
        const range = calendarVisibleRange(focusDate, 'week')
        const days = buildCalendarDays({
            focusDate,
            range,
            today: parseCalendarDate('2026-09-15'),
            items: [
                { id: 2, title: 'Später', reminder_date: '2026-09-15', reminder_time: '18:00' },
                { id: 1, title: 'Früher', reminder_date: '2026-09-15', reminder_time: '08:00' },
            ],
        })

        expect(days).toHaveLength(7)
        expect(days[0].key).toBe('2026-09-14')
        expect(days[1].isToday).toBe(true)
        expect(days[1].items.map((item) => item.id)).toEqual([1, 2])
    })
})
