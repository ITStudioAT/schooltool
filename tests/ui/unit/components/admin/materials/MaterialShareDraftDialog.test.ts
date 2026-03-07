import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import MaterialShareDraftDialog from '@/pages/admin/materials/components/overview/dialogs/MaterialShareDraftDialog.vue'

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
    },
}))

function createDialogCtx(overrides: Record<string, unknown> = {}) {
    const component = MaterialShareDraftDialog as any
    const ctx: any = {
        ...(component.data?.call({}) ?? {}),
        ...(component.methods || {}),
        modelValue: true,
        target: { level: 'subject', id: 7, label: 'Mathematik', parentLabel: '' },
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

describe('MaterialShareDraftDialog', () => {
    const axiosMock = axios as any

    beforeEach(() => {
        axiosMock.get.mockReset()
        axiosMock.post.mockReset()
    })

    it('initializes dialog state and lazily loads schools', () => {
        const loadExternalSchools = vi.fn()
        const ctx = createDialogCtx({
            loadExternalSchools,
            externalSchoolsLoaded: false,
            recipientMode: 'group',
            shareMode: 'full_access',
            selectedExternalSchoolId: 5,
            externalUserEmail: 'x@y.z',
        })

        ctx.initializeDialogState()

        expect(ctx.recipientMode).toBe('same_school_person')
        expect(ctx.shareMode).toBe('read_only')
        expect(ctx.selectedExternalSchoolId).toBeNull()
        expect(ctx.externalUserEmail).toBe('')
        expect(loadExternalSchools).toHaveBeenCalledTimes(1)
    })

    it('exposes the staged group master and category options', () => {
        const ctx = createDialogCtx()

        expect(ctx.groupMasterOptions).toEqual([
            { value: 'school', label: 'Schulgruppen' },
            { value: 'materials', label: 'Materialgruppen' },
            { value: 'own', label: 'Eigene Gruppen' },
        ])
        expect(ctx.schoolGroupCategoryOptions).toEqual([
            { value: 'classes', label: 'Klassen' },
            { value: 'teachers', label: 'Lehrer' },
            { value: 'parents', label: 'Eltern' },
            { value: 'own', label: 'Eigene Gruppen' },
        ])
        expect(ctx.ownGroupCategoryOptions).toEqual([
            { value: 'course_groups', label: 'Kursgruppen' },
            { value: 'course_parent_groups', label: 'Eltern Kursgruppen' },
            { value: 'own', label: 'Eigene Gruppen' },
        ])
    })

    it('loads material groups when selecting the material group master', async () => {
        const ctx = createDialogCtx()

        axiosMock.get.mockResolvedValueOnce({
            data: {
                data: [
                    { id: 7, label: 'Materialgruppe A' },
                    { id: 9, name: 'Materialgruppe B' },
                ],
            },
        })

        ctx.selectGroupMaster('school')
        expect(ctx.selectedGroupMaster).toBe('school')

        await ctx.selectGroupMaster('materials')

        expect(ctx.selectedGroupMaster).toBe('materials')
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/materials/shares/lookup-groups', {
            params: { type: 'materials' },
        })
        expect(ctx.materialsGroupOptions).toEqual([
            { id: 7, label: 'Materialgruppe A', typeLabel: 'Materialgruppe' },
            { id: 9, label: 'Materialgruppe B', typeLabel: 'Materialgruppe' },
        ])
        expect(ctx.materialsGroupsLoaded).toBe(true)

        ctx.selectGroupMaster('own')
        expect(ctx.selectedGroupMaster).toBe('own')
    })

    it('loads school groups when selecting a school category', async () => {
        const ctx = createDialogCtx()

        axiosMock.get.mockResolvedValueOnce({
            data: {
                data: [
                    { id: 12, label: '1A' },
                    { id: 13, name: '1B' },
                ],
            },
        })

        await ctx.selectGroupMaster('school')
        await ctx.selectSchoolGroupCategory('classes')

        expect(ctx.selectedGroupMaster).toBe('school')
        expect(ctx.selectedSchoolGroupCategory).toBe('classes')
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/materials/shares/lookup-groups', {
            params: {
                type: 'school',
                category: 'classes',
            },
        })
        expect(ctx.schoolGroupOptions).toEqual([
            { id: 12, label: '1A', typeLabel: 'Gruppe' },
            { id: 13, label: '1B', typeLabel: 'Gruppe' },
        ])
    })

    it('treats a selected group as the active recipient', async () => {
        const ctx = createDialogCtx({
            recipientMode: 'group',
        })

        axiosMock.get.mockResolvedValueOnce({
            data: {
                data: [
                    { id: 12, label: '1A', type_label: 'Schulgruppe' },
                ],
            },
        })

        await ctx.selectGroupMaster('school')
        await ctx.selectSchoolGroupCategory('classes')
        ctx.selectGroupOption(ctx.schoolGroupOptions[0])

        expect(ctx.hasRecipientSelection).toBe(true)
        expect(ctx.selectedRecipient).toMatchObject({
            type: 'group',
            typeLabel: 'Schulgruppe',
            label: '1A',
            payload: {
                target_type: 'group',
                user_group_id: 12,
            },
        })
    })

    it('loads own groups when selecting an own-group category', async () => {
        const ctx = createDialogCtx()

        axiosMock.get.mockResolvedValueOnce({
            data: {
                data: [
                    { id: 21, label: 'Informatik 1' },
                    { id: 22, name: 'Mathematik 2' },
                ],
            },
        })

        await ctx.selectGroupMaster('own')
        await ctx.selectOwnGroupCategory('course_groups')

        expect(ctx.selectedGroupMaster).toBe('own')
        expect(ctx.selectedOwnGroupCategory).toBe('course_groups')
        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/materials/shares/lookup-groups', {
            params: {
                type: 'own',
                category: 'course_groups',
            },
        })
        expect(ctx.ownGroupOptions).toEqual([
            { id: 21, label: 'Informatik 1', typeLabel: 'Gruppe' },
            { id: 22, label: 'Mathematik 2', typeLabel: 'Gruppe' },
        ])
    })

    it('searches same-school users by query and maps short/email fields', async () => {
        const ctx = createDialogCtx({
            sameSchoolSearch: 'ANM',
        })

        axiosMock.get.mockResolvedValueOnce({
            data: {
                data: [
                    { id: 11, label: 'Muster Anna', short: 'ANM', email: 'anna@test.local' },
                ],
            },
        })

        await ctx.searchSameSchoolUsers()

        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/materials/shares/lookup-users', { params: { search: 'ANM' } })
        expect(ctx.sameSchoolSearchResults).toEqual([
            { id: 11, label: 'Muster Anna', short: 'ANM', email: 'anna@test.local' },
        ])
        expect(ctx.sameSchoolSearchError).toBe('')
    })

    it('checks external user email and sets recipient when user exists', async () => {
        const ctx = createDialogCtx({
            recipientMode: 'external_person',
            selectedExternalSchoolId: 9,
            externalSchools: [{ id: 9, label: 'CDGym' }],
            externalUserEmail: 'EXTERN@test.local',
        })

        axiosMock.get.mockResolvedValueOnce({
            data: {
                data: {
                    exists: true,
                    label: 'Extern Eva',
                    school_label: 'CDGym',
                    email: 'extern@test.local',
                },
            },
        })

        await ctx.checkExternalUser()

        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/materials/shares/lookup-external-user', {
            params: {
                target_school_id: 9,
                user_email: 'extern@test.local',
            },
        })
        expect(ctx.externalUserLookupError).toBe('')
        expect(ctx.selectedRecipient).toMatchObject({
            type: 'external_person',
            typeLabel: 'Person (andere Schule)',
            label: 'Extern Eva',
            metaLabel: 'CDGym',
            payload: {
                target_type: 'user',
                target_school_id: 9,
                user_email: 'extern@test.local',
            },
        })
    })

    it('stores the selected recipient and closes the dialog', async () => {
        const ctx = createDialogCtx({
            recipientMode: 'same_school_person',
            selectedSameSchoolUserId: 4,
            sameSchoolSearchResults: [{ id: 4, label: 'Fachgruppe Mathe', short: 'FGM', email: 'mathe@test.local' }],
            shareMode: 'full_access',
        })

        axiosMock.post.mockResolvedValueOnce({ data: { data: { id: 77 } } })

        await ctx.applySelection()

        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/shares/targets', {
            scope_type: 'subject',
            scope_id: 7,
            permission: 'full_access',
            target_type: 'user',
            user_id: 4,
        })
        expect(ctx.$emit).toHaveBeenCalledWith('shares-changed')
        expect(ctx.$emit).toHaveBeenCalledWith('update:modelValue', false)
    })

    it('stores the selected group recipient with group target type', async () => {
        const ctx = createDialogCtx({
            recipientMode: 'group',
            selectedGroupOption: {
                id: 18,
                label: 'Projektgruppe',
                typeLabel: 'Schulgruppe',
            },
        })

        axiosMock.post.mockResolvedValueOnce({ data: { data: { id: 78 } } })

        await ctx.applySelection()

        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/shares/targets', {
            scope_type: 'subject',
            scope_id: 7,
            permission: 'read_only',
            target_type: 'group',
            user_group_id: 18,
        })
    })
})
