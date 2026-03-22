import { describe, expect, it } from 'vitest'
import WorksAndGrades from '@/pages/admin/teaching/settings/components/WorksAndGrades.vue'

describe('Works and grades settings edit flow', () => {
    it('returns to the editable work list level on save', async () => {
        const methods = (WorksAndGrades as any).methods
        let payload: Record<string, unknown> | null = null

        const ctx: Record<string, any> = {
            schemaId: 'schema-1',
            settings: {
                teaching_schemas: [
                    {
                        id: 'schema-1',
                        works: [
                            {
                                short_name: 'ALT',
                                name: 'Alt',
                                grades: [{ grade: '1', name: 'Sehr gut', value: '1' }],
                                calculation: 'average',
                                points_table: [],
                                default_grade: '',
                            },
                        ],
                    },
                ],
            },
            teaching_works: [
                {
                    short_name: 'ALT',
                    name: 'Alt',
                    grades: [{ grade: '1', name: 'Sehr gut', value: '1' }],
                    calculation: 'average',
                    points_table: [],
                    default_grade: '',
                },
            ],
            data: {
                short_name: 'NEU',
                name: 'Neu',
                grades: [{ grade: '1', name: 'Sehr gut', value: '1' }],
                calculation: 'average',
                points_table: [],
                default_grade: '',
            },
            edit_index: 0,
            action: 'teaching_work_new_or_edit',
            is_editing: false,
            teachingStore: {
                saveSettings: async (input: Record<string, unknown>) => {
                    payload = input
                    return true
                },
            },
            sortedGrades: methods.sortedGrades,
            ensureValidDefaultGrade: methods.ensureValidDefaultGrade,
            normalizeGradeKey: methods.normalizeGradeKey,
        }

        await methods.save.call(ctx)

        expect(payload).toEqual({
            teaching_schemas: [
                {
                    id: 'schema-1',
                    works: [
                        {
                            short_name: 'NEU',
                            name: 'Neu',
                            grades: [{ grade: '1', name: 'Sehr gut', value: '1' }],
                            calculation: 'average',
                            points_table: [],
                            default_grade: '',
                        },
                    ],
                },
            ],
        })
        expect(ctx.action).toBe('')
        expect(ctx.edit_index).toBeNull()
        expect(ctx.is_editing).toBe(true)
    })
})
