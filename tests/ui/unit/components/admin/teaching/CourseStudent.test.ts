import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
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
        let continueNextTick: (() => void) | null = null
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
            saving_action_key: null,
            $nextTick: vi.fn(() => new Promise<void>((resolve) => {
                continueNextTick = resolve
            })),
            normalizeDateString: methods.normalizeDateString,
            toDateString: methods.toDateString,
            runStudentMutation(action: string, callback: () => Promise<unknown>) {
                return methods.runStudentMutation.call(this, action, callback)
            },
            loadEntries: async () => true,
            abortEntry: () => true,
        }

        const savePromise = methods.saveEntry.call(ctx)

        expect(ctx.saving_action_key).toBe('save-entry')

        continueNextTick?.()
        await savePromise

        expect(savedPayload).toBeTruthy()
        expect(savedPayload.date).toBe('2026-02-15')
        expect(ctx.saving_action_key).toBeNull()
    })
})

describe('CourseStudent next and previous navigation order', () => {
    it('matches the overview order for class and name sorting', () => {
        const computed = (CourseStudent as any).computed
        const methods = (CourseStudent as any).methods
        const ctx: Record<string, any> = {
            selected_course: {
                students_info: [
                    { id: 1, schoolclass: '2B', last_name: 'Bauer', first_name: 'Anna', canceled_at: null },
                    { id: 2, schoolclass: '1A', last_name: 'Zeis', first_name: 'Berta', canceled_at: null },
                    { id: 3, schoolclass: '1A', last_name: 'Auer', first_name: 'Clara', canceled_at: null },
                    { id: 4, schoolclass: '1A', last_name: 'Zimmer', first_name: 'Dora', canceled_at: '2026-02-16 10:00:00' },
                ],
            },
            selected_course_student: { id: 2, schoolclass: '1A', last_name: 'Zeis', first_name: 'Berta', canceled_at: null },
            students_sort_mode: 'class_last_name',
            isStudentCanceled: methods.isStudentCanceled,
            compareStudentsBySelectedSort: methods.compareStudentsBySelectedSort,
            studentClassValue: methods.studentClassValue,
        }

        const sorted = computed.courseStudentsList.call(ctx)

        expect(sorted.map((student: { id: number }) => student.id)).toEqual([3, 2, 1, 4])
        expect(computed.currentStudentIndex.call({ ...ctx, courseStudentsList: sorted })).toBe(1)
    })

    it('uses last name plus first name for name sorting', () => {
        const computed = (CourseStudent as any).computed
        const methods = (CourseStudent as any).methods
        const ctx: Record<string, any> = {
            selected_course: {
                students_info: [
                    { id: 1, schoolclass: '2B', last_name: 'Mayer', first_name: 'Zoe', canceled_at: null },
                    { id: 2, schoolclass: '1A', last_name: 'Mayer', first_name: 'Anna', canceled_at: null },
                    { id: 3, schoolclass: '3C', last_name: 'Auer', first_name: 'Clara', canceled_at: null },
                ],
            },
            selected_course_student: { id: 2, schoolclass: '1A', last_name: 'Mayer', first_name: 'Anna', canceled_at: null },
            students_sort_mode: 'last_name_first_name',
            isStudentCanceled: methods.isStudentCanceled,
            compareStudentsBySelectedSort: methods.compareStudentsBySelectedSort,
            studentClassValue: methods.studentClassValue,
        }

        const sorted = computed.courseStudentsList.call(ctx)

        expect(sorted.map((student: { id: number }) => student.id)).toEqual([3, 2, 1])
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
            semester_points_table: [
                { grade: '1', min_points: 5 },
                { grade: '2', min_points: 3 },
                { grade: '3', min_points: 1 },
                { grade: '4', min_points: 0 },
            ],
            semester_points_sonst_grade: '5',
        }

        const result = methods.pointsGradeForWork.call({}, work, -2)

        expect(result).toBe('5')
    })

    it('falls back to legacy points tables when no semester points table exists', () => {
        const methods = (CourseStudent as any).methods
        const work = {
            calculation: 'points',
            points_table: [
                { grade: '1', min_points: 5 },
                { grade: '2', min_points: 3 },
            ],
            points_sonst_grade: '5',
        }

        const result = methods.pointsGradeForWork.call({}, work, 4)

        expect(result).toBe('2')
    })
})

