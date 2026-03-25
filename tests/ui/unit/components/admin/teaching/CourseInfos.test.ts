import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import CourseInfos from '@/pages/admin/teaching/overview/components/CourseInfos.vue'

describe('CourseInfos representative countdowns', () => {
    function toLocalDateString(value: Date): string {
        const year = value.getFullYear()
        const month = String(value.getMonth() + 1).padStart(2, '0')
        const day = String(value.getDate()).padStart(2, '0')
        return `${year}-${month}-${day}`
    }

    it('formats "Findet statt in" with day/hour/minute and omits zero day/hour', () => {
        const computed = (CourseInfos as any).computed
        const methods = (CourseInfos as any).methods
        const now = new Date(2026, 2, 1, 7, 0, 0)

        const ctx: Record<string, unknown> = {
            nowTs: now.getTime(),
            selected_course: {
                course_dates: [
                    { date: toLocalDateString(new Date(2026, 2, 2)), hours: [1] },
                ],
            },
            school_hours: [{ hour: 1, from: '08:15:00', until: '09:05:00' }],
            lessonStartFromHour: methods.lessonStartFromHour,
            padTwo: methods.padTwo,
        }

        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)
        ctx.nextCourseStartAt = computed.nextCourseStartAt.call(ctx)

        expect(computed.startsInLabel.call(ctx)).toBe('1d 01h 15m')
    })

    it('formats "End in" and hides hour when it is zero', () => {
        const computed = (CourseInfos as any).computed
        const methods = (CourseInfos as any).methods
        const now = new Date(2026, 2, 2, 8, 10, 0)

        const ctx: Record<string, unknown> = {
            nowTs: now.getTime(),
            selected_course: {
                course_dates: [
                    { date: toLocalDateString(now), hours: [1] },
                ],
            },
            school_hours: [{ hour: 1, from: '08:00:00', until: '08:50:00' }],
            lessonStartFromHour: methods.lessonStartFromHour,
            lessonEndFromHour: methods.lessonEndFromHour,
            padTwo: methods.padTwo,
        }

        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)
        ctx.activeCourseEndAt = computed.activeCourseEndAt.call(ctx)

        expect(computed.endsInLabel.call(ctx)).toBe('40m')
    })

    it('uses active representative state for label/value/class', () => {
        const computed = (CourseInfos as any).computed
        const methods = (CourseInfos as any).methods
        const now = new Date(2026, 2, 2, 8, 10, 0)

        const ctx: Record<string, unknown> = {
            nowTs: now.getTime(),
            selected_course: {
                course_dates: [
                    { date: toLocalDateString(now), hours: [1] },
                ],
            },
            school_hours: [{ hour: 1, from: '08:00:00', until: '08:50:00' }],
            lessonStartFromHour: methods.lessonStartFromHour,
            lessonEndFromHour: methods.lessonEndFromHour,
            padTwo: methods.padTwo,
        }

        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)
        ctx.activeCourseEndAt = computed.activeCourseEndAt.call(ctx)
        ctx.endsInLabel = computed.endsInLabel.call(ctx)
        ctx.startsInLabel = computed.startsInLabel.call(ctx)
        ctx.isCourseActiveNow = computed.isCourseActiveNow.call(ctx)

        expect(computed.representativeCountdownLabel.call(ctx)).toBe('Endet in:')
        expect(computed.representativeCountdownValue.call(ctx)).toBe('40m')
        expect(computed.representativeCountdownValueClass.call(ctx)).toBe('text-body-1 font-weight-medium text-primary')
    })

    it('uses upcoming representative state for label/value/class when not active', () => {
        const computed = (CourseInfos as any).computed
        const methods = (CourseInfos as any).methods
        const now = new Date(2026, 2, 1, 7, 0, 0)

        const ctx: Record<string, unknown> = {
            nowTs: now.getTime(),
            selected_course: {
                course_dates: [
                    { date: toLocalDateString(new Date(2026, 2, 2)), hours: [1] },
                ],
            },
            school_hours: [{ hour: 1, from: '08:15:00', until: '09:05:00' }],
            lessonStartFromHour: methods.lessonStartFromHour,
            lessonEndFromHour: methods.lessonEndFromHour,
            padTwo: methods.padTwo,
        }

        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)
        ctx.activeCourseEndAt = computed.activeCourseEndAt.call(ctx)
        ctx.nextCourseStartAt = computed.nextCourseStartAt.call(ctx)
        ctx.endsInLabel = computed.endsInLabel.call(ctx)
        ctx.startsInLabel = computed.startsInLabel.call(ctx)
        ctx.isCourseActiveNow = computed.isCourseActiveNow.call(ctx)

        expect(computed.representativeCountdownLabel.call(ctx)).toBe('Findet statt in:')
        expect(computed.representativeCountdownValue.call(ctx)).toBe('1d 01h 15m')
        expect(computed.representativeCountdownValueClass.call(ctx)).toBe('text-body-2 font-weight-medium')
    })

    it('renders representative labels in template', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseInfos.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('Findet statt in:')
        expect(source).toContain('Endet in:')
        expect(source).toContain('{{ representativeCountdownLabel }}')
        expect(source).toContain('{{ representativeCountdownValue }}')
    })
})

describe('CourseInfos course-specific definitions', () => {
    it('prefers the selected course schema and notification definitions', () => {
        const computed = (CourseInfos as any).computed
        const ctx: Record<string, unknown> = {
            selected_course: {
                teaching_schema_id: 'schema-teacher',
                teacher_teaching_schema: { id: 'schema-teacher', name: 'Lehrkraft-Schema' },
                teacher_teaching_notifications: [{ short_name: 'INF', name: 'Info Lehrkraft' }],
            },
            teachingStore: {
                schemaById: () => ({ id: 'schema-global', name: 'Global-Schema' }),
                settings: { teaching_notifications: [{ short_name: 'INF', name: 'Info Global' }] },
            },
        }

        ctx.selectedCourseSchema = computed.selectedCourseSchema.call(ctx)

        expect((ctx.selectedCourseSchema as any)).toMatchObject({ id: 'schema-teacher', name: 'Lehrkraft-Schema' })
        expect(computed.schemaName.call(ctx)).toBe('Schema: Lehrkraft-Schema')

        const notificationTypes = computed.notificationTypesByShort.call(ctx)
        expect(notificationTypes.get('INF')).toBe('Info Lehrkraft')
    })
})
