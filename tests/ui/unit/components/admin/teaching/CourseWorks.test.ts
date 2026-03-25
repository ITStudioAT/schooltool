import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import CourseWorks from '@/pages/admin/teaching/overview/components/CourseWorks.vue'

describe('CourseWorks defaults', () => {
    it('defaults student sort mode to name', () => {
        const data = (CourseWorks as any).data.call({
            emptyWorkForm: () => ({}),
        })

        expect(data.students_sort_mode).toBe('last_name_first_name')
    })
})

describe('CourseWorks title rendering', () => {
    it('renders work title in second line in Arbeiten list', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="text-caption text-medium-emphasis work-type-first-line"')
        expect(source).toContain('class="text-body-2 work-title-second-line"')
        expect(source).not.toContain('class="text-caption text-medium-emphasis work-title-second-line"')
        expect(source).not.toContain('<span v-if="work.title || work.description">– {{ work.title || work.description }}</span>')
    })
})

describe('CourseWorks course-specific schema', () => {
    it('prefers the selected course schema snapshot over the global teaching store', () => {
        const computed = (CourseWorks as any).computed
        const ctx: Record<string, unknown> = {
            selected_course: {
                teaching_schema_id: 'schema-teacher',
                teacher_teaching_schema: {
                    id: 'schema-teacher',
                    works: [{ short_name: 'MA', name: 'Mitarbeit' }],
                    grading: { semester_count: 2 },
                },
            },
            teachingStore: {
                schemaById: () => ({ id: 'schema-global', works: [{ short_name: 'AK', name: 'Auftrag' }], grading: { semester_count: 1 } }),
            },
        }

        ctx.selectedCourseSchema = computed.selectedCourseSchema.call(ctx)

        expect((ctx.selectedCourseSchema as any).id).toBe('schema-teacher')
        expect(computed.teachingWorks.call(ctx)).toEqual([{ short_name: 'MA', name: 'Mitarbeit' }])
        expect(computed.semesterCount.call(ctx)).toBe(2)
    })
})

describe('CourseWorks points mode', () => {
    it('shows the Punkte action only for non-group work types with points-note configuration', () => {
        const computed = (CourseWorks as any).computed
        const ctx: Record<string, unknown> = {
            work_form: { type: 'SA', is_group_work: false },
            teachingWorks: [
                {
                    short_name: 'SA',
                    name: 'Schularbeit',
                    points_note_enabled: true,
                    points_table: [{ grade: '1', min_points: 40 }],
                    points_sonst_grade: '5',
                },
            ],
            workConfigForType(type: string) {
                return (this.teachingWorks as Array<Record<string, unknown>>).find((work) => work.short_name === type) ?? null
            },
            workSupportsPoints: (work: Record<string, unknown>) => Boolean(work?.points_note_enabled),
        }

        ctx.selectedTypeWork = computed.selectedTypeWork.call(ctx)

        expect(computed.selectedTypeSupportsPoints.call(ctx)).toBe(true)
        expect(computed.selectedTypeSupportsPoints.call({
            ...ctx,
            work_form: { type: 'SA', is_group_work: true },
        })).toBe(false)
    })

    it('derives the grade from entered points for per-work points tables', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            work_form: {
                groups: [{
                    student_ids: [11],
                    grades: {},
                    comments: {},
                    points: {},
                }],
            },
            selectedTypeWork: {
                short_name: 'SA',
                points_note_enabled: true,
                points_table: [
                    { grade: '1', min_points: 40 },
                    { grade: '2', min_points: 35 },
                    { grade: '3', min_points: 30 },
                    { grade: '4', min_points: 25 },
                ],
                points_sonst_grade: '5',
            },
            normalizeNumericInput: methods.normalizeNumericInput,
            normalizePointsNumber: methods.normalizePointsNumber,
            workSupportsPoints: methods.workSupportsPoints,
            gradeFromPointsForWork: methods.gradeFromPointsForWork,
        }

        methods.setStudentPoints.call(ctx, 0, 11, '37,5')

        expect(ctx.work_form.groups[0].points[11]).toBe('37,5')
        expect(ctx.work_form.groups[0].grades[11]).toBe('2')
    })

    it('serializes numeric points with student ids for saving', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            normalizeNumericInput: methods.normalizeNumericInput,
            normalizePointsNumber: methods.normalizePointsNumber,
        }

        const result = methods.serializeGroupPoints.call(ctx, {
            student_ids: [11, 12, 13],
            points: {
                11: '42',
                12: '37,5',
                13: '',
            },
        })

        expect(result).toEqual([
            { student_id: 11, points: 42 },
            { student_id: 12, points: 37.5 },
        ])
    })
})
