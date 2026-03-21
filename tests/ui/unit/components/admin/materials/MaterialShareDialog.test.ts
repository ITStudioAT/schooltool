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

    it('exposes the master group options for the group share panel', () => {
        const ctx = createDialogCtx()

        expect(ctx.groupMasterOptions).toEqual([
            { value: 'school', label: 'Schulgruppen' },
            { value: 'materials', label: 'Materialiengruppen' },
            { value: 'own', label: 'Eigene Gruppen' },
        ])
    })

    it('stages everyone and external-user share payloads', async () => {
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

        axiosMock.get.mockResolvedValueOnce({
            data: {
                data: {
                    exists: true,
                    label: 'Extern Eva',
                    school_label: 'Partnerschule',
                },
            },
        })

        await ctx.stageExternalUserShare()
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/materials/shares/lookup-external-user', {
            params: {
                target_school_id: 5,
                user_email: 'extern@test.local',
            },
        })
        expect(ctx.externalUserLookupError).toBe('')
        expect(ctx.pendingShareTarget).toMatchObject({
            typeLabel: 'Person (andere Schule)',
            label: 'Extern Eva',
            metaLabel: 'Partnerschule',
            payload: { target_type: 'user', target_school_id: 5, user_email: 'extern@test.local' },
            busyKey: 'external-user:5:extern@test.local',
        })
    })

    it('stageExternalUserShare shows error when external user does not exist', async () => {
        const ctx = createDialogCtx({
            externalSchools: [{ id: 5, label: 'Partnerschule' }],
            selectedExternalSchoolId: 5,
            externalUserEmail: 'missing@test.local',
            pendingShareTarget: { keep: true },
        })

        axiosMock.get.mockResolvedValueOnce({
            data: {
                data: {
                    exists: false,
                },
            },
        })

        await ctx.stageExternalUserShare()

        expect(ctx.pendingShareTarget).toBeNull()
        expect(ctx.externalUserLookupError).toBe('Benutzer wurde nicht gefunden.')
    })

    it('stageExternalUserShare shows backend validation message for invalid email', async () => {
        const ctx = createDialogCtx({
            externalSchools: [{ id: 5, label: 'Partnerschule' }],
            selectedExternalSchoolId: 5,
            externalUserEmail: 'invalid-email',
        })

        axiosMock.get.mockRejectedValueOnce({
            response: {
                data: {
                    errors: {
                        user_email: ['Bitte eine gültige E-Mail-Adresse eingeben.'],
                    },
                },
            },
        })

        await ctx.stageExternalUserShare()

        expect(ctx.pendingShareTarget).toBeNull()
        expect(ctx.externalUserLookupError).toBe('Bitte eine gültige E-Mail-Adresse eingeben.')
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

    it('allows full access mode for all scope types', () => {
        const unitCtx = createDialogCtx({
            target: { level: 'unit', id: 9, label: 'Kapitel A' },
            shareMode: 'full_access',
        })

        expect(unitCtx.availableShareModes.map((entry: any) => entry.value)).toEqual(['full_access', 'read_write', 'read_append', 'read_only'])
        unitCtx.ensureShareModeForScope('unit')
        expect(unitCtx.shareMode).toBe('full_access')

        const subjectCtx = createDialogCtx({
            target: { level: 'subject', id: 12, label: 'Mathematik' },
        })
        expect(subjectCtx.availableShareModes.map((entry: any) => entry.value)).toEqual(['full_access', 'read_write', 'read_append', 'read_only'])
    })

    it('requestStoreTarget keeps full access for topic scope', async () => {
        axiosMock.post.mockResolvedValue({ data: {} })

        const ctx = createDialogCtx({
            shareMode: 'full_access',
            target: { level: 'topic', id: 22, label: 'Algebra' },
        })

        const ok = await ctx.requestStoreTarget({ target_type: 'everyone', audience_scope: 'school' }, 'everyone:school')
        expect(ok).toBe(true)
        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/shares/targets', {
            scope_type: 'topic',
            scope_id: 22,
            permission: 'full_access',
            target_type: 'everyone',
            audience_scope: 'school',
        })
        expect(ctx.shareMode).toBe('full_access')
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

    it('watch modelValue initializes dialog state on open', () => {
        const initializeDialogState = vi.fn()
        const ctx = createDialogCtx({ initializeDialogState })
        const watcher = (MaterialShareDialog as any).watch.modelValue

        watcher.call(ctx, false)
        expect(initializeDialogState).not.toHaveBeenCalled()

        watcher.call(ctx, true)
        expect(initializeDialogState).toHaveBeenCalledTimes(1)
    })

    it('initializeDialogState resets state and loads schools + groups', () => {
        const loadExternalSchools = vi.fn()
        const loadGroups = vi.fn()
        const ctx = createDialogCtx({
            loadExternalSchools,
            loadGroups,
            shareTargetPanel: 4,
            pendingShareTarget: { foo: 'bar' },
            shareMode: 'full_access',
            lastActionError: 'x',
            peopleSearchError: 'y',
            peopleSearchResults: [{ id: 1 }],
            externalSchoolsError: 'z',
            selectedExternalSchoolId: 8,
            externalUserEmail: 'a@b.c',
        })

        ctx.initializeDialogState()

        expect(ctx.shareTargetPanel).toBe(0)
        expect(ctx.pendingShareTarget).toBeNull()
        expect(ctx.shareMode).toBe('read_only')
        expect(ctx.lastActionError).toBe('')
        expect(ctx.peopleSearchError).toBe('')
        expect(ctx.peopleSearchResults).toEqual([])
        expect(ctx.externalSchoolsError).toBe('')
        expect(ctx.selectedExternalSchoolId).toBeNull()
        expect(ctx.externalUserEmail).toBe('')
        expect(loadExternalSchools).toHaveBeenCalledTimes(1)
        expect(loadGroups).toHaveBeenNthCalledWith(1, 'materials')
        expect(loadGroups).toHaveBeenNthCalledWith(2, 'own')
    })

    it('scopePayload returns normalized payload for all and scoped targets', () => {
        const allCtx = createDialogCtx({ target: { level: 'all', id: null, label: 'Alles' } })
        expect(allCtx.scopePayload()).toEqual({ scope_type: 'all', scope_id: null })

        const subjectCtx = createDialogCtx({ target: { level: 'subject', id: 12, label: 'Mathe' } })
        expect(subjectCtx.scopePayload()).toEqual({ scope_type: 'subject', scope_id: 12 })

        const invalidCtx = createDialogCtx({ target: { level: 'subject', id: null, label: 'Mathe' } })
        expect(invalidCtx.scopePayload()).toBeNull()
    })

    it('stages user and group targets', () => {
        const ctx = createDialogCtx({
            selectedMaterialsGroupId: 7,
            materialsGroups: [{ id: 7, label: 'Materialgruppe A' }],
            selectedOwnGroupId: 9,
            ownGroups: [{ id: 9, label: 'Eigene Gruppe B' }],
        })

        ctx.stageUserShare({ id: 13, label: 'Anna Muster', email: 'anna@test.local' })
        expect(ctx.pendingShareTarget).toMatchObject({
            typeLabel: 'Person',
            label: 'Anna Muster',
            metaLabel: 'anna@test.local',
            payload: { target_type: 'user', user_id: 13 },
            busyKey: 'user:13',
        })

        ctx.stageSelectedGroup('materials')
        expect(ctx.pendingShareTarget).toMatchObject({
            typeLabel: 'Materialiengruppe',
            label: 'Materialgruppe A',
            payload: { target_type: 'group', user_group_id: 7 },
            busyKey: 'group:7',
        })

        ctx.stageSelectedGroup('own')
        expect(ctx.pendingShareTarget).toMatchObject({
            typeLabel: 'Eigene Gruppe',
            label: 'Eigene Gruppe B',
            payload: { target_type: 'group', user_group_id: 9 },
            busyKey: 'group:9',
        })
    })

    it('confirmPendingShareTarget clears pending target on success and keeps it on failure', async () => {
        const ctx = createDialogCtx({
            pendingShareTarget: {
                payload: { target_type: 'everyone', audience_scope: 'school' },
                busyKey: 'everyone:school',
            },
            requestStoreTarget: vi.fn().mockResolvedValueOnce(true).mockResolvedValueOnce(false),
            clearPendingShareTarget: vi.fn(),
        })

        await ctx.confirmPendingShareTarget()
        expect(ctx.requestStoreTarget).toHaveBeenNthCalledWith(1, { target_type: 'everyone', audience_scope: 'school' }, 'everyone:school')
        expect(ctx.clearPendingShareTarget).toHaveBeenCalledTimes(1)

        await ctx.confirmPendingShareTarget()
        expect(ctx.clearPendingShareTarget).toHaveBeenCalledTimes(1)
    })

    it('loadExternalSchools stores schools and error states', async () => {
        const ctx = createDialogCtx()

        axiosMock.get.mockResolvedValueOnce({
            data: { data: [{ id: 2, label: 'Schule B' }] },
        })
        await ctx.loadExternalSchools()

        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/materials/shares/lookup-schools')
        expect(ctx.externalSchools).toEqual([{ id: 2, label: 'Schule B' }])
        expect(ctx.externalSchoolsLoaded).toBe(true)
        expect(ctx.externalSchoolsError).toBe('')
        expect(ctx.externalSchoolsLoading).toBe(false)

        axiosMock.get.mockRejectedValueOnce({
            response: { data: { message: 'Schulen kaputt' } },
        })
        await ctx.loadExternalSchools()

        expect(ctx.externalSchools).toEqual([])
        expect(ctx.externalSchoolsError).toBe('Schulen kaputt')
        expect(ctx.externalSchoolsLoading).toBe(false)
    })

    it('loadGroups stores materials and own groups and handles errors', async () => {
        const ctx = createDialogCtx()

        axiosMock.get.mockResolvedValueOnce({ data: { data: [{ id: 3, label: 'MG' }] } })
        await ctx.loadGroups('materials')
        expect(ctx.materialsGroups).toEqual([{ id: 3, label: 'MG' }])
        expect(ctx.materialsGroupsLoaded).toBe(true)
        expect(ctx.materialsGroupsError).toBe('')

        axiosMock.get.mockResolvedValueOnce({ data: { data: [{ id: 4, label: 'OG' }] } })
        await ctx.loadGroups('own')
        expect(ctx.ownGroups).toEqual([{ id: 4, label: 'OG' }])
        expect(ctx.ownGroupsLoaded).toBe(true)
        expect(ctx.ownGroupsError).toBe('')

        axiosMock.get.mockRejectedValueOnce({ response: { data: { message: 'Gruppenfehler' } } })
        await ctx.loadGroups('materials')
        expect(ctx.materialsGroups).toEqual([])
        expect(ctx.materialsGroupsError).toBe('Gruppenfehler')
        expect(ctx.materialsGroupsLoading).toBe(false)
    })

    it('lazy menu open handlers only load when needed', () => {
        const ctx = createDialogCtx({
            loadExternalSchools: vi.fn(),
            loadGroups: vi.fn(),
            externalSchoolsLoaded: false,
            materialsGroupsLoaded: false,
            ownGroupsLoaded: true,
        })

        ctx.onExternalSchoolsMenuOpen(false)
        ctx.onExternalSchoolsMenuOpen(true)
        expect(ctx.loadExternalSchools).toHaveBeenCalledTimes(1)

        ctx.onGroupsMenuOpen('materials', false)
        ctx.onGroupsMenuOpen('materials', true)
        ctx.onGroupsMenuOpen('own', true)
        expect(ctx.loadGroups).toHaveBeenCalledTimes(1)
        expect(ctx.loadGroups).toHaveBeenCalledWith('materials')
    })
})
