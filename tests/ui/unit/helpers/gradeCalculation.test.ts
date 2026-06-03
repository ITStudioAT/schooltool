import { describe, expect, it } from 'vitest'
import { buildCategoryGroups } from '@/helpers/gradeCalculation'

describe('grade calculation helper', () => {
    it('uses the configured grade value before falling back to a numeric grade key', () => {
        const groups = buildCategoryGroups(
            [
                {
                    id: 1,
                    type: 'TE-E',
                    grade: '2',
                    effective_grade: '',
                },
            ],
            [
                {
                    short_name: 'TE-E',
                    calculation: 'average',
                    grades: [
                        { grade: '1', value: '1' },
                        { grade: '2', value: '5' },
                    ],
                    default_grade: '',
                },
            ],
            {
                categories: [
                    {
                        name: 'Test - Excel',
                        weight: 30,
                        works: [{ short_name: 'TE-E', factor: 100 }],
                    },
                ],
            },
        )

        expect(groups[0].value).toBe(5)
    })

    it('calculates a practical exercise grade key of zero as grade value five', () => {
        const groups = buildCategoryGroups(
            [
                {
                    id: 1,
                    type: 'PÜ',
                    grade: '0',
                    effective_grade: '0',
                },
            ],
            [
                {
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
                },
            ],
            {
                categories: [
                    {
                        name: 'Praktische Übungen',
                        weight: 33,
                        works: [{ short_name: 'PÜ', factor: 100 }],
                    },
                ],
            },
        )

        expect(groups[0].value).toBe(5)
    })

    it('uses numeric entry grades directly for points work in category calculation', () => {
        const groups = buildCategoryGroups(
            [
                {
                    id: 1,
                    type: 'TE-E',
                    grade: '2',
                    effective_grade: '',
                },
            ],
            [
                {
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
                },
            ],
            {
                categories: [
                    {
                        name: 'Test - Excel',
                        weight: 30,
                        works: [{ short_name: 'TE-E', factor: 100 }],
                    },
                ],
            },
        )

        expect(groups[0].value).toBe(2)
    })
})
