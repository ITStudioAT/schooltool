import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import PerformancesDummy from '@/pages/admin/teaching/more/components/PerformancesDummy.vue'

describe('PerformancesDummy type columns', () => {
    it('defaults student sort mode to name', () => {
        const data = (PerformancesDummy as any).data.call({})

        expect(data.sortMode).toBe('last_name_first_name')
    })

    it('sizes performance columns to their content instead of fixed wide columns', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/more/components/PerformancesDummy.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<col v-for="column in typeColumns" :key="\'cg-\'+column.key">')
        expect(source).toContain('width: max-content;')
        expect(source).toContain('min-width: max-content;')
        expect(source).toContain('table-layout: auto;')
        expect(source).not.toContain('style="width: 400px;"')
        expect(source).not.toContain('table-layout: fixed;')
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

    it('shows only schema columns with values when schema works are configured', () => {
        const methods = (PerformancesDummy as any).methods
        const ctx = {
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
            students: [],
            localCategoryEvaluationValues: {},
            categoryEvaluationCategoriesForType: methods.categoryEvaluationCategoriesForType,
            categoryEvaluationKey: methods.categoryEvaluationKey,
            storedCategoryEvaluationValue: () => '',
            activeEvaluationSemester: () => 1,
            enabledCategoryEvaluationCategories: [],
            typeLabel: (type: string) => `${type} - Label`,
        }
        ctx.typeColumnHasValues = methods.typeColumnHasValues

        const columns = (PerformancesDummy as any).computed.typeColumns.call(ctx)

        expect(columns).toEqual([
            { type: 'Q', label: 'Q - Quiz', key: 'type-Q', kind: 'type' },
        ])
    })

    it('falls back to entry types when schema has no work columns', () => {
        const methods = (PerformancesDummy as any).methods
        const ctx = {
            workColumnsFromSchema: [],
            hasSchemaWorkColumns: false,
            filteredEntries: [
                { type: 'MA' },
                { type: 'K' },
                { type: 'MA' },
            ],
            students: [],
            localCategoryEvaluationValues: {},
            categoryEvaluationCategoriesForType: methods.categoryEvaluationCategoriesForType,
            categoryEvaluationKey: methods.categoryEvaluationKey,
            storedCategoryEvaluationValue: () => '',
            activeEvaluationSemester: () => 1,
            enabledCategoryEvaluationCategories: [],
            typeLabel: (type: string) => `${type} - Label`,
        }
        ctx.typeColumnHasValues = methods.typeColumnHasValues

        const columns = (PerformancesDummy as any).computed.typeColumns.call(ctx)

        expect(columns).toEqual([
            { type: 'MA', label: 'MA - Label', key: 'type-MA', kind: 'type' },
            { type: 'K', label: 'K - Label', key: 'type-K', kind: 'type' },
        ])
    })

    it('keeps a category-evaluation column because its cells show editable values', () => {
        const methods = (PerformancesDummy as any).methods
        const ctx = {
            workColumnsFromSchema: [
                { type: 'FU', label: 'FU - Fernunterricht' },
                { type: 'MA', label: 'MA - Mitarbeit' },
            ],
            hasSchemaWorkColumns: true,
            filteredEntries: [],
            enabledCategoryEvaluationCategories: [
                { name: 'Fernunterricht', works: ['FU'] },
                { name: 'Mitarbeit', works: ['MA'] },
            ],
            typeLabel: (type: string) => `${type} - Label`,
        }
        ctx.typeColumnHasValues = methods.typeColumnHasValues
        ctx.categoryEvaluationCategoriesForType = methods.categoryEvaluationCategoriesForType
        ctx.categoryEvaluationKey = methods.categoryEvaluationKey

        const columns = (PerformancesDummy as any).computed.typeColumns.call(ctx)

        expect(columns).toEqual([
            { type: 'FU', label: 'FU - Fernunterricht', key: 'type-FU', kind: 'type' },
            { type: 'MA', label: 'MA - Mitarbeit', key: 'type-MA', kind: 'type' },
        ])
    })

    it('shows student-specific work comments as a smaller line below the performance item label', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/more/components/PerformancesDummy.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const methods = (PerformancesDummy as any).methods
        const ctx = {
            courseWorksById: {
                12: {
                    title: 'Podcast',
                    description: 'Erstellung und Abgabe',
                    groups: [
                        {
                            student_ids: [44],
                            comment: 'Gruppenkommentar',
                            comments: [{ student_id: 44, comment: 'Individueller Kommentar' }],
                        },
                    ],
                },
            },
            typeLabel: () => 'PÜ - Praktische Übung',
            entryDateLabel: () => '16.05.2026',
            entryItemLabel: methods.entryItemLabel,
            entryItemDetail: methods.entryItemDetail,
            entryStudentComment: methods.entryStudentComment,
            entryWorkDescription: methods.entryWorkDescription,
            entryWork: methods.entryWork,
            entryWorkGroup: methods.entryWorkGroup,
            entryWorkIndividualComment: methods.entryWorkIndividualComment,
            entryWorkGroupComment: methods.entryWorkGroupComment,
            sameId: methods.sameId,
        }

        const item = methods.entryItem.call(ctx, {
            teaching_course_work_id: 12,
            user_id: 44,
            type: 'PÜ',
            grade: '2',
            date: '2026-05-16',
            description: 'Erstellung und Abgabe',
        })

        expect(source).toContain('class="performance-item-label"')
        expect(source).toContain('class="performance-item-detail"')
        expect(source).toContain('font-size: 0.72rem;')
        expect(item).toEqual({
            label: '16.05.2026 - Podcast: 2',
            detail: 'Individueller Kommentar',
        })
    })

    it('does not use the general work description as a performance item detail', () => {
        const methods = (PerformancesDummy as any).methods
        const ctx = {
            courseWorksById: {
                12: {
                    title: 'Podcast',
                    description: 'Erstellung und Abgabe',
                    groups: [],
                },
            },
            entryStudentComment: methods.entryStudentComment,
            entryWorkDescription: methods.entryWorkDescription,
            entryWork: methods.entryWork,
            entryWorkGroup: methods.entryWorkGroup,
            entryWorkIndividualComment: methods.entryWorkIndividualComment,
            entryWorkGroupComment: methods.entryWorkGroupComment,
            sameId: methods.sameId,
        }

        const detail = methods.entryItemDetail.call(ctx, {
            teaching_course_work_id: 12,
            user_id: 44,
            description: 'Erstellung und Abgabe',
        })

        expect(detail).toBe('')
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
