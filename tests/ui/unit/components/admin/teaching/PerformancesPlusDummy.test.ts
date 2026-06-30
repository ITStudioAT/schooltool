import { describe, expect, it, vi } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import PerformancesPlusDummy from '@/pages/admin/teaching/more/components/PerformancesPlusDummy.vue'

describe('PerformancesPlusDummy grade calculation', () => {
    function makeCtx(overrides: Record<string, unknown> = {}) {
        const methods = (PerformancesPlusDummy as any).methods

        return {
            grading: {
                categories: [
                    {
                        name: 'Test - Excel',
                        weight: 30,
                        works: [{ short_name: 'TE-E', factor: 100 }],
                    },
                ],
            },
            teachingWorks: [],
            normalizeGradeKey: methods.normalizeGradeKey,
            isNaGradeKey: methods.isNaGradeKey,
            isNbGradeKey: methods.isNbGradeKey,
            isGradedEntry: methods.isGradedEntry,
            effectiveGradeKeyForEntry: methods.effectiveGradeKeyForEntry,
            defaultGradeForWork: methods.defaultGradeForWork,
            workConfigForType: methods.workConfigForType,
            gradeValueForWork: methods.gradeValueForWork,
            numericValueFromGradeKey: methods.numericValueFromGradeKey,
            numericGradeValuesForEntries: methods.numericGradeValuesForEntries,
            pointsGradeForWork: methods.pointsGradeForWork,
            entryWorkTitle: () => '',
            formatShortDate: () => '',
            ...overrides,
        }
    }

    it('uses numeric entry grades directly for points work in category calculation', () => {
        const methods = (PerformancesPlusDummy as any).methods
        const work = {
            short_name: 'TE-E',
            calculation: 'points',
            grades: [
                { grade: '1', value: '1' },
                { grade: '2', value: '2' },
                { grade: '5', value: '5' },
            ],
            semester_points_table: [
                { grade: '1', min_points: 5 },
                { grade: '2', min_points: 4 },
                { grade: '3', min_points: 3 },
                { grade: '4', min_points: 2.5 },
            ],
            semester_points_sonst_grade: '5',
            default_grade: '',
        }
        const ctx = makeCtx({
            teachingWorks: [work],
        })

        const groups = methods.buildCategoryGroups.call(ctx, [
            {
                id: 1,
                type: 'TE-E',
                grade: '2',
                effective_grade: '',
                description: 'Excel - Grundlagen',
                date: '2026-05-01',
            },
        ])

        expect(groups[0].value).toBe(2)
        expect(groups[0].entries[0].displayGrade).toBe('2')
    })

    it('uses configured grade values for numeric grade keys in average work', () => {
        const methods = (PerformancesPlusDummy as any).methods
        const work = {
            short_name: 'PÜ',
            calculation: 'average',
            grades: [
                { grade: '++++', value: '1' },
                { grade: '+++', value: '2' },
                { grade: '++', value: '3' },
                { grade: '+', value: '4' },
                { grade: '0', value: '5' },
            ],
            default_grade: '',
        }
        const ctx = makeCtx({
            grading: {
                categories: [
                    {
                        name: 'Praktische Übungen',
                        weight: 33,
                        works: [{ short_name: 'PÜ', factor: 100 }],
                    },
                ],
            },
            teachingWorks: [work],
        })

        const groups = methods.buildCategoryGroups.call(ctx, [
            {
                id: 1,
                type: 'PÜ',
                grade: '0',
                effective_grade: '0',
                description: 'PÜ: Excel: Faktura',
                date: '2026-02-25',
            },
        ])

        expect(groups[0].value).toBe(5)
        expect(groups[0].entries[0].displayValue).toBe(5)
    })

    it('calculates an empty points work as zero points when the table defines a zero threshold', () => {
        const methods = (PerformancesPlusDummy as any).methods
        const work = {
            short_name: 'MA',
            calculation: 'points',
            grades: [
                { grade: '+', value: '1' },
                { grade: '-', value: '-1' },
                { grade: '~', value: '0' },
            ],
            semester_points_table: [
                { grade: '1', min_points: 2 },
                { grade: '2', min_points: 2 },
                { grade: '3', min_points: 1 },
                { grade: '4', min_points: 0 },
            ],
            semester_points_sonst_grade: '5',
            default_grade: '',
        }
        const ctx = makeCtx({
            grading: {
                categories: [
                    {
                        name: 'Mitarbeit',
                        weight: 100,
                        works: [{ short_name: 'MA', factor: 100 }],
                    },
                ],
            },
            teachingWorks: [work],
        })

        const groups = methods.buildCategoryGroups.call(ctx, [])

        expect(groups[0].value).toBe(4)
    })
})

