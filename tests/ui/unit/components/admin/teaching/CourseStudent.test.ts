import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import CourseStudent from '@/pages/admin/teaching/overview/components/CourseStudent.vue'

describe('CourseStudent date handling', () => {
    it('normalizes timestamp strings using local date parsing (not raw T-split)', () => {
        const methods = (CourseStudent as any).methods
        const ctx = {
            normalizeDateString: methods.normalizeDateString,
            toDateString: methods.toDateString,
        }

        const raw = '2026-02-14T23:00:00.000000Z'
        const normalized = methods.normalizeDateString.call(ctx, raw)
        const expected = methods.toDateString.call(ctx, new Date(raw))

        expect(normalized).toBe(expected)
    })

    it('normalizes ISO date-time strings to stable local input dates', () => {
        const methods = (CourseStudent as any).methods
        const ctx = {
            normalizeDateString: methods.normalizeDateString,
            toDateString: methods.toDateString,
        }

        const inputDate = methods.toInputDate.call(ctx, '2026-02-15T00:00:00.000000Z')

        expect(inputDate).toBeInstanceOf(Date)
        expect(methods.normalizeDateString.call(ctx, inputDate)).toBe('2026-02-15')
    })

    it('fills edit entry date as Date object for v-date-input', () => {
        const methods = (CourseStudent as any).methods
        const ctx: Record<string, unknown> = {
            show_entry_form: false,
            entry_form: null,
            normalizeDateString: methods.normalizeDateString,
            toDateString: methods.toDateString,
            toInputDate: methods.toInputDate,
        }

        methods.editEntry.call(ctx, {
            id: 10,
            type: 'M',
            grade: '2',
            date: '2026-02-15',
            description: 'Test',
        })

        expect((ctx.entry_form as any).date).toBeInstanceOf(Date)
        expect(methods.normalizeDateString.call(ctx, (ctx.entry_form as any).date)).toBe('2026-02-15')
        expect(ctx.show_entry_form).toBe(true)
    })

    it('uses selected lesson date for new entry without day shift', () => {
        const methods = (CourseStudent as any).methods
        const ctx: Record<string, unknown> = {
            selected_courseDate: { date: '2026-02-15' },
            show_entry_form: false,
            entry_form: null,
            normalizeDateString: methods.normalizeDateString,
            toDateString: methods.toDateString,
            toInputDate: methods.toInputDate,
        }
        ctx.emptyEntryForm = function () {
            return methods.emptyEntryForm.call(ctx)
        }

        methods.newEntry.call(ctx)

        expect((ctx.entry_form as any).date).toBeInstanceOf(Date)
        expect(methods.normalizeDateString.call(ctx, (ctx.entry_form as any).date)).toBe('2026-02-15')
        expect(ctx.show_entry_form).toBe(true)
    })

    it('saves created entry with stable YYYY-MM-DD date', async () => {
        const methods = (CourseStudent as any).methods
        let savedPayload: any = null
        const ctx: Record<string, unknown> = {
            selected_course: { id: 77 },
            selected_course_student: { id: 88 },
            entry_form: {
                id: null,
                type: 'M',
                grade: '2',
                date: new Date(2026, 1, 15),
                description: 'Test',
            },
            entryStore: {
                store: async (payload: any) => {
                    savedPayload = payload
                    return true
                },
                update: async () => true,
            },
            normalizeDateString: methods.normalizeDateString,
            toDateString: methods.toDateString,
            loadEntries: async () => true,
            abortEntry: () => true,
        }

        await methods.saveEntry.call(ctx)

        expect(savedPayload).toBeTruthy()
        expect(savedPayload.date).toBe('2026-02-15')
    })
})

describe('CourseStudent auswertung labels', () => {
    it('shows the required marker only at category level with localized wording', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseStudent.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('v-chip v-if="cat.requireAllEntries" size="x-small" variant="tonal" color="primary"')
        expect(source).toContain('Alle erforderlich')
        expect(source).not.toContain('v-chip v-if="row.requireAllEntries" size="x-small" variant="tonal" color="info"')
        expect(source).toContain('Pflichtkategorie "Alle erforderlich"')
        expect(source).not.toContain('Pflichtkategorie mit require_all_entries')
        expect(source).not.toContain('Mindestens eine Pflichtkategorie (require_all_entries)')
    })
})

