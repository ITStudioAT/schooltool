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
        expect(source).not.toContain('v-model="entryForm.teaching_entry_area_id"')
        expect(source).not.toContain('v-model="entryForm.category"')
        expect(source).not.toContain('categorySelectionOptions')
        expect(source).not.toContain('<strong>Bereich / Oberbegriff</strong>')
        expect(source).not.toContain('<strong>Kategorie</strong>')
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
