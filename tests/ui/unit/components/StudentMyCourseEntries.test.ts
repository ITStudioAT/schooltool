import { describe, expect, it, vi } from 'vitest'
import MyCourse from '@/pages/homepage/student/overview/myCourse/MyCourse.vue'

describe('Student MyCourse entry grade chip color', () => {
    it('returns error color for NA grade chips', () => {
        const color = (MyCourse as any).methods.entryGradeChipColor({ grade: 'NA' })

        expect(color).toBe('error')
    })

    it('returns success color for non-NA grades', () => {
        const color = (MyCourse as any).methods.entryGradeChipColor({ grade: '2' })

        expect(color).toBe('success')
    })

    it('returns error color for open grades', () => {
        const color = (MyCourse as any).methods.entryGradeChipColor({ grade: '' })

        expect(color).toBe('error')
    })

    it('resolves the grading category label for an entry type from the teaching schema', () => {
        const methods = (MyCourse as any).methods
        const label = methods.entryCategoryLabel.call({
            entryCategory: methods.entryCategory,
            schemaGradingCategories: [
                { name: 'Fernunterricht', index: 0, works: ['FU'] },
                { name: 'Projekt', index: 1, works: ['PR', 'TE'] },
            ],
        }, { type: 'TE' })

        expect(label).toBe('Projekt')
    })

    it('builds a stable category class from the resolved grading category', () => {
        const methods = (MyCourse as any).methods
        const cssClass = methods.entryCategoryClass.call({
            entryCategory: () => ({ name: 'Projekt', index: 5 }),
        }, { type: 'TE' })

        expect(cssClass).toBe('entry-category-group--category-1')
    })

    it('resolves the category evaluation value for a semester-aware entry', () => {
        const methods = (MyCourse as any).methods
        const value = methods.entryCategoryEvaluationValue.call({
            entryCategory: () => ({ name: 'Projekt', categoryEvaluationEnabled: true }),
            courseCategoryEvaluations: [
                { semester: 1, category_name: 'Projekt', value: 'Offen' },
                { semester: 2, category_name: 'Projekt', value: 'Bestanden' },
            ],
            entrySemester: () => 2,
            categoryEvaluationDefaultValue: 'Offen',
        }, { type: 'TE', date: '2026-03-10' })

        expect(value).toBe('Bestanden')
    })

    it('falls back to the configured default category evaluation value', () => {
        const methods = (MyCourse as any).methods
        const value = methods.entryCategoryEvaluationValue.call({
            entryCategory: () => ({ name: 'Projekt', categoryEvaluationEnabled: true }),
            courseCategoryEvaluations: [],
            entrySemester: () => 2,
            categoryEvaluationDefaultValue: 'Offen',
        }, { type: 'TE', date: '2026-03-10' })

        expect(value).toBe('Offen')
    })

    it('does not show the category evaluation chip for "Keine Bewertung"', () => {
        const methods = (MyCourse as any).methods

        expect(methods.shouldShowCategoryEvaluationValue('Keine Bewertung')).toBe(false)
        expect(methods.shouldShowCategoryEvaluationValue('Bestanden')).toBe(true)
    })

    it('uses the configured color for the category evaluation chip', () => {
        const methods = (MyCourse as any).methods
        const color = methods.categoryEvaluationValueColor.call({
            categoryEvaluationValueItems: [
                { value: 'Offen', color: '#fb8c00' },
                { value: 'Bestanden', color: '#43a047' },
            ],
        }, 'Bestanden')

        expect(color).toBe('#43a047')
    })

    it('groups entries of the same category into one category block', () => {
        const methods = (MyCourse as any).methods
        const groups = methods.buildEntryGroups.call({
            entryCategory: (entry: any) => {
                if (entry.type === 'FU') {
                    return { name: 'Fernunterricht', categoryEvaluationEnabled: true }
                }

                return { name: 'Tests', categoryEvaluationEnabled: false }
            },
            entryCategoryEvaluationValue: (entry: any) => entry.type === 'FU' ? 'Bestanden' : '',
            entryCategoryClass: (entry: any) => entry.type === 'FU' ? 'entry-category-group--category-0' : 'entry-category-group--category-1',
        }, [
            { id: 1, type: 'FU' },
            { id: 2, type: 'FU' },
            { id: 3, type: 'TE' },
        ])

        expect(groups).toEqual([
            {
                key: 'category-Fernunterricht',
                categoryName: 'Fernunterricht',
                categoryEvaluationEnabled: true,
                categoryEvaluationValue: 'Bestanden',
                categoryClass: 'entry-category-group--category-0',
                entries: [{ id: 1, type: 'FU' }, { id: 2, type: 'FU' }],
            },
            {
                key: 'category-Tests',
                categoryName: 'Tests',
                categoryEvaluationEnabled: false,
                categoryEvaluationValue: '',
                categoryClass: 'entry-category-group--category-1',
                entries: [{ id: 3, type: 'TE' }],
            },
        ])
    })

    it('does not build semester headers when the course has only one semester', () => {
        const groupedEntries = (MyCourse as any).computed.groupedEntries.call({
            sortedEntries: [
                { id: 1, type: 'FU', date: '2026-03-20' },
                { id: 2, type: 'FU', date: '2026-03-13' },
            ],
            hasTwoSemesters: false,
            buildEntryGroups: () => [{
                key: 'category-Fernunterricht',
                categoryName: 'Fernunterricht',
                categoryEvaluationEnabled: true,
                categoryEvaluationValue: 'Bestanden',
                categoryClass: 'entry-category-group--category-0',
                entries: [{ id: 1 }, { id: 2 }],
            }],
        })

        expect(groupedEntries).toEqual([
            {
                kind: 'group',
                key: 'group-category-Fernunterricht',
                group: {
                    key: 'category-Fernunterricht',
                    categoryName: 'Fernunterricht',
                    categoryEvaluationEnabled: true,
                    categoryEvaluationValue: 'Bestanden',
                    categoryClass: 'entry-category-group--category-0',
                    entries: [{ id: 1 }, { id: 2 }],
                },
                stripe: 0,
            },
        ])
    })

    it('loads entries again when the refresh action is triggered', async () => {
        const methods = (MyCourse as any).methods
        const getCourseEntries = vi.fn().mockResolvedValue(true)
        const ctx: any = {
            loadingEntries: false,
            courseId: 11,
            courseStore: {
                getCourseEntries,
                entries: [{ id: 1, type: 'FU' }],
            },
            entries: [],
        }

        await methods.loadEntries.call(ctx)

        expect(getCourseEntries).toHaveBeenCalledWith(11)
        expect(ctx.entries).toEqual([{ id: 1, type: 'FU' }])
        expect(ctx.loadingEntries).toBe(false)
    })
})
