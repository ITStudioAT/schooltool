import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import { render, screen, waitFor } from '@testing-library/vue'
import MaterialsSharesView from '@/pages/admin/materials/components/views/MaterialsSharesView.vue'

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
}))

const vuetifyStubs = {
    'v-card': { template: '<div><slot /></div>' },
    VCard: { template: '<div><slot /></div>' },
    'v-card-text': { template: '<div><slot /></div>' },
    VCardText: { template: '<div><slot /></div>' },
    'v-btn': { template: '<button><slot /></button>' },
    VBtn: { template: '<button><slot /></button>' },
    'v-alert': { template: '<div role="alert"><slot /></div>' },
    VAlert: { template: '<div role="alert"><slot /></div>' },
    'v-chip': { template: '<span><slot /></span>' },
    VChip: { template: '<span><slot /></span>' },
    'v-list': { template: '<div><slot /></div>' },
    VList: { template: '<div><slot /></div>' },
    'v-list-item': {
        emits: ['click'],
        template: '<div @click="$emit(\'click\', $event)"><slot /></div>',
    },
    VListItem: {
        emits: ['click'],
        template: '<div @click="$emit(\'click\', $event)"><slot /></div>',
    },
    'v-list-item-title': { template: '<div><slot /></div>' },
    VListItemTitle: { template: '<div><slot /></div>' },
    'v-list-subheader': { template: '<div><slot /></div>' },
    VListSubheader: { template: '<div><slot /></div>' },
    'v-menu': {
        template: '<div><slot name="activator" :props="{}" /><slot /></div>',
    },
    VMenu: {
        template: '<div><slot name="activator" :props="{}" /><slot /></div>',
    },
    'v-switch': { template: '<input type="checkbox" />' },
    VSwitch: { template: '<input type="checkbox" />' },
    'v-table': { template: '<table><slot /></table>' },
    VTable: { template: '<table><slot /></table>' },
    'v-icon': { template: '<span><slot /></span>' },
    VIcon: { template: '<span><slot /></span>' },
}

function renderMaterialsSharesView() {
    return render(MaterialsSharesView, {
        global: {
            stubs: {
                ...vuetifyStubs,
                MaterialsOverviewView: true,
                MaterialShareDialog: true,
            },
        },
    })
}

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

    it('loadShares stores error state on failure', async () => {
        axiosMock.get.mockRejectedValue({
            response: { data: { message: 'Freigaben laden fehlgeschlagen' } },
        })

        const ctx = createViewCtx({
            rows: [{ id: 1 }],
            needsMigration: true,
        })
        await ctx.loadShares()

        expect(ctx.rows).toEqual([])
        expect(ctx.needsMigration).toBe(false)
        expect(ctx.errorMessage).toBe('Freigaben laden fehlgeschlagen')
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

    it('updateRuleActive falls back to local row update when api returns no rule payload', async () => {
        axiosMock.patch.mockResolvedValue({
            data: { message: 'ok' },
        })

        const ctx = createViewCtx({
            rows: [{ id: 9, is_active: true, scope_type: 'all', targets: [] }],
        })

        await ctx.updateRuleActive({ id: 9 }, false)

        expect(ctx.rows[0].is_active).toBe(false)
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

    it('loadWorkspaceShareAssignments stores filtered all-scope assignments', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: [
                    { id: 1, scope_type: 'all' },
                    { id: 2, scope_type: 'subject' },
                ],
            },
        })

        const ctx = createViewCtx()
        await ctx.loadWorkspaceShareAssignments()

        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/materials/shares', { params: { scope_type: 'all' } })
        expect(ctx.workspaceShareAssignments).toEqual([{ id: 1, scope_type: 'all' }, { id: 2, scope_type: 'subject' }])
        expect(ctx.workspaceShareAssignmentsError).toBe('')
        expect(ctx.workspaceShareAssignmentsLoading).toBe(false)
    })

    it('loadWorkspaceShareAssignments stores error state on failure', async () => {
        axiosMock.get.mockRejectedValue({
            response: { data: { message: 'Workspace-Freigaben kaputt' } },
        })

        const ctx = createViewCtx()
        await ctx.loadWorkspaceShareAssignments()

        expect(ctx.workspaceShareAssignments).toEqual([])
        expect(ctx.workspaceShareAssignmentsError).toBe('Workspace-Freigaben kaputt')
        expect(ctx.workspaceShareAssignmentsLoading).toBe(false)
    })

    it('renders migration warning state', async () => {
        axiosMock.get.mockResolvedValue({
            data: { data: [], meta: { needs_migration: true } },
        })

        renderMaterialsSharesView()

        expect(await screen.findByText(/Freigaben-Tabellen sind noch nicht vorhanden/i)).toBeInTheDocument()
    })

    it('renders empty state when no shares exist and no migration is needed', async () => {
        axiosMock.get.mockResolvedValue({
            data: { data: [], meta: { needs_migration: false } },
        })

        renderMaterialsSharesView()

        await waitFor(() => {
            expect(screen.getByText(/Noch keine Freigaben vorhanden\./i)).toBeInTheDocument()
        })
    })

    it('renders error state when loadShares fails', async () => {
        axiosMock.get.mockRejectedValue({
            response: { data: { message: 'Freigaben konnten nicht geladen werden (Test).' } },
        })

        renderMaterialsSharesView()

        await waitFor(() => {
            expect(screen.getByText(/Freigaben konnten nicht geladen werden \(Test\)\./i)).toBeInTheDocument()
        })
    })
})
