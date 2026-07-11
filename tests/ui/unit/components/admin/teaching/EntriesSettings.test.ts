import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import Entries from '@/pages/admin/teaching/settings/components/Entries.vue'

describe('Teaching entries settings preview', () => {
    it('normalizes short names to two uppercase letters', () => {
        const data = (Entries as any).data()
        const ctx = {
            entries: [
                {
                    short_name: 'abc',
                },
            ],
        }

        ;(Entries as any).methods.normalizeShortName.call(ctx, 0)

        expect(ctx.entries[0].short_name).toBe('AB')
        ;(Entries as any).methods.normalizeShortName.call(ctx, ctx.entries[0])

        expect(ctx.entries[0].short_name).toBe('AB')
        expect(data.activeCategory).toBe('Benotung')
        expect(data.categoryOptions).toEqual(['Benotung', 'Verhalten', 'Weitere'])
    })

    it('filters entries by the active top-level category tab', () => {
        const data = (Entries as any).data()
        const ctx = {
            activeCategory: 'Verhalten',
            entries: data.entries,
        }

        const entries = (Entries as any).computed.filteredEntries.call(ctx)

        expect(entries.map((entry: { short_name: string }) => entry.short_name)).toEqual(['V'])
    })

    it('opens and closes a persistent edit dialog for the selected entry', () => {
        const methods = (Entries as any).methods
        const entry = {
            short_name: 'M',
            name: 'Mitarbeit',
        }
        const ctx = {
            editDialogOpen: false,
            selectedEntry: null,
        }

        methods.openEditDialog.call(ctx, entry)

        expect(ctx.editDialogOpen).toBe(true)
        expect(ctx.selectedEntry).toBe(entry)

        methods.closeEditDialog.call(ctx)

        expect(ctx.editDialogOpen).toBe(false)
        expect(ctx.selectedEntry).toBeNull()
    })

    it('renders the version 2 preview fields without persistence', () => {
        const source = readFileSync(
            resolve('resources/js/pages/admin/teaching/settings/components/Entries.vue'),
            'utf8',
        )

        expect(source).toContain('title="Einträge"')
        expect(source).toContain('Version 2')
        expect(source).toContain('Noch ohne Speichern')
        expect(source).toContain("activeCategory: 'Benotung'")
        expect(source).toContain('v-model="activeCategory"')
        expect(source).toContain("categoryOptions: ['Benotung', 'Verhalten', 'Weitere']")
        expect(source).toContain('<v-tabs')
        expect(source).toContain('class="entry-settings-category-tabs"')
        expect(source).toContain('<v-tab')
        expect(source).toContain('v-for="category in categoryOptions"')
        expect(source).toContain(':value="category"')
        expect(source).toContain('v-for="entry in filteredEntries"')
        expect(source).toContain('filteredEntries()')
        expect(source).toContain('class="entry-settings-list bg-transparent"')
        expect(source).toContain('class="entry-settings-list-row px-0"')
        expect(source).toContain('.entry-settings-list-row:not(:last-child)')
        expect(source).not.toContain('<v-divider')
        expect(source).toContain('class="entry-settings-short-name"')
        expect(source).toContain('class="entry-settings-name"')
        expect(source).toContain('icon="mdi-pencil"')
        expect(source).toContain(':title="`${entry.short_name} bearbeiten`"')
        expect(source).toContain('@click="openEditDialog(entry)"')
        expect(source).toContain('<v-dialog v-model="editDialogOpen" persistent max-width="520">')
        expect(source).toContain('Eintrag bearbeiten')
        expect(source).toContain('v-if="selectedEntry"')
        expect(source).toContain('@click="closeEditDialog"')
        expect(source).toContain('selectedEntry: null')
        expect(source).toContain("category: 'Weitere'")
        expect(source).toContain("short_name: 'M'")
        expect(source).toContain("name: 'Mitarbeit'")
        expect(source).not.toContain('v-text-field')
        expect(source).not.toContain('v-combobox')
        expect(source).not.toContain('label="Eigenschaften"')
        expect(source).not.toContain('saveSettings')
    })
})
