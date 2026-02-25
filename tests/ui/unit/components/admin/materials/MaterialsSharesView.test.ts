import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import MaterialsSharesView from '@/pages/admin/materials/components/views/MaterialsSharesView.vue'

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
}))

function createViewCtx(overrides: Record<string, unknown> = {}) {
    const component = MaterialsSharesView as any
    const ctx: any = {
        ...(component.data?.call({}) ?? {}),
        ...(component.methods || {}),
        ...overrides,
    }

    for (const [key, getter] of Object.entries(component.computed || {})) {
        Object.defineProperty(ctx, key, {
            configurable: true,
            enumerable: true,
            get: () => (getter as Function).call(ctx),
        })
    }

    return ctx
}

describe('MaterialsSharesView', () => {
    const axiosMock = axios as any

    beforeEach(() => {
        axiosMock.get.mockReset()
        axiosMock.patch.mockReset()
        axiosMock.post.mockReset()
        axiosMock.delete.mockReset()
    })

    it('computes workspace indicator color using highest permission rank', () => {
        const ctx = createViewCtx({
            rows: [
                {
                    id: 1,
                    scope_type: 'all',
                    targets: [
                        { permission: 'read_only' },
                        { permission: 'read_write' },
                    ],
                },
                {
                    id: 2,
                    scope_type: 'subject',
                    targets: [{ permission: 'full_access' }],
                },
            ],
        })

        expect(ctx.workspaceShareIndicatorColor).toBe('warning')

        ctx.rows = [
            {
                id: 3,
                scope_type: 'all',
                targets: [{ permission: 'full_access' }],
            },
        ]
        expect(ctx.workspaceShareIndicatorColor).toBe('error')
    })

    it('formats target labels for cross-school users', () => {
        const ctx = createViewCtx()

        expect(ctx.targetChipLabel({
            target_type: 'user',
            label: 'Max Mustermann',
            meta: { is_other_school: true, school_label: 'HTL Graz' },
        })).toBe('Max Mustermann · HTL Graz')

        expect(ctx.targetChipLabel({
            target_type: 'group',
            label: 'Materialteam',
            meta: {},
        })).toBe('Materialteam')
    })

    it('loadShares stores rows and needsMigration meta', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [{ id: 1, scope_type: 'all', targets: [] }],
                meta: { needs_migration: true },
            },
        })

        const ctx = createViewCtx()
        await ctx.loadShares()

        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/materials/shares')
        expect(ctx.rows).toHaveLength(1)
        expect(ctx.needsMigration).toBe(true)
        expect(ctx.errorMessage).toBe('')
        expect(ctx.isLoading).toBe(false)
    })

    it('updateRuleActive patches rule and updates row with returned payload', async () => {
        axiosMock.patch.mockResolvedValue({
            data: {
                rule: { id: 7, is_active: false, scope_type: 'all', targets: [] },
            },
        })

        const ctx = createViewCtx({
            rows: [
                { id: 7, is_active: true, scope_type: 'all', targets: [] },
                { id: 8, is_active: true, scope_type: 'subject', targets: [] },
            ],
        })

        await ctx.updateRuleActive({ id: 7 }, false)

        expect(axiosMock.patch).toHaveBeenCalledWith('/api/admin/materials/shares/7', { is_active: false })
        expect(ctx.rows.find((row: any) => row.id === 7)?.is_active).toBe(false)
        expect(ctx.statusBusyIds).toEqual([])
        expect(ctx.errorMessage).toBe('')
    })

    it('updateRuleActive stores error message on patch failure', async () => {
        axiosMock.patch.mockRejectedValue({
            response: { data: { message: 'Patch fehlgeschlagen.' } },
        })

        const ctx = createViewCtx({
            rows: [{ id: 4, is_active: true, scope_type: 'all', targets: [] }],
        })

        await ctx.updateRuleActive({ id: 4 }, false)

        expect(ctx.errorMessage).toBe('Patch fehlgeschlagen.')
        expect(ctx.statusBusyIds).toEqual([])
    })

    it('opens workspace share dialog and triggers assignment loading', () => {
        const ctx = createViewCtx({
            loadWorkspaceShareAssignments: vi.fn(),
            workspaceShareAssignments: [{ id: 1 }],
            workspaceShareAssignmentsError: 'x',
            workspaceShareDialogOpen: false,
        })

        ctx.openWorkspaceShareDialog()

        expect(ctx.workspaceShareDialogOpen).toBe(true)
        expect(ctx.workspaceShareAssignments).toEqual([])
        expect(ctx.workspaceShareAssignmentsError).toBe('')
        expect(ctx.loadWorkspaceShareAssignments).toHaveBeenCalledTimes(1)
    })

    it('handleWorkspaceSharesChanged refreshes overview and workspace assignments', () => {
        const ctx = createViewCtx({
            loadWorkspaceShareAssignments: vi.fn(),
            loadShares: vi.fn(),
        })

        ctx.handleWorkspaceSharesChanged()

        expect(ctx.loadWorkspaceShareAssignments).toHaveBeenCalledTimes(1)
        expect(ctx.loadShares).toHaveBeenCalledTimes(1)
    })
})
