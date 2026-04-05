import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import RestaurantSepa from '@/pages/admin/restaurant/components/RestaurantSepa.vue'

describe('Restaurant SEPA page', () => {
    const axiosMock = {
        get: vi.fn(),
    }
    let openMock: ReturnType<typeof vi.fn>

    function makeUser(index: number, overrides: Record<string, unknown> = {}) {
        return {
            id: index,
            name: `Benutzer ${index}`,
            email: `benutzer${index}@example.test`,
            schoolclass: index % 2 === 0 ? '2A' : '4B',
            flow_uuid: `flow-uuid-${index}`,
            entry_point: index % 2 === 0 ? 'login' : 'register',
            entry_point_label: index % 2 === 0 ? 'Login' : 'Registrierung',
            completed_at: `${String(index).padStart(2, '0')}.04.2026 09:45`,
            ...overrides,
        }
    }

    beforeEach(() => {
        axiosMock.get.mockReset()
        globalThis.axios = axiosMock as never
        openMock = vi.fn()
        vi.stubGlobal('open', openMock)
    })

    it('loads and renders the online SEPA users', async () => {
        const firstPageUsers = [
            makeUser(30, {
                name: 'Berta Register',
                email: 'berta.register@example.test',
                schoolclass: '4B',
                flow_uuid: 'flow-register-uuid-002',
                entry_point: 'register',
                entry_point_label: 'Registrierung',
                completed_at: '01.04.2026 09:45',
            }),
            makeUser(29, {
                name: 'Anna Login',
                email: 'anna.login@example.test',
                schoolclass: '2A',
                flow_uuid: 'flow-login-uuid-001',
                entry_point: 'login',
                entry_point_label: 'Login',
                completed_at: '31.03.2026 08:15',
            }),
            ...Array.from({ length: 28 }, (_, index) => makeUser(index + 1)),
        ]

        const secondPageUsers = [
            makeUser(31, {
                name: 'Celine Seite Zwei',
                email: 'celine.seite.zwei@example.test',
                schoolclass: '5B',
                flow_uuid: 'flow-page-two-uuid',
                entry_point: 'login',
                entry_point_label: 'Login',
                completed_at: '02.04.2026 10:15',
            }),
        ]

        axiosMock.get
            .mockResolvedValueOnce({
                data: {
                    data: firstPageUsers,
                    meta: {
                        current_page: 1,
                        last_page: 2,
                        per_page: 30,
                        total: 31,
                        from: 1,
                        to: 30,
                    },
                },
            })
            .mockResolvedValueOnce({
                data: {
                    data: secondPageUsers,
                    meta: {
                        current_page: 2,
                        last_page: 2,
                        per_page: 30,
                        total: 31,
                        from: 31,
                        to: 31,
                    },
                },
            })

        const wrapper = mount(RestaurantSepa, {
            global: {
                stubs: {
                    'v-alert': { template: '<div><slot /></div>' },
                    'v-card': { template: '<div><slot /></div>' },
                    'v-card-text': { template: '<div><slot /></div>' },
                    'v-chip': { template: '<span><slot /></span>' },
                    'v-col': { template: '<div><slot /></div>' },
                    'v-btn': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
                    'v-pagination': {
                        props: ['modelValue', 'length'],
                        template: '<button class="pagination" @click="$emit(\'update:modelValue\', Number(modelValue || 1) + 1)">Seite {{ modelValue }} von {{ length }}</button>',
                    },
                    'v-progress-linear': { template: '<div />' },
                    ItsGridBox: {
                        props: ['title'],
                        template: '<section><h3>{{ title }}</h3><slot /></section>',
                    },
                },
            },
        })

        await flushPromises()

        expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/restaurant/sepa-users', { params: { page: 1 } })
        expect(wrapper.text()).toContain('31 Benutzer mit Online-SEPA gefunden')
        expect(wrapper.text()).toContain('1 - 30 von 31')
        expect(wrapper.text()).toContain('Seite 1 von 2')
        expect(wrapper.text()).toContain('Berta Register')
        expect(wrapper.text()).toContain('berta.register@example.test')
        expect(wrapper.text()).toContain('Registrierung')
        expect(wrapper.text()).toContain('IDENTIFIKATION')
        expect(wrapper.text()).toContain('flow-register-uuid-002')
        expect(wrapper.text()).toContain('Anna Login')
        expect(wrapper.text()).toContain('Login')
        expect(wrapper.text()).toContain('flow-login-uuid-001')

        const printButtons = wrapper.findAll('button').filter((button) => button.text().includes('Drucken'))
        expect(printButtons).toHaveLength(30)

        await printButtons[0].trigger('click')

        expect(openMock).toHaveBeenCalledWith(
            '/api/admin/restaurant/sepa-users/flow-register-uuid-002/print',
            '_blank',
            'noopener',
        )

        await wrapper.find('button.pagination').trigger('click')
        await flushPromises()

        expect(axiosMock.get).toHaveBeenLastCalledWith('/api/admin/restaurant/sepa-users', { params: { page: 2 } })
        expect(wrapper.text()).toContain('Seite 2 von 2')
        expect(wrapper.text()).toContain('31 - 31 von 31')
        expect(wrapper.text()).toContain('Celine Seite Zwei')
        expect(wrapper.text()).toContain('flow-page-two-uuid')
        expect(wrapper.text()).not.toContain('Berta Register')
    })
})
