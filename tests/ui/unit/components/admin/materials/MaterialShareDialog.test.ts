import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import MaterialShareDialog from '@/pages/admin/materials/components/overview/dialogs/MaterialShareDialog.vue'

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
        patch: vi.fn(),
    },
}))

function createDialogCtx(overrides: Record<string, unknown> = {}) {
    const component = MaterialShareDialog as any
    const ctx: any = {
        ...(component.data?.call({}) ?? {}),
        ...(component.methods || {}),
        modelValue: true,
        target: { level: 'all', id: null, label: 'Workspace' },
        assignments: [],
        loading: false,
        error: '',
        $emit: vi.fn(),
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

describe('MaterialShareDialog', () => {
    const axiosMock = axios as any

    beforeEach(() => {
        axiosMock.get.mockReset()
        axiosMock.post.mockReset()
        axiosMock.delete.mockReset()
        axiosMock.patch.mockReset()
    })

    it('flattens assigned targets and resolves external user assignment by school+email', () => {
        const ctx = createDialogCtx({
            assignments: [
                {
                    id: 11,
                    targets: [
                        { id: 101, target_type: 'everyone', audience_scope: 'school' },
                        {
                            id: 102,
                            target_type: 'user',
                            user_id: 55,
                            meta: { school_id: 99, email: 'remote@example.test', is_other_school: true },
                        },
                    ],
                },
            ],
            selectedExternalSchoolId: 99,
            externalUserEmail: 'REMOTE@example.test',
        })

        expect(ctx.allAssignedTargets).toHaveLength(2)
        expect(ctx.allAssignedTargets[1]._rule_id).toBe(11)
        expect(ctx.assignedExternalUserTarget?.id).toBe(102)
    })

    it('stages everyone and external-user share payloads', () => {
        const ctx = createDialogCtx({
            shareEveryoneScope: 'global',
            externalSchools: [{ id: 5, label: 'Partnerschule' }],
            selectedExternalSchoolId: 5,
            externalUserEmail: 'extern@test.local',
        })

        ctx.stageEveryoneShare()
        expect(ctx.pendingShareTarget).toMatchObject({
            typeLabel: 'Jeder',
            payload: { target_type: 'everyone', audience_scope: 'global' },
            busyKey: 'everyone:global',
        })

        ctx.stageExternalUserShare()
        expect(ctx.pendingShareTarget).toMatchObject({
            typeLabel: 'Person (andere Schule)',
            label: 'extern@test.local',
            metaLabel: 'Partnerschule',
            payload: { target_type: 'user', target_school_id: 5, user_email: 'extern@test.local' },
            busyKey: 'external-user:5:extern@test.local',
        })
    })

    it('requestStoreTarget posts payload and emits reload + changed on success', async () => {
        axiosMock.post.mockResolvedValue({ data: {} })

        const ctx = createDialogCtx({
            shareMode: 'read_write',
            target: { level: 'subject', id: 12, label: 'Mathematik' },
        })

        const ok = await ctx.requestStoreTarget({ target_type: 'everyone', audience_scope: 'school' }, 'everyone:school')

        expect(ok).toBe(true)
        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/shares/targets', {
            scope_type: 'subject',
            scope_id: 12,
            permission: 'read_write',
            target_type: 'everyone',
            audience_scope: 'school',
        })
        expect(ctx.$emit).toHaveBeenCalledWith('reload-assignments')
        expect(ctx.$emit).toHaveBeenCalledWith('shares-changed')
        expect(ctx.targetActionBusyKeys).toEqual([])
        expect(ctx.lastActionError).toBe('')
    })

    it('requestStoreTarget captures first field error message on failure', async () => {
        axiosMock.post.mockRejectedValue({
            response: {
                data: {
                    errors: {
                        user_email: ['Bitte E-Mail angeben.'],
                    },
                    message: 'Fallback',
                },
            },
        })

        const ctx = createDialogCtx({
            target: { level: 'all', id: null, label: 'Workspace' },
        })

        const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
        const ok = await ctx.requestStoreTarget({ target_type: 'user' }, 'x')
        consoleErrorSpy.mockRestore()

        expect(ok).toBe(false)
        expect(ctx.lastActionError).toBe('Bitte E-Mail angeben.')
        expect(ctx.targetActionBusyKeys).toEqual([])
    })

    it('removeAssignedTarget deletes target and emits refresh events', async () => {
        axiosMock.delete.mockResolvedValue({ data: {} })
        const ctx = createDialogCtx()

        await ctx.removeAssignedTarget({ id: 44 })

        expect(axiosMock.delete).toHaveBeenCalledWith('/api/admin/materials/shares/targets/44')
        expect(ctx.$emit).toHaveBeenCalledWith('reload-assignments')
        expect(ctx.$emit).toHaveBeenCalledWith('shares-changed')
        expect(ctx.targetActionBusyIds).toEqual([])
    })

    it('searchPeople loads results and stores error on failure', async () => {
        const ctx = createDialogCtx({ sharePersonSearch: 'Anna' })

        axiosMock.get.mockResolvedValueOnce({
            data: { data: [{ id: 1, label: 'Anna Muster', email: 'anna@test.local' }] },
        })

        await ctx.searchPeople()

        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/materials/shares/lookup-users', { params: { search: 'Anna' } })
        expect(ctx.peopleSearchResults).toHaveLength(1)
        expect(ctx.peopleSearchError).toBe('')

        axiosMock.get.mockRejectedValueOnce({
            response: { data: { message: 'Suche fehlgeschlagen.' } },
        })

        await ctx.searchPeople()

        expect(ctx.peopleSearchResults).toEqual([])
        expect(ctx.peopleSearchError).toBe('Suche fehlgeschlagen.')
    })
})
