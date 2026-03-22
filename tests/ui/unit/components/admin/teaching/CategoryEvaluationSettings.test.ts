import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import CategoryEvaluation from '@/pages/admin/teaching/settings/components/CategoryEvaluation.vue'

describe('Teaching category evaluation settings', () => {
    it('renders the default value selector before the editable values list', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/settings/components/CategoryEvaluation.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const defaultFieldIndex = source.indexOf('label="Standardwert"')
        const valueFieldIndex = source.indexOf(':label="`Wert ${index + 1}`"')

        expect(source).toContain('class="category-evaluation-default-box mb-2"')
        expect(source).toContain('label="HEX"')
        expect(source).toContain('.category-evaluation-default-box {')
        expect(defaultFieldIndex).toBeGreaterThan(-1)
        expect(valueFieldIndex).toBeGreaterThan(-1)
        expect(defaultFieldIndex).toBeLessThan(valueFieldIndex)
    })

    it('initializes editable values from the selected schema', () => {
        const methods = (CategoryEvaluation as any).methods
        const ctx: Record<string, unknown> = {
            valueItems: [
                { value: 'Keine Bewertung', color: '#b0bec5' },
                { value: 'Offen', color: '#fb8c00' },
                { value: 'Bestanden', color: '#43a047' },
            ],
            defaultValue: 'Offen',
            data: { items: [] },
            new_value: 'Alt',
            new_color: '#123456',
        }

        methods.initData.call(ctx)

        expect(ctx.data).toEqual({
            items: [
                { value: 'Keine Bewertung', color: '#b0bec5' },
                { value: 'Offen', color: '#fb8c00' },
                { value: 'Bestanden', color: '#43a047' },
            ],
            default_value: 'Offen',
        })
        expect(ctx.new_value).toBe('')
        expect(ctx.new_color).toBe('')
    })

    it('saves normalized unique category evaluation values with colors and default into schema grading', async () => {
        const methods = (CategoryEvaluation as any).methods
        const saveSettingsMock = vi.fn().mockResolvedValue(true)
        const initDataMock = vi.fn()
        const ctx: Record<string, any> = {
            schemaId: 'schema-1',
            settings: {
                teaching_schemas: [
                    {
                        id: 'schema-1',
                        grading: {
                            semester_count: 2,
                            categories: [],
                        },
                    },
                ],
            },
            teachingStore: {
                saveSettings: saveSettingsMock,
            },
            data: {
                items: [
                    { value: ' Offen ', color: '#fb8c00' },
                    { value: '', color: '#000000' },
                    { value: 'Bestanden', color: '43a047' },
                    { value: 'Offen', color: '#123456' },
                    { value: '5 ', color: '#e53935' },
                ],
                default_value: '5',
            },
            cleanedItems: [
                { value: 'Offen', color: '#fb8c00' },
                { value: 'Bestanden', color: '#43a047' },
                { value: '5', color: '#e53935' },
            ],
            cleanedValues: ['Offen', 'Bestanden', '5'],
            isValid: true,
            is_editing: true,
            initData: initDataMock,
        }

        await methods.save.call(ctx)

        expect(saveSettingsMock).toHaveBeenCalledWith({
            teaching_schemas: [
                {
                    id: 'schema-1',
                    grading: {
                        semester_count: 2,
                        categories: [],
                        category_evaluation_values: [
                            { value: 'Offen', color: '#fb8c00' },
                            { value: 'Bestanden', color: '#43a047' },
                            { value: '5', color: '#e53935' },
                        ],
                        default_category_evaluation_value: '5',
                    },
                },
            ],
        })
        expect(ctx.is_editing).toBe(false)
        expect(initDataMock).toHaveBeenCalled()
    })

    it('falls back to the first cleaned value when the selected default is no longer available', async () => {
        const methods = (CategoryEvaluation as any).methods
        const saveSettingsMock = vi.fn().mockResolvedValue(true)
        const ctx: Record<string, any> = {
            schemaId: 'schema-1',
            settings: {
                teaching_schemas: [
                    {
                        id: 'schema-1',
                        grading: {
                            semester_count: 2,
                            categories: [],
                        },
                    },
                ],
            },
            teachingStore: {
                saveSettings: saveSettingsMock,
            },
            data: {
                items: [
                    { value: 'Bestanden', color: '#43a047' },
                    { value: '4', color: '#ef6c00' },
                ],
                default_value: 'Nicht bestanden',
            },
            cleanedItems: [
                { value: 'Bestanden', color: '#43a047' },
                { value: '4', color: '#ef6c00' },
            ],
            cleanedValues: ['Bestanden', '4'],
            isValid: true,
            is_editing: true,
            initData: vi.fn(),
        }

        await methods.save.call(ctx)

        expect(saveSettingsMock).toHaveBeenCalledWith({
            teaching_schemas: [
                {
                    id: 'schema-1',
                    grading: {
                        semester_count: 2,
                        categories: [],
                        category_evaluation_values: [
                            { value: 'Bestanden', color: '#43a047' },
                            { value: '4', color: '#ef6c00' },
                        ],
                        default_category_evaluation_value: 'Bestanden',
                    },
                },
            ],
        })
    })
})