describe('CourseStudent NA cascade (require_all_entries + NA entry)', () => {
    const methods = (CourseStudent as any).methods

    function makeCtx(overrides: Record<string, unknown> = {}) {
        return {
            selectedSchema: null,
            teachingWorks: [],
            courseWorkStore: null,
            normalizeGradeKey: methods.normalizeGradeKey,
            isNaGradeKey: methods.isNaGradeKey,
            isNbGradeKey: methods.isNbGradeKey,
            isNbValue: methods.isNbValue,
            isGradedEntry: methods.isGradedEntry,
            effectiveGradeKeyForEntry: methods.effectiveGradeKeyForEntry,
            defaultGradeForWork: methods.defaultGradeForWork,
            workConfigForType: () => null,
            gradeValueForWork: methods.gradeValueForWork,
            pointsGradeForWork: methods.pointsGradeForWork,
            ...overrides,
        }
    }

    it('marks category as isNa when require_all_entries and one entry has NA grade', () => {
        const work = { short_name: 'M', calculation: 'grade', grades: [{ grade: 'NA', value: null }, { grade: '1', value: 1 }], default_grade: '' }
        const ctx = makeCtx({
            teachingWorks: [work],
            selectedSchema: {
                grading: {
                    categories: [
                        { name: 'Mitarbeit', weight: 100, require_all_entries: true, works: [{ short_name: 'M', factor: 100 }] },
                    ],
                },
            },
        })

        const entries = [
            { id: 1, type: 'M', grade: 'NA', effective_grade: '' },
            { id: 2, type: 'M', grade: '2', effective_grade: '' },
        ]

        const groups = methods.buildCategoryGroups.call(ctx, entries)

        expect(groups).toHaveLength(1)
        expect(groups[0].isNa).toBe(true)
        expect(groups[0].grade).toBe('NA')
        expect(groups[0].isNb).toBe(false)
    })

    it('does not mark category as isNa when require_all_entries is false', () => {
        const work = { short_name: 'M', calculation: 'grade', grades: [{ grade: 'NA', value: null }, { grade: '1', value: 1 }], default_grade: '' }
        const ctx = makeCtx({
            teachingWorks: [work],
            selectedSchema: {
                grading: {
                    categories: [
                        { name: 'Mitarbeit', weight: 100, require_all_entries: false, works: [{ short_name: 'M', factor: 100 }] },
                    ],
                },
            },
        })

        const entries = [{ id: 1, type: 'M', grade: 'NA', effective_grade: '' }]

        const groups = methods.buildCategoryGroups.call(ctx, entries)

        expect(groups[0].isNa).toBe(false)
    })

    it('totalFromCategoryGroups returns NA when any category isNa', () => {
        const ctx = makeCtx()
        const groups = [
            { name: 'Kat A', weight: '50', value: null, grade: 'NA', isNb: false, isNa: true },
            { name: 'Kat B', weight: '50', value: 2, grade: null, isNb: false, isNa: false },
        ]

        const result = methods.totalFromCategoryGroups.call(ctx, groups)

        expect(result).toBe('NA')
    })

    it('totalFromCategoryGroups returns NB (not NA) when only isNb is set', () => {
        const ctx = makeCtx()
        const groups = [
            { name: 'Kat A', weight: '100', value: null, grade: 'NB', isNb: true, isNa: false },
        ]

        const result = methods.totalFromCategoryGroups.call(ctx, groups)

        expect(result).toBe('NB')
    })

    it('categoryGroupTotalLine returns NA explanation when a category isNa', () => {
        const ctx = makeCtx()
        const groups = [
            { name: 'Mitarbeit', weight: '100', value: null, grade: 'NA', isNb: false, isNa: true },
        ]

        const line = methods.categoryGroupTotalLine.call(ctx, groups, 'NA')

        expect(line).toContain('NA')
        expect(line).toContain('Mitarbeit')
        expect(line).toContain('Alle erforderlich')
    })
})
