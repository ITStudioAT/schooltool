import { describe, expect, it } from 'vitest'
import CourseDates from '@/pages/admin/teaching/overview/components/CourseDates.vue'

describe('CourseDates course-specific schema', () => {
    it('prefers the selected course schema snapshot for semester count', () => {
        const computed = (CourseDates as any).computed
        const ctx: Record<string, unknown> = {
            selected_course: {
                teaching_schema_id: 'schema-teacher',
                teacher_teaching_schema: {
                    id: 'schema-teacher',
                    grading: { semester_count: 2 },
                },
            },
            teachingStore: {
                schemaById: () => ({ id: 'schema-global', grading: { semester_count: 1 } }),
            },
        }

        ctx.selectedCourseSchema = computed.selectedCourseSchema.call(ctx)

        expect((ctx.selectedCourseSchema as any).id).toBe('schema-teacher')
        expect(computed.semesterCount.call(ctx)).toBe(2)
    })
})
