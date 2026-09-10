import { describe, expect, it } from 'vitest'
import MyCourse from '@/pages/homepage/student/overview/myCourse/MyCourse.vue'

describe('Student MyCourse date status display', () => {
    it.each([
        ['present', 'Anwesend', 'success'],
        ['absent', 'Abwesend', 'error'],
    ])('labels own attendance %s without assuming presence', (attendanceStatus, label, color) => {
        const context = {
            hasFreeStatus: (MyCourse as any).methods.hasFreeStatus,
            isDatePast: () => true,
            isDateToday: () => false,
        }

        const indicator = (MyCourse as any).methods.dateAttendanceIndicator.call(context, {
            date: '2026-09-09', status: [], attendance_status: attendanceStatus,
        })

        expect(indicator).toMatchObject({ label, color })
    })

    it.each([null, undefined])('hides unrecorded attendance %s', (attendanceStatus) => {
        const indicator = (MyCourse as any).methods.dateAttendanceIndicator.call({
            hasFreeStatus: () => false,
        }, { attendance_status: attendanceStatus })

        expect(indicator).toBeNull()
    })

    it('shows recorded attendance for today', () => {
        const indicator = (MyCourse as any).methods.dateAttendanceIndicator.call({
            hasFreeStatus: () => false,
            isDatePast: () => false,
            isDateToday: () => true,
        }, { attendance_status: 'absent' })

        expect(indicator?.label).toBe('Abwesend')
    })

    it.each([
        ['present', 'Anwesend'],
        ['absent', 'Abwesend'],
        [null, undefined],
    ])('shows future lesson attendance %s instead of hiding the status', (attendanceStatus, label) => {
        const indicator = (MyCourse as any).methods.dateAttendanceIndicator.call({
            hasFreeStatus: () => false,
            isDatePast: () => false,
            isDateToday: () => false,
        }, { date: '2099-09-15', status: [], attendance_status: attendanceStatus })

        expect(indicator?.label).toBe(label)
    })

    it.each([
        [true, ['free']],
        [true, ['entfaellt']],
    ])('hides attendance for cancelled dates (%s, %s)', (isPast, status) => {
        const indicator = (MyCourse as any).methods.dateAttendanceIndicator.call({
            hasFreeStatus: (MyCourse as any).methods.hasFreeStatus,
            isDatePast: () => isPast,
            isDateToday: () => false,
        }, { date: '2026-09-15', status, attendance_status: 'present' })

        expect(indicator).toBeNull()
    })

    it('treats entfaellt status as free-like status', () => {
        const hasFreeStatus = (MyCourse as any).methods.hasFreeStatus.call({}, ['entfaellt'])

        expect(hasFreeStatus).toBe(true)
    })

    it('returns green icon color for entfaellt status', () => {
        const ctx = {
            hasFreeStatus: (MyCourse as any).methods.hasFreeStatus,
            isDatePast: () => false,
        }
        const color = (MyCourse as any).methods.getDateIconColor.call(ctx, '2099-03-01', ['entfaellt'])

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

    it('recognizes past date entries', () => {
        const methods = (MyCourse as any).methods
        const ctx = {
            normalizeDateKey: methods.normalizeDateKey,
        }
        const yesterday = new Date()
        yesterday.setDate(yesterday.getDate() - 1)
        const yesterdayKey = methods.normalizeDateKey.call(ctx, yesterday)

        const isPast = methods.isDatePast.call(ctx, yesterdayKey)

        expect(isPast).toBe(true)
    })

    it('uses a checked icon for past date entries', () => {
        const methods = (MyCourse as any).methods
        const icon = methods.getDateLeadingIcon.call({
            isDatePast: () => true,
        }, '2026-03-01')

        expect(icon).toBe('mdi-check-circle')
    })

    it('uses green icon color for past date entries', () => {
        const methods = (MyCourse as any).methods
        const color = methods.getDateIconColor.call({
            isDatePast: () => true,
            hasFreeStatus: methods.hasFreeStatus,
        }, '2026-03-01', ['pruefung'])

        expect(color).toBe('#4caf50')
    })
})
