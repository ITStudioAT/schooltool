import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import Notifications from '@/pages/admin/teaching/settings/components/Notifications.vue'

describe('Notifications settings edit flow', () => {
    it('shows the active schoolyear label in the notifications settings panel', () => {
        const ctx = {
            config: {
                selected_schoolyear: {
                    name: 'Schuljahr 2025/26',
                    concerns: '2025/26',
                },
            },
        }

        expect((Notifications as any).computed.activeSchoolyearLabel.call(ctx)).toBe('Schuljahr 2025/26')
    })

    it('builds the previous schoolyear import label for notifications import', () => {
        const methods = (Notifications as any).methods
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

        ctx.activeSchoolyearConcern = (Notifications as any).computed.activeSchoolyearConcern.call(ctx)
        ctx.previousSchoolyearConcern = (Notifications as any).computed.previousSchoolyearConcern.call(ctx)
        ctx.previousSchoolyear = (Notifications as any).computed.previousSchoolyear.call(ctx)

        expect((Notifications as any).computed.notificationsImportLabel.call(ctx)).toBe('Import vom Schuljahr: 2025/26')
    })

    it('returns to list level on save', async () => {
        const methods = (Notifications as any).methods
        let payload: Record<string, unknown> | null = null

        const ctx: Record<string, unknown> = {
            notification_entries: [{ short_name: 'ALT', name: 'Alt' }],
            data: { short_name: 'NEU', name: 'Neu' },
            edit_index: 0,
            action: 'teaching_notifications_new_or_edit',
            teachingStore: {
                saveSettings: async (input: Record<string, unknown>) => {
                    payload = input
                    return true
                },
            },
        }

        await methods.save.call(ctx)

        expect(payload).toEqual({
            teaching_notifications: [{ short_name: 'NEU', name: 'Neu' }],
        })
        expect(ctx.action).toBe('')
        expect(ctx.edit_index).toBeNull()
    })

    it('opens a persistent delete dialog and deletes only after confirmation', async () => {
        const methods = (Notifications as any).methods
        let payload: Record<string, unknown> | null = null

        const ctx: Record<string, unknown> = {
            notification_entries: [
                { short_name: 'A', name: 'Alpha' },
                { short_name: 'B', name: 'Beta' },
            ],
            delete_index: null,
            notifications_delete_dialog_open: false,
            teachingStore: {
                saveSettings: async (input: Record<string, unknown>) => {
                    payload = input
                    return true
                },
            },
            closeDeleteDialog: () => {
                ctx.notifications_delete_dialog_open = false
                ctx.delete_index = null
            },
        }

        methods.openDeleteDialog.call(ctx, 0)

        expect(ctx.delete_index).toBe(0)
        expect(ctx.notifications_delete_dialog_open).toBe(true)

        await methods.confirmDelete.call(ctx)

        expect(payload).toEqual({
            teaching_notifications: [{ short_name: 'B', name: 'Beta' }],
        })
        expect(ctx.notifications_delete_dialog_open).toBe(false)
        expect(ctx.delete_index).toBeNull()
    })

    it('computes the affected student-entry count for the selected notification delete item', () => {
        const ctx = {
            delete_index: 1,
            notification_entries: [
                { short_name: 'A', name: 'Alpha' },
                { short_name: 'B', name: 'Beta' },
            ],
            notificationsUsageCounts: {
                A: 2,
                B: 7,
            },
        }

        ctx.deleteEntryDefinition = (Notifications as any).computed.deleteEntryDefinition.call(ctx)

        expect(ctx.deleteEntryDefinition).toEqual({ short_name: 'B', name: 'Beta' })
        expect((Notifications as any).computed.notificationsDeleteUsageCount.call(ctx)).toBe(7)
    })

    it('shows the schoolyear usage count for each notification list item', () => {
        const ctx = {
            notificationsUsageCounts: {
                E: 4,
            },
        }

        expect((Notifications as any).methods.notificationsUsageCountForEntry.call(ctx, { short_name: 'E', name: 'Erinnerung' })).toBe(4)
        expect((Notifications as any).methods.notificationsUsageCountForEntry.call(ctx, { short_name: 'X', name: 'Unbenutzt' })).toBe(0)
    })

    it('imports notifications settings and closes the dialog on success', async () => {
        const methods = (Notifications as any).methods
        const ctx: Record<string, unknown> = {
            notifications_import_loading: false,
            teachingStore: {
                importNotifications: async () => true,
            },
            closeNotificationsImportDialog: () => {
                ctx.notifications_import_dialog_open = false
            },
            notifications_import_dialog_open: true,
        }

        await methods.importNotifications.call(ctx)

        expect(ctx.notifications_import_loading).toBe(false)
        expect(ctx.notifications_import_dialog_open).toBe(false)
    })

    it('resets notifications settings and closes the dialog on success', async () => {
        const methods = (Notifications as any).methods
        const ctx: Record<string, unknown> = {
            notifications_reset_loading: false,
            teachingStore: {
                resetNotifications: async () => true,
            },
            closeNotificationsResetDialog: () => {
                ctx.notifications_reset_dialog_open = false
            },
            notifications_reset_dialog_open: true,
        }

        await methods.resetNotifications.call(ctx)

        expect(ctx.notifications_reset_loading).toBe(false)
        expect(ctx.notifications_reset_dialog_open).toBe(false)
    })

    it('adds notifications import and reset buttons in the header with persistent dialogs', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/settings/components/Notifications.vue',
        )

        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<template #header-actions>')
        expect(source).toContain('prepend-icon="mdi-plus"')
        expect(source).toContain('@click="newEntry"')
        expect(source).toContain('Hinzufügen')
        expect(source).toContain('prepend-icon="mdi-restore"')
        expect(source).toContain('@click="openNotificationsResetDialog"')
        expect(source).toContain('Reset')
        expect(source).toContain('prepend-icon="mdi-import"')
        expect(source).toContain('@click="openNotificationsImportDialog"')
        expect(source).toContain('<v-dialog v-model="notifications_import_dialog_open" persistent max-width="560">')
        expect(source).toContain('<v-dialog v-model="notifications_reset_dialog_open" persistent max-width="560">')
        expect(source).toContain('<v-dialog v-model="notifications_delete_dialog_open" persistent max-width="520">')
        expect(source).toContain('Verständigungen importieren')
        expect(source).toContain('Verständigungen zurücksetzen')
        expect(source).toContain('Verständigung löschen')
        expect(source).toContain('Möchten Sie diesen Verständigungs-Eintrag wirklich löschen?')
        expect(source).toContain("notificationsDeleteUsageCount > 0 ? 'warning' : 'info'")
        expect(source).toContain('Diese Verständigung wird bei Schüler:innen bereits verwendet:')
        expect(source).toContain('Diese Verständigung wird bei Schüler:innen derzeit nicht verwendet.')
        expect(source).toContain('{{ notificationsDeleteUsageCount }} Einträge')
        expect(source).toContain('Beim Löschen werden auch alle betroffenen Verständigungen der Schüler:innen entfernt.')
        expect(source).toContain('@click="openDeleteDialog(index)"')
        expect(source).toContain('{{ notificationsUsageCountForEntry(entry) }} Einträge')
        expect(source).toContain('color="warning" icon="mdi-delete" @click="openDeleteDialog(index)"')
        expect(source).toContain('color="primary" icon="mdi-pencil" @click="editEntry(index)"')
        expect(source).not.toContain('v-if="is_editing"')
        expect(source).not.toContain('ItsMenuButton')
        expect(source).toContain('Diese Verständigungen werden bereits verwendet:')
        expect(source).toContain('Diese Verständigungen werden derzeit nicht verwendet.')
        expect(source).toContain('{{ notificationsUsageCount }} Einträge')
        expect(source).toContain('<strong>Wenn Sie Verständigungen importieren, werden alle bisherigen Verständigungen gelöscht!</strong>')
        expect(source).toContain('<strong>Wenn Sie die Verständigungen zurücksetzen, werden alle bisherigen Verständigungen gelöscht!</strong>')
        expect(source).toContain('<strong>{{ notificationsImportLabel }}</strong>')
        expect(source).toContain(':loading="notifications_import_loading"')
        expect(source).toContain(':loading="notifications_reset_loading"')
        expect(source).toContain('@click="importNotifications"')
        expect(source).toContain('@click="resetNotifications"')
        expect(source).toContain('@click="confirmDelete"')
        expect(source).toContain('Importieren')
        expect(source).toContain('Löschen')
        expect(source).toContain('async importNotifications() {')
        expect(source).toContain('async resetNotifications() {')
        expect(source).toContain('openDeleteDialog(index) {')
        expect(source).toContain('closeDeleteDialog() {')
        expect(source).toContain('async confirmDelete() {')
        expect(source).toContain('notificationsUsageCounts() {')
        expect(source).toContain('deleteEntryDefinition() {')
        expect(source).toContain('notificationsDeleteUsageCount() {')
        expect(source).toContain('notificationsUsageCountForEntry(entry) {')
        expect(source).toContain('openNotificationsImportDialog() {')
        expect(source).toContain('closeNotificationsImportDialog() {')
        expect(source).toContain('openNotificationsResetDialog() {')
        expect(source).toContain('closeNotificationsResetDialog() {')
        expect(source).toContain('notifications_delete_dialog_open: false')
        expect(source).toContain('notifications_import_dialog_open: false')
        expect(source).toContain('notifications_reset_dialog_open: false')
        expect(source).toContain('notifications_import_loading: false')
        expect(source).toContain('notifications_reset_loading: false')
    })
})
