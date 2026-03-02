import { describe, expect, it, vi } from 'vitest'
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

    it('newSchema clones standard schema and selects the newly created schema id', async () => {
        const randomId = 'schema-new-123'
        const randomUuidSpy = vi.spyOn(globalThis.crypto, 'randomUUID').mockReturnValue(randomId)

        const standardWorks = [{ short_name: 'MA', name: 'Mitarbeit' }]
        const standardGrading = { semester_count: 2, categories: [{ name: 'Mitarbeit', weight: 100 }] }
        const saveSettingsMock = vi.fn().mockResolvedValue(true)

        const ctx = {
            schemas: [
                {
                    id: 'schema-standard',
                    name: 'Standard',
                    works: standardWorks,
                    grading: standardGrading,
                },
            ],
            teachingStore: {
                saveSettings: saveSettingsMock,
            },
            selected_schema_id: 'schema-standard',
        }

        await (Settings as any).methods.newSchema.call(ctx)

        expect(saveSettingsMock).toHaveBeenCalledTimes(1)
        const payload = saveSettingsMock.mock.calls[0][0]
        expect(payload.teaching_schemas).toHaveLength(2)
        expect(payload.teaching_schemas[1]).toMatchObject({
            id: randomId,
            name: 'Neues Schema',
        })
        expect(ctx.selected_schema_id).toBe(randomId)

        standardWorks[0].name = 'Mutated'
        standardGrading.categories[0].name = 'Mutated'
        expect(payload.teaching_schemas[1].works[0].name).toBe('Mitarbeit')
        expect(payload.teaching_schemas[1].grading.categories[0].name).toBe('Mitarbeit')

        randomUuidSpy.mockRestore()
    })
})
