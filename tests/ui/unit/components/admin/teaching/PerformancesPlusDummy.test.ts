import { describe, expect, it } from 'vitest'
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
})
