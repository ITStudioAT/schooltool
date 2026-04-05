import { createTestingPinia } from '@pinia/testing'
import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import Sepa from '@/pages/admin/restaurant/components/Sepa.vue'

describe('Restaurant settings SEPA component', () => {
    it('renders the original SEPA settings card content', () => {
        const wrapper = mount(Sepa, {
            global: {
                plugins: [
                    createTestingPinia({
                        createSpy: vi.fn,
                        initialState: {
                            AdminRestaurantStore: {
                                settings: {
                                    sepa_settings: {
                                        sepa_online_enabled: true,
                                        sepa_payee: '<p>Zahlungsempfänger</p>',
                                        sepa_mandate_text: '<p>Mandatstext</p>',
                                    },
                                },
                            },
                        },
                    }),
                ],
                stubs: {
                    'v-col': { template: '<div><slot /></div>' },
                    'v-row': { template: '<div><slot /></div>' },
                    'v-chip': { template: '<span><slot /></span>' },
                    'v-form': { template: '<form><slot /></form>' },
                    'v-switch': { template: '<div />' },
                    'v-btn': { template: '<button><slot /></button>' },
                    ItsGridBox: {
                        props: ['title'],
                        template: '<section><h3>{{ title }}</h3><slot name="header-actions" /><slot /></section>',
                    },
                    ItsRichTextEditor: { template: '<div />' },
                },
            },
        })

        expect(wrapper.text()).toContain('SEPA-Lastschriftmandat')
        expect(wrapper.text()).toContain('Sollen Benutzer Online-SEPA bestätigen können?')
        expect(wrapper.text()).toContain('Zahlungsempfänger')
        expect(wrapper.text()).toContain('Mandatstext')
        expect(wrapper.text()).toContain('Ja')
    })
})
