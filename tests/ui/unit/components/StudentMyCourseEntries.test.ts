import { describe, expect, it, vi } from 'vitest'
import MyCourse from '@/pages/homepage/student/overview/myCourse/MyCourse.vue'

describe('Student MyCourse entry area categories', () => {
    it('separates assessment, behaviour and additional entries using the configured category', async () => {
        const entries = [
            { id: 1, type: 'D', category: 'Verhalten', comment: 'Disziplinarbogen' },
            { id: 2, type: 'E', category: 'Verhalten', comment: 'Ermahnung' },
            { id: 3, type: 'FW', category: 'Weitere' },
            { id: 4, type: 'LA', category: 'Weitere' },
            { id: 5, type: 'MA', category: 'Benotung', grade: '-' },
            { id: 6, type: 'D', category: 'Benotung', grade: '1' },
            { id: 7, type: 'Legacy', grade: '2' },
        ]
        const ctx = {
            courseId: 18,
            loadingEntries: false,
            entries: [],
            entryAreaBehaviourEntries: [],
            additionalEntries: [],
            courseStore: { entries, getCourseEntries: vi.fn().mockResolvedValue(true) },
        }

        await (MyCourse as any).methods.loadEntries.call(ctx)

        expect(ctx.entries).toEqual([entries[4], entries[5], entries[6]])
        expect(ctx.entryAreaBehaviourEntries).toEqual([entries[0], entries[1]])
        expect(ctx.additionalEntries).toEqual([entries[2], entries[3]])
        expect(ctx.loadingEntries).toBe(false)
    })

    it('clears each category when a refresh returns no entries', async () => {
        const ctx = {
            courseId: 18,
            loadingEntries: false,
            entries: [{ id: 1 }],
            entryAreaBehaviourEntries: [{ id: 2 }],
            additionalEntries: [{ id: 3 }],
            courseStore: { entries: [], getCourseEntries: vi.fn().mockResolvedValue(true) },
        }

        await (MyCourse as any).methods.loadEntries.call(ctx)

        expect(ctx.entries).toEqual([])
        expect(ctx.entryAreaBehaviourEntries).toEqual([])
        expect(ctx.additionalEntries).toEqual([])
    })
})

