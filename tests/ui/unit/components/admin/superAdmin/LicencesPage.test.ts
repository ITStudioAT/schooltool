import { describe, expect, it } from 'vitest'
import Licences from '@/pages/admin/superAdmin/components/Licences.vue'

describe('Super admin licences overview', () => {
    it('opens create/edit licence dialog based on action', () => {
        expect((Licences as any).computed.licenceDialogOpen.get.call({ action: 'create_licence' })).toBe(true)
        expect((Licences as any).computed.licenceDialogOpen.get.call({ action: 'edit_licence' })).toBe(true)
        expect((Licences as any).computed.licenceDialogOpen.get.call({ action: '' })).toBe(false)

        const ctx = { action: 'create_licence' }
        ;(Licences as any).computed.licenceDialogOpen.set.call(ctx, false)
        expect(ctx.action).toBe('')
    })

    it('computes licence dialog title from action', () => {
        expect((Licences as any).computed.licenceDialogTitle.call({ action: 'create_licence' })).toBe('Neue Lizenz')
        expect((Licences as any).computed.licenceDialogTitle.call({ action: 'edit_licence' })).toBe('Lizenz ändern')
        expect((Licences as any).computed.licenceDialogTitle.call({ action: '' })).toBe('Lizenz')
    })

    it('opens licence model dialog based on action', () => {
        expect((Licences as any).computed.licenceModelDialogOpen.get.call({ action: 'licence_model' })).toBe(true)
        expect((Licences as any).computed.licenceModelDialogOpen.get.call({ action: '' })).toBe(false)

        const ctx = { action: 'licence_model' }
        ;(Licences as any).computed.licenceModelDialogOpen.set.call(ctx, false)
        expect(ctx.action).toBe('')
        expect((Licences as any).computed.licenceModelDialogMaxWidth.call({})).toBe(1100)
    })

    it('opens delete dialog based on action', () => {
        expect((Licences as any).computed.licenceDeleteDialogOpen.get.call({ action: 'delete_licence' })).toBe(true)
        expect((Licences as any).computed.licenceDeleteDialogOpen.get.call({ action: '' })).toBe(false)

        const ctx = { action: 'delete_licence' }
        ;(Licences as any).computed.licenceDeleteDialogOpen.set.call(ctx, false)
        expect(ctx.action).toBe('')
    })

    it('computes total count from meta with fallback to local list length', () => {
        const withMeta = {
            meta: { total: 12 },
            licences: [{ id: 1 }, { id: 2 }],
        }
        const withoutMeta = {
            meta: {},
            licences: [{ id: 1 }, { id: 2 }, { id: 3 }],
        }

        expect((Licences as any).computed.totalLicencesCount.call(withMeta)).toBe(12)
        expect((Licences as any).computed.totalLicencesCount.call(withoutMeta)).toBe(3)
    })

    it('builds safe pagination meta defaults', () => {
        const ctx = {
            meta: {
                from: 2,
                to: 5,
                total: 9,
                current_page: 2,
                last_page: 4,
            },
            licences: [{ id: 1 }],
        }

        expect((Licences as any).computed.safeMeta.call(ctx)).toEqual({
            from: 2,
            to: 5,
            total: 9,
            current_page: 2,
            last_page: 4,
        })

        expect(
            (Licences as any).computed.safeMeta.call({
                meta: {},
                licences: [{ id: 10 }, { id: 20 }],
            })
        ).toEqual({
            from: 0,
            to: 0,
            total: 2,
            current_page: 1,
            last_page: 1,
        })
    })

    it('supports select all and unselect all actions', () => {
        const ctx = {
            licences: [{ id: 11 }, { id: 12 }, { id: 13 }],
            selected_licences: [],
        }

        ;(Licences as any).methods.selectAll.call(ctx)
        expect(ctx.selected_licences).toEqual([11, 12, 13])
        expect((Licences as any).methods.isSelectedLicence.call(ctx, 12)).toBe(true)

        ;(Licences as any).methods.unselectAll.call(ctx)
        expect(ctx.selected_licences).toEqual([])
        expect((Licences as any).methods.isSelectedLicence.call(ctx, 12)).toBe(false)
    })
})
