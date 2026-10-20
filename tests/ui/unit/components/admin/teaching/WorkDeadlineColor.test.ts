import { describe, expect, it } from 'vitest'
import { workDeadlineColor } from '@/helpers/date'

describe('Work deadline display in Vienna', () => {
    it.each([
        ['2026-10-21', '16:00', '2026-10-20T12:00:00Z', 'success'],
        ['2026-10-20', '16:00', '2026-10-20T13:59:59Z', 'warning'],
        ['2026-10-20', '16:00', '2026-10-20T14:00:00Z', 'warning'],
        ['2026-10-20', '16:00', '2026-10-20T14:00:00.001Z', 'error'],
        ['2026-10-19', null, '2026-10-20T12:00:00Z', 'error'],
        ['2026-10-20', null, '2026-10-20T21:59:59Z', 'warning'],
        ['2026-10-20', null, '2026-10-20T22:00:00Z', 'error'],
        ['2026-10-21', null, '2026-10-20T22:00:00Z', 'warning'],
        ['2026-10-22', null, '2026-10-20T22:00:00Z', 'success'],
        ['2026-10-25', '02:30', '2026-10-25T00:45:00Z', 'warning'],
        ['2026-10-25', '02:30', '2026-10-25T01:30:00.001Z', 'error'],
        ['2026-12-01', '12:00', '2026-12-01T11:00:00.001Z', 'error'],
    ])('colors %s %s at %s as %s', (date, time, now, color) => {
        expect(workDeadlineColor({ finish_until_date: date, finish_until_time: time }, Date.parse(now))).toBe(color)
    })

    it.each([null, '', '2026-02-30'])('does not assign a due status to an invalid date: %s', date => {
        expect(workDeadlineColor({ finish_until_date: date }, Date.parse('2026-10-20T12:00:00Z'))).toBe('medium-emphasis')
    })
})