describe('PerformancesPlusDummy semester grade editing', () => {
    it('renders semester grade chips and a persistent edit dialog in the student column', () => {
        const source = readFileSync(
            resolve(process.cwd(), 'resources/js/pages/admin/teaching/more/components/PerformancesPlusDummy.vue'),
            'utf8',
        )

        expect(source).toContain('class="student-semester-grades"')
        expect(source).toContain('class="student-semester-grade"')
        expect(source).toContain("S1 {{ row.student.sem_1_grade || '–' }}")
        expect(source).toContain("S2 {{ row.student.sem_2_grade || '–' }}")
        expect(source).toContain("Sem {{ row.student.sem_grade || '–' }}")
        expect(source).toContain('icon="mdi-pencil"')
        expect(source).toContain('@click.stop="openGradeDialog(row.student, \'sem_1_grade\')"')
        expect(source).toContain('@click.stop="openGradeDialog(row.student, \'sem_2_grade\')"')
        expect(source).toContain('@click.stop="openGradeDialog(row.student, \'sem_grade\')"')
        expect(source).toContain('<v-dialog v-model="showGradeDialog" persistent max-width="420">')
        expect(source).toContain('Semesternoten bearbeiten')
    })

    it('saves semester grades for the selected student only', async () => {
        const methods = (PerformancesPlusDummy as any).methods
        const selectedCourse = {
            id: 5,
            teaching_schema_id: 'schema-1',
            students_info: [
                { id: 11, last_name: 'Alpha', first_name: 'Anna', sem_1_grade: null, sem_2_grade: null },
                { id: 12, last_name: 'Beta', first_name: 'Berta', sem_1_grade: '3', sem_2_grade: '2' },
            ],
            students: [11, 12],
            students_deleted: [],
        }
        const update = vi.fn().mockResolvedValue(true)
        const ctx: Record<string, any> = {
            selectedCourse,
            selectedGradeStudent: null,
            showGradeDialog: false,
            savingGrades: false,
            hasTwoSemesters: true,
            gradeDialogFocusField: 'sem_1_grade',
            gradeForm: {
                sem_1_grade: '',
                sem_2_grade: '',
                sem_grade: '',
            },
            courseStore: {
                ensureCourseStudentCollections: vi.fn(),
                update,
            },
            studentLabelWithClass: methods.studentLabelWithClass,
            openGradeDialog: methods.openGradeDialog,
            closeGradeDialog: methods.closeGradeDialog,
        }

        methods.openGradeDialog.call(ctx, selectedCourse.students_info[0], 'sem_2_grade')
        expect(ctx.gradeDialogFocusField).toBe('sem_2_grade')

        ctx.gradeForm.sem_1_grade = '4'
        ctx.gradeForm.sem_2_grade = '2'

        await methods.saveGradeDialog.call(ctx)

        expect(update).toHaveBeenCalledWith(expect.objectContaining({
            id: 5,
            students: [
                expect.objectContaining({ id: 11, sem_1_grade: '4', sem_2_grade: '2' }),
                expect.objectContaining({ id: 12, sem_1_grade: '3', sem_2_grade: '2' }),
            ],
            students_deleted: [],
        }))
        expect(selectedCourse.students_info[0].sem_1_grade).toBe('4')
        expect(selectedCourse.students_info[0].sem_2_grade).toBe('2')
        expect(ctx.showGradeDialog).toBe(false)
        expect(ctx.selectedGradeStudent).toBeNull()
    })

    it('saves the semester grade for single-semester courses', async () => {
        const methods = (PerformancesPlusDummy as any).methods
        const selectedCourse = {
            id: 6,
            teaching_schema_id: 'schema-1',
            students_info: [
                { id: 21, last_name: 'Gamma', first_name: 'Gina', sem_grade: null },
                { id: 22, last_name: 'Delta', first_name: 'Dora', sem_grade: '3' },
            ],
            students: [21, 22],
            students_deleted: [],
        }
        const update = vi.fn().mockResolvedValue(true)
        const ctx: Record<string, any> = {
            selectedCourse,
            selectedGradeStudent: null,
            showGradeDialog: false,
            savingGrades: false,
            hasTwoSemesters: false,
            gradeDialogFocusField: 'sem_1_grade',
            gradeForm: {
                sem_1_grade: '',
                sem_2_grade: '',
                sem_grade: '',
            },
            courseStore: {
                ensureCourseStudentCollections: vi.fn(),
                update,
            },
            studentLabelWithClass: methods.studentLabelWithClass,
            openGradeDialog: methods.openGradeDialog,
            closeGradeDialog: methods.closeGradeDialog,
        }

        methods.openGradeDialog.call(ctx, selectedCourse.students_info[0], 'sem_grade')
        expect(ctx.gradeDialogFocusField).toBe('sem_grade')

        ctx.gradeForm.sem_grade = '2'

        await methods.saveGradeDialog.call(ctx)

        expect(update).toHaveBeenCalledWith(expect.objectContaining({
            id: 6,
            students: [
                expect.objectContaining({ id: 21, sem_grade: '2' }),
                expect.objectContaining({ id: 22, sem_grade: '3' }),
            ],
            students_deleted: [],
        }))
        expect(update.mock.calls[0][0].students[0]).not.toHaveProperty('sem_1_grade')
        expect(update.mock.calls[0][0].students[0]).not.toHaveProperty('sem_2_grade')
        expect(selectedCourse.students_info[0].sem_grade).toBe('2')
        expect(ctx.showGradeDialog).toBe(false)
        expect(ctx.selectedGradeStudent).toBeNull()
    })
})
