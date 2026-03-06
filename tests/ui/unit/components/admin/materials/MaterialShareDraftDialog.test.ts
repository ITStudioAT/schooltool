import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import MaterialShareDraftDialog from '@/pages/admin/materials/components/overview/dialogs/MaterialShareDraftDialog.vue'

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
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
    })

    it('initializes dialog state and lazily loads groups + schools', () => {
        const loadGroupOptions = vi.fn()
        const loadExternalSchools = vi.fn()
        const ctx = createDialogCtx({
            loadGroupOptions,
            loadExternalSchools,
            groupsLoaded: false,
            externalSchoolsLoaded: false,
            recipientMode: 'group',
            shareMode: 'full_access',
            selectedGroupId: 3,
            selectedExternalSchoolId: 5,
            externalUserEmail: 'x@y.z',
        })

        ctx.initializeDialogState()

        expect(ctx.recipientMode).toBe('same_school_person')
        expect(ctx.shareMode).toBe('read_only')
        expect(ctx.selectedGroupId).toBeNull()
        expect(ctx.selectedExternalSchoolId).toBeNull()
        expect(ctx.externalUserEmail).toBe('')
        expect(loadGroupOptions).toHaveBeenCalledTimes(1)
        expect(loadExternalSchools).toHaveBeenCalledTimes(1)
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

    it('emits selected recipient + permission without saving', () => {
        const ctx = createDialogCtx({
            recipientMode: 'group',
            selectedGroupId: 4,
            groupOptions: [{ id: 4, label: 'Fachgruppe Mathe', type_label: 'Materialiengruppe' }],
            shareMode: 'full_access',
        })

        ctx.applySelection()

        expect(ctx.$emit).toHaveBeenCalledWith('dummy-selected', {
            target: {
                level: 'subject',
                id: 7,
                label: 'Mathematik',
                parentLabel: '',
            },
            recipient: {
                type: 'group',
                typeLabel: 'Gruppe',
                label: 'Fachgruppe Mathe',
                metaLabel: 'Materialiengruppe',
                payload: {
                    target_type: 'group',
                    user_group_id: 4,
                },
            },
            permission: 'full_access',
            permission_label: 'VOLLZUGRIFF',
        })
        expect(ctx.$emit).toHaveBeenCalledWith('update:modelValue', false)
    })
})