describe('CourseStudent course-specific definitions', () => {
    it('prefers the selected course schema, entry definitions, and behaviour visibility', () => {
        const computed = (CourseStudent as any).computed
        const ctx: Record<string, unknown> = {
            selected_course: {
                teaching_schema_id: 'schema-teacher',
                teacher_teaching_schema: {
                    id: 'schema-teacher',
                    works: [{ short_name: 'MA', name: 'Mitarbeit' }],
                    grading: { semester_count: 2 },
                },
                teacher_teaching_behaviour: [{ short_name: 'BZ', name: 'Benehmen' }],
                teacher_teaching_notifications: [{ short_name: 'INF', name: 'Info' }],
                teacher_teaching_show_behaviour: false,
            },
            config: { user: { teaching_schemas: [{ id: 'schema-global', works: [], grading: { semester_count: 1 } }] } },
            settings: {
                teaching_behaviour: [{ short_name: 'ALT', name: 'Alt' }],
                teaching_notifications: [{ short_name: 'ALTN', name: 'Alt Notification' }],
                teaching_show_behaviour: true,
                teaching_schemas: [{ id: 'schema-global', works: [], grading: { semester_count: 1 } }],
            },
        }

        ctx.teachingSchemas = computed.teachingSchemas.call(ctx)
        ctx.selectedSchema = computed.selectedSchema.call(ctx)

        expect(ctx.teachingSchemas).toHaveLength(1)
        expect((ctx.selectedSchema as any)?.id).toBe('schema-teacher')
        expect(computed.semesterCount.call(ctx)).toBe(2)
        expect(computed.teachingBehaviour.call(ctx)).toEqual([{ short_name: 'BZ', name: 'Benehmen' }])
        expect(computed.teachingNotifications.call(ctx)).toEqual([{ short_name: 'INF', name: 'Info' }])
        expect(computed.showBehaviourEnabled.call(ctx)).toBe(false)
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
    it('keeps auswertung active when switching selected students', () => {
        const watcher = (CourseStudent as any).watch.selected_course_student.handler
        const methods = (CourseStudent as any).methods
        const ctx = {
            show_entry_form: true,
            entry_form: { id: 1 },
            show_behaviour_form: true,
            behaviour_form: { id: 2 },
            show_star_form: true,
            star_form: { id: 3 },
            delete_star_id: 4,
            delete_behaviour_id: 5,
            delete_notification_id: 6,
            is_editing_grades: true,
            is_editing_behaviour_grades: true,
            show_auswertung: true,
            emptyEntryForm: methods.emptyEntryForm,
            emptyBehaviourForm: methods.emptyBehaviourForm,
            emptyStarForm: methods.emptyStarForm,
            selected_courseDate: null,
            toInputDate: methods.toInputDate,
            normalizeDateString: methods.normalizeDateString,
            toDateString: methods.toDateString,
            loadEntries: vi.fn(),
            loadBehaviourEntries: vi.fn(),
        }

        watcher.call(ctx)

        expect(ctx.show_auswertung).toBe(true)
        expect(ctx.loadEntries).toHaveBeenCalledOnce()
        expect(ctx.loadBehaviourEntries).toHaveBeenCalledOnce()
    })

    it('clears auswertung when closing the student detail', () => {
        const methods = (CourseStudent as any).methods
        const ctx = {
            selected_course_student: { id: 1 },
            action_2: 'detail',
            entryStore: { clear: vi.fn() },
            behaviourEntryStore: { clear: vi.fn() },
            show_star_form: true,
            star_form: { id: 2 },
            delete_star_id: 3,
            delete_behaviour_id: 4,
            delete_notification_id: 5,
            show_auswertung: true,
            emptyStarForm: methods.emptyStarForm,
        }

        methods.closeStudent.call(ctx)

        expect(ctx.selected_course_student).toBeNull()
        expect(ctx.action_2).toBe('')
        expect(ctx.show_auswertung).toBe(false)
        expect(ctx.entryStore.clear).toHaveBeenCalledOnce()
        expect(ctx.behaviourEntryStore.clear).toHaveBeenCalledOnce()
    })

    it('uses Auswerten button near Zurück and removes inline Auswertung header row', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseStudent.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const auswertenButtonIndex = source.indexOf('Auswerten')
        const auswertungCardIndex = source.indexOf('<v-card v-if="hasAuswertungContent && show_auswertung" variant="outlined">')
        const entriesTitleIndex = source.indexOf('Bewertungen')

        expect(source).toContain(":color=\"show_auswertung ? 'success' : 'primary'\"")
        expect(source).toContain(":variant=\"show_auswertung ? 'flat' : 'tonal'\"")
        expect(source).toContain(":prepend-icon=\"show_auswertung ? 'mdi-eye-off' : 'mdi-eye'\"")
        expect(source).toContain(":aria-pressed=\"show_auswertung ? 'true' : 'false'\"")
        expect(source).toContain('@click="show_auswertung = !show_auswertung"')
        expect(source).toContain('Auswerten')
        expect(source).toContain('<v-card v-if="hasAuswertungContent && show_auswertung" variant="outlined">')
        expect(source).toContain('<v-card v-if="!show_auswertung" variant="outlined" class="mt-4">')
        expect(source).not.toContain('v-card-text v-if="hasAuswertungContent && show_auswertung" class="py-2"')
        expect(source).not.toContain('<div class="text-subtitle-2">Auswertung</div>')
        expect(auswertenButtonIndex).toBeGreaterThan(-1)
        expect(auswertungCardIndex).toBeGreaterThan(-1)
        expect(entriesTitleIndex).toBeGreaterThan(auswertungCardIndex)
    })
})

describe('CourseStudent auswertung semester cards', () => {
    it('wraps semester sections in bordered background cards', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseStudent.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="auswertung-semester-card auswertung-semester-card--semester-1"')
        expect(source).toContain('class="auswertung-semester-card auswertung-semester-card--semester-2"')
        expect(source).toContain('.auswertung-semester-card {')
        expect(source).toContain('border: 1px solid rgba(var(--v-theme-primary), 0.18);')
        expect(source).toContain('background: linear-gradient(180deg, rgba(var(--v-theme-primary), 0.08) 0%, rgba(var(--v-theme-surface), 0.96) 100%);')
        expect(source).toContain('.auswertung-semester-card--semester-2 {')
    })
})

