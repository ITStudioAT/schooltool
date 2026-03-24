import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import WorksAndGrades from '@/pages/admin/teaching/settings/components/WorksAndGrades.vue'

describe('Works and grades settings edit flow', () => {
    it('renders the points-to-grade configuration as its own independent checkbox area', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/settings/components/WorksAndGrades.vue',
        )

        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('\n                                Punkte\n')
        expect(source).toContain('Berechnung der Semesternote (optional)')
        expect(source).toContain('Punkte-Note-Tabelle pro Arbeit (optional)')
        expect(source).toContain('<div class="form-group-box mt-4 points-note-box">')
        expect(source).toContain('v-model="data.points_note_enabled"')
        expect(source).toContain('v-for="(grade, index) in pointsNoteGrades"')
        expect(source).toContain(`:label="index === pointsNoteGrades.length - 1 ? 'Ab Punkte (optional)' : 'Ab Punkte'"`)
        expect(source).toContain('return this.pointsNoteGradesFor(this.data.grades || [])')
        expect(source).toContain('v-btn variant="outlined" size="small" color="primary" :disabled="any_dialog_open" @click="useDefaultPointsTable"')
        expect(source).toContain('inputmode="decimal"')
        expect(source).toContain(':error-messages="gradeDialogValueError"')
        expect(source).toContain("return /^-?\\d+(,\\d+)?$/.test(String(value))")
        expect(source).not.toContain('class="points-sonst-row"')
    })

    it('keeps comma decimals visible in the grade dialog and grade list while saving dot decimals internally', () => {
        const methods = (WorksAndGrades as any).methods
        const ctx: Record<string, any> = {
            grade_dialog_mode: 'add',
            grade_dialog_data: { grade: '2', name: 'Gut', value: '' },
            grade_dialog_original_grade: null,
            grade_dialog: true,
            data: { grades: [], points_table: [], points_sonst_grade: '', points_note_enabled: false, default_grade: '' },
            normalizeNumericInput: methods.normalizeNumericInput,
            displayGradeValue: methods.displayGradeValue,
            normalizeGradeValue: methods.normalizeGradeValue,
            isNumericGradeValue: methods.isNumericGradeValue,
            pointsNoteGradesFor: methods.pointsNoteGradesFor,
            syncPointsNoteConfiguration: methods.syncPointsNoteConfiguration,
            normalizeGradeKey: methods.normalizeGradeKey,
            ensureValidDefaultGrade: methods.ensureValidDefaultGrade,
            sortedGrades: methods.sortedGrades,
        }

        methods.updateGradeDialogValue.call(ctx, '1,2')
        methods.saveGradeDialog.call(ctx)

        expect(ctx.grade_dialog_data.value).toBe('1,2')
        expect(ctx.data.grades).toEqual([
            { grade: '2', name: 'Gut', value: '1,2' },
        ])
        expect(ctx.grade_dialog).toBe(false)
    })

    it('shows loaded decimal grade values with a comma in edit mode', () => {
        const methods = (WorksAndGrades as any).methods
        const ctx: Record<string, any> = {
            teaching_works: [
                {
                    short_name: 'SA',
                    name: 'Schularbeit',
                    grades: [
                        { grade: '1', name: 'Sehr gut', value: '1.2' },
                    ],
                    calculation: 'average',
                    points_table: [],
                    points_sonst_grade: '',
                    default_grade: '',
                },
            ],
            normalizePointsConfiguration: methods.normalizePointsConfiguration,
            normalizeNumericInput: methods.normalizeNumericInput,
            isNumericGradeValue: methods.isNumericGradeValue,
            displayGradeValue: methods.displayGradeValue,
            normalizeGradeValue: methods.normalizeGradeValue,
            pointsNoteGradesFor: methods.pointsNoteGradesFor,
            syncPointsNoteConfiguration: methods.syncPointsNoteConfiguration,
            ensureValidDefaultGrade: methods.ensureValidDefaultGrade,
            normalizeGradeKey: methods.normalizeGradeKey,
            action: '',
            edit_index: null,
            sortedGrades: methods.sortedGrades,
        }

        methods.editWork.call(ctx, 0)

        expect(ctx.data.grades).toEqual([
            { grade: '1', name: 'Sehr gut', value: '1,2' },
        ])
    })

    it('removes deleted grades from the points-note options and configuration', () => {
        const methods = (WorksAndGrades as any).methods
        const ctx: Record<string, any> = {
            data: {
                grades: [
                    { grade: '1', name: 'Sehr gut', value: '1' },
                    { grade: '2', name: 'Gut', value: '2' },
                    { grade: '5', name: 'Nicht genügend', value: '5' },
                ],
                points_table: [
                    { grade: '1', min_points: 3 },
                    { grade: '2', min_points: 1 },
                ],
                points_sonst_grade: '5',
                default_grade: '',
            },
            grade_delete_item: { grade: '2' },
            grade_delete_dialog: true,
            normalizeNumericInput: methods.normalizeNumericInput,
            isNumericGradeValue: methods.isNumericGradeValue,
            normalizeGradeValue: methods.normalizeGradeValue,
            pointsNoteGradesFor: methods.pointsNoteGradesFor,
            syncPointsNoteConfiguration: methods.syncPointsNoteConfiguration,
            ensureValidDefaultGrade: methods.ensureValidDefaultGrade,
            normalizeGradeKey: methods.normalizeGradeKey,
            sortedGrades: methods.sortedGrades,
        }

        methods.confirmDeleteGrade.call(ctx)

        expect(methods.pointsNoteGradesFor.call(ctx, ctx.data.grades)).toEqual(['1', '5'])
        expect(ctx.data.points_table).toEqual([
            { grade: '1', min_points: 3 },
        ])
        expect(ctx.data.points_sonst_grade).toBe('5')
    })

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
                calculation_enabled: true,
                calculation: 'average',
                points_note_enabled: false,
                points_table: [],
                points_sonst_grade: '',
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
            normalizeNumericInput: methods.normalizeNumericInput,
            isNumericGradeValue: methods.isNumericGradeValue,
            normalizeGradeValue: methods.normalizeGradeValue,
            normalizedGradesForSave: methods.normalizedGradesForSave,
            pointsNoteGradesFor: methods.pointsNoteGradesFor,
            syncPointsNoteConfiguration: methods.syncPointsNoteConfiguration,
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
                            points_note_enabled: false,
                            points_table: [],
                            points_sonst_grade: '',
                            default_grade: '',
                        },
                    ],
                },
            ],
        })
        expect(ctx.action).toBe('')
        expect(ctx.edit_index).toBeNull()
        expect(ctx.is_editing).toBe(false)
    })

    it('does not save points-note thresholds when the optional checkbox is not enabled', async () => {
        const methods = (WorksAndGrades as any).methods
        let payload: Record<string, unknown> | null = null

        const ctx: Record<string, any> = {
            schemaId: 'schema-1',
            settings: {
                teaching_schemas: [
                    {
                        id: 'schema-1',
                        works: [],
                    },
                ],
            },
            teaching_works: [],
            data: {
                short_name: 'MA',
                name: 'Mitarbeit',
                grades: [{ grade: '+', name: 'Plus', value: '1' }],
                calculation_enabled: true,
                calculation: 'points',
                points_note_enabled: false,
                points_table: [
                    { grade: '1', min_points: 5 },
                    { grade: '2', min_points: 3 },
                ],
                points_sonst_grade: '5',
                default_grade: '',
            },
            edit_index: null,
            action: 'teaching_work_new_or_edit',
            teachingStore: {
                saveSettings: async (input: Record<string, unknown>) => {
                    payload = input
                    return true
                },
            },
            normalizeNumericInput: methods.normalizeNumericInput,
            isNumericGradeValue: methods.isNumericGradeValue,
            normalizeGradeValue: methods.normalizeGradeValue,
            normalizedGradesForSave: methods.normalizedGradesForSave,
            pointsNoteGradesFor: methods.pointsNoteGradesFor,
            syncPointsNoteConfiguration: methods.syncPointsNoteConfiguration,
            sortedGrades: methods.sortedGrades,
            ensureValidDefaultGrade: methods.ensureValidDefaultGrade,
            normalizeGradeKey: methods.normalizeGradeKey,
            normalizedPointsTableForSave: methods.normalizedPointsTableForSave,
        }

        await methods.save.call(ctx)

        expect(payload).toEqual({
            teaching_schemas: [
                {
                    id: 'schema-1',
                    works: [
                        {
                            short_name: 'MA',
                            name: 'Mitarbeit',
                            grades: [{ grade: '+', name: 'Plus', value: '1' }],
                            calculation: 'points',
                            points_note_enabled: false,
                            points_table: [],
                            points_sonst_grade: '',
                            default_grade: '',
                        },
                    ],
                },
            ],
        })
    })

    it('saves points-note independently from semester calculation', async () => {
        const methods = (WorksAndGrades as any).methods
        let payload: Record<string, unknown> | null = null

        const ctx: Record<string, any> = {
            schemaId: 'schema-1',
            settings: {
                teaching_schemas: [
                    {
                        id: 'schema-1',
                        works: [],
                    },
                ],
            },
            teaching_works: [],
            data: {
                short_name: 'SA',
                name: 'Schularbeit',
                grades: [
                    { grade: '1', name: 'Sehr gut', value: '1' },
                    { grade: '2', name: 'Gut', value: '2' },
                ],
                calculation_enabled: false,
                calculation: 'average',
                points_note_enabled: true,
                points_table: [
                    { grade: '1', min_points: 5 },
                ],
                points_sonst_grade: '2',
                default_grade: '',
            },
            edit_index: null,
            action: 'teaching_work_new_or_edit',
            teachingStore: {
                saveSettings: async (input: Record<string, unknown>) => {
                    payload = input
                    return true
                },
            },
            normalizeNumericInput: methods.normalizeNumericInput,
            isNumericGradeValue: methods.isNumericGradeValue,
            normalizeGradeValue: methods.normalizeGradeValue,
            normalizedGradesForSave: methods.normalizedGradesForSave,
            pointsNoteGradesFor: methods.pointsNoteGradesFor,
            syncPointsNoteConfiguration: methods.syncPointsNoteConfiguration,
            sortedGrades: methods.sortedGrades,
            ensureValidDefaultGrade: methods.ensureValidDefaultGrade,
            normalizeGradeKey: methods.normalizeGradeKey,
            normalizedPointsTableForSave: methods.normalizedPointsTableForSave,
        }

        await methods.save.call(ctx)

        expect(payload).toEqual({
            teaching_schemas: [
                {
                    id: 'schema-1',
                    works: [
                        {
                            short_name: 'SA',
                            name: 'Schularbeit',
                            grades: [
                                { grade: '1', name: 'Sehr gut', value: '1' },
                                { grade: '2', name: 'Gut', value: '2' },
                            ],
                            calculation: null,
                            points_note_enabled: true,
                            points_table: [
                                { grade: '1', min_points: 5 },
                            ],
                            points_sonst_grade: '2',
                            default_grade: '',
                        },
                    ],
                },
            ],
        })
    })

    it('stores the last points grade as fallback when its minimum points field is left empty', async () => {
        const methods = (WorksAndGrades as any).methods
        let payload: Record<string, unknown> | null = null

        const ctx: Record<string, any> = {
            schemaId: 'schema-1',
            settings: {
                teaching_schemas: [
                    {
                        id: 'schema-1',
                        works: [],
                    },
                ],
            },
            teaching_works: [],
            data: {
                short_name: 'SA',
                name: 'Schularbeit',
                grades: [
                    { grade: '1', name: 'Sehr gut', value: '1' },
                    { grade: '2', name: 'Gut', value: '2' },
                    { grade: '3', name: 'Befriedigend', value: '3' },
                    { grade: '4', name: 'Genügend', value: '4' },
                    { grade: '5', name: 'Nicht genügend', value: '5' },
                ],
                calculation_enabled: true,
                calculation: 'points',
                points_note_enabled: true,
                points_table: [
                    { grade: '1', min_points: 5 },
                    { grade: '2', min_points: 3 },
                    { grade: '3', min_points: 1 },
                    { grade: '4', min_points: 0 },
                ],
                points_sonst_grade: '5',
                default_grade: '',
            },
            edit_index: null,
            action: 'teaching_work_new_or_edit',
            teachingStore: {
                saveSettings: async (input: Record<string, unknown>) => {
                    payload = input
                    return true
                },
            },
            normalizeNumericInput: methods.normalizeNumericInput,
            isNumericGradeValue: methods.isNumericGradeValue,
            normalizeGradeValue: methods.normalizeGradeValue,
            normalizedGradesForSave: methods.normalizedGradesForSave,
            pointsNoteGradesFor: methods.pointsNoteGradesFor,
            syncPointsNoteConfiguration: methods.syncPointsNoteConfiguration,
            sortedGrades: methods.sortedGrades,
            ensureValidDefaultGrade: methods.ensureValidDefaultGrade,
            normalizeGradeKey: methods.normalizeGradeKey,
            normalizedPointsTableForSave: methods.normalizedPointsTableForSave,
        }

        await methods.save.call(ctx)

        expect(payload).toEqual({
            teaching_schemas: [
                {
                    id: 'schema-1',
                    works: [
                        {
                            short_name: 'SA',
                            name: 'Schularbeit',
                            grades: [
                                { grade: '1', name: 'Sehr gut', value: '1' },
                                { grade: '2', name: 'Gut', value: '2' },
                                { grade: '3', name: 'Befriedigend', value: '3' },
                                { grade: '4', name: 'Genügend', value: '4' },
                                { grade: '5', name: 'Nicht genügend', value: '5' },
                            ],
                            calculation: 'points',
                            points_note_enabled: true,
                            points_table: [
                                { grade: '1', min_points: 5 },
                                { grade: '2', min_points: 3 },
                                { grade: '3', min_points: 1 },
                                { grade: '4', min_points: 0 },
                            ],
                            points_sonst_grade: '5',
                            default_grade: '',
                        },
                    ],
                },
            ],
        })
    })

    it('saves comma decimals across work grades as dot decimals', async () => {
        const methods = (WorksAndGrades as any).methods
        let payload: Record<string, unknown> | null = null

        const ctx: Record<string, any> = {
            schemaId: 'schema-1',
            settings: {
                teaching_schemas: [
                    {
                        id: 'schema-1',
                        works: [],
                    },
                ],
            },
            teaching_works: [],
            data: {
                short_name: 'SA',
                name: 'Schularbeit',
                grades: [
                    { grade: '1', name: 'Sehr gut', value: '1,2' },
                    { grade: '2', name: 'Gut', value: '2' },
                ],
                calculation_enabled: true,
                calculation: 'average',
                points_note_enabled: false,
                points_table: [],
                points_sonst_grade: '',
                default_grade: '',
            },
            edit_index: null,
            action: 'teaching_work_new_or_edit',
            teachingStore: {
                saveSettings: async (input: Record<string, unknown>) => {
                    payload = input
                    return true
                },
            },
            normalizeNumericInput: methods.normalizeNumericInput,
            isNumericGradeValue: methods.isNumericGradeValue,
            normalizeGradeValue: methods.normalizeGradeValue,
            normalizedGradesForSave: methods.normalizedGradesForSave,
            pointsNoteGradesFor: methods.pointsNoteGradesFor,
            syncPointsNoteConfiguration: methods.syncPointsNoteConfiguration,
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
                            short_name: 'SA',
                            name: 'Schularbeit',
                            grades: [
                                { grade: '1', name: 'Sehr gut', value: '1.2' },
                                { grade: '2', name: 'Gut', value: '2' },
                            ],
                            calculation: 'average',
                            points_note_enabled: false,
                            points_table: [],
                            points_sonst_grade: '',
                            default_grade: '',
                        },
                    ],
                },
            ],
        })
    })

    it('restores the saved points-note checkbox state when editing an existing work', () => {
        const methods = (WorksAndGrades as any).methods
        const ctx: Record<string, any> = {
            teaching_works: [
                {
                    short_name: 'SA',
                    name: 'Schularbeit',
                    grades: [
                        { grade: '1', name: 'Sehr gut', value: '1' },
                        { grade: '2', name: 'Gut', value: '2' },
                    ],
                    calculation: 'points',
                    points_note_enabled: true,
                    points_table: [{ grade: '1', min_points: 5 }],
                    points_sonst_grade: '2',
                    default_grade: '',
                },
            ],
            normalizePointsConfiguration: methods.normalizePointsConfiguration,
            normalizeNumericInput: methods.normalizeNumericInput,
            isNumericGradeValue: methods.isNumericGradeValue,
            displayGradeValue: methods.displayGradeValue,
            normalizeGradeValue: methods.normalizeGradeValue,
            pointsNoteGradesFor: methods.pointsNoteGradesFor,
            syncPointsNoteConfiguration: methods.syncPointsNoteConfiguration,
            ensureValidDefaultGrade: methods.ensureValidDefaultGrade,
            normalizeGradeKey: methods.normalizeGradeKey,
            action: '',
            edit_index: null,
            sortedGrades: methods.sortedGrades,
        }

        methods.editWork.call(ctx, 0)

        expect(ctx.data.points_note_enabled).toBe(true)
    })
})
