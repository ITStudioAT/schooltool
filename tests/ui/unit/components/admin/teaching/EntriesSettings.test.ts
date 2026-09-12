import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import Entries from '@/pages/admin/teaching/settings/components/Entries.vue'

function entryFixture(overrides = {}) {
    return {
        id: 1,
        teaching_entry_area_id: 10,
        teaching_entry_grading_part_id: null,
        short_name: 'M',
        name: 'Mitarbeit',
        description: null,
        category: 'Benotung',
        has_properties: true,
        properties_mode: 'fixed',
        fixed_properties: ['+', '-'],
        has_notifications: false,
        notification_recipients: [],
        has_table_marking: false,
        table_marking_color: null,
        ...overrides,
    }
}

describe('Teaching entries settings', () => {
    it('shows an extra mapped to grade 5 as a negative grade without a plus sign', () => {
        const entry = { calculation_mode: 'grades', property_evaluations: [{ property: 'F', evaluation: 5 }] }
        expect((Entries as any).methods.propertyEvaluationLabel.call({}, entry, 'F')).toBe('Note 5')
        expect((Entries as any).methods.propertyEvaluationClass.call({}, entry, 'F')).toBe('calculation-evaluation--negative')
    })

    it('uses Standard Noten for grades 1 to 5 and keeps F as an editable extra', () => {
        const ctx: any = {}
        const entry = entryFixture({ calculation_mode: 'grades', fixed_properties: ['1', '2', '3', '4', '5', 'F'], property_evaluations: [] })
        ;(Entries as any).methods.openCalculationEntryDialog.call(ctx, entry)
        expect(ctx.calculationMode).toBe('grades')
        expect((Entries as any).computed.calculationEvaluationRows.call(ctx)).toEqual([])
        ctx.showUnassignedCalculationProperties = true
        expect((Entries as any).computed.calculationEvaluationRows.call(ctx)).toEqual([{ property: 'F', evaluation: null }])
        expect((Entries as any).methods.standardCalculationLabel.call({}, entry)).toBe('Standard Noten')
    })

    it('keeps standard signs hidden and makes blank predefined extras available without retyping them', () => {
        const ctx: any = {}
        const entry = entryFixture({
            calculation_mode: 'plus_minus', properties_mode: 'fixed',
            fixed_properties: ['++++', '+++', '++', '+', '0', 'F'], property_evaluations: [],
        })
        const open = (Entries as any).methods.openCalculationEntryDialog
        const rows = (Entries as any).computed.calculationEvaluationRows
        open.call(ctx, entry)
        expect(rows.call(ctx)).toEqual([])
        ctx.showUnassignedCalculationProperties = true
        expect(rows.call(ctx)).toEqual([{ property: 'F', evaluation: null }])
        open.call(ctx, { ...entry, property_evaluations: [{ property: 'F', evaluation: 'ignored' }] })
        expect(rows.call(ctx)).toEqual([{ property: 'F', evaluation: 'ignored' }])
    })

    it('loads free input mappings only from the selected entry', () => {
        const ctx: any = {}
        const mappings = [{ property: '++++', evaluation: 4 }, { property: '~', evaluation: 0 }, { property: 'x', evaluation: 'ignored' }]
        const open = (Entries as any).methods.openCalculationEntryDialog
        open.call(ctx, entryFixture({ properties_mode: 'free', property_evaluations: mappings }))
        expect(ctx.calculationEvaluationForm).toEqual(mappings)
        open.call(ctx, entryFixture({ id: 2, properties_mode: 'free', property_evaluations: [] }))
        expect(ctx.calculationEvaluationForm).toEqual([])
    })

    it.each([
        [[{ property: '++++', evaluation: 4 }, { property: '~', evaluation: 0 }], true],
        [[{ property: 'x', evaluation: 'ignored' }], true],
        [[{ property: '', evaluation: 1 }], false],
        [[{ property: '+', evaluation: null }], false],
        [[{ property: '+', evaluation: 1 }, { property: ' + ', evaluation: 2 }], false],
        [[{ property: '+', evaluation: Infinity }], false],
    ])('validates free input mappings %j', (form, valid) => {
        const result = (Entries as any).computed.calculationSettingsValidationMessage.call({
            calculationEntryForEditing: { properties_mode: 'free' }, calculationEvaluationForm: form,
        })
        expect(result === '').toBe(valid)
    })

    it.each([
        ['positive', '+1'], ['negative', '-1'], ['neutral', '0'], [0, '0'], [4, '+4'], [-2.5, '-2,5'],
        ['ignored', 'NB'], [null, ''],
    ])('shows the configured evaluation %s in the overview', (evaluation, expected) => {
        const entry = { property_evaluations: evaluation !== null ? [{ property: 'erledigt', evaluation }] : [] }
        expect((Entries as any).methods.propertyEvaluationLabel.call({}, entry, 'erledigt')).toBe(expected)
    })

    it('loads classifications without guessing from property names and discards unsaved edits when reopened', () => {
        const ctx: any = {}
        const entry = entryFixture({
            properties_mode: 'fixed', fixed_properties: ['erledigt', 'nicht erledigt', 'gefehlt'],
            property_evaluations: [{ property: 'gefehlt', evaluation: 'neutral' }],
        })
        const open = (Entries as any).methods.openCalculationEntryDialog
        open.call(ctx, entry)
        expect(ctx.calculationEvaluationForm).toEqual([
            { property: 'erledigt', evaluation: null },
            { property: 'nicht erledigt', evaluation: null },
            { property: 'gefehlt', evaluation: 0 },
        ])
        ctx.calculationEvaluationForm[0].evaluation = 1
        open.call(ctx, entry)
        expect(ctx.calculationEvaluationForm[0].evaluation).toBeNull()
        expect(ctx.calculationEntryDialogOpen).toBe(true)
    })

    it('saves only explicitly selected property classifications', async () => {
        const saved = entryFixture()
        const put = vi.fn().mockResolvedValue({ data: { data: saved } })
        vi.stubGlobal('axios', { put })
        const ctx: any = {
            calculationEntryForEditing: saved,
            calculationEntryDialogOpen: true,
            isSavingCalculationSettings: false,
            calculationEvaluationForm: [
                { property: '+', evaluation: 1 },
                { property: '-', evaluation: null },
                { property: 'gefehlt', evaluation: 0 },
            ],
            replaceGradingEntry: vi.fn(), notifyError: vi.fn(),
        }
        try {
            await (Entries as any).methods.saveCalculationSettings.call(ctx)
            expect(put).toHaveBeenCalledWith('/api/admin/teaching/entry_definitions/1/calculation-settings', {
                calculation_mode: 'individual',
                property_evaluations: [
                    { property: '+', evaluation: 1 },
                    { property: 'gefehlt', evaluation: 0 },
                ],
            })
            expect(ctx.replaceGradingEntry).toHaveBeenCalledWith(saved)
            expect(ctx.calculationEntryDialogOpen).toBe(false)
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it('loads saved semester settings for the selected area and keeps drafts separate', () => {
        const ctx: any = {
            activeAreaId: 10,
            areas: [{ id: 10, semester_count: 2, semester_1_weight: 40, semester_2_weight: 60 }, { id: 20 }],
            semesterDrafts: {},
        }
        const form = (Entries as any).computed.semesterForm
        ctx.semesterForm = form.call(ctx)
        expect(ctx.semesterForm).toEqual({ semester_count: 2, semester_1_weight: 40, semester_2_weight: 60 })
        ;(Entries as any).methods.updateSemesterField.call(ctx, 'semester_2_weight', '30')
        expect(form.call(ctx).semester_2_weight).toBe('30')
        expect(form.call(ctx).semester_1_weight).toBe(70)
        ctx.activeAreaId = 20
        expect(form.call(ctx)).toEqual({ semester_count: 1, semester_1_weight: 50, semester_2_weight: 50 })
    })

    it.each([
        ['0', 100], ['100', 0], ['40', 60], ['', ''], [null, ''], ['101', ''], ['-1', ''], ['40.5', ''],
    ])('automatically complements the second semester percentage %s', (value, expected) => {
        const ctx: any = {
            activeAreaId: 10,
            semesterDrafts: {},
            semesterForm: { semester_count: 2, semester_1_weight: 50, semester_2_weight: 50 },
        }
        ;(Entries as any).methods.updateSemesterField.call(ctx, 'semester_2_weight', value)
        expect(ctx.semesterDrafts[10].semester_1_weight).toBe(expected)
    })

    it.each([
        [2, 40, 60, true],
        [2, 0, 100, true],
        [2, 100, 0, true],
        [2, 30, 60, false],
        [2, '', 100, false],
        [2, -1, 101, false],
        [2, 40.5, 59.5, false],
        [1, '', '', true],
    ])('validates semester count %s with weights %s/%s', (count, first, second, valid) => {
        const message = (Entries as any).computed.semesterValidationMessage.call({
            semesterForm: { semester_count: count, semester_1_weight: first, semester_2_weight: second },
        })
        expect(message === '').toBe(valid)
    })

    it.each([1, 2])('saves %s semester configuration for its original area', async (count) => {
        const saved = { id: 10, name: 'Unterstufe', semester_count: count, semester_1_weight: count === 1 ? 100 : 40, semester_2_weight: count === 1 ? 0 : 60 }
        const put = vi.fn().mockResolvedValue({ data: { data: saved } })
        vi.stubGlobal('axios', { put })
        const ctx: any = {
            activeAreaId: 10,
            areas: [{ id: 10, name: 'Unterstufe' }],
            semesterForm: { semester_count: count, semester_1_weight: '40', semester_2_weight: '60' },
            semesterDrafts: { 10: {} },
            semesterValidationMessage: '',
            isSavingSemesters: false,
            notifyError: vi.fn(),
        }
        try {
            await (Entries as any).methods.saveSemesterSettings.call(ctx)
            expect(put).toHaveBeenCalledWith('/api/admin/teaching/entry_areas/10', {
                name: saved.name,
                semester_count: count,
                semester_1_weight: saved.semester_1_weight,
                semester_2_weight: saved.semester_2_weight,
            })
            expect(ctx.areas[0]).toEqual(saved)
            expect(ctx.semesterDrafts[10]).toBeUndefined()
            expect(ctx.isSavingSemesters).toBe(false)
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it('retains semester inputs when saving fails', async () => {
        const error = new Error('Save failed')
        vi.stubGlobal('axios', { put: vi.fn().mockRejectedValue(error) })
        const draft = { semester_count: 2, semester_1_weight: 40, semester_2_weight: 60 }
        const ctx: any = {
            activeAreaId: 10,
            areas: [{ id: 10, name: 'Unterstufe' }],
            semesterForm: draft,
            semesterDrafts: { 10: draft },
            semesterValidationMessage: '',
            isSavingSemesters: false,
            notifyError: vi.fn(),
        }
        try {
            await (Entries as any).methods.saveSemesterSettings.call(ctx)
            expect(ctx.semesterDrafts[10]).toEqual(draft)
            expect(ctx.notifyError).toHaveBeenCalledWith(error)
            expect(ctx.isSavingSemesters).toBe(false)
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it('filters entries by area and category', () => {
        const ctx = {
            activeAreaId: 20,
            activeCategory: 'Verhalten',
            entries: [entryFixture(), entryFixture({ id: 2, teaching_entry_area_id: 20, category: 'Verhalten' })],
        }

        expect((Entries as any).computed.filteredEntries.call(ctx).map((entry: any) => entry.id)).toEqual([2])
    })

    it('sorts overview entries by short name without changing the source order', () => {
        const entries = [
            entryFixture({ id: 1, short_name: 'Z', name: 'Erste Mitarbeit' }),
            entryFixture({ id: 2, short_name: 'A', name: 'Zweite Mitarbeit' }),
            entryFixture({ id: 3, category: 'Verhalten', name: 'Andere Kategorie' }),
        ]
        const ctx = {
            activeAreaId: 10,
            activeCategory: 'Benotung',
            entries,
        }

        expect((Entries as any).computed.filteredEntries.call(ctx).map((entry: any) => entry.id)).toEqual([2, 1])
        expect(entries.map((entry) => entry.id)).toEqual([1, 2, 3])
    })

    it('restores the selected entry category from the URL', () => {
        const methods = (Entries as any).methods
        const replace = vi.fn().mockResolvedValue(undefined)
        const ctx: any = {
            activeCategory: 'Benotung',
            categoryOptions: ['Benotung', 'Berechnung', 'Verhalten', 'Weitere'],
            $route: { query: { panel: 'entries', entry_category: 'Verhalten' } },
            $router: { replace },
            normalizeCategoryQuery: methods.normalizeCategoryQuery,
            isEntriesPanelActive: methods.isEntriesPanelActive,
            syncCategoryQuery: methods.syncCategoryQuery,
        }

        methods.restoreCategoryFromRoute.call(ctx)

        expect(ctx.activeCategory).toBe('Verhalten')
        expect(replace).not.toHaveBeenCalled()
    })

    it('writes the selected entry category to the URL and preserves the settings panel', () => {
        const methods = (Entries as any).methods
        const replace = vi.fn().mockResolvedValue(undefined)
        const ctx: any = {
            categoryOptions: ['Benotung', 'Berechnung', 'Verhalten', 'Weitere'],
            $route: { query: { panel: 'entries', existing: 'value' } },
            $router: { replace },
            normalizeCategoryQuery: methods.normalizeCategoryQuery,
            isEntriesPanelActive: methods.isEntriesPanelActive,
        }

        methods.syncCategoryQuery.call(ctx, 'Weitere')

        expect(replace).toHaveBeenCalledWith({
            query: {
                panel: 'entries',
                existing: 'value',
                entry_category: 'Weitere',
            },
        })
    })

    it('replaces an invalid entry category with Benotung', () => {
        const methods = (Entries as any).methods
        const replace = vi.fn().mockResolvedValue(undefined)
        const ctx: any = {
            activeCategory: 'Weitere',
            categoryOptions: ['Benotung', 'Berechnung', 'Verhalten', 'Weitere'],
            $route: { query: { panel: 'entries', entry_category: 'Unbekannt' } },
            $router: { replace },
            normalizeCategoryQuery: methods.normalizeCategoryQuery,
            isEntriesPanelActive: methods.isEntriesPanelActive,
            syncCategoryQuery: methods.syncCategoryQuery,
        }

        methods.restoreCategoryFromRoute.call(ctx)

        expect(ctx.activeCategory).toBe('Benotung')
        expect(replace).toHaveBeenCalledWith({
            query: { panel: 'entries', entry_category: 'Benotung' },
        })
    })

    it('does not restore the entry category after leaving the entries panel', () => {
        const methods = (Entries as any).methods
        const replace = vi.fn().mockResolvedValue(undefined)
        const ctx: any = {
            activeCategory: 'Benotung',
            categoryOptions: ['Benotung', 'Berechnung', 'Verhalten', 'Weitere'],
            $route: { query: { panel: 'basic' } },
            $router: { replace },
            normalizeCategoryQuery: methods.normalizeCategoryQuery,
            isEntriesPanelActive: methods.isEntriesPanelActive,
            syncCategoryQuery: methods.syncCategoryQuery,
        }

        methods.restoreCategoryFromRoute.call(ctx)

        expect(replace).not.toHaveBeenCalled()
    })

    it('restores the current category when returning to the entries panel', () => {
        const methods = (Entries as any).methods
        const restoreCategoryFromRoute = vi.fn()
        const ctx: any = {
            activeCategory: 'Berechnung',
            $route: { query: { panel: 'entries' } },
            isEntriesPanelActive: methods.isEntriesPanelActive,
            restoreCategoryFromRoute,
        }

        ;(Entries as any).watch['$route.query.panel'].call(ctx, 'entries')

        expect(restoreCategoryFromRoute).toHaveBeenCalledWith('Berechnung')
    })

    it('shows calculation entries only for the selected area', () => {
        const ctx = {
            activeAreaId: 20,
            entries: [
                entryFixture(),
                entryFixture({ id: 2, category: 'Verhalten' }),
                entryFixture({ id: 3, teaching_entry_area_id: 20 }),
            ],
        }

        expect((Entries as any).computed.calculationEntries.call(ctx)).toEqual([expect.objectContaining({ id: 3 })])
    })

    it('shows all grading entries and their properties independently from grading parts', () => {
        const entries = [
            entryFixture({ id: 3, teaching_entry_area_id: 20, fixed_properties: ['+', '++', '-'] }),
            entryFixture({ id: 4, teaching_entry_area_id: 20, properties_mode: 'free', fixed_properties: [] }),
            entryFixture({ id: 5, teaching_entry_area_id: 20, category: 'Verhalten' }),
        ]
        const ctx = {
            activeAreaId: 20,
            entries,
        }

        expect((Entries as any).computed.calculationEntries.call(ctx)).toEqual([entries[0], entries[1]])
    })

    it('renders assigned grading entries in their grading part card', () => {
        const assignedEntry = entryFixture({
            id: 3,
            teaching_entry_area_id: 20,
            teaching_entry_grading_part_id: 100,
        })
        const ctx = {
            activeAreaId: 20,
            calculationEntries: [assignedEntry, entryFixture({ id: 4, teaching_entry_area_id: 20 })],
            gradingParts: [
                { id: 100, teaching_entry_area_id: 20, name: 'Mündlich' },
                { id: 200, teaching_entry_area_id: 10, name: 'Schriftlich' },
            ],
        }

        expect((Entries as any).computed.calculationAreas.call(ctx)).toEqual([{
            id: 'grading-part-100',
            gradingPartId: 100,
            teaching_entry_area_id: 20,
            name: 'Mündlich',
            entries: [assignedEntry],
        }])
    })

    it('preselects the entries already assigned to the selected grading part', () => {
        const methods = (Entries as any).methods
        const ctx: any = {
            calculationEntries: [
                entryFixture({ id: 1, short_name: 'M', name: 'Mitarbeit' }),
                entryFixture({ id: 2, short_name: 'S', name: 'Schularbeit', teaching_entry_grading_part_id: 100 }),
                entryFixture({ id: 3, short_name: 'P', name: 'Prüfung', teaching_entry_grading_part_id: 200 }),
            ],
            assignGradingPartId: null,
            selectedGradingEntryIds: [],
        }

        methods.toggleGradingEntryAssignment.call(ctx, { gradingPartId: 100 })

        expect(ctx.selectedGradingEntryIds).toEqual([2])
    })

    it('shows the possible values for every calculation entry type', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/settings/components/Entries.vue'), 'utf8')

        expect(source).not.toContain('Mögliche Werte:')
        expect(source).toContain('v-for="entry in calculationEntries"')
        expect(source).toContain('Benotungsteil hinzufügen')
        expect(source).toContain('@click="openCreateGradingPartDialog"')
        expect(source).toContain('v-for="property in entry.fixed_properties"')
        expect(source).toContain("entry.has_properties && entry.properties_mode === 'free'")
        expect(source).toContain('Freie Eingabe')
        expect(source).toContain('Keine zusätzlichen Werte')
    })

    it('opens a blank entry in the selected area', () => {
        const methods = (Entries as any).methods
        expect((Entries as any).data().entryForm.description).toBe('')

        const ctx: any = {
            areas: [{ id: 10, name: 'Unterstufe' }],
            activeAreaId: 10,
            activeCategory: 'Weitere',
            selectedEntryId: 8,
            entryForm: null,
            formErrors: {},
            editDialogOpen: false,
            createEmptyEntry: methods.createEmptyEntry,
        }

        methods.openCreateDialog.call(ctx)

        expect(ctx.entryForm.teaching_entry_area_id).toBe(10)
        expect(ctx.entryForm.category).toBe('Weitere')
        expect(ctx.entryForm.description).toBe('')
        expect(ctx.entryForm.has_table_marking).toBe(false)
        expect(ctx.entryForm.table_marking_color).toBeNull()
        expect(ctx.editDialogOpen).toBe(true)
    })

    it('loads an existing description when editing an entry', () => {
        const methods = (Entries as any).methods
        const ctx: any = {
            selectedEntryId: null,
            entryForm: null,
            formErrors: { description: ['Veraltet'] },
            editDialogOpen: false,
        }

        methods.openEditDialog.call(ctx, entryFixture({ description: 'Hinweise zur Verwendung' }))

        expect(ctx.selectedEntryId).toBe(1)
        expect(ctx.entryForm.description).toBe('Hinweise zur Verwendung')
        expect(ctx.formErrors).toEqual({})
        expect(ctx.editDialogOpen).toBe(true)
    })

    it('shows an entry description below its title in the overview', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/settings/components/Entries.vue'), 'utf8')
        const titleIndex = source.indexOf('<span class="entry-name"')
        const descriptionIndex = source.indexOf('class="entry-description"')

        expect(titleIndex).toBeGreaterThan(-1)
        expect(descriptionIndex).toBeGreaterThan(titleIndex)
        expect(source).toContain('{{ formatEntryDescription(entry.description) }}')
        expect(source).toContain('font-size: 0.78rem')
        expect(source).toContain('white-space: pre-line')
    })

    it('renders line feeds and br markers as description line breaks', () => {
        const formatEntryDescription = (Entries as any).methods.formatEntryDescription

        expect(formatEntryDescription('Erste Zeile\nZweite Zeile')).toBe('Erste Zeile\nZweite Zeile')
        expect(formatEntryDescription('Erste Zeile<br>Zweite Zeile<BR />Dritte Zeile')).toBe(
            'Erste Zeile\nZweite Zeile\nDritte Zeile',
        )
    })

    it('saves notifications instead of properties for behaviour and other entries', async () => {
        const methods = (Entries as any).methods
        const post = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { id: 2, ...payload } } }))
        ;(globalThis as any).axios = { post }

        for (const category of ['Verhalten', 'Weitere']) {
            const ctx: any = {
                areas: [{ id: 10, name: 'Unterstufe' }],
                entries: [],
                entryForm: entryFixture({
                    category,
                    has_notifications: true,
                    notification_recipients: ['class_teacher', 'parents'],
                    has_table_marking: false,
                    table_marking_color: null,
                }),
                selectedEntryId: null,
                activeAreaId: 10,
                activeCategory: category,
                canSaveEntry: true,
                formErrors: {},
                isSaving: false,
                normalizeShortName: methods.normalizeShortName,
                closeEditDialog: methods.closeEditDialog,
                notifyError: vi.fn(),
            }

            await methods.saveEntry.call(ctx)

            expect(post).toHaveBeenLastCalledWith(
                '/api/admin/teaching/entry_definitions',
                expect.objectContaining({
                    category,
                    has_properties: false,
                    properties_mode: 'free',
                    fixed_properties: [],
                    has_notifications: true,
                    notification_recipients: ['class_teacher', 'parents'],
                }),
            )
        }
    })

    it('saves table marking and clears stale notification recipients for grading entries', async () => {
        const methods = (Entries as any).methods
        const post = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { id: 2, ...payload } } }))
        const syncEntryDefinition = vi.fn()
        ;(globalThis as any).axios = { post }
        const ctx: any = {
            areas: [{ id: 10, name: 'Unterstufe' }],
            entries: [],
            entryForm: entryFixture({
                description: '  Für Wiederholungen.  ',
                has_notifications: true,
                notification_recipients: ['student'],
                has_table_marking: true,
                table_marking_color: 'purple',
            }),
            selectedEntryId: null,
            activeAreaId: 10,
            activeCategory: 'Benotung',
            canSaveEntry: true,
            formErrors: {},
            isSaving: false,
            normalizeShortName: methods.normalizeShortName,
            closeEditDialog: methods.closeEditDialog,
            notifyError: vi.fn(),
            courseStore: { syncEntryDefinition },
        }

        await methods.saveEntry.call(ctx)

        expect(post).toHaveBeenCalledWith(
            '/api/admin/teaching/entry_definitions',
            expect.objectContaining({
                has_notifications: false,
                notification_recipients: [],
                has_table_marking: true,
                table_marking_color: 'purple',
                description: 'Für Wiederholungen.',
            }),
        )
        expect(syncEntryDefinition).toHaveBeenCalledWith(expect.objectContaining({
            table_marking_color: 'purple',
        }))
    })

    it('requires a configured color when table marking is enabled', () => {
        const canSaveEntry = (Entries as any).computed.canSaveEntry
        const ctx: any = {
            areas: [{ id: 10, name: 'Unterstufe' }],
            entryForm: entryFixture({ teaching_entry_area_id: 10, has_table_marking: true, table_marking_color: null }),
        }

        expect(canSaveEntry.call(ctx)).toBe(false)

        ctx.entryForm.table_marking_color = 'green'
        expect(canSaveEntry.call(ctx)).toBe(true)
    })

    it('offers exactly five table marking colors', () => {
        expect((Entries as any).data().tableMarkingColors).toEqual([
            expect.objectContaining({ value: 'blue', label: 'Blau' }),
            expect.objectContaining({ value: 'green', label: 'Grün' }),
            expect.objectContaining({ value: 'orange', label: 'Orange' }),
            expect.objectContaining({ value: 'purple', label: 'Violett' }),
            expect.objectContaining({ value: 'red', label: 'Rot' }),
        ])
    })

    it('creates and renames areas through their API', async () => {
        const methods = (Entries as any).methods
        const initialGradingPart = { id: 20, teaching_entry_area_id: 10, name: 'Unterstufe' }
        const post = vi.fn().mockResolvedValue({
            data: { data: { id: 10, name: 'Unterstufe', entry_count: 0 }, grading_part: initialGradingPart },
        })
        const put = vi.fn().mockResolvedValue({ data: { data: { id: 10, name: 'Mittelstufe', entry_count: 0 } } })
        ;(globalThis as any).axios = { post, put }
        const ctx: any = {
            areas: [],
            gradingParts: [],
            activeAreaId: null,
            editingAreaId: null,
            areaForm: { name: '  Unterstufe  ' },
            areaFormErrors: {},
            areaDialogOpen: true,
            isSavingArea: false,
            closeAreaDialog: methods.closeAreaDialog,
            notifyError: vi.fn(),
        }

        await methods.saveArea.call(ctx)
        expect(ctx.areas[0].name).toBe('Unterstufe')
        expect(ctx.gradingParts).toEqual([initialGradingPart])
        expect(post).toHaveBeenCalledWith('/api/admin/teaching/entry_areas', { name: 'Unterstufe' })

        ctx.editingAreaId = 10
        ctx.areaForm = { name: 'Mittelstufe' }
        await methods.saveArea.call(ctx)
        expect(ctx.areas[0].name).toBe('Mittelstufe')
        expect(put).toHaveBeenCalledWith('/api/admin/teaching/entry_areas/10', { name: 'Mittelstufe' })
    })

    it('keeps the selected area visible after adding a grading part', async () => {
        const methods = (Entries as any).methods
        const post = vi.fn().mockResolvedValue({ data: { data: { id: 20, teaching_entry_area_id: 10, name: 'Mündlich' } } })
        ;(globalThis as any).axios = { post }
        const ctx: any = {
            gradingParts: [],
            activeAreaId: 10,
            gradingPartForm: { name: 'Mündlich', weight: 6, is_required: false },
            gradingPartWeightValid: true,
            gradingPartFormErrors: {},
            gradingPartDialogOpen: true,
            isSavingGradingPart: false,
            closeGradingPartDialog: methods.closeGradingPartDialog,
            notifyError: vi.fn(),
        }

        await methods.saveGradingPart.call(ctx)

        expect(ctx.activeAreaId).toBe(10)
        expect(ctx.gradingParts).toContainEqual(expect.objectContaining({ id: 20, name: 'Mündlich' }))
        expect(post).toHaveBeenCalledWith('/api/admin/teaching/entry_grading_parts', {
            teaching_entry_area_id: 10,
            name: 'Mündlich',
            weight: 6,
            is_required: false,
            fixed_percentage: null,
        })
    })

    it('prefills and updates an existing grading part', async () => {
        const methods = (Entries as any).methods
        const updatedGradingPart = { id: 20, teaching_entry_area_id: 10, name: 'Mitarbeit', weight: 2.5, is_required: false }
        const put = vi.fn().mockResolvedValue({ data: { data: updatedGradingPart } })
        ;(globalThis as any).axios = { put }
        const ctx: any = {
            gradingParts: [{ id: 20, teaching_entry_area_id: 10, name: 'Mündlich' }],
            activeAreaId: 10,
            editingGradingPartId: null,
            gradingPartForm: { name: '' },
            gradingPartWeightValid: true,
            gradingPartFormErrors: {},
            gradingPartDialogOpen: false,
            isSavingGradingPart: false,
            closeGradingPartDialog: methods.closeGradingPartDialog,
            notifyError: vi.fn(),
        }

        methods.openEditGradingPartDialog.call(ctx, {
            gradingPartId: 20,
            name: 'Mündlich',
            weight: 6,
            is_required: true,
        })

        expect(ctx.editingGradingPartId).toBe(20)
        expect(ctx.gradingPartForm.name).toBe('Mündlich')
        expect(ctx.gradingPartForm.weight).toBe(6)
        expect(ctx.gradingPartForm.is_required).toBe(true)
        expect(ctx.gradingPartDialogOpen).toBe(true)

        ctx.gradingPartForm.name = 'Mitarbeit'
        ctx.gradingPartForm.weight = '2.5'
        ctx.gradingPartForm.is_required = false
        await methods.saveGradingPart.call(ctx)

        expect(put).toHaveBeenCalledWith('/api/admin/teaching/entry_grading_parts/20', {
            name: 'Mitarbeit',
            weight: 2.5,
            is_required: false,
            fixed_percentage: null,
        })
        expect(ctx.gradingParts).toEqual([updatedGradingPart])
        expect(ctx.editingGradingPartId).toBeNull()
    })

    it.each([['6', true], ['2.5', true], ['0.001', true], ['', false], [null, false], ['0', false], ['-1', false], ['1.0001', false], ['Infinity', false], ['10000000', false]])('validates grading weight %s', (weight, valid) => {
        expect((Entries as any).computed.gradingPartWeightValid.call({ gradingPartForm: { weight } })).toBe(valid)
    })

    it('starts new and legacy grading parts with equal weight', () => {
        const methods = (Entries as any).methods
        const ctx: any = { activeAreaId: 10 }
        methods.openCreateGradingPartDialog.call(ctx)
        expect(ctx.gradingPartForm.weight).toBe(1)
        expect(ctx.gradingPartForm.is_required).toBe(false)
        methods.openEditGradingPartDialog.call(ctx, { gradingPartId: 20, name: 'Mitarbeit' })
        expect(ctx.gradingPartForm.weight).toBe(1)
        expect(ctx.gradingPartForm.is_required).toBe(false)
    })

    it('does not submit an invalid grading weight', async () => {
        const post = vi.fn()
        ;(globalThis as any).axios = { post }
        await (Entries as any).methods.saveGradingPart.call({ activeAreaId: 10, gradingPartForm: { name: 'Mitarbeit', weight: '' }, gradingPartWeightValid: false })
        expect(post).not.toHaveBeenCalled()
    })

    it('loads, saves and clears a fixed percentage without replacing the saved relative weight', async () => {
        const methods = (Entries as any).methods
        const put = vi.fn().mockResolvedValue({ data: { data: { id: 20, name: 'Prüfung', weight: 3, fixed_percentage: 30 } } })
        ;(globalThis as any).axios = { put }
        const ctx: any = { activeAreaId: 10, gradingParts: [], gradingPartWeightValid: true, closeGradingPartDialog: vi.fn(), notifyError: vi.fn() }
        methods.openEditGradingPartDialog.call(ctx, { gradingPartId: 20, name: 'Prüfung', weight: 3, fixed_percentage: 30 })
        expect(ctx.gradingPartForm.weighting_mode).toBe('fixed')
        expect(ctx.gradingPartForm.fixed_percentage).toBe(30)
        await methods.saveGradingPart.call(ctx)
        expect(put).toHaveBeenLastCalledWith('/api/admin/teaching/entry_grading_parts/20', { name: 'Prüfung', fixed_percentage: 30, is_required: false })
        ctx.gradingPartForm.weighting_mode = 'relative'
        await methods.saveGradingPart.call(ctx)
        expect(put).toHaveBeenLastCalledWith('/api/admin/teaching/entry_grading_parts/20', { name: 'Prüfung', weight: 3, fixed_percentage: null, is_required: false })
    })

    it.each([['30', false], ['100', false], ['0.001', false], ['', true], ['0', true], ['101', true], ['1.0001', true]])('validates fixed percentage %s', (fixedPercentage, invalid) => {
        const ctx = { gradingPartForm: { weighting_mode: 'fixed', fixed_percentage: fixedPercentage }, gradingParts: [], activeAreaId: 10 }
        const error = (Entries as any).computed.gradingPartPercentageError.call(ctx)
        expect(Boolean(error)).toBe(invalid)
        expect((Entries as any).computed.gradingPartWeightValid.call({ ...ctx, gradingPartPercentageError: error })).toBe(!invalid)
    })

    it('limits fixed percentages to 100 in the active area and excludes the edited part', () => {
        const ctx: any = { activeAreaId: 10, editingGradingPartId: 20, gradingPartForm: { weighting_mode: 'fixed', fixed_percentage: 30 }, gradingParts: [
            { id: 20, teaching_entry_area_id: 10, fixed_percentage: 50 },
            { id: 21, teaching_entry_area_id: 10, fixed_percentage: 70 },
            { id: 22, teaching_entry_area_id: 11, fixed_percentage: 100 },
        ] }
        expect((Entries as any).computed.gradingPartPercentageError.call(ctx)).toBe('')
        ctx.gradingPartForm.fixed_percentage = 30.001
        expect((Entries as any).computed.gradingPartPercentageError.call(ctx)).toContain('100 %')
    })

    it('distinguishes fixed percentages from weights in the overview', () => {
        const label = (Entries as any).methods.gradingPartWeightLabel
        expect(label({ weight: 3, fixed_percentage: 30 })).toBe('30 % fest')
        expect(label({ weight: 6, fixed_percentage: null })).toBe('Gewicht 6')
    })

    it('deletes a grading part without removing entries', async () => {
        const methods = (Entries as any).methods
        const deleteRequest = vi.fn().mockResolvedValue({})
        ;(globalThis as any).axios = { delete: deleteRequest }
        const entries = [entryFixture()]
        const ctx: any = {
            gradingParts: [{ id: 20, teaching_entry_area_id: 10, name: 'Mündlich' }],
            entries,
            deleteGradingPartId: 20,
            gradingPartDeleteDialogOpen: true,
            isDeletingGradingPart: false,
            closeDeleteGradingPartDialog: methods.closeDeleteGradingPartDialog,
            notifyError: vi.fn(),
        }

        await methods.confirmGradingPartDelete.call(ctx)

        expect(deleteRequest).toHaveBeenCalledWith('/api/admin/teaching/entry_grading_parts/20')
        expect(ctx.gradingParts).toEqual([])
        expect(ctx.entries).toEqual(entries)
    })

    it('loads all grading entries and saves selected and deselected assignments', async () => {
        const methods = (Entries as any).methods
        const entries = [
            entryFixture({ id: 1, name: 'Mitarbeit' }),
            entryFixture({ id: 2, name: 'Prüfung', teaching_entry_grading_part_id: 20 }),
            entryFixture({ id: 3, name: 'Auftrag', teaching_entry_grading_part_id: 30 }),
        ]
        const post = vi.fn().mockImplementation((_url, payload) => Promise.resolve({
            data: {
                data: {
                    ...entries.find((entry) => entry.id === payload.teaching_entry_definition_id),
                    teaching_entry_grading_part_id: 20,
                },
            },
        }))
        const deleteRequest = vi.fn().mockResolvedValue({})
        ;(globalThis as any).axios = { delete: deleteRequest, post }
        const ctx: any = {
            entries,
            calculationEntries: entries,
            gradingParts: [
                { id: 20, name: 'Mündlich' },
                { id: 30, name: 'Schriftlich' },
            ],
            assignGradingPartId: null,
            selectedGradingEntryIds: [],
            isAssigningGradingEntry: false,
            cancelGradingEntryAssignment: methods.cancelGradingEntryAssignment,
            replaceGradingEntry: methods.replaceGradingEntry,
            notifyError: vi.fn(),
        }

        methods.toggleGradingEntryAssignment.call(ctx, { gradingPartId: 20 })
        expect(ctx.selectedGradingEntryIds).toEqual([2])

        methods.toggleGradingEntrySelection.call(ctx, entries[0])
        methods.toggleGradingEntrySelection.call(ctx, entries[1])
        methods.toggleGradingEntrySelection.call(ctx, entries[2])
        expect(ctx.selectedGradingEntryIds).toEqual([1, 3])

        await methods.saveGradingEntryAssignments.call(ctx)

        expect(post).toHaveBeenNthCalledWith(1, '/api/admin/teaching/entry_grading_parts/20/entries', {
            teaching_entry_definition_id: 1,
        })
        expect(post).toHaveBeenNthCalledWith(2, '/api/admin/teaching/entry_grading_parts/20/entries', {
            teaching_entry_definition_id: 3,
        })
        expect(deleteRequest).toHaveBeenNthCalledWith(1, '/api/admin/teaching/entry_grading_parts/20/entries/2')
        expect(deleteRequest).toHaveBeenNthCalledWith(2, '/api/admin/teaching/entry_grading_parts/30/entries/3')
        expect(ctx.entries).toEqual([
            expect.objectContaining({ id: 1, teaching_entry_grading_part_id: 20 }),
            expect.objectContaining({ id: 2, teaching_entry_grading_part_id: null }),
            expect.objectContaining({ id: 3, teaching_entry_grading_part_id: 20 }),
        ])
        expect(ctx.assignGradingPartId).toBeNull()
        expect(ctx.selectedGradingEntryIds).toEqual([])
    })

    it('allows saving an empty selection to remove every assignment', () => {
        const hasChanges = (Entries as any).computed.hasGradingEntryAssignmentChanges
        const ctx: any = {
            assignGradingPartId: 20,
            isAssigningGradingEntry: false,
            calculationEntries: [entryFixture({ teaching_entry_grading_part_id: 20 })],
            selectedGradingEntryIds: [],
        }

        expect(hasChanges.call(ctx)).toBe(true)
    })

    it('removes only empty areas after confirmation', async () => {
        const methods = (Entries as any).methods
        const deleteRequest = vi.fn().mockResolvedValue({})
        ;(globalThis as any).axios = { delete: deleteRequest }
        const ctx: any = {
            areas: [
                { id: 10, name: 'Leer' },
                { id: 20, name: 'Unterstufe' },
            ],
            entries: [entryFixture({ teaching_entry_area_id: 20 })],
            activeAreaId: 10,
            deleteAreaId: null,
            areaDeleteDialogOpen: false,
            isDeletingArea: false,
            entryCountForArea: methods.entryCountForArea,
            closeAreaDeleteDialog: methods.closeAreaDeleteDialog,
            notifyError: vi.fn(),
        }

        methods.openDeleteAreaDialog.call(ctx, ctx.areas[0])
        await methods.confirmAreaDelete.call(ctx)

        expect(ctx.areas.map((area: any) => area.id)).toEqual([20])
        expect(deleteRequest).toHaveBeenCalledWith('/api/admin/teaching/entry_areas/10')
    })

    it('copies every returned entry from a source area into the active area', async () => {
        const methods = (Entries as any).methods
        const copiedEntries = [entryFixture({ id: 20, teaching_entry_area_id: 10 }), entryFixture({ id: 21, teaching_entry_area_id: 10, short_name: 'A' })]
        const post = vi.fn().mockResolvedValue({ data: { data: copiedEntries, copied_count: 2 } })
        ;(globalThis as any).axios = { post }
        const ctx: any = {
            entries: [entryFixture({ teaching_entry_area_id: 20 })],
            activeAreaId: 10,
            selectedSourceAreaId: 20,
            entryCopyDialogOpen: true,
            entryCopyErrors: {},
            isCopyingEntries: false,
            closeEntryCopyDialog: methods.closeEntryCopyDialog,
            notifyError: vi.fn(),
            notifySuccess: vi.fn(),
        }

        await methods.copyEntriesFromArea.call(ctx)

        expect(post).toHaveBeenCalledWith('/api/admin/teaching/entry_areas/10/entry-copies', { source_area_id: 20 })
        expect(ctx.entries.slice(-2)).toEqual(copiedEntries)
        expect(ctx.entryCopyDialogOpen).toBe(false)
        expect(ctx.notifySuccess).toHaveBeenCalledWith('2 Einträge wurden übernommen.')
    })

    it('loads the previous schoolyear offer without opening the dialog', async () => {
        const methods = (Entries as any).methods
        const previousYearImport = {
            schoolyear: { id: 5, label: '2025/26' },
            area_count: 2,
            entry_count: 7,
        }
        const get = vi.fn().mockImplementation((url: string) => {
            if (url === '/api/admin/teaching/entry_areas') {
                return Promise.resolve({ data: { data: [], meta: { previous_year_import: previousYearImport } } })
            }

            return Promise.resolve({ data: { data: [] } })
        })
        ;(globalThis as any).axios = { get }
        const ctx: any = {
            areas: [],
            entries: [],
            gradingParts: [],
            activeAreaId: null,
            isLoading: false,
            previousYearImportOffer: null,
            previousYearImportDialogOpen: false,
            notifyError: vi.fn(),
        }

        await methods.loadData.call(ctx)

        expect(ctx.previousYearImportOffer).toEqual(previousYearImport)
        expect(ctx.previousYearImportDialogOpen).toBe(false)

        methods.openPreviousYearImport.call(ctx)
        expect(ctx.previousYearImportDialogOpen).toBe(true)
    })

    it('imports previous schoolyear areas and entries into the local view', async () => {
        const methods = (Entries as any).methods
        const areas = [{ id: 30, name: 'Unterstufe', entry_count: 1 }]
        const entries = [entryFixture({ id: 40, teaching_entry_area_id: 30 })]
        const gradingParts = [{ id: 50, teaching_entry_area_id: 30, name: 'Unterstufe' }]
        const post = vi.fn().mockResolvedValue({
            data: {
                data: { areas, entries, grading_parts: gradingParts },
                imported_area_count: 1,
                imported_entry_count: 1,
            },
        })
        ;(globalThis as any).axios = { post }
        const ctx: any = {
            areas: [],
            entries: [],
            gradingParts: [],
            activeAreaId: null,
            previousYearImportOffer: { schoolyear: { id: 5, label: '2025/26' } },
            previousYearImportDialogOpen: true,
            isImportingPreviousYear: false,
            notifyError: vi.fn(),
            notifySuccess: vi.fn(),
        }

        await methods.importPreviousYearAreas.call(ctx)

        expect(post).toHaveBeenCalledWith('/api/admin/teaching/entry-area-imports')
        expect(ctx.areas).toEqual(areas)
        expect(ctx.entries).toEqual(entries)
        expect(ctx.gradingParts).toEqual(gradingParts)
        expect(ctx.activeAreaId).toBe(30)
        expect(ctx.previousYearImportDialogOpen).toBe(false)
        expect(ctx.notifySuccess).toHaveBeenCalledWith('1 Bereich und 1 Eintrag wurden übernommen.')
    })

    it('renders all area cards in a wrapping grid and persistent CRUD dialogs without dropdowns', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/settings/components/Entries.vue'), 'utf8')
        const areasTitleIndex = source.indexOf(':title="assignGradingPartId ? \'Zuordnung\' : \'Bereiche\'"')
        const selectedAreaTitleIndex = source.indexOf('<div class="text-h6 font-weight-bold">{{ activeAreaName }}</div>')
        const entryListIndex = source.indexOf('<v-list class="bg-transparent pa-0 mt-2">')
        const addEntryButtonIndex = source.indexOf('@click="openCreateDialog"')
        const designationCardIndex = source.indexOf('<strong>Bezeichnung</strong>')
        const descriptionFieldIndex = source.indexOf('v-model="entryForm.description"')
        const tableMarkingCardIndex = source.indexOf('<strong>Markierung in Tabelle</strong>')
        const propertiesCardIndex = source.indexOf('<strong>Eigenschaften</strong>')
        const calculationPageIndex = source.indexOf('<template v-if="activeCategory === \'Berechnung\'">')
        const semesterGradeTitleIndex = source.indexOf('<div class="text-h6 font-weight-bold">Semesternote</div>')
        const calculationAreaListIndex = source.indexOf('class="calculation-area-list mt-3"')
        const addGradingPartButtonIndex = source.indexOf('Benotungsteil hinzufügen')
        const assignGradingPartButtonIndex = source.indexOf('@click="toggleGradingEntryAssignment(area)"')
        const editGradingPartButtonIndex = source.indexOf('@click="openEditGradingPartDialog(area)"')
        const deleteGradingPartButtonIndex = source.indexOf('@click="openDeleteGradingPartDialog(area)"')

        expect(areasTitleIndex).toBeGreaterThanOrEqual(0)
        expect(selectedAreaTitleIndex).toBeGreaterThan(areasTitleIndex)
        expect(addEntryButtonIndex).toBeGreaterThan(entryListIndex)
        expect(descriptionFieldIndex).toBeGreaterThan(designationCardIndex)
        expect(tableMarkingCardIndex).toBeGreaterThan(descriptionFieldIndex)
        expect(propertiesCardIndex).toBeGreaterThan(tableMarkingCardIndex)
        expect(semesterGradeTitleIndex).toBeGreaterThan(calculationPageIndex)
        expect(addGradingPartButtonIndex).toBeGreaterThan(calculationAreaListIndex)
        expect(editGradingPartButtonIndex).toBeGreaterThan(assignGradingPartButtonIndex)
        expect(deleteGradingPartButtonIndex).toBeGreaterThan(editGradingPartButtonIndex)
        expect(source).toContain('label="Beschreibung"')
        expect(source).toContain(':error-messages="formErrors.description"')
        expect(source).not.toContain('Aktiver Bereich: {{ activeAreaName }}')
        expect(source).toContain('class="entry-list-actions mt-4"')
        expect(source).toContain('@click="openEntryCopyDialog"')
        expect(source).toContain('v-if="entryCountForArea(activeAreaId) === 0"')
        expect(source).toContain('v-model="entryCopyDialogOpen"')
        expect(source).toContain('Einträge übernehmen')
        expect(source).toContain('Quellbereich auswählen')
        expect(source).toContain('@click="copyEntriesFromArea"')
        expect(source).toContain('class="entry-area-grid mt-4"')
        expect(source).toContain("categoryOptions: ['Benotung', 'Berechnung', 'Verhalten', 'Weitere']")
        expect(source).toContain('v-for="area in calculationAreas"')
        expect(source).toContain('v-for="entry in area.entries"')
        expect(source).toContain('class="calculation-area-list mt-3"')
        expect(source).toContain('class="calculation-entry-selection-grid"')
        expect(source).toContain('<section v-if="assignGradingPartId" class="calculation-area-card mt-3">')
        expect(source).toContain('v-for="entry in calculationEntries"')
        expect(source).toContain('class="calculation-entry-selection-card"')
        expect(source).toContain("'calculation-entry-selection-card--selected': selectedGradingEntryIds.includes(entry.id)")
        expect(source).toContain(':aria-pressed="selectedGradingEntryIds.includes(entry.id)"')
        expect(source).toContain('grid-template-columns: repeat(auto-fit, minmax(180px, 1fr))')
        expect(source).toContain('@click="toggleGradingEntryAssignment(area)"')
        expect(source).toContain('title="Benotungsteil bearbeiten"')
        expect(source).toContain('@click="openEditGradingPartDialog(area)"')
        expect(source).toContain('@click="toggleGradingEntrySelection(entry)"')
        expect(source).toContain('@click="saveGradingEntryAssignments"')
        expect(source).toContain('Zuordnung speichern')
        expect(source).toContain("{{ assignGradingPartId === area.gradingPartId ? 'Auswahl abbrechen' : 'Zuordnung' }}")
        expect(source).not.toContain('@click="assignGradingEntry(entry)"')
        expect(source).not.toContain('calculation-entry-list--source')
        expect(source).not.toContain('v-model="assignGradingEntryDialogOpen"')
        expect(source).not.toContain('v-model="selectedGradingEntryId"')
        expect(source).not.toContain('mdi-link-off')
        expect(source).not.toContain('@click="unassignGradingEntry(area, entry)"')
        expect(source).toContain('title="Benotungsteil löschen"')
        expect(source).toContain('class="calculation-area-card calculation-part-card"')
        expect(source).toContain('@click="openDeleteGradingPartDialog(area)"')
        expect(source).toContain('@click="confirmGradingPartDelete"')
        expect(source).toContain('<template v-if="activeCategory !== \'Berechnung\'">')
        expect(source).toContain('<div v-if="activeCategory !== \'Berechnung\'" class="entry-section-actions">')
        expect(source).toContain('grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))')
        expect(source).toContain('class="entry-area-card"')
        expect(source).toContain('@click="activeAreaId = area.id"')
        expect(source).not.toContain('class="entry-area-count"')
        expect(source).not.toContain('<v-slide-group')
        expect(source).toContain('@click="openCreateAreaDialog"')
        expect(source).toContain('class="entry-area-actions"')
        expect(source).toContain('@click="openEditAreaDialog(area)"')
        expect(source).toContain('class="entry-area-action-button entry-area-action-button--edit"')
        expect(source).toContain('class="entry-area-action-button entry-area-action-button--delete"')
        expect(source).toContain('<v-icon icon="mdi-pencil-outline" size="18" />')
        expect(source).toContain('<v-icon icon="mdi-delete-outline" size="18" />')
        expect(source).toContain('<span>Bearbeiten</span>')
        expect(source).toContain('<span>Löschen</span>')
        expect(source).toContain('grid-template-columns: repeat(2, minmax(0, 1fr))')
        expect(source).toContain('margin-top: auto')
        expect(source).toContain('text-transform: none')
        expect(source).toContain('@click="confirmAreaDelete"')
        expect(source).toContain("axios.get('/api/admin/teaching/entry_areas')")
        expect(source).toContain('v-model="previousYearImportDialogOpen"')
        expect(source).toContain('@click="openPreviousYearImport"')
        expect(source).toContain('Aus Vorjahr übernehmen')
        expect(source).toContain('Aus dem Vorjahr übernehmen?')
        expect(source).toContain("axios.post('/api/admin/teaching/entry-area-imports')")
        expect(source).not.toContain('v-model="entryForm.teaching_entry_area_id"')
        expect(source).not.toContain('v-model="entryForm.category"')
        expect(source).not.toContain('categorySelectionOptions')
        expect(source).not.toContain('<strong>Bereich / Oberbegriff</strong>')
        expect(source).not.toContain('<strong>Kategorie</strong>')
        expect(source).toContain('<section v-if="entryForm.category === \'Benotung\'" class="form-section">\n                        <div class="properties-heading">')
        expect(source).toContain('v-model="entryForm.has_table_marking"')
        expect(source).toContain('v-model="entryForm.table_marking_color"')
        expect(source).toContain('<span>Nein</span>')
        expect(source).toContain('<span>Ja</span>')
        expect(source).toContain('<section v-else class="form-section">')
        expect(source).toContain('<strong>Verständigungen</strong>')
        expect(source).toContain('v-model="entryForm.has_notifications"')
        expect(source).toContain('label="Klassenvorstand"')
        expect(source).toContain('label="Eltern"')
        expect(source).toContain('label="Schüler:in"')
        expect(source).toContain('<span class="entry-short">{{ entry.short_name }}</span>')
        expect(source).not.toContain('<v-chip class="entry-short-chip"')
        expect(source).toContain('<div v-if="entry.category === \'Benotung\' && entry.has_properties" class="entry-properties">')
        expect(source).not.toContain('<span>Eigenschaften:</span>')
        expect(source).toContain('v-for="property in entry.fixed_properties"')
        expect(source).toContain('class="entry-property-chip"')
        expect(source).toContain('size="x-small"')
        expect(source).toContain('class="entry-property-chip entry-property-chip--free"')
        expect(source).toContain('.entry-property-chip--free {')
        expect(source).toContain('font-size: 0.65rem')
        expect(source).not.toContain('mdi-tag-outline')
        expect(source).toContain('class="entry-properties-combobox mt-4"')
        expect(source).toContain('.entry-properties-combobox :deep(.v-chip)')
        expect(source).toContain('height: 42px !important')
        expect(source).not.toContain('teaching_schema_id')
        expect(source.slice(0, source.indexOf('<v-dialog v-model="calculationEntryDialogOpen"'))).not.toContain('<v-select')
        expect(source).not.toContain('Feste Eigenschaften')
        expect(source).not.toContain('areaExamples')
        expect(source).not.toContain('area-example-row')
    })
})
