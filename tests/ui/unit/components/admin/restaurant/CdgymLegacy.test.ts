import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import CdgymLegacy from '@/pages/admin/restaurant/components/CdgymLegacy.vue'

describe('CDGYM legacy restaurant page', () => {
    const axiosMock = {
        get: vi.fn(),
        post: vi.fn(),
    }

    beforeEach(() => {
        axiosMock.get.mockReset()
        axiosMock.post.mockReset()
        globalThis.axios = axiosMock as never
    })

    function mountComponent() {
        return mount(CdgymLegacy, {
            global: {
                stubs: {
                    'v-alert': { template: '<div><slot /></div>' },
                    'v-btn': {
                        props: ['href'],
                        template: '<button :data-href="href"><slot /></button>',
                    },
                    'v-card': { template: '<section><slot /></section>' },
                    'v-col': { template: '<div><slot /></div>' },
                    'v-divider': { template: '<hr />' },
                    'v-icon': { template: '<i />' },
                    'v-row': { template: '<div><slot /></div>' },
                },
            },
        })
    }

    it('shows the old online lunch information card with live stats', async () => {
        axiosMock.get.mockResolvedValue({
            data: {
                data: {
                    foods_count: 14,
                    menus_count: 8,
                    menu_plans_count: 96,
                    bookings_count: 321,
                    lunch_users_count: 240,
                    lunch_admins_count: 12,
                    update_preview: {
                        foods: {
                            total: 3,
                            to_create: 1,
                            to_update: 2,
                        },
                        menus: {
                            total: 4,
                            to_create: 1,
                            to_update: 3,
                        },
                        menu_plans: {
                            total: 12,
                            to_create: 10,
                            to_update: 2,
                            source_count: 96,
                        },
                        bookings: {
                            total: 7,
                            to_create: 6,
                            to_update: 1,
                            source_count: 321,
                        },
                        lunch_users: {
                            total: 5,
                            to_create: 2,
                            existing_users_to_assign: 3,
                        },
                        lunch_admins: {
                            total: 2,
                            to_create: 1,
                            existing_users_to_assign: 1,
                        },
                    },
                    loaded_at: '2026-05-15T12:30:00+02:00',
                },
            },
        })
        axiosMock.post.mockResolvedValue({
            data: {
                data: {
                    items: ['menu_plans'],
                    summary: {},
                },
            },
        })

        const wrapper = mountComponent()
        await flushPromises()

        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/restaurant/cdgym/legacy-stats')
        expect(wrapper.text()).toContain('Anzahl Speisen')
        expect(wrapper.text()).toContain('Anzahl Menüs')
        expect(wrapper.text()).toContain('Anzahl Menüpläne')
        expect(wrapper.text()).toContain('Anzahl Bestellungen')
        expect(wrapper.text()).toContain('User mit lunch_user')
        expect(wrapper.text()).toContain('User mit lunch_admin')
        expect(wrapper.text()).toContain('14')
        expect(wrapper.text()).toContain('8')
        expect(wrapper.text()).toContain('96')
        expect(wrapper.text()).toContain('321')
        expect(wrapper.text()).toContain('240')
        expect(wrapper.text()).toContain('12')
        expect(wrapper.text()).toContain('Live-Daten geladen:')
        expect(wrapper.text()).toContain('Aktualisieren')
        expect(wrapper.text()).toContain('CDGYM Mittagessen auf cdgym.info')
        expect(wrapper.text()).toContain('Vergleich mit der App')
        expect(wrapper.text()).toContain('Würden bei einem Update geändert')
        expect(wrapper.text()).toContain('Speisen')
        expect(wrapper.text()).toContain('Menüs')
        expect(wrapper.text()).toContain('Anzahl Menüpläne')
        expect(wrapper.text()).toContain('Bestellungen')
        expect(wrapper.text()).toContain('Lunch-User')
        expect(wrapper.text()).toContain('Lunch-Admins')
        expect(wrapper.text()).toContain('Übernehmen')
        expect(wrapper.text()).toContain('Neu: 1 / Geändert: 2')
        expect(wrapper.text()).toContain('Neu: 1 / Geändert: 3')
        expect(wrapper.text()).toContain('Neu: 10 / Geändert: 2')
        expect(wrapper.text()).toContain('Neu: 6 / Geändert: 1')
        expect(wrapper.text()).not.toContain('App: 96 / cdgym.info: 120 / Differenz: 24')
        expect(wrapper.text()).toContain('Neue User: 2 / Bestehende ohne Rolle: 3')
        expect(wrapper.text()).toContain('Neue User: 1 / Bestehende ohne Rolle: 1')
        expect(wrapper.text()).not.toContain('Online-Version öffnen')
        expect(wrapper.text()).not.toContain('Menüpläne und Hinweise')
        expect(wrapper.text()).not.toContain('Buffet-Informationsblatt')
        expect(wrapper.text()).not.toContain('Montag bis Donnerstag zwei warme Mahlzeiten')
        expect(wrapper.text()).not.toContain('EUR 9,40')
        expect(wrapper.text()).not.toContain('0664 12 90 130')

        expect(wrapper.text()).not.toContain('Importieren')

        await wrapper.find('input[value="menu_plans"]').setValue(true)
        await flushPromises()

        expect(wrapper.text()).toContain('Importieren')

        const importButton = wrapper.findAll('button').find(button => button.text().includes('Importieren'))
        await importButton?.trigger('click')
        await flushPromises()

        expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/restaurant/cdgym/legacy-import', {
            items: ['menu_plans'],
        })
        expect(wrapper.text()).toContain('Import abgeschlossen.')

        const refreshButton = wrapper.findAll('button').find(button => button.text().includes('Aktualisieren'))
        await refreshButton?.trigger('click')
        await flushPromises()

        expect(axiosMock.get).toHaveBeenCalledTimes(3)
        expect(wrapper.find('[data-href="https://cdgym.info/lunch"]').exists()).toBe(false)
        expect(wrapper.find('[data-href="https://cdgym.at/terminuebersicht/"]').exists()).toBe(false)
        expect(
            wrapper
                .find(
                    '[data-href="https://cdgym.at/wp-content/uploads/2025/09/Informationsblatt-Buffet-2025-26.pdf"]',
                )
                .exists(),
        ).toBe(false)
    })

    it('shows an error when the live stats cannot be loaded', async () => {
        axiosMock.get.mockRejectedValue({
            response: {
                data: {
                    message: 'Die Live-Daten der alten CDGYM-Version konnten nicht geladen werden.',
                },
            },
        })

        const wrapper = mount(CdgymLegacy, {
            global: {
                stubs: {
                    'v-alert': { template: '<div><slot /></div>' },
                    'v-btn': {
                        props: ['href'],
                        template: '<button :data-href="href"><slot /></button>',
                    },
                    'v-card': { template: '<section><slot /></section>' },
                    'v-col': { template: '<div><slot /></div>' },
                    'v-divider': { template: '<hr />' },
                    'v-icon': { template: '<i />' },
                    'v-row': { template: '<div><slot /></div>' },
                },
            },
        })
        await flushPromises()

        expect(wrapper.text()).toContain('Die Live-Daten der alten CDGYM-Version konnten nicht geladen werden.')
    })
})
