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

describe('CourseStudent points grade fallback', () => {
    it('returns the fallback points grade when no minimum threshold matches', () => {
        const methods = (CourseStudent as any).methods
        const work = {
            calculation: 'points',
            points_table: [
                { grade: '1', min_points: 5 },
                { grade: '2', min_points: 3 },
                { grade: '3', min_points: 1 },
                { grade: '4', min_points: 0 },
            ],
            points_sonst_grade: '5',
        }

        const result = methods.pointsGradeForWork.call({}, work, -2)

        expect(result).toBe('5')
    })
})

describe('CourseStudent entry title rendering', () => {
    it('renders work title as text line instead of chip in entries list', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseStudent.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="text-caption text-medium-emphasis entry-work-title-line"')
        expect(source).not.toContain('class="entry-work-title"')
        expect(source).not.toContain('v-chip\n                                                v-if="entryWorkTitle(item.entry)"')
    })
})

describe('CourseStudent auswertung entry title rendering', () => {
    it('renders row title and row work title as text lines in auswertung list', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseStudent.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const mainRowIndex = source.indexOf('class="d-flex align-center ga-2 w-100 auswertung-entry-main-row"')
        const workTitleIndex = source.indexOf('v-if="row.workTitle" class="text-body-2 auswertung-entry-work-title"')

        expect(source).toContain('class="d-flex align-center ga-2 w-100 auswertung-entry-main-row"')
        expect(source).toContain('class="text-caption text-medium-emphasis auswertung-entry-title"')
        expect(source).toContain('v-if="row.workTitle" class="text-body-2 auswertung-entry-work-title"')
        expect(source).toContain('v-chip v-if="row.date" size="x-small" variant="tonal" color="primary"')
        expect(source).toContain('v-chip v-if="row.value != null" size="x-small" variant="tonal" :color="isNaGradeKey(row.value) ? \'error\' : \'primary\'"')
        expect(mainRowIndex).toBeGreaterThan(-1)
        expect(workTitleIndex).toBeGreaterThan(mainRowIndex)
        expect(source).toContain('workTitle: this.entryWorkTitle(entry)')
        expect(source).not.toContain('v-chip v-if="row.type" size="x-small" variant="outlined"')
    })
})

describe('CourseStudent auswertung category colors', () => {
    it('uses success color for category title and Bewertung when rating exists', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseStudent.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain("categoryHasBewertung(cat) ? 'text-success'")
        expect(source).toContain('categoryHasBewertung(category)')
    })
})

describe('CourseStudent auswertung trigger placement', () => {
    it('uses Auswerten button near Zurück and removes inline Auswertung header row', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseStudent.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const auswertenButtonIndex = source.indexOf('Auswerten')
        const auswertungCardIndex = source.indexOf('<v-card v-if="hasAuswertungContent && show_auswertung" variant="outlined" class="mt-2">')
        const entriesTitleIndex = source.indexOf('Einträge')

        expect(source).toContain(":color=\"show_auswertung ? 'success' : 'primary'\"")
        expect(source).toContain(":variant=\"show_auswertung ? 'flat' : 'tonal'\"")
        expect(source).toContain(":prepend-icon=\"show_auswertung ? 'mdi-eye-off' : 'mdi-eye'\"")
        expect(source).toContain(":aria-pressed=\"show_auswertung ? 'true' : 'false'\"")
        expect(source).toContain('@click="show_auswertung = !show_auswertung"')
        expect(source).toContain('Auswerten')
        expect(source).toContain('<v-card v-if="hasAuswertungContent && show_auswertung" variant="outlined" class="mt-2">')
        expect(source).not.toContain('v-card-text v-if="hasAuswertungContent && show_auswertung" class="py-2"')
        expect(source).not.toContain('<div class="text-subtitle-2">Auswertung</div>')
        expect(auswertenButtonIndex).toBeGreaterThan(-1)
        expect(auswertungCardIndex).toBeGreaterThan(auswertenButtonIndex)
        expect(entriesTitleIndex).toBeGreaterThan(auswertungCardIndex)
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
            entryWorkTitle: () => '',
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

describe('CourseStudent behaviour visibility', () => {
    it('enables behaviour by default when setting flag is missing', () => {
        const ctx = {
            settings: {},
        }

        const enabled = (CourseStudent as any).computed.showBehaviourEnabled.call(ctx)

        expect(enabled).toBe(true)
    })

    it('disables behaviour when setting flag is false', () => {
        const ctx = {
            settings: { teaching_show_behaviour: false },
        }

        const enabled = (CourseStudent as any).computed.showBehaviourEnabled.call(ctx)

        expect(enabled).toBe(false)
    })

    it('closes behaviour editing UI when behaviour is disabled', () => {
        const watcher = (CourseStudent as any).watch['settings.teaching_show_behaviour']
        const methods = (CourseStudent as any).methods

        const ctx = {
            show_behaviour_form: true,
            behaviour_form: { id: 123, type: 'BZ' },
            delete_behaviour_id: 999,
            is_editing_behaviour_grades: true,
            emptyBehaviourForm: methods.emptyBehaviourForm,
        }

        watcher.call(ctx, false)

        expect(ctx.show_behaviour_form).toBe(false)
        expect((ctx.behaviour_form as any).id).toBeNull()
        expect(ctx.delete_behaviour_id).toBeNull()
        expect(ctx.is_editing_behaviour_grades).toBe(false)
    })
})