describe('CourseStudent auswertung category cards', () => {
    it('wraps each category within a semester in its own bordered card', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseStudent.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<div class="auswertung-category-card">')
        expect(source).toContain('.auswertung-category-card {')
        expect(source).toContain('border: 1px solid rgba(var(--v-theme-primary), 0.12);')
        expect(source).toContain('background: rgba(var(--v-theme-surface), 0.92);')
        expect(source).toContain('0 8px 18px rgba(var(--v-theme-primary), 0.08),')
    })
})

describe('CourseStudent auswertung sum chip order', () => {
    it('renders the sum chip before the grade chip in semester rows', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseStudent.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const sumIndex = source.indexOf('v-chip v-if="row.sum != null" size="x-small" variant="tonal" color="primary">Σ {{ row.sum }}</v-chip>')
        const gradeIndex = source.indexOf('v-chip v-if="row.grade" size="x-small" variant="tonal" :color="isNaGradeKey(row.grade) ? \'error\' : \'primary\'">{{ row.grade }}</v-chip>')

        expect(sumIndex).toBeGreaterThan(-1)
        expect(gradeIndex).toBeGreaterThan(sumIndex)
    })
})

describe('CourseStudent auswertung semester header totals', () => {
    it('renders semester Bewertung values in the semester headers instead of footer cards', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseStudent.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const semester1HeaderIndex = source.indexOf("<span>{{ semesterCount === 2 ? 'Semester 1' : 'Auswertung' }}</span>")
        const semester1BewertungIndex = source.indexOf('{{ formatEvaluationValue(semester1Total) }}')
        const semester1NoteIndex = source.indexOf("Note: {{ semesterCount === 2 ? (selected_course_student?.sem_1_grade || '–') : (selected_course_student?.sem_grade || '–') }}")
        const semester2HeaderIndex = source.indexOf('<span>Semester 2</span>')
        const semester2BewertungIndex = source.indexOf('{{ formatEvaluationValue(semester2Total) }}')
        const semester2GesamtIndex = source.indexOf('Gesamt: {{ formatEvaluationValue(semesterWeightedGrade.value) }}')
        const semester2NoteIndex = source.indexOf("Note: {{ selected_course_student?.sem_2_grade || '–' }}")

        expect(source).toContain('v-if="semester1Total != null"')
        expect(source).toContain('v-if="semester2Total != null"')
        expect(semester1BewertungIndex).toBeGreaterThan(semester1HeaderIndex)
        expect(semester2BewertungIndex).toBeGreaterThan(semester2HeaderIndex)
        expect(semester1NoteIndex).toBeGreaterThan(semester1BewertungIndex)
        expect(semester2GesamtIndex).toBeGreaterThan(semester2BewertungIndex)
        expect(semester2NoteIndex).toBeGreaterThan(semester2GesamtIndex)
        expect(source).not.toContain('Bewertung: {{ formatEvaluationValue(semester1Total) }}')
        expect(source).not.toContain('Bewertung: {{ formatEvaluationValue(semester2Total) }}')
        expect(source).not.toContain('class="auswertung-total-card"')
        expect(source).not.toContain('class="auswertung-total-card auswertung-total-card--semester-2"')
        expect(source).not.toContain('Berechnung Sem 2</v-list-item-title>')
    })
})

