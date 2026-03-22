import { describe, expect, it, vi } from 'vitest'
import PerformancesDummy from '@/pages/admin/teaching/more/components/PerformancesDummy.vue'

describe('PerformancesDummy type columns', () => {
    it('defaults student sort mode to name', () => {
        const data = (PerformancesDummy as any).data.call({})

        expect(data.sortMode).toBe('last_name_first_name')
    })

    it('filters schema work columns by grading categories when categories are configured', () => {
        const columns = (PerformancesDummy as any).computed.workColumnsFromSchema.call({
            selectedCourse: { teaching_schema_id: 'schema-inf2' },
            schemaCategoryTypes: ['FU', 'TE'],
            teachingStore: {
                worksForSchema: () => [
                    { short_name: 'FU', name: 'Fernunterricht' },
                    { short_name: 'MA', name: 'Mitarbeit' },
                    { short_name: 'TE', name: 'Test/Projekt' },
                ],
            },
            typeLabel: (type: string) => `${type} - Label`,
        })

        expect(columns).toEqual([
            { type: 'FU', label: 'FU - Fernunterricht' },
            { type: 'TE', label: 'TE - Test/Projekt' },
        ])
    })

    it('shows only schema columns when schema works are configured', () => {
        const columns = (PerformancesDummy as any).computed.typeColumns.call({
            workColumnsFromSchema: [
                { type: 'Q', label: 'Q - Quiz' },
                { type: 'S', label: 'S - Schularbeit' },
                { type: 'K', label: 'K - Kontrolle' },
            ],
            hasSchemaWorkColumns: true,
            filteredEntries: [
                { type: 'MA' },
                { type: 'Q' },
            ],
            typeLabel: (type: string) => `${type} - Label`,
        })

        expect(columns).toEqual([
            { type: 'Q', label: 'Q - Quiz', key: 'type-Q', kind: 'type' },
            { type: 'S', label: 'S - Schularbeit', key: 'type-S', kind: 'type' },
            { type: 'K', label: 'K - Kontrolle', key: 'type-K', kind: 'type' },
        ])
    })

    it('falls back to entry types when schema has no work columns', () => {
        const columns = (PerformancesDummy as any).computed.typeColumns.call({
            workColumnsFromSchema: [],
            hasSchemaWorkColumns: false,
            filteredEntries: [
                { type: 'MA' },
                { type: 'K' },
                { type: 'MA' },
            ],
            typeLabel: (type: string) => `${type} - Label`,
        })

        expect(columns).toEqual([
            { type: 'MA', label: 'MA - Label', key: 'type-MA', kind: 'type' },
            { type: 'K', label: 'K - Label', key: 'type-K', kind: 'type' },
        ])
    })

    it('maps enabled category evaluations into the existing type cells', () => {
        const categories = (PerformancesDummy as any).methods.categoryEvaluationCategoriesForType.call({
            enabledCategoryEvaluationCategories: [
                {
                    name: 'Fernunterricht',
                    works: ['FU'],
                },
                {
                    name: 'Projekt',
                    works: [{ short_name: 'TE' }, { short_name: 'PR' }],
                },
            ],
        }, 'TE')

        expect(categories).toEqual([
            {
                name: 'Projekt',
                works: [{ short_name: 'TE' }, { short_name: 'PR' }],
            },
        ])
    })

    it('uses the configured default category evaluation value when no record exists', () => {
        const methods = (PerformancesDummy as any).methods
        const value = methods.categoryEvaluationValue.call({
            activeEvaluationSemester: () => 2,
            categoryEvaluationKey: methods.categoryEvaluationKey,
            localCategoryEvaluationValues: {},
            storedCategoryEvaluationValue: () => '',
            defaultCategoryEvaluationValue: 'Offen',
        }, 12, 'Fernunterricht')

        expect(value).toBe('Offen')
    })

    it('uses the configured color for category evaluation chips', () => {
        const methods = (PerformancesDummy as any).methods
        const color = methods.categoryEvaluationValueColor.call({
            categoryEvaluationValueItems: [
                { value: 'Offen', color: '#fb8c00' },
                { value: 'Bestanden', color: '#43a047' },
            ],
        }, 'Bestanden')

        expect(color).toBe('#43a047')
    })

    it('does not save category evaluation again when the selected chip is clicked', async () => {
        const methods = (PerformancesDummy as any).methods
        const store = { store: vi.fn() }

        await methods.saveCategoryEvaluation.call({
            selectedCourse: { id: 9 },
            categoryEvaluationStore: store,
            categoryEvaluationSaving: () => false,
            categoryEvaluationValue: () => 'Bestanden',
        }, 12, 'Fernunterricht', 'Bestanden')

        expect(store.store).not.toHaveBeenCalled()
    })
})