describe('Student MyCourse entry grade chip color', () => {
    it('returns error color for NA grade chips', () => {
        const color = (MyCourse as any).methods.entryGradeChipColor({ grade: 'NA' })

        expect(color).toBe('error')
    })

    it('returns success color for non-NA grades', () => {
        const color = (MyCourse as any).methods.entryGradeChipColor({ grade: '2' })

        expect(color).toBe('success')
    })

    it('returns error color for open grades', () => {
        const color = (MyCourse as any).methods.entryGradeChipColor({ grade: '' })

        expect(color).toBe('error')
    })

    it('resolves the grading category label for an entry type from the teaching schema', () => {
        const methods = (MyCourse as any).methods
        const label = methods.entryCategoryLabel.call({
            entryCategory: methods.entryCategory,
            schemaGradingCategories: [
                { name: 'Fernunterricht', index: 0, works: ['FU'] },
                { name: 'Projekt', index: 1, works: ['PR', 'TE'] },
            ],
        }, { type: 'TE' })

        expect(label).toBe('Projekt')
    })

    it('builds a stable category class from the resolved grading category', () => {
        const methods = (MyCourse as any).methods
        const cssClass = methods.entryCategoryClass.call({
            entryCategory: () => ({ name: 'Projekt', index: 5 }),
        }, { type: 'TE' })

        expect(cssClass).toBe('entry-category-group--category-1')
    })

    it('resolves the category evaluation value for a semester-aware entry', () => {
        const methods = (MyCourse as any).methods
        const value = methods.entryCategoryEvaluationValue.call({
            entryCategory: () => ({ name: 'Projekt', categoryEvaluationEnabled: true }),
            courseCategoryEvaluations: [
                { semester: 1, category_name: 'Projekt', value: 'Offen' },
                { semester: 2, category_name: 'Projekt', value: 'Bestanden' },
            ],
            entrySemester: () => 2,
            categoryEvaluationDefaultValue: 'Offen',
        }, { type: 'TE', date: '2026-03-10' })

        expect(value).toBe('Bestanden')
    })

    it('falls back to the configured default category evaluation value', () => {
        const methods = (MyCourse as any).methods
        const value = methods.entryCategoryEvaluationValue.call({
            entryCategory: () => ({ name: 'Projekt', categoryEvaluationEnabled: true }),
            courseCategoryEvaluations: [],
            entrySemester: () => 2,
            categoryEvaluationDefaultValue: 'Offen',
        }, { type: 'TE', date: '2026-03-10' })

        expect(value).toBe('Offen')
    })

    it('does not show the category evaluation chip for "Keine Bewertung"', () => {
        const methods = (MyCourse as any).methods

        expect(methods.shouldShowCategoryEvaluationValue('Keine Bewertung')).toBe(false)
        expect(methods.shouldShowCategoryEvaluationValue('Bestanden')).toBe(true)
    })

    it('uses the configured color for the category evaluation chip', () => {
        const methods = (MyCourse as any).methods
        const color = methods.categoryEvaluationValueColor.call({
            categoryEvaluationValueItems: [
                { value: 'Offen', color: '#fb8c00' },
                { value: 'Bestanden', color: '#43a047' },
            ],
        }, 'Bestanden')

        expect(color).toBe('#43a047')
    })

    it('groups entries of the same category into one category block', () => {
        const methods = (MyCourse as any).methods
        const groups = methods.buildEntryGroups.call({
            entryCategory: (entry: any) => {
                if (entry.type === 'FU') {
                    return { name: 'Fernunterricht', categoryEvaluationEnabled: true }
                }

                return { name: 'Tests', categoryEvaluationEnabled: false }
            },
            entryCategoryEvaluationValue: (entry: any) => entry.type === 'FU' ? 'Bestanden' : '',
            entryCategoryClass: (entry: any) => entry.type === 'FU' ? 'entry-category-group--category-0' : 'entry-category-group--category-1',
        }, [
            { id: 1, type: 'FU' },
            { id: 2, type: 'FU' },
            { id: 3, type: 'TE' },
        ])

        expect(groups).toEqual([
            {
                key: 'category-Fernunterricht',
                categoryName: 'Fernunterricht',
                categoryEvaluationEnabled: true,
                categoryEvaluationValue: 'Bestanden',
                categoryClass: 'entry-category-group--category-0',
                entries: [{ id: 1, type: 'FU' }, { id: 2, type: 'FU' }],
            },
            {
                key: 'category-Tests',
                categoryName: 'Tests',
                categoryEvaluationEnabled: false,
                categoryEvaluationValue: '',
                categoryClass: 'entry-category-group--category-1',
                entries: [{ id: 3, type: 'TE' }],
            },
        ])
    })

    it('does not build semester headers when the course has only one semester', () => {
        const groupedEntries = (MyCourse as any).computed.groupedEntries.call({
            sortedEntries: [
                { id: 1, type: 'FU', date: '2026-03-20' },
                { id: 2, type: 'FU', date: '2026-03-13' },
            ],
            hasTwoSemesters: false,
            buildEntryGroups: () => [{
                key: 'category-Fernunterricht',
                categoryName: 'Fernunterricht',
                categoryEvaluationEnabled: true,
                categoryEvaluationValue: 'Bestanden',
                categoryClass: 'entry-category-group--category-0',
                entries: [{ id: 1 }, { id: 2 }],
            }],
        })

        expect(groupedEntries).toEqual([
            {
                kind: 'group',
                key: 'group-category-Fernunterricht',
                group: {
                    key: 'category-Fernunterricht',
                    categoryName: 'Fernunterricht',
                    categoryEvaluationEnabled: true,
                    categoryEvaluationValue: 'Bestanden',
                    categoryClass: 'entry-category-group--category-0',
                    entries: [{ id: 1 }, { id: 2 }],
                },
                stripe: 0,
            },
        ])
    })

    it('omits empty semester headings from the combined feedback page', () => {
        const entry = { id: 1, type: 'MA', date: '2026-10-12' }
        const grouped = (MyCourse as any).computed.groupedEntries.call({
            sortedEntries: [entry],
            hasTwoSemesters: true,
            selectedSemester: 3,
            semesterBoundary: '2027-02-01',
            normalizeDateKey: (date: string) => date,
            buildEntryGroups: (entries: any[]) => entries.map((item) => ({ key: item.id, entries: [item] })),
        })

        expect(grouped.filter((item: any) => item.kind === 'header')).toEqual([
            { kind: 'header', key: 'header-sem1', label: '1. Semester' },
        ])
        expect(grouped.filter((item: any) => item.kind === 'group')[0].group.entries).toEqual([entry])
    })

    it('loads entries again when the refresh action is triggered', async () => {
        const methods = (MyCourse as any).methods
        const getCourseEntries = vi.fn().mockResolvedValue(true)
        const ctx: any = {
            loadingEntries: false,
            courseId: 11,
            courseStore: {
                getCourseEntries,
                entries: [{ id: 1, type: 'FU' }],
            },
            entries: [],
        }

        await methods.loadEntries.call(ctx)

        expect(getCourseEntries).toHaveBeenCalledWith(11)
        expect(ctx.entries).toEqual([{ id: 1, type: 'FU' }])
        expect(ctx.loadingEntries).toBe(false)
    })

    it('uses the teacher-selected calculated grade columns for two-semester courses', () => {
        const computed = (MyCourse as any).computed
        const ctx = {
            course: {
                teacher_teaching_student_grade_columns: {
                    show_sem1: true,
                    show_sem2: false,
                    show_year: true,
                },
            },
            hasTwoSemesters: true,
            calculatedGrades: {
                sem1: '2',
                sem2: '3',
                year: '2',
            },
        }

        ctx.calculatedGradeValues = computed.calculatedGradeValues.call(ctx)
        ctx.teacherCalculatedGradeColumns = computed.teacherCalculatedGradeColumns.call(ctx)
        ctx.showCalculatedGradeSem1 = computed.showCalculatedGradeSem1.call(ctx)
        ctx.showCalculatedGradeSem2 = computed.showCalculatedGradeSem2.call(ctx)
        ctx.showCalculatedGradeYear = computed.showCalculatedGradeYear.call(ctx)

        expect(ctx.teacherCalculatedGradeColumns).toEqual({
            show_sem1: true,
            show_sem2: false,
            show_year: true,
        })
        expect(ctx.calculatedGradeValues).toEqual({
            sem1: '2',
            sem2: '3',
            year: '2',
        })
        expect(ctx.showCalculatedGradeSem1).toBe(true)
        expect(ctx.showCalculatedGradeSem2).toBe(false)
        expect(ctx.showCalculatedGradeYear).toBe(true)
        expect(computed.showCalculatedGradesSection.call(ctx)).toBe(true)
    })

    it('hides the calculated grades section when no teacher column is enabled', () => {
        const computed = (MyCourse as any).computed
        const ctx = {
            course: {
                teacher_teaching_student_grade_columns: {
                    show_sem1: false,
                    show_sem2: false,
                    show_year: false,
                },
            },
            hasTwoSemesters: true,
            calculatedGrades: {
                sem1: '2',
                sem2: '3',
                year: '2',
            },
        }

        ctx.calculatedGradeValues = computed.calculatedGradeValues.call(ctx)
        ctx.teacherCalculatedGradeColumns = computed.teacherCalculatedGradeColumns.call(ctx)
        ctx.showCalculatedGradeSem1 = computed.showCalculatedGradeSem1.call(ctx)
        ctx.showCalculatedGradeSem2 = computed.showCalculatedGradeSem2.call(ctx)
        ctx.showCalculatedGradeYear = computed.showCalculatedGradeYear.call(ctx)

        expect(computed.showCalculatedGradesSection.call(ctx)).toBe(false)
    })

    it('keeps selected calculated grade columns visible even when a value is not yet available', () => {
        const computed = (MyCourse as any).computed
        const ctx = {
            course: {
                teacher_teaching_student_grade_columns: {
                    show_sem1: true,
                    show_sem2: true,
                    show_year: true,
                },
            },
            hasTwoSemesters: true,
            calculatedGrades: {
                sem1: '2',
                sem2: null,
                year: null,
            },
        }

        ctx.calculatedGradeValues = computed.calculatedGradeValues.call(ctx)
        ctx.teacherCalculatedGradeColumns = computed.teacherCalculatedGradeColumns.call(ctx)
        ctx.showCalculatedGradeSem1 = computed.showCalculatedGradeSem1.call(ctx)
        ctx.showCalculatedGradeSem2 = computed.showCalculatedGradeSem2.call(ctx)
        ctx.showCalculatedGradeYear = computed.showCalculatedGradeYear.call(ctx)

        expect(ctx.showCalculatedGradeSem1).toBe(true)
        expect(ctx.showCalculatedGradeSem2).toBe(true)
        expect(ctx.showCalculatedGradeYear).toBe(true)
        expect(computed.showCalculatedGradesSection.call(ctx)).toBe(true)
    })

    it('falls back to the legacy teacher calculated grade columns when the student-specific setting is missing', () => {
        const computed = (MyCourse as any).computed
        const ctx = {
            course: {
                teacher_teaching_grade_columns: {
                    show_sem1: true,
                    show_sem2: false,
                    show_year: false,
                },
            },
            hasTwoSemesters: true,
        }

        expect(computed.teacherCalculatedGradeColumns.call(ctx)).toEqual({
            show_sem1: true,
            show_sem2: false,
            show_year: false,
        })
    })

    it('provides null-safe calculated grade values when no calculation exists yet', () => {
        const computed = (MyCourse as any).computed
        const ctx = {
            calculatedGrades: null,
        }

        expect(computed.calculatedGradeValues.call(ctx)).toEqual({
            sem1: null,
            sem2: null,
            year: null,
        })
    })

    it('uses the course schema fallback for two-semester rendering', () => {
        const computed = (MyCourse as any).computed
        const ctx = {
            course: {
                teaching_schema: {
                    grading: {
                        semester_count: 2,
                    },
                },
                teaching_schema_id: 'schema-from-course',
            },
            user: {
                teaching_schemas: [],
            },
        }

        ctx.currentTeachingSchema = computed.currentTeachingSchema.call(ctx)

        expect(ctx.currentTeachingSchema).toEqual({
            grading: {
                semester_count: 2,
            },
        })
        expect(computed.hasTwoSemesters.call(ctx)).toBe(true)
    })
})