describe('CourseStudent auswertung sum card', () => {
    it('renders the weighted semester sum inside its own highlighted bordered card', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseStudent.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="w-100 auswertung-sum-card"')
        expect(source).toContain('.auswertung-sum-card {')
        expect(source).toContain('border: 1px solid rgba(var(--v-theme-secondary), 0.32);')
        expect(source).toContain('background: linear-gradient(135deg, rgba(var(--v-theme-secondary), 0.18) 0%, rgba(var(--v-theme-secondary), 0.08) 100%);')
        expect(source).toContain('<span>GESAMT</span>')
        expect(source).not.toContain('Berechnung:')
        expect(source).not.toContain('Basis Sem 2:')
        expect(source).not.toContain('categoryCalculationLine')
        expect(source).not.toContain('sem1CalculatedFormula')
        expect(source).not.toContain('sem2CalculatedFormula')
        expect(source).toContain('<div class="auswertung-section-header">')
        expect(source).not.toContain('<div class="auswertung-section-header auswertung-section-header--sum">')
        expect(source).toContain('class="sum-formula-columns" :class="{ \'sum-formula-columns--stacked\': semesterCount !== 2 }"')
        expect(source).toContain('class="sum-formula-column"')
        expect(source).toContain('class="sum-formula-column-label">Semester 1</div>')
        expect(source).toContain('class="sum-formula-column-label">Semester 2</div>')
        expect(source).toContain('class="sum-formula-column-share">{{ semesterWeightedGrade.sem1Weight }}%</div>')
        expect(source).toContain('class="sum-formula-column-share">{{ semesterWeightedGrade.sem2Weight }}%</div>')
        expect(source).toContain('class="sum-formula-column-grade">{{ formatEvaluationValue(semesterWeightedGrade.sem1Value) }}</div>')
        expect(source).toContain('class="sum-formula-column-grade">{{ formatEvaluationValue(semesterWeightedGrade.sem2Value) }}</div>')
        expect(source).toContain('.sum-formula-columns {')
        expect(source).toContain('grid-template-columns: repeat(2, minmax(0, 1fr));')
        expect(source).toContain('.sum-formula-column-share {')
        expect(source).toContain('class="sum-formula-result-card"')
        expect(source).toContain('class="sum-formula-result-value" :class="isNaGradeKey(semesterWeightedGrade.value) ? \'text-error\' : \'text-primary\'"')
        expect(source).not.toContain("{{ formatTwoDecimals(semesterWeightedGrade.sem1Value) }} * {{ formatTwoDecimals(semesterWeightedGrade.sem1SharePercent) }}% +")
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

    it('uses NA as display value when a work entry has no grade', () => {
        const work = {
            short_name: 'PÜ',
            calculation: 'grade',
            grades: [{ grade: '1', value: 1 }, { grade: '2', value: 2 }],
            default_grade: '',
        }
        const ctx = makeCtx({
            teachingWorks: [work],
            selectedSchema: {
                grading: {
                    categories: [
                        { name: 'Praktische Übungen', weight: 100, require_all_entries: false, works: [{ short_name: 'PÜ', factor: 100 }] },
                    ],
                },
            },
        })

        const entries = [
            { id: 1, type: 'PÜ', grade: '', effective_grade: '', teaching_course_work_id: 10, date: '2026-04-13' },
        ]

        const groups = methods.buildCategoryGroups.call(ctx, entries)

        expect(groups).toHaveLength(1)
        expect(groups[0].rows).toHaveLength(1)
        expect(groups[0].rows[0].value).toBe('NA')
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
