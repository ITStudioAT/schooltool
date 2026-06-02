import { describe, expect, it } from 'vitest'
import { buildCategoryGroups } from '@/helpers/gradeCalculation'

describe('grade calculation helper', () => {
    it('uses a numeric grade key as its numeric value even when the configured grade value is wrong', () => {
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

        expect(groups[0].value).toBe(2)
    })
})
