import { describe, expect, it } from 'vitest'
import Settings from '@/pages/admin/teaching/settings/Settings.vue'

describe('Teaching settings page', () => {
    it('computes own-holidays permission from roles', () => {
        const ctx = {
            config: {
                roles: ['teacher'],
            },
        }

        expect((Settings as any).computed.canManageOwnHolidays.call(ctx)).toBe(true)
    })

    it('builds available panels based on role permissions', () => {
        const ctx = {
            canManageOwnHolidays: true,
        }
        const panelsWithPermission = (Settings as any).computed.availablePanels.call(ctx)

        expect(panelsWithPermission.map((panel: { id: string }) => panel.id)).toEqual([
            'basic',
            'behaviour',
            'notifications',
            'schemas',
            'my_holidays',
        ])
    })

    it('activates only allowed primary panels', () => {
        const ctx = {
            active_panel: 'behaviour',
            canManageOwnHolidays: false,
            availablePanels: [
                { id: 'basic' },
                { id: 'behaviour' },
                { id: 'notifications' },
                { id: 'schemas' },
            ],
        }

        ;(Settings as any).methods.activatePanel.call(ctx, 'schemas')
        expect(ctx.active_panel).toBe('schemas')

        ;(Settings as any).methods.activatePanel.call(ctx, 'my_holidays')
        expect(ctx.active_panel).toBe('schemas')
    })

    it('switches schema sub-panels in exclusive mode', () => {
        const ctx = {
            active_schema_panel: 'works',
        }

        ;(Settings as any).methods.activateSchemaPanel.call(ctx, 'grading')
        expect((Settings as any).methods.isSchemaPanelActive.call(ctx, 'grading')).toBe(true)
        expect((Settings as any).methods.isSchemaPanelActive.call(ctx, 'works')).toBe(false)
    })

    it('falls back to behaviour when own-holidays permission is removed', () => {
        const ctx = {
            active_panel: 'my_holidays',
        }

        ;(Settings as any).watch.canManageOwnHolidays.call(ctx, false)

        expect(ctx.active_panel).toBe('behaviour')
    })
})