import { beforeEach, describe, expect, it, vi } from 'vitest'
import Import116 from '@/pages/admin/teaching/admin/import116/Import116.vue'

describe('Teaching import116 page', () => {
    const axiosMock = {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
    }

    beforeEach(() => {
        axiosMock.get.mockReset()
        axiosMock.post.mockReset()
        axiosMock.delete.mockReset()
        globalThis.axios = axiosMock as never
    })

    it('reconciles a completed import and clears the running state', () => {
        const stopImportStatusPolling = vi.fn()
        const ctx: Record<string, unknown> = {
            is_importing: true,
            last_import_116_at: null,
            run_action_message: '',
            run_action_error: 'old error',
            stopImportStatusPolling,
        }

        ;(Import116 as any).methods.reconcileImportRun.call(ctx, {
            status: 'completed',
            finished_at: '2026-07-14T05:00:00.000Z',
            counts: { inserted: 4, updated: 2, deleted: 1 },
        })

        expect(stopImportStatusPolling).toHaveBeenCalledTimes(1)
        expect(ctx.is_importing).toBe(false)
        expect(ctx.last_import_116_at).toBe('2026-07-14T05:00:00.000Z')
        expect(ctx.run_action_error).toBe('')
        expect(ctx.run_action_message).toBe('Import abgeschlossen: +4 / ~2 / -1')
    })

    it('shows failed imports as errors instead of successes', async () => {
        const reconcileImportRun = vi.fn()
        const loadRuns = vi.fn().mockResolvedValue(undefined)
        const ctx: Record<string, unknown> = {
            import_run_baseline_id: 20,
            active_import_run_id: 21,
            reconcileImportRun,
            loadRuns,
        }

        await (Import116 as any).methods.handleImportFinished.call(ctx, {
            detail: {
                status: 500,
                message: 'Import 116 fehlgeschlagen.',
                data: { run_id: 21 },
            },
        })

        expect(reconcileImportRun).toHaveBeenCalledWith(expect.objectContaining({
            id: 21,
            status: 'failed',
            error_message: 'Import 116 fehlgeschlagen.',
        }))
        expect(loadRuns).toHaveBeenCalledTimes(1)
    })

    it('polls for the current user run when the Echo event is missed', async () => {
        const reconcileImportRun = vi.fn()
        const scheduleImportStatusPoll = vi.fn()
        const ctx: Record<string, any> = {
            is_importing: true,
            import_poll_generation: 3,
            import_run_baseline_id: 40,
            active_import_run_id: null,
            config: { user: { id: 7 } },
            runs: [],
            reconcileImportRun,
            scheduleImportStatusPoll,
        }
        ctx.loadRuns = vi.fn().mockImplementation(async () => {
            ctx.runs = [
                { id: 41, user_id: 8, status: 'completed' },
                { id: 42, user_id: 7, status: 'completed', counts: { inserted: 1 } },
            ]
        })

        await (Import116 as any).methods.pollImportStatus.call(ctx, 3)

        expect(ctx.active_import_run_id).toBe(42)
        expect(reconcileImportRun).toHaveBeenCalledWith(ctx.runs[1])
        expect(scheduleImportStatusPoll).not.toHaveBeenCalled()
    })

    it('computes canRestoreSelection from selected depth and run meta limits', () => {
        const ctx = {
            run_tracking_error: '',
            selected_restore_target_id: 10,
            selected_restore_depth: 2,
            runs_meta: { reset_max_runs: 3 },
        }

        expect((Import116 as any).computed.canRestoreSelection.call(ctx)).toBe(true)

        ctx.selected_restore_depth = 4
        expect((Import116 as any).computed.canRestoreSelection.call(ctx)).toBe(false)

        ctx.run_tracking_error = 'tracking failed'
        expect((Import116 as any).computed.canRestoreSelection.call(ctx)).toBe(false)
    })

    it('selectRestoreTarget toggles selection and resets action messages', () => {
        const ctx: Record<string, unknown> = {
            selected_restore_target_id: null,
            run_action_error: 'old error',
            run_action_message: 'old message',
            canSelectAsRestoreTarget: () => true,
        }

        ;(Import116 as any).methods.selectRestoreTarget.call(ctx, { id: 11 })
        expect(ctx.selected_restore_target_id).toBe(11)
        expect(ctx.run_action_error).toBe('')
        expect(ctx.run_action_message).toBe('')

        ;(Import116 as any).methods.selectRestoreTarget.call(ctx, { id: 11 })
        expect(ctx.selected_restore_target_id).toBeNull()
    })

    it('toggleRunDetails loads once and reuses cached details on reopen', async () => {
        axiosMock.get.mockResolvedValue({
            data: { run: { id: 5 }, changes: { inserted: [], updated: [], deleted: [] } },
        })

        const ctx: Record<string, unknown> = {
            expanded_run_ids: {},
            run_details: {},
            loading_run_id: null,
            run_action_error: '',
        }

        await (Import116 as any).methods.toggleRunDetails.call(ctx, 5)
        expect((ctx.expanded_run_ids as Record<number, boolean>)[5]).toBe(true)
        expect((ctx.run_details as Record<number, unknown>)[5]).toBeTruthy()
        expect(axiosMock.get).toHaveBeenCalledTimes(1)

        await (Import116 as any).methods.toggleRunDetails.call(ctx, 5)
        expect((ctx.expanded_run_ids as Record<number, boolean>)[5]).toBe(false)

        await (Import116 as any).methods.toggleRunDetails.call(ctx, 5)
        expect((ctx.expanded_run_ids as Record<number, boolean>)[5]).toBe(true)
        expect(axiosMock.get).toHaveBeenCalledTimes(1)
    })

    it('loadRuns sets tracking error and fallback state when request fails', async () => {
        axiosMock.get.mockRejectedValue({
            response: {
                status: 409,
                data: { message: 'Import-Protokolle nicht verfügbar.' },
            },
        })

        const ctx: Record<string, unknown> = {
            is_loading_runs: false,
            run_tracking_error: '',
            run_action_error: 'old',
            runs: [{ id: 1 }],
            runs_meta: { reset_max_runs: 1, available_reset_runs: 1, history_limit: 1 },
            selected_restore_target_id: 1,
        }

        await (Import116 as any).methods.loadRuns.call(ctx)

        expect(ctx.is_loading_runs).toBe(false)
        expect(ctx.run_action_error).toBe('')
        expect(ctx.runs).toEqual([])
        expect(ctx.runs_meta).toEqual({ reset_max_runs: 0, available_reset_runs: 0, history_limit: 0 })
        expect(ctx.run_tracking_error).toBe('Import-Protokolle nicht verfügbar.')
    })

    it('toggleRunDetails closes panel and stores error when loading details fails', async () => {
        axiosMock.get.mockRejectedValue({
            response: {
                status: 404,
                data: { message: 'Import-Details konnten nicht geladen werden.' },
            },
        })

        const ctx: Record<string, unknown> = {
            expanded_run_ids: {},
            run_details: {},
            loading_run_id: null,
            run_action_error: '',
        }

        await (Import116 as any).methods.toggleRunDetails.call(ctx, 15)

        expect((ctx.expanded_run_ids as Record<number, boolean>)[15]).toBe(false)
        expect(ctx.loading_run_id).toBeNull()
        expect(ctx.run_action_error).toBe('Import-Details konnten nicht geladen werden.')
    })

    it('resetRecentRuns clears detail state and refreshes run list after success', async () => {
        axiosMock.post.mockResolvedValue({
            data: { message: '2 Importe wurden zurückgesetzt.' },
        })

        const loadRunsMock = vi.fn().mockResolvedValue(undefined)
        const ctx: Record<string, unknown> = {
            canRestoreSelection: true,
            is_resetting_runs: false,
            run_action_message: '',
            run_action_error: '',
            selected_restore_target_id: 77,
            run_details: { 77: { changes: {} } },
            expanded_run_ids: { 77: true },
            expanded_change_groups: { '77:inserted': true },
            loadRuns: loadRunsMock,
        }

        await (Import116 as any).methods.resetRecentRuns.call(ctx)

        expect(ctx.is_resetting_runs).toBe(false)
        expect(ctx.run_action_message).toBe('2 Importe wurden zurückgesetzt.')
        expect(ctx.selected_restore_target_id).toBeNull()
        expect(ctx.run_details).toEqual({})
        expect(ctx.expanded_run_ids).toEqual({})
        expect(ctx.expanded_change_groups).toEqual({})
        expect(loadRunsMock).toHaveBeenCalledTimes(1)
    })

    it('deleteImport removes local run details for deleted id and refreshes list', async () => {
        axiosMock.delete.mockResolvedValue({
            data: { message: 'Import #33 wurde gelöscht.' },
        })

        const loadRunsMock = vi.fn().mockResolvedValue(undefined)
        const ctx: Record<string, unknown> = {
            canDeleteImport: () => true,
            deleting_import_id: null,
            run_action_message: '',
            run_action_error: '',
            selected_restore_target_id: 33,
            run_details: { 33: { run: { id: 33 } }, 22: { run: { id: 22 } } },
            expanded_change_groups: { '33:inserted': true, '22:deleted': true },
            loadRuns: loadRunsMock,
        }

        await (Import116 as any).methods.deleteImport.call(ctx, { id: 33 })

        expect(ctx.deleting_import_id).toBeNull()
        expect(ctx.run_action_message).toBe('Import #33 wurde gelöscht.')
        expect(ctx.selected_restore_target_id).toBeNull()
        expect((ctx.run_details as Record<number, unknown>)[33]).toBeUndefined()
        expect((ctx.run_details as Record<number, unknown>)[22]).toBeTruthy()
        expect(ctx.expanded_change_groups).toEqual({ '22:deleted': true })
        expect(loadRunsMock).toHaveBeenCalledTimes(1)
    })

    it('resetRecentRuns keeps selection and details when reset fails', async () => {
        axiosMock.post.mockRejectedValue({
            response: {
                status: 422,
                data: { message: 'Import konnte nicht zurückgesetzt werden.' },
            },
        })

        const loadRunsMock = vi.fn().mockResolvedValue(undefined)
        const ctx: Record<string, unknown> = {
            canRestoreSelection: true,
            is_resetting_runs: false,
            run_action_message: 'old message',
            run_action_error: '',
            selected_restore_target_id: 77,
            run_details: { 77: { changes: {} } },
            expanded_run_ids: { 77: true },
            expanded_change_groups: { '77:inserted': true },
            loadRuns: loadRunsMock,
        }

        await (Import116 as any).methods.resetRecentRuns.call(ctx)

        expect(ctx.is_resetting_runs).toBe(false)
        expect(ctx.run_action_error).toBe('Import konnte nicht zurückgesetzt werden.')
        expect(ctx.selected_restore_target_id).toBe(77)
        expect(ctx.run_details).toEqual({ 77: { changes: {} } })
        expect(loadRunsMock).not.toHaveBeenCalled()
    })

    it('deleteImport keeps local state and sets error when delete fails', async () => {
        axiosMock.delete.mockRejectedValue({
            response: {
                status: 409,
                data: { message: 'Import konnte nicht gelöscht werden.' },
            },
        })

        const loadRunsMock = vi.fn().mockResolvedValue(undefined)
        const ctx: Record<string, unknown> = {
            canDeleteImport: () => true,
            deleting_import_id: null,
            run_action_message: '',
            run_action_error: '',
            selected_restore_target_id: 33,
            run_details: { 33: { run: { id: 33 } }, 22: { run: { id: 22 } } },
            expanded_change_groups: { '33:inserted': true, '22:deleted': true },
            loadRuns: loadRunsMock,
        }

        await (Import116 as any).methods.deleteImport.call(ctx, { id: 33 })

        expect(ctx.deleting_import_id).toBeNull()
        expect(ctx.run_action_error).toBe('Import konnte nicht gelöscht werden.')
        expect(ctx.selected_restore_target_id).toBe(33)
        expect(ctx.run_details).toEqual({ 33: { run: { id: 33 } }, 22: { run: { id: 22 } } })
        expect(ctx.expanded_change_groups).toEqual({ '33:inserted': true, '22:deleted': true })
        expect(loadRunsMock).not.toHaveBeenCalled()
    })
})
