import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import DataBackup from '@/pages/admin/teaching/backup/DataBackup.vue'

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
    },
}))

describe('Teaching data backup page', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('loads existing backups from the teaching backups endpoint', async () => {
        vi.mocked(axios.get).mockResolvedValueOnce({
            data: {
                data: [{ id: 1, filename: 'backup.json' }],
            },
        })
        vi.mocked(axios.get).mockResolvedValueOnce({
            data: {
                data: [{ id: 9, status: 'completed' }],
                meta: {
                    queue_health: {
                        needs_attention: true,
                        message: 'Queue worker prüfen.',
                    },
                },
            },
        })

        const ctx: Record<string, unknown> = {
            backups: [],
            restore_runs: [],
            restore_queue_health: null,
            loading: false,
            error: '',
            updateRestoreRunPolling: vi.fn(),
        }

        await (DataBackup as any).methods.loadBackups.call(ctx)

        expect(axios.get).toHaveBeenCalledWith('/api/admin/teaching/backups')
        expect(axios.get).toHaveBeenCalledWith('/api/admin/teaching/backups/restore-runs')
        expect(ctx.backups).toEqual([{ id: 1, filename: 'backup.json' }])
        expect(ctx.restore_runs).toEqual([{ id: 9, status: 'completed' }])
        expect(ctx.restore_queue_health).toEqual({
            needs_attention: true,
            message: 'Queue worker prüfen.',
        })
        expect(ctx.loading).toBe(false)
        expect(ctx.error).toBe('')
        expect(ctx.updateRestoreRunPolling).toHaveBeenCalled()
    })

    it('creates a new backup and prepends it to the list', async () => {
        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: { id: 2, filename: 'new-backup.json' },
            },
        })

        const ctx: Record<string, unknown> = {
            backups: [{ id: 1, filename: 'old-backup.json' }],
            creating: false,
            error: '',
            loadBackups: vi.fn(),
        }

        await (DataBackup as any).methods.createBackup.call(ctx)

        expect(axios.post).toHaveBeenCalledWith('/api/admin/teaching/backups')
        expect(ctx.backups).toEqual([
            { id: 2, filename: 'new-backup.json' },
            { id: 1, filename: 'old-backup.json' },
        ])
        expect(ctx.creating).toBe(false)
    })

    it('imports an external backup file and prepends it to the list', async () => {
        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: { id: 3, filename: 'external.json' },
                meta: {
                    message: 'Backup-Datei wurde importiert.',
                },
            },
        })

        const event = {
            target: {
                files: [new File(['{}'], 'external.json', { type: 'application/json' })],
                value: 'external.json',
            },
        }
        const ctx: Record<string, unknown> = {
            backups: [{ id: 1, filename: 'old-backup.json' }],
            importing: false,
            error: '',
            import_notice: '',
            loadBackups: vi.fn(),
        }

        await (DataBackup as any).methods.importBackup.call(ctx, event)

        expect(axios.post).toHaveBeenCalledWith(
            '/api/admin/teaching/backups/import',
            expect.any(FormData),
            {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            },
        )
        expect(ctx.backups).toEqual([
            { id: 3, filename: 'external.json' },
            { id: 1, filename: 'old-backup.json' },
        ])
        expect(ctx.importing).toBe(false)
        expect(ctx.import_notice).toBe('Backup-Datei wurde importiert.')
        expect(event.target.value).toBe('')
    })

    it('shows the duplicate import message and reuses the existing backup row', async () => {
        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: { id: 1, filename: 'old-backup.json' },
                meta: {
                    imported: false,
                    duplicate: true,
                    message: 'Diese Backup-Datei ist bereits vorhanden. Der vorhandene Eintrag wurde verwendet.',
                },
            },
        })

        const event = {
            target: {
                files: [new File(['{}'], 'external.json', { type: 'application/json' })],
                value: 'external.json',
            },
        }
        const ctx: Record<string, unknown> = {
            backups: [{ id: 1, filename: 'old-backup.json' }],
            importing: false,
            error: '',
            import_notice: '',
            loadBackups: vi.fn(),
        }

        await (DataBackup as any).methods.importBackup.call(ctx, event)

        expect(ctx.backups).toEqual([{ id: 1, filename: 'old-backup.json' }])
        expect(ctx.import_notice).toBe('Diese Backup-Datei ist bereits vorhanden. Der vorhandene Eintrag wurde verwendet.')
        expect(ctx.importing).toBe(false)
        expect(event.target.value).toBe('')
    })

    it('opens the delete confirmation dialog', () => {
        const methods = (DataBackup as any).methods
        const ctx: Record<string, unknown> = {
            selected_delete_backup: null,
            delete_confirm_open: false,
            delete_loading: false,
            delete_error: 'Alt',
        }

        methods.openDeleteDialog.call(ctx, { id: 7, filename: 'backup.json' })

        expect(ctx.selected_delete_backup).toEqual({ id: 7, filename: 'backup.json' })
        expect(ctx.delete_confirm_open).toBe(true)
        expect(ctx.delete_error).toBe('')
    })

    it('builds a backup download url from explicit urls or ids', () => {
        const methods = (DataBackup as any).methods

        expect(methods.backupDownloadUrl({ id: 7, download_url: '/custom/download' })).toBe('/custom/download')
        expect(methods.backupDownloadUrl({ id: 7 })).toBe('/api/admin/teaching/backups/7/download')
        expect(methods.backupDownloadUrl(null)).toBe('')
    })

    it('deletes a backup after confirmation', async () => {
        vi.mocked(axios.delete).mockResolvedValueOnce({
            data: {
                data: { id: 7, deleted: true },
            },
        })

        const ctx: Record<string, unknown> = {
            backups: [
                { id: 7, filename: 'delete-me.json' },
                { id: 8, filename: 'keep-me.json' },
            ],
            selected_delete_backup: { id: 7, filename: 'delete-me.json' },
            delete_confirm_open: true,
            delete_loading: false,
            delete_loading_id: null,
            delete_error: '',
            error: '',
            preview_open: true,
            selected_preview: { courses: [] },
            selected_preview_backup: { id: 7, filename: 'delete-me.json' },
        }

        await (DataBackup as any).methods.deleteBackup.call(ctx)

        expect(axios.delete).toHaveBeenCalledWith('/api/admin/teaching/backups/7')
        expect(ctx.backups).toEqual([{ id: 8, filename: 'keep-me.json' }])
        expect(ctx.delete_confirm_open).toBe(false)
        expect(ctx.selected_delete_backup).toBeNull()
        expect(ctx.delete_loading).toBe(false)
        expect(ctx.delete_loading_id).toBeNull()
        expect(ctx.preview_open).toBe(false)
        expect(ctx.selected_preview_backup).toBeNull()
    })

    it('keeps the delete dialog open when deletion fails', async () => {
        vi.mocked(axios.delete).mockRejectedValueOnce({
            response: {
                data: {
                    message: 'Nicht erlaubt',
                },
            },
        })

        const ctx: Record<string, unknown> = {
            backups: [{ id: 7, filename: 'delete-me.json' }],
            selected_delete_backup: { id: 7, filename: 'delete-me.json' },
            delete_confirm_open: true,
            delete_loading: false,
            delete_loading_id: null,
            delete_error: '',
            error: '',
        }

        await (DataBackup as any).methods.deleteBackup.call(ctx)

        expect(ctx.backups).toEqual([{ id: 7, filename: 'delete-me.json' }])
        expect(ctx.delete_confirm_open).toBe(true)
        expect(ctx.delete_error).toBe('Nicht erlaubt')
        expect(ctx.delete_loading).toBe(false)
        expect(ctx.delete_loading_id).toBeNull()
    })

    it('formats backup creation timestamps for German users', () => {
        const formatted = (DataBackup as any).methods.formatDateTime('2026-05-09 06:30:00')

        expect(formatted).toContain('09.05.2026')
        expect(formatted).toContain('06:30')
    })

    it('shows a readable backup title with schoolyear and save time', () => {
        const methods = (DataBackup as any).methods
        const ctx = {
            formatDateTime: methods.formatDateTime,
            backupKind: methods.backupKind,
            isSafetyBackup: methods.isSafetyBackup,
        }

        const title = methods.backupDisplayTitle.call(ctx, {
            schoolyear_name: '2025/26',
            schoolyear_id: 1,
            created_at: '2026-05-09 06:45:28',
            filename: 'teaching-backup-school-1-schoolyear-1-20260509-064528.json',
        })

        expect(title).toContain('2025/26')
        expect(title).toContain('09.05.2026')
        expect(title).toContain('06:45')
        expect(title).not.toContain('teaching-backup-school-1')
    })

    it('labels safety backups as rollback points', () => {
        const methods = (DataBackup as any).methods
        const ctx = {
            formatDateTime: methods.formatDateTime,
            backupKind: methods.backupKind,
            isSafetyBackup: methods.isSafetyBackup,
        }
        const backup = {
            schoolyear_name: '2025/26',
            created_at: '2026-05-10 19:20:00',
            summary: {
                backup_kind: 'pre_restore',
            },
        }

        expect(methods.backupKindLabel(backup)).toBe('Sicherheitskopie')
        expect(methods.backupKindColor(backup)).toBe('warning')
        expect(methods.backupDisplayTitle.call(ctx, backup)).toContain('Sicherheitskopie vor Wiederherstellung')
    })

    it('shows validation labels and colors from the backup summary', () => {
        const methods = (DataBackup as any).methods
        const backup = {
            summary: {
                validation: {
                    status: 'warning',
                    issues: [],
                    warnings: ['Eine Datei fehlt.'],
                },
            },
        }

        expect(methods.validationLabel.call({ validationStatus: methods.validationStatus }, backup)).toBe('Warnung')
        expect(methods.validationColor.call({ validationStatus: methods.validationStatus }, backup)).toBe('warning')
        expect(methods.validationWarnings.call({}, backup)).toEqual(['Eine Datei fehlt.'])
    })

    it('prepares sorted table count entries for the details dialog', () => {
        const methods = (DataBackup as any).methods
        const ctx = {
            tableCountLabel: methods.tableCountLabel,
        }
        const backup = {
            summary: {
                table_counts: {
                    teaching_courses: 7,
                    teaching_curricula: 2,
                },
            },
        }

        expect(methods.tableCountEntries.call(ctx, backup)).toEqual([
            { key: 'teaching_curricula', label: 'Curricula', count: 2 },
            { key: 'teaching_courses', label: 'Kurse', count: 7 },
        ])
    })

    it('loads the combined backup dialog and restore preview', async () => {
        vi.mocked(axios.get).mockResolvedValueOnce({
            data: {
                data: {
                    setting_sections: [{ key: 'basic_settings', count: 1, status: 'different' }],
                    courses: [{ id: 7, title: 'Gelöschter Kurs', status: 'missing_current' }],
                    curricula: [{ id: 8, title: 'Gelöschtes Curriculum', status: 'missing_current' }],
                },
            },
        })

        const ctx: Record<string, unknown> = {
            preview_open: false,
            preview_error: '',
            selected_preview: null,
            preview_loading_id: null,
            selected_preview_backup: null,
            restore_error: '',
            restore_result: null,
            restore_selection: {
                courses: [],
                curricula: [],
                settings: [],
            },
            initializeRestoreSelection: (DataBackup as any).methods.initializeRestoreSelection,
            restoreSelectableValues: (DataBackup as any).methods.restoreSelectableValues,
            previewSettingSections: (DataBackup as any).methods.previewSettingSections,
            isCourseRestoreSelectable: (DataBackup as any).methods.isCourseRestoreSelectable,
            isCurriculumRestoreSelectable: (DataBackup as any).methods.isCurriculumRestoreSelectable,
            isSettingRestoreSelectable: (DataBackup as any).methods.isSettingRestoreSelectable,
        }

        await (DataBackup as any).methods.openRestorePreview.call(ctx, { id: 12 })

        expect(axios.get).toHaveBeenCalledWith('/api/admin/teaching/backups/12/preview')
        expect(ctx.preview_open).toBe(true)
        expect(ctx.selected_preview).toEqual({
            setting_sections: [{ key: 'basic_settings', count: 1, status: 'different' }],
            courses: [{ id: 7, title: 'Gelöschter Kurs', status: 'missing_current' }],
            curricula: [{ id: 8, title: 'Gelöschtes Curriculum', status: 'missing_current' }],
        })
        expect(ctx.restore_selection).toEqual({
            courses: [7],
            curricula: [8],
            settings: ['basic_settings'],
        })
        expect(ctx.preview_loading_id).toBeNull()
    })

    it('labels preview restore status without implying a write action', () => {
        const methods = (DataBackup as any).methods

        expect(methods.restoreStatusLabel('missing_current')).toBe('fehlt aktuell')
        expect(methods.restoreStatusColor('missing_current')).toBe('error')
        expect(methods.restoreStatusLabel('current_exists')).toBe('aktuell vorhanden')
        expect(methods.restoreStatusLabel('different')).toBe('abweichend')
        expect(methods.restoreStatusLabel('in_backup')).toBe('im Backup')
        expect(methods.classesLabel(['5A', '6B'])).toBe('5A, 6B')
    })

    it('shows explicit setting sections in the restore preview', () => {
        const methods = (DataBackup as any).methods
        const preview = {
            setting_sections: [
                { key: 'basic_settings', label: 'Grundeinstellungen', count: 1, unit: 'Benutzer:innen', status: 'current_exists' },
                { key: 'school_hours', label: 'Schulstunden', count: 10, unit: 'Stunden', status: 'different' },
                {
                    key: 'notifications',
                    label: 'Verständigungen',
                    count: 6,
                    unit: 'Regeln',
                    secondary_count: 2,
                    secondary_unit: 'Einträge',
                    status: 'missing_current',
                },
            ],
        }
        const ctx = {
            restoreStatusLabel: methods.restoreStatusLabel,
            restoreStatusColor: methods.restoreStatusColor,
        }

        expect(methods.previewSettingSections(preview)).toEqual(preview.setting_sections)
        expect(methods.sectionCountLabel(preview.setting_sections[0])).toBe('1 Benutzer:innen')
        expect(methods.sectionCountLabel(preview.setting_sections[2])).toBe('6 Regeln / 2 Einträge')
        expect(methods.settingSectionStatusLabel.call(ctx, preview.setting_sections[0])).toBe('aktuell vorhanden')
        expect(methods.settingSectionStatusColor.call(ctx, preview.setting_sections[1])).toBe('warning')
        expect(methods.settingSectionStatusLabel.call(ctx, preview.setting_sections[2])).toBe('fehlt aktuell')
        expect(methods.settingSectionStatusColor.call(ctx, preview.setting_sections[2])).toBe('error')
    })

    it('only allows changed or missing settings to be selected for restore', () => {
        const methods = (DataBackup as any).methods

        expect(methods.isSettingRestoreSelectable({ count: 1, status: 'different' })).toBe(true)
        expect(methods.isSettingRestoreSelectable({ count: 1, status: 'missing_current' })).toBe(true)
        expect(methods.isSettingRestoreSelectable({ count: 1, status: 'current_exists' })).toBe(false)
        expect(methods.isSettingRestoreSelectable({ count: 0, status: 'different' })).toBe(false)
        expect(methods.isSettingRestoreSelectable({ count: 1, status: 'missing_current', restore_scope: 'full' })).toBe(false)
    })

    it('does not show stale selections for rows that are no longer restoreable', () => {
        const methods = (DataBackup as any).methods
        const ctx = {
            overwrite_existing: false,
            restore_selection: {
                courses: [7],
                curricula: [8],
                settings: ['basic_settings'],
            },
            isCourseRestoreSelectable: methods.isCourseRestoreSelectable,
            isCurriculumRestoreSelectable: methods.isCurriculumRestoreSelectable,
            isSettingRestoreSelectable: methods.isSettingRestoreSelectable,
        }

        expect(methods.isRestoreSelected.call(ctx, 'settings', 'basic_settings', { key: 'basic_settings', count: 1, status: 'current_exists' })).toBe(false)
        expect(methods.isRestoreSelected.call(ctx, 'courses', 7, { id: 7, status: 'current_exists' })).toBe(false)
        expect(methods.isRestoreSelected.call(ctx, 'curricula', 8, { id: 8, status: 'current_exists' })).toBe(false)
    })

    it('allows only different courses and curricula when overwrite is enabled', () => {
        const methods = (DataBackup as any).methods
        const ctx = {
            overwrite_existing: true,
        }

        expect(methods.isCourseRestoreSelectable.call(ctx, { id: 7, status: 'current_exists' })).toBe(false)
        expect(methods.isCourseRestoreSelectable.call(ctx, { id: 10, status: 'different' })).toBe(true)
        expect(methods.isCurriculumRestoreSelectable.call(ctx, { id: 8, status: 'different' })).toBe(true)
        expect(methods.isCurriculumRestoreSelectable.call(ctx, { id: 9, status: 'current_exists' })).toBe(false)
    })

    it('allows full restore once a valid preview is loaded', () => {
        const ctx = {
            selected_preview: {
                validation: { is_valid: true },
                courses: [{ id: 7, status: 'current_exists' }],
                curricula: [{ id: 8, status: 'current_exists' }],
                setting_sections: [{ key: 'basic_settings', count: 1, status: 'current_exists' }],
            },
            full_restore_loading: false,
        }

        expect((DataBackup as any).computed.canRestoreFull.call(ctx)).toBe(true)

        ctx.full_restore_loading = true

        expect((DataBackup as any).computed.canRestoreFull.call(ctx)).toBe(false)
    })

    it('uses fresh preview validation to block restoring previously valid backups', async () => {
        const { methods, computed } = DataBackup as any
        const issue = 'Der Sicherung fehlen Eintragsbereiche. Erstelle eine neue Sicherung auf dem Quellsystem.'
        const ctx = {
            selected_preview_backup: { id: 12, summary: { validation: { is_valid: true, issues: [], warnings: [] } } },
            selected_preview: {
                validation: { is_valid: false, issues: [issue], warnings: ['Eine Datei fehlt.'] },
            },
            restoreSelectionCount: 1,
            restore_loading: false,
            full_restore_loading: false,
            full_restore_confirm_open: false,
            canRestoreSelected: false,
            canRestoreFull: false,
        }

        expect(methods.validationIssues(ctx.selected_preview)).toEqual([issue])
        expect(methods.validationWarnings(ctx.selected_preview)).toEqual(['Eine Datei fehlt.'])
        expect(computed.canRestoreSelected.call(ctx)).toBe(false)
        expect(computed.canRestoreFull.call(ctx)).toBe(false)
        methods.openFullRestoreDialog.call(ctx)
        await methods.restoreFull.call(ctx)
        await methods.restoreSelected.call(ctx)

        expect(ctx.full_restore_confirm_open).toBe(false)
        expect(axios.post).not.toHaveBeenCalled()

        ctx.selected_preview.validation.is_valid = true

        expect(computed.canRestoreSelected.call(ctx)).toBe(true)
        expect(computed.canRestoreFull.call(ctx)).toBe(true)
    })

    it('posts the selected restore plan, closes preview, and refreshes backups', async () => {
        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: {
                    restored: {
                        courses: [{ old_id: 7, new_id: 17 }],
                        curricula: [],
                        settings: [],
                    },
                },
            },
        })

        const ctx: Record<string, unknown> = {
            preview_open: true,
            selected_preview_backup: { id: 12 },
            selected_preview: {
                courses: [{ id: 7, title: 'Gelöschter Kurs', status: 'missing_current' }],
                curricula: [],
                setting_sections: [],
            },
            restore_selection: {
                courses: [7],
                curricula: [],
                settings: [],
            },
            restore_loading: false,
            restore_error: '',
            restore_result: null,
            overwrite_existing: false,
            canRestoreSelected: true,
            initializeRestoreSelection: (DataBackup as any).methods.initializeRestoreSelection,
            restoreSelectableValues: (DataBackup as any).methods.restoreSelectableValues,
            previewSettingSections: (DataBackup as any).methods.previewSettingSections,
            isCourseRestoreSelectable: (DataBackup as any).methods.isCourseRestoreSelectable,
            isCurriculumRestoreSelectable: (DataBackup as any).methods.isCurriculumRestoreSelectable,
            isSettingRestoreSelectable: (DataBackup as any).methods.isSettingRestoreSelectable,
            sanitizedRestoreSelection: (DataBackup as any).methods.sanitizedRestoreSelection,
            closeRestorePreviewAfterSuccess: (DataBackup as any).methods.closeRestorePreviewAfterSuccess,
            loadBackups: vi.fn(),
        }

        await (DataBackup as any).methods.restoreSelected.call(ctx)

        expect(axios.post).toHaveBeenCalledWith('/api/admin/teaching/backups/12/restore', {
            courses: [7],
            curricula: [],
            settings: [],
            overwrite_existing: false,
        })
        expect(ctx.restore_result).toEqual({
            restored: {
                courses: [{ old_id: 7, new_id: 17 }],
                curricula: [],
                settings: [],
            },
        })
        expect(ctx.preview_open).toBe(false)
        expect(ctx.selected_preview).toBeNull()
        expect(ctx.selected_preview_backup).toBeNull()
        expect(ctx.restore_selection).toEqual({
            courses: [],
            curricula: [],
            settings: [],
        })
        expect(ctx.loadBackups).toHaveBeenCalled()
    })

    it('opens the full restore confirmation dialog', () => {
        const methods = (DataBackup as any).methods
        const ctx: Record<string, unknown> = {
            selected_preview_backup: { id: 12 },
            canRestoreFull: true,
            full_restore_loading: false,
            full_restore_confirm_open: false,
        }

        methods.openFullRestoreDialog.call(ctx)

        expect(ctx.full_restore_confirm_open).toBe(true)
    })

    it('opens the restore preview for a rollback safety backup', async () => {
        const methods = (DataBackup as any).methods
        const backup = { id: 44, filename: 'safety.json' }
        const run = {
            type: 'full',
            status: 'completed',
            pre_restore_backup: backup,
        }
        const ctx = {
            rollbackBackupForRun: methods.rollbackBackupForRun,
            openRestorePreview: vi.fn(),
        }

        expect(methods.canRollbackRun.call(ctx, run)).toBe(true)

        await methods.openRollbackPreview.call(ctx, run)

        expect(ctx.openRestorePreview).toHaveBeenCalledWith(backup)
    })

    it('opens a restore run report from history', () => {
        const methods = (DataBackup as any).methods
        const backup = { id: 44, filename: 'safety.json' }
        const run = {
            type: 'full',
            status: 'completed',
            pre_restore_backup: backup,
            result: {
                restored: true,
                counts: {
                    courses: 1,
                },
            },
        }
        const ctx: Record<string, unknown> = {
            restore_result: null,
            restore_report_open: false,
            canViewRestoreRunReport: methods.canViewRestoreRunReport,
        }

        expect(methods.canViewRestoreRunReport.call(ctx, run)).toBe(true)

        methods.openRestoreRunReport.call(ctx, run)

        expect(ctx.restore_result).toEqual({
            restored: true,
            counts: {
                courses: 1,
            },
            pre_restore_backup: backup,
        })
        expect(ctx.restore_report_open).toBe(true)
        expect(methods.canViewRestoreRunReport.call(ctx, { ...run, status: 'running' })).toBe(false)
    })

    it('opens a failed restore run report from history', () => {
        const methods = (DataBackup as any).methods
        const run = {
            type: 'full',
            status: 'failed',
            result: {
                failed: true,
                reason: 'stale_restore_run',
                message: 'Wiederherstellung wurde automatisch entsperrt, weil sie zu lange aktiv war.',
            },
        }
        const ctx: Record<string, unknown> = {
            restore_result: null,
            restore_report_open: false,
            canViewRestoreRunReport: methods.canViewRestoreRunReport,
        }

        expect(methods.canViewRestoreRunReport.call(ctx, run)).toBe(true)

        methods.openRestoreRunReport.call(ctx, run)

        expect(ctx.restore_result).toEqual({
            failed: true,
            reason: 'stale_restore_run',
            message: 'Wiederherstellung wurde automatisch entsperrt, weil sie zu lange aktiv war.',
            pre_restore_backup: null,
        })
        expect(ctx.restore_report_open).toBe(true)
    })

    it('builds restore run backup download urls', () => {
        const methods = (DataBackup as any).methods
        const ctx = {
            backupDownloadUrl: methods.backupDownloadUrl,
        }
        const run = {
            backup_id: 12,
            pre_restore_backup_id: 44,
            pre_restore_backup: {
                id: 44,
                download_url: '/api/admin/teaching/backups/44/download',
            },
        }

        expect(methods.restoreRunBackupDownloadUrl.call(ctx, run, 'backup')).toBe('/api/admin/teaching/backups/12/download')
        expect(methods.restoreRunBackupDownloadUrl.call(ctx, run, 'pre_restore')).toBe('/api/admin/teaching/backups/44/download')
        expect(methods.restoreRunBackupDownloadUrl.call(ctx, {}, 'backup')).toBe('')
    })

    it('posts a full restore request, closes preview, and refreshes backups', async () => {
        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: {
                    queued: true,
                    message: 'Vollständige Wiederherstellung wurde gestartet.',
                    restore_run: { id: 15, status: 'pending' },
                },
            },
        })

        const ctx: Record<string, unknown> = {
            preview_open: true,
            selected_preview_backup: { id: 12 },
            selected_preview: {
                courses: [{ id: 7, status: 'missing_current' }],
                curricula: [],
                setting_sections: [],
            },
            full_restore_loading: false,
            full_restore_confirm_open: true,
            canRestoreFull: true,
            restore_error: '',
            restore_result: null,
            restore_selection: {
                courses: [7],
                curricula: [],
                settings: [],
            },
            overwrite_existing: true,
            closeRestorePreviewAfterSuccess: (DataBackup as any).methods.closeRestorePreviewAfterSuccess,
            loadBackups: vi.fn(),
        }

        await (DataBackup as any).methods.restoreFull.call(ctx)

        expect(axios.post).toHaveBeenCalledWith('/api/admin/teaching/backups/12/restore-full')
        expect(ctx.restore_result).toEqual({
            queued: true,
            message: 'Vollständige Wiederherstellung wurde gestartet.',
            restore_run: { id: 15, status: 'pending' },
        })
        expect(ctx.preview_open).toBe(false)
        expect(ctx.selected_preview).toBeNull()
        expect(ctx.selected_preview_backup).toBeNull()
        expect(ctx.restore_selection).toEqual({
            courses: [],
            curricula: [],
            settings: [],
        })
        expect(ctx.overwrite_existing).toBe(false)
        expect(ctx.loadBackups).toHaveBeenCalled()
        expect(ctx.full_restore_loading).toBe(false)
        expect(ctx.full_restore_confirm_open).toBe(false)
    })

    it('summarizes queued full restore results', () => {
        const methods = (DataBackup as any).methods

        expect(methods.restoreResultLabel.call({}, {
            queued: true,
            message: 'Vollständige Wiederherstellung wurde gestartet.',
        })).toBe('Vollständige Wiederherstellung wurde gestartet.')
    })

    it('shows queue health warnings when restore processing is delayed', () => {
        const methods = (DataBackup as any).methods

        expect(methods.restoreQueueHealthMessage.call({
            restore_queue_health: {
                needs_attention: true,
                message: 'Eine vollständige Wiederherstellung wartet ungewöhnlich lange.',
            },
        })).toBe('Eine vollständige Wiederherstellung wartet ungewöhnlich lange.')

        expect(methods.restoreQueueHealthMessage.call({
            restore_queue_health: {
                needs_attention: false,
                message: 'Nicht anzeigen.',
            },
        })).toBe('')
    })

    it('shows when a backup was used for restore', () => {
        const methods = (DataBackup as any).methods
        const ctx = {
            restore_runs: [
                {
                    backup_id: 12,
                    status: 'completed',
                    finished_at: '2026-05-10 19:20:00',
                },
                {
                    backup_id: 13,
                    status: 'running',
                },
            ],
            backupRestoreRun: methods.backupRestoreRun,
            formatDateTime: methods.formatDateTime,
        }

        expect(methods.backupRestoreRun.call(ctx, { id: 12 })?.status).toBe('completed')
        expect(methods.backupRestoreLabel.call(ctx, { id: 12 })).toContain('wiederhergestellt')
        expect(methods.backupRestoreLabel.call(ctx, { id: 12 })).toContain('10.05.2026')
        expect(methods.backupRestoreColor.call(ctx, { id: 12 })).toBe('success')
        expect(methods.backupRestoreLabel.call(ctx, { id: 13 })).toBe('Wiederherstellung läuft')
        expect(methods.backupRestoreRun.call(ctx, { id: 99 })).toBeNull()
    })

    it('summarizes failed restore results', () => {
        const methods = (DataBackup as any).methods

        expect(methods.restoreResultLabel.call({}, {
            failed: true,
            message: 'Wiederherstellung fehlgeschlagen.',
        })).toBe('Wiederherstellung fehlgeschlagen.')

        expect(methods.restoreReportEntries({
            failed: true,
            reason: 'unexpected_error',
            message: 'Wiederherstellung fehlgeschlagen.',
        })).toEqual([
            { key: 'failure_message', label: 'Meldung', count: 'Wiederherstellung fehlgeschlagen.' },
            { key: 'failure_reason', label: 'Code', count: 'unexpected_error' },
        ])
    })

    it('builds detailed partial restore reports', () => {
        const methods = (DataBackup as any).methods
        const result = {
            restored: {
                courses: [
                    {
                        old_id: 1,
                        new_id: 17,
                        title: 'Informatik - 5B1',
                        counts: {
                            students: 10,
                            dates: 21,
                            entries: 53,
                            behaviour_entries: 1,
                        },
                    },
                ],
                curricula: [],
                settings: [
                    {
                        key: 'behaviour',
                        count: 854,
                    },
                ],
            },
            skipped: {
                courses: [],
                curricula: [],
                settings: [],
            },
            warnings: [],
        }
        const ctx = {
            partialRestoreRowCount: methods.partialRestoreRowCount,
            restoreReportCourses: methods.restoreReportCourses,
            restoreReportSettings: methods.restoreReportSettings,
            restoreReportSkipped: methods.restoreReportSkipped,
            settingSectionKeyLabel: methods.settingSectionKeyLabel,
        }

        expect(methods.restoreReportEntries.call(ctx, result)).toEqual([
            { key: 'courses', label: 'Kurse wiederhergestellt', count: 1 },
            { key: 'settings', label: 'Einstellungsbereiche wiederhergestellt', count: 1 },
            { key: 'rows', label: 'Wiederhergestellte abhängige Datensätze', count: 85 },
        ])
        expect(methods.restoreReportCourses(result)).toEqual(result.restored.courses)
        expect(methods.restoreReportSettings(result)).toEqual(result.restored.settings)
        expect(methods.settingSectionKeyLabel('behaviour')).toBe('Verhalten')
    })

    it('summarizes full restore result counts', () => {
        const methods = (DataBackup as any).methods
        const ctx = {
            fullRestoreCount: methods.fullRestoreCount,
        }

        expect(methods.restoreResultLabel.call(ctx, {
            restored: true,
            counts: {
                courses: 2,
                curricula: 1,
                users_created: 1,
                users_matched_by_email: 1,
            },
        })).toBe('Vollständig wiederhergestellt: 4 Datensätze.')
    })

    it('summarizes full restore user reconciliation', () => {
        const methods = (DataBackup as any).methods

        expect(methods.fullRestoreUserReport({
            restored: true,
            user_reconciliation: {
                matched_by_email: [{ user_id: 5 }],
                created_placeholders: [{ user_id: 6 }],
            },
        })).toBe('1 Benutzer:innen per E-Mail zugeordnet, 1 Benutzer:innen als inaktive Platzhalter angelegt.')

        expect(methods.fullRestoreUserReport({
            restored: true,
            user_reconciliation: {
                matched_by_email: [],
                created_placeholders: [],
            },
        })).toBe('')
    })

    it('removes non-restoreable rows from the restore payload', () => {
        const methods = (DataBackup as any).methods
        const ctx = {
            selected_preview: {
                courses: [
                    { id: 7, status: 'missing_current' },
                    { id: 9, status: 'current_exists' },
                ],
                curricula: [
                    { id: 8, status: 'missing_current' },
                    { id: 10, status: 'current_exists' },
                ],
                setting_sections: [
                    { key: 'basic_settings', count: 1, status: 'different' },
                    { key: 'school_hours', count: 1, status: 'current_exists' },
                ],
            },
            restore_selection: {
                courses: [7, 9],
                curricula: [8, 10],
                settings: ['basic_settings', 'school_hours'],
            },
            overwrite_existing: false,
            restoreSelectableValues: methods.restoreSelectableValues,
            previewSettingSections: methods.previewSettingSections,
            isCourseRestoreSelectable: methods.isCourseRestoreSelectable,
            isCurriculumRestoreSelectable: methods.isCurriculumRestoreSelectable,
            isSettingRestoreSelectable: methods.isSettingRestoreSelectable,
        }

        expect(methods.sanitizedRestoreSelection.call(ctx)).toEqual({
            courses: [7],
            curricula: [8],
            settings: ['basic_settings'],
            overwrite_existing: false,
        })
    })
})
