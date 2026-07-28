import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import MyInfos from '@/pages/admin/teaching/overview/components/MyInfos.vue'

describe('MyInfos counts', () => {
    it('excludes canceled students from myStudentCount', () => {
        const ctx = {
            myCourses: [
                {
                    students_info: [
                        { id: 1, canceled_at: null },
                        { id: 2, canceled_at: '2026-02-16 12:00:00' },
                    ],
                },
                {
                    students_info: [
                        { id: 1, canceled_at: null },
                        { id: 3, canceled_at: '' },
                    ],
                },
            ],
        }

        const count = (MyInfos as any).computed.myStudentCount.call(ctx)
        expect(count).toBe(2)
    })
})

describe('MyInfos next lesson countdown', () => {
    function toLocalDateString(value: Date): string {
        const year = value.getFullYear()
        const month = String(value.getMonth() + 1).padStart(2, '0')
        const day = String(value.getDate()).padStart(2, '0')
        return `${year}-${month}-${day}`
    }

    it('computes countdown and omits zero-value leading units without seconds', () => {
        const computed = (MyInfos as any).computed
        const methods = (MyInfos as any).methods
        const now = new Date(2026, 2, 2, 7, 0, 0)

        const ctx: Record<string, unknown> = {
            nowTs: now.getTime(),
            myCourses: [
                {
                    course_dates: [
                        { date: toLocalDateString(now), hours: [1] },
                    ],
                },
            ],
            school_hours: [{ hour: 1, from: '08:00:00', until: '08:50:00' }],
            isFreeCourseDate: methods.isFreeCourseDate,
            lessonStartFromHour: methods.lessonStartFromHour,
            padTwo: methods.padTwo,
        }

        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)
        ctx.nextLessonStartAt = computed.nextLessonStartAt.call(ctx)

        const countdown = computed.nextLessonCountdownLabel.call(ctx)

        expect(countdown).toBe('01h')
    })

    it('shows < 1m when no larger unit is needed', () => {
        const computed = (MyInfos as any).computed
        const methods = (MyInfos as any).methods
        const now = new Date(2026, 2, 2, 7, 59, 45)

        const ctx: Record<string, unknown> = {
            nowTs: now.getTime(),
            myCourses: [
                {
                    course_dates: [
                        { date: toLocalDateString(now), hours: [1] },
                    ],
                },
            ],
            school_hours: [{ hour: 1, from: '08:00:00', until: '08:50:00' }],
            isFreeCourseDate: methods.isFreeCourseDate,
            lessonStartFromHour: methods.lessonStartFromHour,
            padTwo: methods.padTwo,
        }

        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)
        ctx.nextLessonStartAt = computed.nextLessonStartAt.call(ctx)

        const countdown = computed.nextLessonCountdownLabel.call(ctx)

        expect(countdown).toBe('< 1m')
    })

    it('returns fallback when no future lesson exists', () => {
        const computed = (MyInfos as any).computed
        const methods = (MyInfos as any).methods
        const now = new Date(2026, 2, 2, 10, 0, 0)

        const ctx: Record<string, unknown> = {
            nowTs: now.getTime(),
            myCourses: [
                {
                    course_dates: [
                        { date: '2026-03-01', hours: [1] },
                    ],
                },
            ],
            school_hours: [{ hour: 1, from: '08:00:00', until: '08:50:00' }],
            isFreeCourseDate: methods.isFreeCourseDate,
            lessonStartFromHour: methods.lessonStartFromHour,
            padTwo: methods.padTwo,
        }

        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)
        ctx.nextLessonStartAt = computed.nextLessonStartAt.call(ctx)

        const countdown = computed.nextLessonCountdownLabel.call(ctx)

        expect(countdown).toBe('–')
    })
})

describe('MyInfos active lesson countdown', () => {
    function toLocalDateString(value: Date): string {
        const year = value.getFullYear()
        const month = String(value.getMonth() + 1).padStart(2, '0')
        const day = String(value.getDate()).padStart(2, '0')
        return `${year}-${month}-${day}`
    }

    it('hides hour in active lesson countdown when hour is zero', () => {
        const computed = (MyInfos as any).computed
        const methods = (MyInfos as any).methods
        const now = new Date(2026, 2, 2, 8, 10, 0)

        const ctx: Record<string, unknown> = {
            nowTs: now.getTime(),
            myCourses: [
                {
                    course_dates: [
                        { date: toLocalDateString(now), hours: [1] },
                    ],
                },
            ],
            school_hours: [{ hour: 1, from: '08:00:00', until: '08:50:00' }],
            isFreeCourseDate: methods.isFreeCourseDate,
            lessonStartFromHour: methods.lessonStartFromHour,
            lessonEndFromHour: methods.lessonEndFromHour,
            padTwo: methods.padTwo,
        }

        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)
        ctx.activeLessonEndAt = computed.activeLessonEndAt.call(ctx)

        const label = computed.activeLessonEndsInDisplay.call(ctx)

        expect(label).toBe('40m')
    })

    it('returns fallback marker when no lesson is active', () => {
        const computed = (MyInfos as any).computed
        const methods = (MyInfos as any).methods
        const now = new Date(2026, 2, 2, 11, 0, 0)

        const ctx: Record<string, unknown> = {
            nowTs: now.getTime(),
            myCourses: [
                {
                    course_dates: [
                        { date: toLocalDateString(now), hours: [1] },
                    ],
                },
            ],
            school_hours: [{ hour: 1, from: '08:00:00', until: '08:50:00' }],
            isFreeCourseDate: methods.isFreeCourseDate,
            lessonStartFromHour: methods.lessonStartFromHour,
            lessonEndFromHour: methods.lessonEndFromHour,
            padTwo: methods.padTwo,
        }

        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)
        ctx.activeLessonEndAt = computed.activeLessonEndAt.call(ctx)

        const label = computed.activeLessonEndsInDisplay.call(ctx)

        expect(label).toBe('–')
    })

    it('contains the requested row label text', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/MyInfos.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('Untericht endet in:')
        expect(source).not.toContain("second: '2-digit'")

        const indexEnds = source.indexOf('Untericht endet in:')
        const indexHeute = source.indexOf('Heute')
        expect(indexEnds).toBeGreaterThan(-1)
        expect(indexHeute).toBeGreaterThan(-1)
        expect(indexEnds).toBeGreaterThan(indexHeute)
    })
})

