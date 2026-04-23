import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import Behaviour from '@/pages/admin/teaching/settings/components/Behaviour.vue'

describe('Behaviour settings edit flow', () => {
    it('shows the active schoolyear label in the behaviour settings panel', () => {
        const ctx = {
            config: {
                selected_schoolyear: {
                    name: 'Schuljahr 2025/26',
                    concerns: '2025/26',
                },
            },
        }

        expect((Behaviour as any).computed.activeSchoolyearLabel.call(ctx)).toBe('Schuljahr 2025/26')
    })

    it('builds the previous schoolyear import label for behaviour import', () => {
        const methods = (Behaviour as any).methods
        const ctx = {
            config: {
                selected_schoolyear: {
                    concerns: '2026/27',
                },
            },
            schoolyears: [
                { id: 1, concerns: '2025/26' },
                { id: 2, concerns: '2026/27' },
            ],
            normalizeSchoolyearConcern: methods.normalizeSchoolyearConcern,
            parseSchoolyearConcern: methods.parseSchoolyearConcern,
        }

        ctx.activeSchoolyearConcern = (Behaviour as any).computed.activeSchoolyearConcern.call(ctx)
        ctx.previousSchoolyearConcern = (Behaviour as any).computed.previousSchoolyearConcern.call(ctx)
        ctx.previousSchoolyear = (Behaviour as any).computed.previousSchoolyear.call(ctx)

        expect((Behaviour as any).computed.behaviourImportLabel.call(ctx)).toBe('Import vom Schuljahr: 2025/26')
    })

    it('returns to edit list level on abort', () => {
        const methods = (Behaviour as any).methods
        const ctx = {
            action: 'teaching_behaviour_new_or_edit',
            edit_index: 2,
        }

        methods.abort.call(ctx)

        expect(ctx.action).toBe('')
        expect(ctx.edit_index).toBeNull()
    })

    it('returns to edit list level on save', async () => {
        const methods = (Behaviour as any).methods
        let payload: Record<string, unknown> | null = null

        const ctx: Record<string, unknown> = {
            behaviour_entries: [{ short_name: 'ALT', name: 'Alt' }],
            data: { short_name: 'NEU', name: 'Neu' },
            edit_index: 0,
            action: 'teaching_behaviour_new_or_edit',
            behaviour_save_action: null,
            $nextTick: async () => {},
            teachingStore: {
                saveSettings: async (input: Record<string, unknown>) => {
                    payload = input
                    return true
                },
            },
            runBehaviourSettingsMutation(action: string, callback: () => Promise<unknown>) {
                return methods.runBehaviourSettingsMutation.call(this, action, callback)
            },
        }

        await methods.save.call(ctx)

        expect(payload).toEqual({
            teaching_behaviour: [{ short_name: 'NEU', name: 'Neu' }],
        })
        expect(ctx.action).toBe('')
        expect(ctx.edit_index).toBeNull()
    })

    it('opens a persistent delete dialog and deletes only after confirmation', async () => {
        const methods = (Behaviour as any).methods
        let payload: Record<string, unknown> | null = null

        const ctx: Record<string, unknown> = {
            behaviour_entries: [
                { short_name: 'A', name: 'Alpha' },
                { short_name: 'B', name: 'Beta' },
            ],
            delete_index: null,
            behaviour_delete_dialog_open: false,
            behaviour_save_action: null,
            $nextTick: async () => {},
            teachingStore: {
                saveSettings: async (input: Record<string, unknown>) => {
                    payload = input
                    return true
                },
            },
            runBehaviourSettingsMutation(action: string, callback: () => Promise<unknown>) {
                return methods.runBehaviourSettingsMutation.call(this, action, callback)
            },
            closeDeleteDialog: () => {
                ctx.behaviour_delete_dialog_open = false
                ctx.delete_index = null
            },
        }

        methods.openDeleteDialog.call(ctx, 0)

        expect(ctx.delete_index).toBe(0)
        expect(ctx.behaviour_delete_dialog_open).toBe(true)

        await methods.confirmDelete.call(ctx)

        expect(payload).toEqual({
            teaching_behaviour: [{ short_name: 'B', name: 'Beta' }],
        })
        expect(ctx.behaviour_delete_dialog_open).toBe(false)
        expect(ctx.delete_index).toBeNull()
    })

    it('computes the affected student-entry count for the selected delete item', () => {
        const ctx = {
            delete_index: 1,
            behaviour_entries: [
                { short_name: 'A', name: 'Alpha' },
                { short_name: 'B', name: 'Beta' },
            ],
            behaviourUsageCounts: {
                A: 2,
                B: 7,
            },
        }

        ctx.deleteEntryDefinition = (Behaviour as any).computed.deleteEntryDefinition.call(ctx)

        expect(ctx.deleteEntryDefinition).toEqual({ short_name: 'B', name: 'Beta' })
        expect((Behaviour as any).computed.behaviourDeleteUsageCount.call(ctx)).toBe(7)
    })

    it('shows the schoolyear usage count for each behaviour list item', () => {
        const ctx = {
            behaviourUsageCounts: {
                E: 4,
            },
        }

        expect((Behaviour as any).methods.behaviourUsageCountForEntry.call(ctx, { short_name: 'E', name: 'Ermahnung' })).toBe(4)
        expect((Behaviour as any).methods.behaviourUsageCountForEntry.call(ctx, { short_name: 'X', name: 'Unbenutzt' })).toBe(0)
    })

    it('imports behaviour settings and closes the dialog on success', async () => {
        const methods = (Behaviour as any).methods
        const ctx: Record<string, unknown> = {
            behaviour_import_loading: false,
            teachingStore: {
                importBehaviour: async () => true,
            },
            closeBehaviourImportDialog: () => {
                ctx.behaviour_import_dialog_open = false
            },
            behaviour_import_dialog_open: true,
        }

        await methods.importBehaviour.call(ctx)

        expect(ctx.behaviour_import_loading).toBe(false)
        expect(ctx.behaviour_import_dialog_open).toBe(false)
    })

    it('resets behaviour settings and closes the dialog on success', async () => {
        const methods = (Behaviour as any).methods
        const ctx: Record<string, unknown> = {
            behaviour_reset_loading: false,
            teachingStore: {
                resetBehaviour: async () => true,
            },
            closeBehaviourResetDialog: () => {
                ctx.behaviour_reset_dialog_open = false
            },
            behaviour_reset_dialog_open: true,
        }

        await methods.resetBehaviour.call(ctx)

        expect(ctx.behaviour_reset_loading).toBe(false)
        expect(ctx.behaviour_reset_dialog_open).toBe(false)
    })

    it('adds behaviour import and reset buttons plus list actions', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/settings/components/Behaviour.vue',
        )

        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<template #header-actions>')
        expect(source).toContain('prepend-icon="mdi-restore"')
        expect(source).toContain('@click="openBehaviourResetDialog"')
        expect(source).toContain('Reset')
        expect(source).toContain('prepend-icon="mdi-import"')
        expect(source).toContain('@click="openBehaviourImportDialog"')
        expect(source).toContain('<v-dialog v-model="behaviour_import_dialog_open" persistent max-width="560">')
        expect(source).toContain('<v-dialog v-model="behaviour_reset_dialog_open" persistent max-width="560">')
        expect(source).toContain('<v-dialog v-model="behaviour_delete_dialog_open" persistent max-width="520">')
        expect(source).toContain('Verhalten importieren')
        expect(source).toContain('Verhalten zurücksetzen')
        expect(source).toContain('Verhaltenseintrag löschen')
        expect(source).toContain('Möchten Sie diesen Verhaltenseintrag wirklich löschen?')
        expect(source).toContain("behaviourDeleteUsageCount > 0 ? 'warning' : 'info'")
        expect(source).toContain('Dieser Verhaltenseintrag wird bei Schüler:innen bereits verwendet:')
        expect(source).toContain('Dieser Verhaltenseintrag wird bei Schüler:innen derzeit nicht verwendet.')
        expect(source).toContain('{{ behaviourDeleteUsageCount }} Einträge')
        expect(source).toContain('{{ behaviourUsageCountForEntry(entry) }} Einträge')
        expect(source).toContain('Beim Löschen werden auch alle betroffenen Verhaltenseinträge der Schüler:innen entfernt.')
        expect(source).toContain('Diese Verhaltenseinträge werden bereits verwendet:')
        expect(source).toContain("behaviourUsageCount > 0 ? 'warning' : 'info'")
        expect(source).toContain('Diese Verhaltenseinträge werden derzeit nicht verwendet.')
        expect(source).toContain('<strong>Wenn Sie die Verhaltenseinträge zurücksetzen, werden alle bisherigen Verhalten gelöscht!</strong>')
        expect(source).toContain('{{ behaviourUsageCount }} Einträge')
        expect(source).toContain(':loading="behaviour_reset_loading"')
        expect(source).toContain('@click="resetBehaviour"')
        expect(source).toContain('Diese Verhaltenseinträge werden bereits verwendet:')
        expect(source).toContain('{{ behaviourUsageCount }} Einträge')
        expect(source).toContain('<strong>Wenn Sie Verhaltenseinträge importieren, werden alle bisherigen Verhalten gelöscht!</strong>')
        expect(source).toContain('<strong>{{ behaviourImportLabel }}</strong>')
        expect(source).toContain(':loading="behaviour_import_loading"')
        expect(source).toContain('@click="importBehaviour"')
        expect(source).toContain('Importieren')
        expect(source).toContain('Verhaltenseinträge')
        expect(source).toContain('prepend-icon="mdi-plus"')
        expect(source).toContain('Hinzufügen')
        expect(source).toContain('@click="newEntry"')
        expect(source).toContain('@click="openDeleteDialog(index)"')
        expect(source).toContain('@click="editEntry(index)"')
        expect(source).toContain('@click="confirmDelete"')
        expect(source).toContain('openBehaviourImportDialog() {')
        expect(source).toContain('closeBehaviourImportDialog() {')
        expect(source).toContain('openBehaviourResetDialog() {')
        expect(source).toContain('closeBehaviourResetDialog() {')
        expect(source).toContain('openDeleteDialog(index) {')
        expect(source).toContain('closeDeleteDialog() {')
        expect(source).toContain('behaviourUsageCountForEntry(entry) {')
        expect(source).toContain('async confirmDelete() {')
        expect(source).toContain('behaviourUsageCounts() {')
        expect(source).toContain('deleteEntryDefinition() {')
        expect(source).toContain('behaviourDeleteUsageCount() {')
        expect(source).toContain('async resetBehaviour() {')
        expect(source).toContain('async importBehaviour() {')
        expect(source).toContain('behaviour_delete_dialog_open: false')
        expect(source).toContain('behaviour_import_dialog_open: false')
        expect(source).toContain('behaviour_reset_dialog_open: false')
        expect(source).toContain('behaviour_import_loading: false')
        expect(source).toContain('behaviour_reset_loading: false')
        expect(source).not.toContain('ItsMenuButton')
        expect(source).not.toContain('exitEditMode() {')
    })
})
