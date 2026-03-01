import { describe, expect, it } from 'vitest'
import Admin from '@/pages/admin/teaching/admin/Admin.vue'

describe('Teaching admin page', () => {
    it('allows holidays management for admin roles', () => {
        const ctx = {
            config: {
                roles: ['teacher', 'teaching_admin'],
            },
        }

        const result = (Admin as any).computed.canManageSchoolHolidays.call(ctx)
        expect(result).toBe(true)
    })

    it('computes visible panel count for single-select mode', () => {
        const importPanel = {
            active_panel: 'import',
            canManageSchoolHolidays: true,
        }
        const holidaysPanel = {
            active_panel: 'holidays',
            canManageSchoolHolidays: true,
        }
        const blockedHolidaysPanel = {
            active_panel: 'holidays',
            canManageSchoolHolidays: false,
        }

        expect((Admin as any).computed.visiblePanelsCount.call(importPanel)).toBe(1)
        expect((Admin as any).computed.visiblePanelsCount.call(holidaysPanel)).toBe(1)
        expect((Admin as any).computed.visiblePanelsCount.call(blockedHolidaysPanel)).toBe(0)
    })

    it('activates only allowed panels through submenu actions', () => {
        const ctx = {
            active_panel: 'import',
            canManageSchoolHolidays: true,
            availablePanels: [
                { id: 'import' },
                { id: 'holidays' },
            ],
        }

        ;(Admin as any).methods.activatePanel.call(ctx, 'holidays')
        expect(ctx.active_panel).toBe('holidays')

        ;(Admin as any).methods.activatePanel.call(ctx, 'missing')
        expect(ctx.active_panel).toBe('holidays')
    })
})