describe('MyInfos lesson countdown visibility', () => {
    it('hides lesson countdown rows when no school hours are configured', () => {
        const computed = (MyInfos as any).computed

        const ctx: Record<string, unknown> = {
            school_hours: [],
            myCourses: [
                {
                    course_dates: [{ date: '2026-03-02', hours: [1] }],
                },
            ],
        }

        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)
        ctx.hasConfiguredSchoolHours = computed.hasConfiguredSchoolHours.call(ctx)
        ctx.hasCourseHoursInMyCourses = computed.hasCourseHoursInMyCourses.call(ctx)

        expect(computed.showLessonCountdownRows.call(ctx)).toBe(false)
    })

    it('hides lesson countdown rows when courses have no hours', () => {
        const computed = (MyInfos as any).computed

        const ctx: Record<string, unknown> = {
            school_hours: [{ hour: 1, from: '08:00:00', until: '08:50:00' }],
            myCourses: [
                {
                    course_dates: [{ date: '2026-03-02', hours: [] }],
                },
            ],
        }

        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)
        ctx.hasConfiguredSchoolHours = computed.hasConfiguredSchoolHours.call(ctx)
        ctx.hasCourseHoursInMyCourses = computed.hasCourseHoursInMyCourses.call(ctx)

        expect(computed.showLessonCountdownRows.call(ctx)).toBe(false)
    })

    it('shows "Untericht endet in" row only when a lesson is active', () => {
        const computed = (MyInfos as any).computed
        const methods = (MyInfos as any).methods
        const now = new Date(2026, 2, 2, 8, 10, 0)

        const activeCtx: Record<string, unknown> = {
            nowTs: now.getTime(),
            school_hours: [{ hour: 1, from: '08:00:00', until: '08:50:00' }],
            myCourses: [{ course_dates: [{ date: '2026-03-02', hours: [1] }] }],
            isFreeCourseDate: methods.isFreeCourseDate,
            lessonStartFromHour: methods.lessonStartFromHour,
            lessonEndFromHour: methods.lessonEndFromHour,
        }
        activeCtx.schoolHoursByHour = computed.schoolHoursByHour.call(activeCtx)
        activeCtx.hasConfiguredSchoolHours = computed.hasConfiguredSchoolHours.call(activeCtx)
        activeCtx.hasCourseHoursInMyCourses = computed.hasCourseHoursInMyCourses.call(activeCtx)
        activeCtx.showLessonCountdownRows = computed.showLessonCountdownRows.call(activeCtx)
        activeCtx.activeLessonEndAt = computed.activeLessonEndAt.call(activeCtx)

        expect(computed.showActiveLessonEndCountdown.call(activeCtx)).toBe(true)

        const inactiveCtx: Record<string, unknown> = {
            nowTs: new Date(2026, 2, 2, 11, 0, 0).getTime(),
            school_hours: [{ hour: 1, from: '08:00:00', until: '08:50:00' }],
            myCourses: [{ course_dates: [{ date: '2026-03-02', hours: [1] }] }],
            isFreeCourseDate: methods.isFreeCourseDate,
            lessonStartFromHour: methods.lessonStartFromHour,
            lessonEndFromHour: methods.lessonEndFromHour,
        }
        inactiveCtx.schoolHoursByHour = computed.schoolHoursByHour.call(inactiveCtx)
        inactiveCtx.hasConfiguredSchoolHours = computed.hasConfiguredSchoolHours.call(inactiveCtx)
        inactiveCtx.hasCourseHoursInMyCourses = computed.hasCourseHoursInMyCourses.call(inactiveCtx)
        inactiveCtx.showLessonCountdownRows = computed.showLessonCountdownRows.call(inactiveCtx)
        inactiveCtx.activeLessonEndAt = computed.activeLessonEndAt.call(inactiveCtx)

        expect(computed.showActiveLessonEndCountdown.call(inactiveCtx)).toBe(false)
    })
})
