import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, describe, expect, it, vi } from 'vitest'
import MyTimetable from '@/pages/admin/teaching/overview/components/MyTimetable.vue'

afterEach(() => {
    vi.useRealTimers()
})

describe('MyTimetable time range labels', () => {
    it('builds a time range label from school hour definitions', () => {
        const computed = (MyTimetable as any).computed
        const methods = (MyTimetable as any).methods

        const ctx: Record<string, unknown> = {
            myCourses: [
                {
                    id: 1,
                    title: 'Digitale Grundbildung',
                    classes: ['2B'],
                    course_dates: [
                        { id: 11, date: '2026-03-02', hours: [1, 2], status: [] },
                    ],
                },
            ],
            school_hours: [
                { hour: 1, from: '07:45:00', until: '08:35:00' },
                { hour: 2, from: '08:40:00', until: '09:30:00' },
            ],
            formatHoursTimeRange: methods.formatHoursTimeRange,
            formatTimeValue: methods.formatTimeValue,
        }

        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)

        const items = computed.timetableItems.call(ctx)

        expect(items).toHaveLength(1)
        expect(items[0].timeRangeLabel).toBe('07:45 - 09:30')
    })

    it('falls back to "-" when school hour data is missing', () => {
        const computed = (MyTimetable as any).computed
        const methods = (MyTimetable as any).methods

        const ctx: Record<string, unknown> = {
            myCourses: [
                {
                    id: 2,
                    title: 'Informatik',
                    classes: ['5A'],
                    course_dates: [
                        { id: 12, date: '2026-03-03', hours: [5], status: [] },
                    ],
                },
            ],
            school_hours: [
                { hour: 1, from: '07:45:00', until: '08:35:00' },
            ],
            formatHoursTimeRange: methods.formatHoursTimeRange,
            formatTimeValue: methods.formatTimeValue,
        }

        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)

        const items = computed.timetableItems.call(ctx)

        expect(items).toHaveLength(1)
        expect(items[0].timeRangeLabel).toBe('-')
    })

    it('uses the next week containing a course date for the next-week range', () => {
        vi.useFakeTimers()
        vi.setSystemTime(new Date(2026, 2, 4, 12))

        const methods = (MyTimetable as any).methods
        const ctx = {
            range: 'next_week',
            offset: 0,
            timetableItems: [
                { dateObj: new Date(2026, 2, 5) },
                { dateObj: new Date(2026, 2, 23) },
            ],
            normalizeDay: methods.normalizeDay,
            startOfWeek: methods.startOfWeek,
            endOfWeek: methods.endOfWeek,
            nextCourseWeekStart: methods.nextCourseWeekStart,
        }

        const [from, until] = methods.currentRangeBounds.call(ctx)

        expect(from).toEqual(new Date(2026, 2, 23))
        expect(until).toEqual(new Date(2026, 2, 29))
    })

    it('renders the time-range chip in timetable rows', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/MyTimetable.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('{{ item.timeRangeLabel }}')
        expect(source).toContain('variant="outlined" color="primary">{{ item.timeRangeLabel }}</v-chip>')
        expect(source).toContain('v-if="!isToday(item)"')
    })

    it('treats entfaellt status as free-like status', () => {
        const hasFreeStatus = (MyTimetable as any).methods.hasFreeStatus.call({}, { status: ['entfaellt'] })

        expect(hasFreeStatus).toBe(true)
    })

    it('returns free row class for entfaellt status', () => {
        const ctx = {
            hasExamStatus: (MyTimetable as any).methods.hasExamStatus,
            hasFreeStatus: (MyTimetable as any).methods.hasFreeStatus,
        }
        const rowClass = (MyTimetable as any).methods.getStatusClass.call(ctx, { status: ['entfaellt'] })

        expect(rowClass).toEqual(['timetable-item--free'])
    })

    it('keeps both exam and free classes when statuses overlap', () => {
        const ctx = {
            hasExamStatus: (MyTimetable as any).methods.hasExamStatus,
            hasFreeStatus: (MyTimetable as any).methods.hasFreeStatus,
        }
        const rowClass = (MyTimetable as any).methods.getStatusClass.call(ctx, { status: ['pruefung', 'free'] })

        expect(rowClass).toEqual(['timetable-item--exam', 'timetable-item--free'])
    })

    it('contains a combined exam and free style override in the source', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/MyTimetable.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('.timetable-item--exam.timetable-item--free')
        expect(source).toContain('.timetable-grid-item.timetable-item--exam.timetable-item--free')
    })

    it('opens timetable courses on the students panel without selecting a date', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/MyTimetable.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain("'show_students'")
        expect(source).toContain("'show_dates'")
        expect(source).toContain('this.selected_courseDate = null')
        expect(source).toContain('this.show_students = true')
        expect(source).toContain('this.show_dates = false')
        expect(source).toContain("panel: 'students'")
        expect(source).toContain('delete query.date')
        expect(source).not.toContain('if (date?.id) query.date = String(date.id)')
    })
})
