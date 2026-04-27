import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
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

    it('shows the assigned curriculum in the dates card header', () => {
        const computed = (CourseDates as any).computed
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')
        const ctx: Record<string, unknown> = {
            selected_course: {
                teaching_curriculum: {
                    id: 10,
                    title: 'Deutsch 6',
                },
            },
        }

        expect(computed.selectedCourseCurriculumTitle.call(ctx)).toBe('Deutsch 6')
        expect(source).toContain('v-if="selectedCourseCurriculumTitle"')
        expect(source).toContain('class="course-date-curriculum-chip"')
        expect(source).toContain('Curriculum:')
        expect(source).toContain('class="course-date-curriculum-chip__title">{{ selectedCourseCurriculumTitle }}</span>')
        expect(source).toContain('.course-date-curriculum-chip :deep(.v-chip__content)')
        expect(source).toContain('overflow-wrap: anywhere;')
        expect(source).toContain('class="course-date-curriculum-inline pl-1 pr-2"')
        expect(source).toContain(':key="`${courseDate.id}-inline-${entry}`"')
        expect(source).toContain('await this.loadSelectedCourseCurriculumDetail()')
    })

    it('does not show a curriculum label when none is assigned', () => {
        const computed = (CourseDates as any).computed
        const ctx: Record<string, unknown> = {
            selected_course: {
                teaching_curriculum: null,
            },
        }

        expect(computed.selectedCourseCurriculumTitle.call(ctx)).toBe('')
    })

    it('resolves matching curriculum entries for a course date and prioritizes free weeks', () => {
        const methods = (CourseDates as any).methods
        const ctx: Record<string, any> = {
            config: {
                selected_schoolyear: {
                    from: '2025-09-01',
                    until: '2025-09-30',
                },
            },
            selectedCourseCurriculumForContent: {
                free_weeks: ['2025-09-15'],
                topics: [
                    {
                        id: 'topic-all-weeks',
                        title: 'Schreibuebungen',
                        assignment_type: 'all_weeks',
                        week_keys: [],
                        units: [],
                    },
                    {
                        id: 'topic-month',
                        title: 'Monatsprojekt',
                        assignment_type: 'month',
                        month_key: '2025-09',
                        units: [],
                    },
                    {
                        id: 'topic-search',
                        title: 'Suchmaschinen und Internetrecherche - Teil 1',
                        assignment_type: 'none',
                        week_keys: [],
                        units: [
                            {
                                id: 'unit-project',
                                title: 'Projekt: Internetrecherche',
                                assignment_type: 'weeks',
                                week_keys: ['2025-09-08'],
                            },
                        ],
                    },
                ],
            },
        }

        Object.assign(ctx, methods)

        expect(methods.curriculumEntriesForCourseDate.call(ctx, { date: '2025-09-08' })).toEqual([
            'Schreibuebungen',
            'Monatsprojekt',
            'Suchmaschinen und Internetrecherche - Teil 1: Projekt: Internetrecherche',
        ])
        expect(methods.curriculumEntriesForCourseDate.call(ctx, { date: '2025-09-15' })).toEqual(['Frei'])
    })

    it('enters saving mode before waiting for the date content update request', async () => {
        const methods = (CourseDates as any).methods
        let continueNextTick: (() => void) | null = null
        let resolveUpdate: ((value: unknown) => void) | null = null

        const update = vi.fn(() => new Promise((resolve) => {
            resolveUpdate = resolve
        }))
        const index = vi.fn().mockResolvedValue(true)

        const ctx: Record<string, any> = {
            action: 'edit_course_date_content',
            editing_content_id: 7,
            saving_content_id: null,
            content_drafts: { 7: '<p>Neuer Inhalt</p>' },
            courseDateStore: { update },
            courseStore: { index },
            show_contents: true,
            collapsed_content_ids: [7],
            expanded_content_ids: [],
            isSavingContent: false,
            $nextTick: vi.fn(() => new Promise<void>((resolve) => {
                continueNextTick = resolve
            })),
        }

        const savePromise = methods.saveContent.call(ctx, {
            id: 7,
            date: '2026-04-23',
        })

        expect(ctx.saving_content_id).toBe(7)
        expect(ctx.$nextTick).toHaveBeenCalledTimes(1)
        expect(update).not.toHaveBeenCalled()

        continueNextTick?.()
        await Promise.resolve()

        expect(update).toHaveBeenCalledWith({
            id: 7,
            date: '2026-04-23',
            content: '<p>Neuer Inhalt</p>',
        })

        resolveUpdate?.(true)
        await savePromise

        expect(index).toHaveBeenCalledTimes(1)
        expect(ctx.editing_content_id).toBeNull()
        expect(ctx.action).toBe('')
        expect(ctx.saving_content_id).toBeNull()
        expect(ctx.collapsed_content_ids).toEqual([])
    })
})
