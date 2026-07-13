import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import Entries from '@/pages/admin/teaching/settings/components/Entries.vue'

function entryFixture(overrides = {}) {
    return {
        id: 1,
        teaching_entry_area_id: 10,
        short_name: 'M',
        name: 'Mitarbeit',
        category: 'Benotung',
        has_properties: true,
        properties_mode: 'fixed',
        fixed_properties: ['+', '-'],
        has_notifications: false,
        notification_recipients: [],
        ...overrides,
    }
}

describe('Teaching entries settings', () => {
    it('filters entries by area and category', () => {
        const ctx = {
            activeAreaId: 20,
            activeCategory: 'Verhalten',
            entries: [entryFixture(), entryFixture({ id: 2, teaching_entry_area_id: 20, category: 'Verhalten' })],
        }

        expect((Entries as any).computed.filteredEntries.call(ctx).map((entry: any) => entry.id)).toEqual([2])
    })

    it('opens a blank entry in the selected area', () => {
        const methods = (Entries as any).methods
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
        expect(ctx.editDialogOpen).toBe(true)
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

    it('clears stale notification recipients for grading entries', async () => {
        const methods = (Entries as any).methods
        const post = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { id: 2, ...payload } } }))
        ;(globalThis as any).axios = { post }
        const ctx: any = {
            areas: [{ id: 10, name: 'Unterstufe' }],
            entries: [],
            entryForm: entryFixture({ has_notifications: true, notification_recipients: ['student'] }),
            selectedEntryId: null,
            activeAreaId: 10,
            activeCategory: 'Benotung',
            canSaveEntry: true,
            formErrors: {},
            isSaving: false,
            normalizeShortName: methods.normalizeShortName,
            closeEditDialog: methods.closeEditDialog,
            notifyError: vi.fn(),
        }

        await methods.saveEntry.call(ctx)

        expect(post).toHaveBeenCalledWith(
            '/api/admin/teaching/entry_definitions',
            expect.objectContaining({ has_notifications: false, notification_recipients: [] }),
        )
    })

    it('creates and renames areas through their API', async () => {
        const methods = (Entries as any).methods
        const post = vi.fn().mockResolvedValue({ data: { data: { id: 10, name: 'Unterstufe', entry_count: 0 } } })
        const put = vi.fn().mockResolvedValue({ data: { data: { id: 10, name: 'Mittelstufe', entry_count: 0 } } })
        ;(globalThis as any).axios = { post, put }
        const ctx: any = {
            areas: [],
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
        expect(post).toHaveBeenCalledWith('/api/admin/teaching/entry_areas', { name: 'Unterstufe' })

        ctx.editingAreaId = 10
        ctx.areaForm = { name: 'Mittelstufe' }
        await methods.saveArea.call(ctx)
        expect(ctx.areas[0].name).toBe('Mittelstufe')
        expect(put).toHaveBeenCalledWith('/api/admin/teaching/entry_areas/10', { name: 'Mittelstufe' })
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
        const post = vi.fn().mockResolvedValue({
            data: {
                data: { areas, entries },
                imported_area_count: 1,
                imported_entry_count: 1,
            },
        })
        ;(globalThis as any).axios = { post }
        const ctx: any = {
            areas: [],
            entries: [],
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
        expect(ctx.activeAreaId).toBe(30)
        expect(ctx.previousYearImportDialogOpen).toBe(false)
        expect(ctx.notifySuccess).toHaveBeenCalledWith('1 Bereich und 1 Eintrag wurden übernommen.')
    })

    it('renders all area cards in a wrapping grid and persistent CRUD dialogs without dropdowns', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/settings/components/Entries.vue'), 'utf8')
        const areasTitleIndex = source.indexOf('title="Bereiche"')
        const entriesTitleIndex = source.indexOf('<div class="text-h6 font-weight-bold">Einträge</div>')

        expect(areasTitleIndex).toBeGreaterThanOrEqual(0)
        expect(entriesTitleIndex).toBeGreaterThan(areasTitleIndex)
        expect(source).toContain('Aktiver Bereich: {{ activeAreaName }}')
        expect(source).toContain('@click="openEntryCopyDialog"')
        expect(source).toContain('v-model="entryCopyDialogOpen"')
        expect(source).toContain('Einträge übernehmen')
        expect(source).toContain('Quellbereich auswählen')
        expect(source).toContain('@click="copyEntriesFromArea"')
        expect(source).toContain('class="entry-area-grid mt-4"')
        expect(source).toContain('grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))')
        expect(source).toContain('class="entry-area-card"')
        expect(source).toContain('@click="activeAreaId = area.id"')
        expect(source).not.toContain('<v-slide-group')
        expect(source).toContain('@click="openCreateAreaDialog"')
        expect(source).toContain('class="entry-area-actions"')
        expect(source).toContain('@click="openEditAreaDialog(area)"')
        expect(source).toContain('>Bearbeiten</v-btn>')
        expect(source).toContain('Löschen')
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
        expect(source).toContain('<section v-else class="form-section">')
        expect(source).toContain('<strong>Verständigungen</strong>')
        expect(source).toContain('v-model="entryForm.has_notifications"')
        expect(source).toContain('label="Klassenvorstand"')
        expect(source).toContain('label="Eltern"')
        expect(source).toContain('label="Schüler:in"')
        expect(source).toContain('<div v-if="entry.category === \'Benotung\'" class="entry-properties">')
        expect(source).toContain('class="entry-property-chip"')
        expect(source).toContain("entry.has_properties && entry.properties_mode === 'free'")
        expect(source).toContain('class="entry-property-chip entry-free-input-chip"')
        expect(source).toContain('Freie Eingabe')
        expect(source).not.toContain('mdi-tag-outline')
        expect(source).toContain('class="entry-properties-combobox mt-4"')
        expect(source).toContain('.entry-properties-combobox :deep(.v-chip)')
        expect(source).toContain('height: 42px !important')
        expect(source).not.toContain('teaching_schema_id')
        expect(source).not.toContain('<v-select')
        expect(source).not.toContain('Feste Eigenschaften')
        expect(source).not.toContain('areaExamples')
        expect(source).not.toContain('area-example-row')
    })
})
