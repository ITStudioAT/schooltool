import { createTestingPinia } from '@pinia/testing'
import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import Overview from '@/pages/admin/restaurant/components/Overview.vue'

function mountOverview(stats = {}) {
    return mount(Overview, {
        global: {
            plugins: [
                createTestingPinia({
                    createSpy: vi.fn,
                    initialState: {
                        AdminRestaurantStore: {
                            settings: {
                                stats: {
                                    foods_count: 12,
                                    menus_count: 4,
                                    lunch_users_count: 27,
                                    ...stats,
                                },
                            },
                        },
                    },
                }),
            ],
            stubs: {
                'v-col': { template: '<div><slot /></div>' },
                'v-row': { template: '<div><slot /></div>' },
                ItsGridBox: { props: ['title'], template: '<section><h3>{{ title }}</h3><slot /></section>' },
            },
        },
    })
}

describe('Restaurant overview component', () => {
    it('shows speisen, menüs, and benutzer boxes', () => {
        const wrapper = mountOverview()

        expect(wrapper.text()).toContain('Speisen')
        expect(wrapper.text()).toContain('Menüs')
        expect(wrapper.text()).toContain('Benutzer')
        expect(wrapper.text()).toContain('27')
        expect(wrapper.text()).not.toContain('Kategorien')
        expect(wrapper.text()).not.toContain('Zutaten-Symbole')
        expect(wrapper.text()).not.toContain('Ohne Preis')
        expect(wrapper.text()).not.toContain('Kategorien schnell erweitern')
        expect(wrapper.text()).not.toContain('Allergene im Umlauf')
        expect(wrapper.text()).not.toContain('Gerichte im aktuellen Restaurant-Bereich')
        expect(wrapper.text()).not.toContain('Zusammengestellte Menüfolgen mit mehreren Gängen')
        expect(wrapper.text()).not.toContain('Lunch-Benutzer dieser Schule')
    })
})
