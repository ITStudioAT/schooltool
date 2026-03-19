import { describe, expect, it } from 'vitest'
import PerformancesDummy from '@/pages/admin/teaching/more/components/PerformancesDummy.vue'

describe('PerformancesDummy type columns', () => {
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
            { type: 'Q', label: 'Q - Quiz' },
            { type: 'S', label: 'S - Schularbeit' },
            { type: 'K', label: 'K - Kontrolle' },
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
            { type: 'MA', label: 'MA - Label' },
            { type: 'K', label: 'K - Label' },
        ])
    })
})
