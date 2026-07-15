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
        const schoolHoursPanel = {
            active_panel: 'school_hours',
            canManageSchoolHolidays: true,
        }
        const blockedHolidaysPanel = {
            active_panel: 'holidays',
            canManageSchoolHolidays: false,
        }

        expect((Admin as any).computed.visiblePanelsCount.call(importPanel)).toBe(1)
        expect((Admin as any).computed.visiblePanelsCount.call(holidaysPanel)).toBe(1)
        expect((Admin as any).computed.visiblePanelsCount.call(schoolHoursPanel)).toBe(1)
        expect((Admin as any).computed.visiblePanelsCount.call(blockedHolidaysPanel)).toBe(0)
    })

    it('activates only allowed panels through submenu actions', () => {
        const ctx = {
            active_panel: 'import',
            canManageSchoolHolidays: true,
            availablePanels: [
                { id: 'import' },
                { id: 'holidays' },
                { id: 'school_hours' },
            ],
        }

        ;(Admin as any).methods.activatePanel.call(ctx, 'holidays')
        expect(ctx.active_panel).toBe('holidays')

        ;(Admin as any).methods.activatePanel.call(ctx, 'school_hours')
        expect(ctx.active_panel).toBe('school_hours')

        ;(Admin as any).methods.activatePanel.call(ctx, 'missing')
        expect(ctx.active_panel).toBe('school_hours')
    })

    it('marks the school hours panel when the loaded list is empty', () => {
        const computed = (Admin as any).computed

        expect(computed.hasMissingSchoolHoursWarning.call({
            school_hours_loaded: false,
            school_hours: [],
        })).toBe(false)

        expect(computed.hasMissingSchoolHoursWarning.call({
            school_hours_loaded: true,
            school_hours: [],
        })).toBe(true)

        const panels = computed.availablePanels.call({
            canManageSchoolHolidays: true,
            hasMissingSchoolHoursWarning: true,
        })

        expect(panels.find((panel: { id: string }) => panel.id === 'school_hours')).toMatchObject({
            label: 'Schulstunden',
            needsAttention: true,
        })
    })

    it('does not mark the school hours panel when entries exist', () => {
        expect((Admin as any).computed.hasMissingSchoolHoursWarning.call({
            school_hours_loaded: true,
            school_hours: [{ id: 1 }],
        })).toBe(false)
    })
})
